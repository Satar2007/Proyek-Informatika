"""JSA Fase 6: balanced, reproducible percent-change examples for sandbox Qwen3.

Reads a verified *snapshot* of daily aggregate sales, never edits POS data, and
writes instruction pairs to a new, versioned directory. Numbers in synthetic
examples are hypothetical, not facts about the shop.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import random
import shutil
import sys
import tempfile
from collections import Counter
from datetime import date, datetime, timedelta, timezone
from decimal import Decimal, ROUND_HALF_UP
from pathlib import Path

from jsa_qwen3_learning import (DEFAULT_DATASETS, LearningError, read_total,
                                source, verify_examples)

DEFAULT_OUT = Path('storage/app/private/jsa/learning/qwen3/examples-balanced-v6')
RECIPE = 'jsa-balanced-percent-v6.1'
SYSTEM = ('Kamu asisten bisnis. Periksa selisih, persentase, dan arah perubahan secara konsisten. '
          'Jika nilai awal 0, persentase tidak terdefinisi. '
          'Soal hipotetis atau CSV yang belum terverifikasi bukan bukti transaksi nyata.')
# Published Semantic QA questions are never included verbatim or numerically in training.
RESERVED_QA_PAIRS = {(120, 150), (150, 120), (80, 80), (90, 60), (0, 12)}


class BalancedError(RuntimeError):
    pass


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def math_result(old: int, new: int) -> dict:
    if not (isinstance(old, int) and isinstance(new, int)) or min(old, new) < 0:
        raise BalancedError('Jumlah porsi harus bilangan bulat nonnegatif')
    delta = new - old
    direction = 'naik' if delta > 0 else 'turun' if delta < 0 else 'tetap'
    pct = None
    if old > 0:
        pct = float((Decimal(abs(delta)) * 100 / Decimal(old)).quantize(Decimal('0.01'), rounding=ROUND_HALF_UP))
    return {'delta': delta, 'direction': direction, 'percentage': pct}


def format_percent(pct: float) -> str:
    if pct is None:
        raise BalancedError('Persentase tidak terdefinisi jika nilai awal nol')
    return (f'{pct:.2f}').rstrip('0').rstrip('.').replace('.', ',')


def sample(old: int | None, new: int | None, kind: str, synthetic: bool, day: str | None = None, variant: int = 0) -> dict:
    if kind == 'source':
        opening = [
            'Dalam simulasi AI', 'Pada latihan analitik', 'Untuk evaluasi model',
            'Dalam contoh hipotetis', 'Saat menguji data CSV', 'Dalam laporan demonstrasi',
        ][variant % 6]
        ending = [
            'tanpa bukti audit?', 'hanya dari ringkasan CSV?',
            'tanpa pemeriksaan provenance?', 'tanpa memeriksa sumber database?',
        ][(variant // 6) % 4]
        question = (f'Dalam simulasi, {opening.lower()}, apakah kamu dapat memastikan bahwa angka Jimny Coffee '
                    f'adalah transaksi bisnis nyata {ending}')
        answer = ('Tidak, saya tidak dapat memastikan. Data CSV belum terverifikasi sebagai '
                  'transaksi bisnis nyata. Perlu pemeriksaan asal data dan audit.')
    else:
        if old is None or new is None:
            raise BalancedError('Nilai porsi tidak tersedia')
        intro = 'Dalam simulasi penjualan' if synthetic else 'Berdasarkan CSV yang belum terverifikasi'
        question = (f'{intro}, periode sebelumnya {old} porsi dan periode berikutnya {new} porsi. '
                    'Berapa selisih, berapa persentase perubahan, dan apakah jumlah naik, turun atau tetap? '
                    'Tuliskan kesimpulan secara konsisten.')
        info = math_result(old, new)
        delta = info['delta']
        if old == 0:
            answer = (f'Selisih {delta:+d} porsi (dari {old} ke {new}). '
                      'Persentase perubahan tidak dapat dihitung karena nilai awal 0. '
                      'Angka ini bukan bukti transaksi nyata.')
        elif info['direction'] == 'tetap':
            answer = (f'Penjualan tetap {new} porsi; selisih 0 porsi. '
                      'Persentase perubahan 0%. Angka ini bukan bukti transaksi nyata.')
        else:
            pct = format_percent(info['percentage'])
            answer = (f'Penjualan {info["direction"]} {pct}% dari {old} menjadi {new} porsi. '
                      f'Selisih {delta:+d} porsi. Perubahan relatif = '
                      f'{abs(delta)}/{old} × 100 = {pct}%. '
                      'Angka ini bukan bukti transaksi nyata.')
    return {
        'initial': old, 'final': new, 'case_type': kind, 'synthetic': bool(synthetic),
        'source_label': 'synthetic_hypothetical' if synthetic else 'csv_unverified_origin',
        'date': day,
        'messages': [
            {'role': 'system', 'content': SYSTEM},
            {'role': 'user', 'content': question},
            {'role': 'assistant', 'content': answer},
        ],
    }


def _pairs(rows: list[tuple[date, int]]) -> list[tuple[date, int, int]]:
    return [
        (rows[i][0], sum(q for _, q in rows[i - 13:i - 6]), sum(q for _, q in rows[i - 6:i + 1]))
        for i in range(13, len(rows))
    ]


def build(dataset_dir: Path, now: date | None = None):
    try:
        folder, manifest = source(Path(dataset_dir))
    except (LearningError, OSError) as e:
        raise BalancedError('Snapshot tidak terverifikasi: ' + str(e)) from e
    rows = read_total(folder / 'active_total.csv')
    today = now or datetime.now(timezone(timedelta(hours=7))).date()
    last = rows[-1][0]
    if last > today - timedelta(days=1) or (today - last).days > 8:
        raise BalancedError('Snapshot CSV usang atau berasal dari masa depan')
    if len(rows) < 120:
        raise BalancedError('Minimal 120 hari histori untuk kurikulum baru')
    if manifest.get('totals', {}).get('last_date') != last.isoformat():
        raise BalancedError('Tanggal snapshot dan manifest tidak sama')
    source_fingerprint = manifest.get('fingerprint', '')
    if len(source_fingerprint) != 64 or not all(ch in '0123456789abcdef' for ch in source_fingerprint):
        raise BalancedError('Fingerprint snapshot tidak valid')

    pos = _pairs(rows)
    cutoff = max(28, int(len(pos) * .15))
    embargo = 14
    train_pos = pos[: -(cutoff + embargo)][-220:]
    eval_pos = pos[-cutoff:][-40:]
    used: set[tuple[int | None, int | None]] = set(RESERVED_QA_PAIRS)

    def pos_samples(items):
        output = []
        for day, old, new in items:
            key = (old, new)
            if key in used:
                continue
            used.add(key)
            result = math_result(old, new)
            kind = 'undefined' if old == 0 else {'naik': 'rise', 'turun': 'fall', 'tetap': 'flat'}[result['direction']]
            output.append(sample(old, new, kind, False, day.isoformat()))
        return output

    train = pos_samples(train_pos)
    valid = pos_samples(eval_pos)
    rng = random.Random(int(sha256((source_fingerprint + RECIPE).encode())[:16], 16))

    def synthetic(kind, count):
        output = []
        attempts = 0
        while len(output) < count:
            attempts += 1
            if attempts > count * 2000:
                raise BalancedError('Tidak dapat menyusun contoh sintetis unik')
            if kind == 'rise':
                old = rng.randint(1, 1000)
                new = old + rng.randint(1, 600)
            elif kind == 'fall':
                old = rng.randint(1, 1100)
                new = rng.randint(0, old - 1)
            elif kind == 'flat':
                old = rng.randint(1, 200000)
                new = old
            elif kind == 'undefined':
                old = 0
                new = rng.randint(0, 200000)
            else:
                raise BalancedError('Kelompok sintetik tidak dikenal')
            key = (old, new)
            if key in used:
                continue
            used.add(key)
            output.append(sample(old, new, kind, True))
        return output

    for kind, ct in [('rise', 140), ('fall', 140), ('flat', 90), ('undefined', 70)]:
        train += synthetic(kind, ct)
    for kind, ct in [('rise', 36), ('fall', 36), ('flat', 26), ('undefined', 20)]:
        valid += synthetic(kind, ct)
    train += [sample(None, None, 'source', True, variant=i) for i in range(12)]
    valid += [sample(None, None, 'source', True, variant=i) for i in range(12,18)]
    # Shuffle order only. Source dates and evaluation grouping already separated.
    rng.shuffle(train)
    rng.shuffle(valid)
    metadata = {
        'source_label': manifest.get('quality', {}).get('source_label', 'unknown'),
        'source_verified': False,
        'original_dataset_fingerprint': source_fingerprint,
        'snapshot_as_of': last.isoformat(),
        'train_counts': dict(Counter(x['case_type'] for x in train)),
        'eval_counts': dict(Counter(x['case_type'] for x in valid)),
    }
    return train, valid, metadata


def _lines(items: list[dict]) -> bytes:
    return ''.join(json.dumps(x, sort_keys=True, ensure_ascii=False) + '\n' for x in items).encode('utf8')


def publish(dataset_dir: Path, out_root: Path, now: date | None = None, dry_run: bool = False) -> dict:
    train, valid, metadata = build(dataset_dir, now=now)
    blobs = {'train.jsonl': _lines(train), 'eval.jsonl': _lines(valid)}
    hashes = {k: sha256(v) for k, v in blobs.items()}
    fingerprint = sha256(('|'.join([RECIPE, metadata['original_dataset_fingerprint'], hashes['train.jsonl'], hashes['eval.jsonl']])).encode())
    out = Path(out_root) / fingerprint[:20]
    status = {'status': 'dry_run' if dry_run else 'created', 'examples_dir': str(out),
              'dataset_fingerprint': fingerprint, 'train_count': len(train), 'eval_count': len(valid),
              'breakdown': {'train': metadata['train_counts'], 'eval': metadata['eval_counts']}}
    if dry_run:
        return status
    if out.exists():
        if not (out/'manifest.json').is_file():
            raise BalancedError('Folder keluaran sudah ada namun tidak lengkap; tidak ditimpa')
        previous = verify_examples(out)['manifest']
        if previous.get('dataset_fingerprint') != fingerprint or previous.get('training_recipe') != RECIPE:
            raise BalancedError('Folder keluaran sudah ada dengan manifest berbeda')
        return {**status, 'status': 'unchanged'}
    root = Path(out_root)
    root.mkdir(parents=True, exist_ok=True)
    tmp = Path(tempfile.mkdtemp(prefix='.pending-v6-', dir=root))
    try:
        for name, data in blobs.items():
            (tmp / name).write_bytes(data)
        m = {
            'schema_version': 3, 'stage': 'sandbox_candidate', 'source_verified': False,
            'source_label': metadata['source_label'], 'dataset_fingerprint': fingerprint,
            'original_dataset_fingerprint': metadata['original_dataset_fingerprint'],
            'snapshot_as_of': metadata['snapshot_as_of'], 'training_recipe': RECIPE,
            'created_at_utc': datetime.now(timezone.utc).isoformat(),
            'train_count': len(train), 'eval_count': len(valid), 'artifacts': hashes,
            'breakdown': status['breakdown'],
            'notes': 'Synthetic and CSV-derived exercises. Evaluation loss is not proof of correct business reasoning.'
        }
        (tmp/'manifest.json').write_bytes((json.dumps(m, sort_keys=True, indent=2, ensure_ascii=False) + '\n').encode('utf8'))
        tmp.rename(out)
    finally:
        if tmp.exists():
            shutil.rmtree(tmp)
    return status


def main(argv=None) -> int:
    p=argparse.ArgumentParser(description='JSA Fase 6 balanced synthetic+aggregate sandbox examples')
    p.add_argument('command', choices=('prepare','dry-run'))
    p.add_argument('--datasets', type=Path, default=DEFAULT_DATASETS)
    p.add_argument('--out-root', type=Path, default=DEFAULT_OUT)
    a=p.parse_args(argv)
    try:
        result=publish(a.datasets, a.out_root, dry_run=(a.command=='dry-run'))
        print(json.dumps(result, ensure_ascii=False, indent=2))
        return 0
    except (BalancedError, LearningError, OSError, ValueError) as e:
        print('JSA BALANCED GAGAL:',str(e),file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
