"""JSA Auto Training Fase 2: train & evaluasi forecasting dari verified rolling CSV.

Tidak mengubah MySQL, stok, Laravel controller, atau bobot model Ollama.
Memublikasikan versi kandidat terpisah yang hanya untuk review/sandbox.
Python 3.11+, standard library.
"""
from __future__ import annotations

import argparse
import csv
import hashlib
import json
import math
import os
import re
import secrets
import shutil
import sys
import tempfile
from collections import defaultdict
from datetime import date, datetime, timedelta, timezone
from pathlib import Path

from jsa_dataset_manager import DataError, verify_latest

VERSION = 2
MIN_HISTORY = 90
HOLDOUT = 28
JAKARTA = timezone(timedelta(hours=7))


class TrainingError(RuntimeError):
    """Kegagalan validasi/training; kandidat lama tetap digunakan."""


def _json_bytes(value: object) -> bytes:
    return (json.dumps(value, sort_keys=True, ensure_ascii=False, indent=2) + "\n").encode('utf-8')


def _sha(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def _sha_path(path: Path) -> str:
    return _sha(path.read_bytes())


def _daily_rows(path: Path) -> list[tuple[date, int]]:
    try:
        with path.open('r', newline='', encoding='utf-8-sig') as stream:
            reader = csv.DictReader(stream)
            if not reader.fieldnames or not {'tanggal', 'total_porsi'}.issubset(reader.fieldnames):
                raise TrainingError('Dataset total memiliki header tidak sesuai')
            rows: list[tuple[date, int]] = []
            for line, r in enumerate(reader, 2):
                day = date.fromisoformat(r['tanggal'])
                text = r['total_porsi']
                if not text.isdecimal():
                    raise TrainingError(f'Data quantity tidak valid di baris {line}')
                qty = int(text)
                if rows and day != rows[-1][0] + timedelta(days=1):
                    raise TrainingError(f'Tanggal duplikat atau terputus di baris {line}')
                rows.append((day, qty))
            return rows
    except (ValueError, OSError, TypeError) as exc:
        raise TrainingError(f'Gagal membaca dataset total: {exc}') from exc


def _solve(a: list[list[float]], b: list[float]) -> list[float]:
    """Gaussian elimination with partial pivot (8x8), regularized by fit_ridge."""
    n = len(b)
    work = [a[i][:] + [b[i]] for i in range(n)]
    for j in range(n):
        p = max(range(j, n), key=lambda i: abs(work[i][j]))
        if abs(work[p][j]) < 1e-10:
            raise TrainingError('Model ridge tidak dapat diestimasi')
        work[j], work[p] = work[p], work[j]
        pivot = work[j][j]
        for k in range(j, n + 1):
            work[j][k] /= pivot
        for i in range(n):
            if i == j:
                continue
            v = work[i][j]
            if v:
                for k in range(j, n + 1):
                    work[i][k] -= v * work[j][k]
    return [work[i][n] for i in range(n)]


def _features(day: date, reference: date) -> list[float]:
    weekday = day.weekday()
    return [1.0] + [1.0 if weekday == i else 0.0 for i in range(6)] + [((day - reference).days / 30.0)]


def fit_ridge(history: list[tuple[date, int]], window: int, alpha: float) -> dict:
    if len(history) < 28:
        raise TrainingError('Data fitting model ridge kurang dari 28 hari')
    if window < 28 or alpha <= 0:
        raise TrainingError('Hyperparameter ridge tidak valid')
    subset = history[-window:]
    reference = subset[-1][0]
    size = 8
    a = [[0.0] * size for _ in range(size)]
    b = [0.0] * size
    for day, actual in subset:
        x = _features(day, reference)
        for i in range(size):
            b[i] += x[i] * actual
            for j in range(size):
                a[i][j] += x[i] * x[j]
    # Penalti tidak dikenakan pada intercept agar level penjualan tidak terdorong nol.
    for i in range(1, size):
        a[i][i] += alpha
    weights = _solve(a, b)
    return {'kind': 'ridge_dow_trend', 'window': window, 'alpha': alpha,
            'reference_date': reference.isoformat(), 'coefficients': [float(x) for x in weights],
            'fitted_rows': len(subset)}


def forecast_ridge(model: dict, target: date) -> float:
    x = _features(target, date.fromisoformat(model['reference_date']))
    value = sum(x[i] * model['coefficients'][i] for i in range(8))
    if not math.isfinite(value):
        raise TrainingError('Prediksi tidak finite')
    return max(0.0, value)


def _predict(history: list[tuple[date, int]], day: date, kind: str,
             window: int = 28, alpha: float = 10.0) -> float:
    if not history:
        raise TrainingError('Riwayat kosong')
    if kind == 'seasonal_naive_7':
        return float(history[-7][1]) if len(history) >= 7 else float(history[-1][1])
    if kind == 'moving_average':
        points = history[-window:]
        return sum(value for _, value in points) / len(points)
    if kind == 'ridge':
        fit = fit_ridge(history, window, alpha)
        return forecast_ridge(fit, day)
    raise TrainingError(f'Jenis model tidak dikenali: {kind}')


def rolling_predictions(rows: list[tuple[date, int]], kind: str,
                        start: int, end: int, window: int = 28,
                        alpha: float = 10.0) -> list[dict]:
    if start < 28 or end > len(rows) or start >= end:
        raise TrainingError('Rentang evaluasi salah')
    out = []
    for i in range(start, end):
        day, actual = rows[i]
        prediction = _predict(rows[:i], day, kind, window=window, alpha=alpha)
        out.append({'date': day.isoformat(), 'prediction': round(prediction, 6),
                    'actual': actual, 'abs_error': round(abs(actual - prediction), 6)})
    return out


def _mae(rows: list[dict]) -> float:
    return sum(x['abs_error'] for x in rows) / len(rows)


def _describe(name: str, window: int = 28, alpha: float = 10.0) -> dict:
    config = {'kind': name}
    if name in ('moving_average', 'ridge'):
        config['window'] = window
    if name == 'ridge':
        config['alpha'] = alpha
    return config


def _evaluate(rows: list[tuple[date, int]]) -> tuple[dict, dict, dict]:
    n = len(rows)
    train_end = n - HOLDOUT
    inner_start = max(56, train_end - 35)
    if train_end - inner_start < 14:
        raise TrainingError('Data benchmark internal tidak cukup')
    configs = [
        _describe('seasonal_naive_7'),
        _describe('moving_average', 7),
        _describe('moving_average', 28),
    ] + [_describe('ridge', w, a) for w in (90, 180, 365) for a in (2.0, 20.0)]
    scores = []
    for cfg in configs:
        predictions = rolling_predictions(rows, cfg['kind'], inner_start, train_end,
                                          window=cfg.get('window', 28), alpha=cfg.get('alpha', 10))
        scores.append({'config': cfg, 'mae_inner': round(_mae(predictions), 6)})
    # Seleksi kandidat hanya dari data SEBELUM holdout.
    selected = min(scores, key=lambda x: (x['mae_inner'], str(x['config'])))
    # Baseline terbaik ditentukan secara independen menggunakan internal window.
    baseline = min((x for x in scores if x['config']['kind'] != 'ridge'),
                   key=lambda x: (x['mae_inner'], str(x['config'])))
    def hold(config):
        return rolling_predictions(rows, config['kind'], train_end, n,
                                   window=config.get('window', 28), alpha=config.get('alpha', 10))
    chosen_pred = hold(selected['config'])
    baseline_pred = hold(baseline['config'])
    chosen_mae, baseline_mae = _mae(chosen_pred), _mae(baseline_pred)
    acceptable = chosen_mae <= (baseline_mae * 1.05 + 1e-12)
    evaluation = {
        'holdout_days': HOLDOUT,
        'holdout_start': rows[train_end][0].isoformat(),
        'holdout_end': rows[-1][0].isoformat(),
        'mae': round(chosen_mae, 6),
        'baseline_mae': round(baseline_mae, 6),
        'benchmark_n': train_end - inner_start,
        'pass_baseline_guardrail': acceptable,
        'selection_basis': 'pre-holdout expanding-origin MAE',
        'holdout_method': 'walk-forward with only prior actuals; validation, not a guarantee',
    }
    return selected, baseline, {'evaluation': evaluation, 'scores': scores, 'holdout_predictions': chosen_pred}


def _context(rows: list[tuple[date, int]], menu_path: Path,
             forecast: float, source_label: str) -> dict:
    menu_count = defaultdict(int)
    cutoff = rows[-1][0]
    since = cutoff - timedelta(days=27)
    try:
        with menu_path.open('r', encoding='utf-8-sig', newline='') as stream:
            reader = csv.DictReader(stream)
            if not reader.fieldnames or not {'tanggal', 'menu_id', 'nama_menu', 'porsi'}.issubset(reader.fieldnames):
                raise TrainingError('Header menu dataset tidak lengkap')
            for record in reader:
                d = date.fromisoformat(record['tanggal'])
                if d >= since:
                    menu_count[(record['menu_id'], record['nama_menu'][:80])] += int(record['porsi'])
    except (OSError, TypeError, ValueError) as exc:
        raise TrainingError(f'Gagal membaca ringkasan menu: {exc}') from exc
    return {
        'schema_version': VERSION,
        'as_of': cutoff.isoformat(),
        'source_label': source_label,
        'verified_real_sales': False,
        'privacy': 'aggregated; no customer identifiers',
        'total_qty_7d': sum(x[1] for x in rows[-7:]),
        'total_qty_28d': sum(x[1] for x in rows[-28:]),
        'total_qty_prev_28d': sum(x[1] for x in rows[-56:-28]),
        'forecast_next_day_total_qty': round(forecast, 2),
        'top_menus_28d': [
            {'menu_id': mid, 'menu_name': name, 'qty': qty}
            for (mid, name), qty in sorted(menu_count.items(), key=lambda t: (-t[1], t[0]))[:5]
        ],
        'disclaimer': 'Data dari CSV belum diverifikasi sebagai data transaksi nyata; model masih kandidat dan bukan dasar pembelian otomatis.',
    }


def verify_latest_candidate(model_dir: Path) -> dict:
    model_dir = Path(model_dir)
    try:
        pointer = json.loads((model_dir / 'latest_candidate.json').read_text(encoding='utf-8'))
        run_id = pointer['run_id']
        if not isinstance(run_id, str) or not re.fullmatch(r'train_[0-9a-f]{20}', run_id):
            raise TrainingError('Run ID model tidak valid')
        run = model_dir / 'runs' / run_id
        manifest_file = run / 'manifest.json'
        if _sha_path(manifest_file) != pointer.get('manifest_sha256'):
            raise TrainingError('Gagal integritas manifest model')
        manifest = json.loads(manifest_file.read_text(encoding='utf-8'))
        if manifest.get('run_id') != run_id or manifest.get('schema_version') != VERSION:
            raise TrainingError('Gagal integritas identitas model')
        for name, digest in manifest['artifacts'].items():
            if name not in ('model.json', 'evaluation.json', 'business_context.json'):
                raise TrainingError('Nama artifact model tidak diizinkan')
            if _sha_path(run / name) != digest:
                raise TrainingError(f'Gagal integritas artifact: {name}')
    except (OSError, KeyError, ValueError, TypeError) as exc:
        raise TrainingError(f'Gagal integritas kandidat: {exc}') from exc
    return {'valid': True, 'run_id': run_id, 'manifest': manifest}


def train_latest(dataset_dir: Path, model_dir: Path, dry_run: bool = False) -> dict:
    dataset_dir, model_dir = Path(dataset_dir), Path(model_dir)
    try:
        verified = verify_latest(dataset_dir)
    except (DataError, OSError, ValueError, KeyError) as exc:
        raise TrainingError(f'Dataset gagal verifikasi integritas: {exc}') from exc
    dm = verified['manifest']
    if not dm['quality'].get('ready_for_training'):
        raise TrainingError('Dataset belum siap training: data kurang, ada gap, atau terlalu lama')
    run_id_ds = verified['run_id']
    rows = _daily_rows(dataset_dir / 'runs' / run_id_ds / 'active_total.csv')
    if len(rows) < MIN_HISTORY:
        raise TrainingError(f'Dataset belum siap: minimal {MIN_HISTORY} tanggal untuk training ini')
    if rows[-1][0].isoformat() != dm['totals']['last_date']:
        raise TrainingError('Tanggal manifest tidak sesuai dataset')
    selected, baseline, details = _evaluate(rows)
    cfg = selected['config']
    next_day = rows[-1][0] + timedelta(days=1)
    forecast = _predict(rows, next_day, cfg['kind'], cfg.get('window', 28), cfg.get('alpha', 10))
    # Fit ridge tetap dilakukan, meskipun hasil validasi memilih baseline.
    ridge_choice = min((s for s in details['scores'] if s['config']['kind'] == 'ridge'),
                       key=lambda s: s['mae_inner'])['config']
    fit = fit_ridge(rows, window=ridge_choice['window'], alpha=ridge_choice['alpha'])
    model_json = {
        'schema_version': VERSION,
        'dataset_run_id': run_id_ds,
        'selected': cfg,
        'trained_ridge_model': fit,
        'prediction_date': next_day.isoformat(),
        'selected_prediction_qty': round(forecast, 2),
        'trained_ridge_prediction_qty': round(forecast_ridge(fit, next_day), 2),
        'production_active': False,
    }
    context = _context(rows, dataset_dir / 'runs' / run_id_ds / 'active_menu.csv',
                       forecast, dm['quality'].get('source_label', 'unknown'))
    evaluation = {**details['evaluation'], 'selected': selected,
                  'baseline': baseline, 'candidate_scores': details['scores'],
                  'holdout_predictions': details['holdout_predictions']}
    artifact_bytes = {
        'model.json': _json_bytes(model_json),
        'evaluation.json': _json_bytes(evaluation),
        'business_context.json': _json_bytes(context),
    }
    source_fingerprint = dm['fingerprint']
    key = _sha(_json_bytes({'version': VERSION, 'source_fingerprint': source_fingerprint,
                            'alg': 'ridge-dow-v2'}))[:20]
    run_id = 'train_' + key
    manifest = {
        'schema_version': VERSION,
        'run_id': run_id,
        'stage': 'sandbox_candidate',
        'source_verified': False,  # CSV tidak membawa bukti asal data yang independen.
        'source_label': dm['quality'].get('source_label', 'csv_unverified_origin'),
        'data_cutoff': rows[-1][0].isoformat(),
        'dataset_run_id': run_id_ds,
        'dataset_fingerprint': source_fingerprint,
        'training_rows': len(rows),
        'trained_model': 'ridge_dow_trend',
        'selected_model': cfg['kind'],
        'evaluation': details['evaluation'],
        'production_promoted': False,
        'ollama_finetuned': False,
        'pos_mutated': False,
        'human_review_required': True,
        'artifacts': {name: _sha(content) for name, content in artifact_bytes.items()},
    }
    if dry_run:
        return {'status': 'dry_run', 'run_id': run_id, 'manifest': manifest,
                'prediction_qty': round(forecast, 2)}

    if (model_dir / 'latest_candidate.json').exists():
        existing = verify_latest_candidate(model_dir)
        if existing['run_id'] == run_id:
            return {'status': 'unchanged', 'run_id': run_id, 'manifest': existing['manifest']}
    model_dir.mkdir(parents=True, exist_ok=True)
    lock = model_dir / '.training.lock'
    try:
        fd = os.open(lock, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
    except FileExistsError as exc:
        raise TrainingError('Auto Training sudah berjalan atau lock lama perlu ditinjau') from exc
    try:
        with os.fdopen(fd, 'w', encoding='utf-8') as stream:
            stream.write(str(os.getpid()))
        runs = model_dir / 'runs'
        runs.mkdir(exist_ok=True)
        dest = runs / run_id
        if dest.exists():
            raise TrainingError('Run model sudah ada, tetapi pointer tidak cocok. Periksa secara manual')
        temp = Path(tempfile.mkdtemp(prefix='.tmp_train_', dir=str(runs)))
        try:
            for name, content in artifact_bytes.items():
                (temp / name).write_bytes(content)
            (temp / 'manifest.json').write_bytes(_json_bytes(manifest))
            temp.rename(dest)
        finally:
            if temp.exists():
                shutil.rmtree(temp)
        pointer = {'run_id': run_id, 'manifest_sha256': _sha_path(dest / 'manifest.json')}
        temp_ptr = model_dir / ('.latest_' + secrets.token_hex(4) + '.tmp')
        try:
            temp_ptr.write_bytes(_json_bytes(pointer))
            os.replace(temp_ptr, model_dir / 'latest_candidate.json')
        finally:
            temp_ptr.unlink(missing_ok=True)
        # Batasi hanya output model yang kita kenali, bukan data asal.
        known = sorted([p for p in runs.iterdir()
                        if p.is_dir() and not p.is_symlink() and re.fullmatch(r'train_[0-9a-f]{20}', p.name)
                        and (p / 'manifest.json').exists()],
                       key=lambda p: p.stat().st_mtime, reverse=True)
        for folder in known[5:]:
            if folder.name != run_id:
                shutil.rmtree(folder)
        return {'status': 'trained', 'run_id': run_id, 'manifest': manifest,
                'prediction_qty': round(forecast, 2)}
    finally:
        lock.unlink(missing_ok=True)


def main(argv=None) -> int:
    parser = argparse.ArgumentParser(description='JSA Auto Trainer: training model kandidat forecasting, tanpa modifikasi POS/Ollama')
    parser.add_argument('--datasets', type=Path, default=Path('storage/app/private/jsa/learning/datasets'))
    parser.add_argument('--models', type=Path, default=Path('storage/app/private/jsa/learning/models'))
    parser.add_argument('--dry-run', action='store_true')
    parser.add_argument('--verify', action='store_true')
    args = parser.parse_args(argv)
    try:
        result = verify_latest_candidate(args.models) if args.verify else train_latest(args.datasets, args.models, args.dry_run)
        print(json.dumps(result, ensure_ascii=False, indent=2))
        return 0
    except (TrainingError, OSError) as exc:
        print(f'JSA Auto Training GAGAL: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
