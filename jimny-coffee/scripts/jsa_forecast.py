"""JSA Fase 2: forecast satu hari, menu top-down/SBA, interval prequential.

Tidak mengubah database. Memakai model Fase 1 tanpa mengubah script lamanya.
Label kepercayaan adalah heuristik kualitas data/backtest, bukan probabilitas.
"""
import argparse
import csv
import importlib.util
import json
import math
import sys
from datetime import date, timedelta
from pathlib import Path

import numpy as np

_spec = importlib.util.spec_from_file_location(
    "jsa_phase1_for_forecast", Path(__file__).with_name("jsa_benchmark.py"))
bench = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(bench)
ForecastError = bench.BenchmarkError
MIN_CALIBRATION = 20
MIN_SELECTION = 14
CALIBRATION_WINDOW = 60
SHARE_WINDOW = 28


def load_menu(path, start, total):
    """CSV sparse diisi nol; jumlah per menu wajib sama dengan total tiap hari.

    CSV tidak memuat tanggal aktivasi menu. Hari sebelum penjualan pertamanya
    tetap nol (asumsi eksplisit, bukan bukti bahwa menu sudah tersedia).
    """
    path = Path(path)
    if not path.is_file():
        raise ForecastError(f"File tidak ditemukan: {path}")
    names, series, seen = {}, {}, set()
    with path.open(encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        if not {"tanggal", "menu_id", "nama_menu", "porsi"}.issubset(reader.fieldnames or []):
            raise ForecastError("daily_menu.csv memerlukan tanggal, menu_id, nama_menu, porsi.")
        for line, row in enumerate(reader, 2):
            try:
                day = date.fromisoformat(row["tanggal"].strip())
                menu_id = int(row["menu_id"])
                qty = int(row["porsi"])
            except (ValueError, TypeError, AttributeError):
                raise ForecastError(f"Baris menu {line}: tanggal/ID/porsi tidak valid.")
            name = (row["nama_menu"] or "").strip()
            index = (day - start).days
            if not 0 <= index < len(total):
                raise ForecastError(f"Baris menu {line}: tanggal di luar rentang daily_total.csv.")
            if menu_id <= 0 or qty < 0 or not name:
                raise ForecastError(f"Baris menu {line}: ID positif, nama, porsi nonnegatif diperlukan.")
            key = (index, menu_id)
            if key in seen:
                raise ForecastError(f"Baris menu {line}: tanggal dan menu_id duplikat.")
            seen.add(key)
            names[menu_id] = name  # nama terbaru; identitas selalu menu_id
            series.setdefault(menu_id, np.zeros(len(total), dtype=float))[index] = qty
    if not series:
        raise ForecastError("daily_menu.csv kosong; daftar menu tidak tersedia.")
    ids = sorted(series)
    matrix = np.stack([series[key] for key in ids], axis=1)
    bad = np.flatnonzero(matrix.sum(axis=1) != total)
    if len(bad):
        day = start + timedelta(days=int(bad[0]))
        raise ForecastError(f"Jumlah porsi menu tidak cocok dengan total pada {day}.")
    return ids, [names[key] for key in ids], matrix


def croston_sba(y, alpha=0.1):
    """Croston-SBA, alpha tetap; semua state dibangun dari riwayat saja."""
    positive = np.flatnonzero(y > 0)
    if not len(positive):
        return 0.0
    first = int(positive[0])
    size, interval = float(y[first]), float(first + 1)
    last = first
    for index in positive[1:]:
        index = int(index)
        size += alpha * (float(y[index]) - size)
        interval += alpha * ((index - last) - interval)
        last = index
    return float((1 - alpha / 2) * size / interval)


def allocate_menu(history, total_prediction):
    """Bagi total, ganti skor menu sporadis dengan SBA, rekonsiliasi ulang.

    SBA sesudah rekonsiliasi bukan lagi output SBA mentah. Nilai mentah dan
    faktor skala disertakan untuk audit. Semua skor memakai prefix history.
    """
    recent = history[-SHARE_WINDOW:]
    qty = recent.sum(axis=0)
    shares = qty / qty.sum() if qty.sum() else np.zeros(history.shape[1])
    raw = shares * total_prediction
    methods = []
    for j in range(history.shape[1]):
        count = int(np.count_nonzero(recent[:, j]))
        intermittent = count == 0 or len(recent) / count >= 1.32
        if intermittent:
            raw[j] = croston_sba(history[:, j])
            methods.append("sba_reconciled")
        else:
            methods.append("top_down_28d")
    if raw.sum() > 0:
        factor = float(total_prediction / raw.sum())
        points = raw * factor
    else:
        # Tidak ada bukti penjualan menu: jangan menciptakan permintaan baru.
        factor = 0.0
        points = np.zeros(history.shape[1])
        if total_prediction > 1e-9:
            raise ForecastError("Total positif tetapi skor seluruh menu nol.")
    return points, methods, raw, factor


def integer_allocation(points):
    """Largest remainder: porsi bulat menu berjumlah round-half-up total."""
    target = int(math.floor(float(np.sum(points)) + 0.5))
    quantities = np.floor(points).astype(int)
    left = target - int(quantities.sum())
    order = np.argsort(-(points - quantities), kind="stable")
    quantities[order[:left]] += 1
    return quantities.tolist(), target


def interval(point, previous_errors, level=0.8):
    """Radius kuantil |galat| sebelumnya, rank ceil((m+1)*level).

    Koreksi rank finite-sample; deret waktu dependen tidak memberi jaminan
    coverage conformal. Cakupan aktual tetap harus diukur terpisah.
    """
    if not 0 < level < 1:
        raise ForecastError("Level interval harus di antara 0 dan 1.")
    errors = np.asarray(previous_errors[-CALIBRATION_WINDOW:], dtype=float)
    if len(errors) < MIN_CALIBRATION:
        return None
    rank = math.ceil((len(errors) + 1) * level)
    if rank > len(errors):
        raise ForecastError("Kalibrasi terlalu sedikit untuk level interval ini.")
    radius = float(np.sort(np.abs(errors))[rank - 1])
    return [max(0, math.floor(point - radius)), math.ceil(point + radius)]


def select_at(t, total, predictions, initial_train, model):
    if model != "auto":
        return model, "fixed_override"
    count = t - initial_train
    if count < MIN_SELECTION:
        return "seasonal_mean4", "warmup_seasonal_mean4"
    actual = total[initial_train:t]
    metrics = {name: bench.metrics_for(actual, values[:count])
               for name, values in predictions.items()}
    chosen, _, _ = bench.select_one_se(metrics)
    return chosen, "one_se_prior_errors_only"


def evaluation(rows, key):
    available = [row for row in rows if row[key] is not None]
    if not available:
        return {"n": 0, "coverage": None, "mean_width": None}
    return {
        "n": len(available),
        "coverage": float(np.mean([r[key][0] <= r["actual"] <= r[key][1]
                                   for r in available])),
        "mean_width": float(np.mean([r[key][1] - r[key][0] for r in available])),
    }


def confidence(summary, nonzero_days, point, available):
    if not available or summary["n"] < 20 or nonzero_days < 14:
        return "data belum cukup"
    coverage = summary["coverage"]
    if summary["n"] >= 60 and coverage >= 0.75 and summary["mean_width"] <= 2 * max(point, 1):
        return "tinggi"
    if coverage >= 0.65:
        return "sedang"
    return "rendah"


def run_forecast(start, total, ids, names, menu, model="auto", initial_train=42,
                 level=0.8, models=None):
    total, menu = np.asarray(total, dtype=float), np.asarray(menu, dtype=float)
    if (total.ndim != 1 or not np.isfinite(total).all() or (total < 0).any()
            or (total != np.floor(total)).any()):
        raise ForecastError("Total harus deret bilangan bulat nonnegatif dan terbatas.")
    if (menu.shape != (len(total), len(ids)) or len(ids) != len(names) or not len(ids)
            or len(set(ids)) != len(ids) or not np.isfinite(menu).all()
            or (menu < 0).any() or (menu != np.floor(menu)).any()
            or not np.array_equal(menu.sum(axis=1), total)):
        raise ForecastError("Matriks menu/ID tidak valid atau jumlahnya tidak sama dengan total.")
    if not 0.5 <= level <= 0.95:
        raise ForecastError("Level yang didukung 0.50 sampai 0.95.")
    models = bench.MODELS if models is None else models
    if model != "auto" and model not in models:
        raise ForecastError(f"Model tidak dikenal: {model}")
    if model == "auto" and "seasonal_mean4" not in models:
        raise ForecastError("Mode auto memerlukan seasonal_mean4 untuk warmup.")
    candidates = models if model == "auto" else {model: models[model]}
    predictions = bench.rolling_origin(total, start, initial_train, candidates)
    # Setiap kandidat hanya dilatih pada prefix oleh rolling_origin Fase 1.
    for name in predictions:
        predictions[name] = np.maximum(0, predictions[name])
        if not np.isfinite(predictions[name]).all():
            raise ForecastError(f"Model {name} menghasilkan nilai tidak terbatas.")
    errors = [[] for _ in range(len(ids) + 1)]
    records = [[] for _ in range(len(ids) + 1)]
    next_result = None
    for t in range(initial_train, len(total) + 1):
        chosen, reason = select_at(t, total, predictions, initial_train, model)
        point = (float(predictions[chosen][t - initial_train]) if t < len(total)
                 else max(0.0, float(candidates[chosen](total.copy(), start))))
        if not math.isfinite(point):
            raise ForecastError("Prediksi akhir tidak terbatas.")
        points, methods, raw, scale = allocate_menu(menu[:t], point)
        all_points = [point, *points.tolist()]
        bounds = [interval(p, e, level) for p, e in zip(all_points, errors)]
        target_day = (start + timedelta(days=t)).isoformat()
        if t == len(total):
            quantities, total_qty = integer_allocation(points)
            next_result = {
                "date": target_day, "model": chosen, "selection_reason": reason,
                "total": {"point": point, "quantity": total_qty, "interval": bounds[0]},
                "menus": [
                    {"menu_id": key, "nama_menu": name,
                     "point": float(points[j]), "quantity": quantities[j],
                     "interval": bounds[j + 1], "method": methods[j],
                     "raw_before_reconciliation": float(raw[j]),
                     "reconciliation_factor": scale,
                     "nonzero_days": int(np.count_nonzero(menu[:, j]))}
                    for j, (key, name) in enumerate(zip(ids, names))
                ],
            }
            break
        actuals = [float(total[t]), *menu[t].tolist()]
        # Interval dihitung SEBELUM aktual hari t dicatat sebagai residual.
        for j, (actual, predicted, bound) in enumerate(zip(actuals, all_points, bounds)):
            records[j].append({"date": target_day, "model": chosen,
                               "actual": actual, "point": predicted, "interval": bound,
                               "method": "total" if j == 0 else methods[j - 1]})
            errors[j].append(actual - predicted)
    summaries = []
    for rows in records:
        summary = evaluation(rows, "interval")
        summary["point_mae"] = float(np.mean([abs(r["actual"] - r["point"]) for r in rows]))
        summaries.append(summary)
    next_result["total"]["evaluation"] = summaries[0]
    next_result["total"]["confidence"] = confidence(
        summaries[0], int(np.count_nonzero(total)), next_result["total"]["point"],
        next_result["total"]["interval"] is not None)
    for j, result in enumerate(next_result["menus"], 1):
        result["evaluation"] = summaries[j]
        result["confidence"] = confidence(summaries[j], result["nonzero_days"],
                                           result["point"], result["interval"] is not None)
    return {
        "schema_version": 1, "phase": 2,
        "data": {"start": start.isoformat(),
                 "end": (start + timedelta(days=len(total) - 1)).isoformat(),
                 "days": len(total), "menu_count": len(ids)},
        "configuration": {"model_policy": model, "initial_train": initial_train,
                          "share_window": SHARE_WINDOW, "interval_level": level,
                          "minimum_calibration": MIN_CALIBRATION,
                          "calibration_window": CALIBRATION_WINDOW,
                          "selection_minimum_errors": MIN_SELECTION},
        "forecast": next_result,
        "backtest": {"total": records[0],
                     "menus": {str(key): records[j + 1] for j, key in enumerate(ids)}},
        "notes": [
            "Horizon hanya 1 hari setelah tanggal data terakhir; bukan ramalan beberapa hari.",
            "Auto memilih ulang dengan galat historis saja; warmup memakai seasonal_mean4.",
            "Rentang berasal dari galat pipeline sebelumnya; coverage diukur sesudah warmup kalibrasi.",
            "Target cakupan bukan jaminan probabilitas, khususnya pada deret waktu dependen.",
            "Rentang tiap menu bersifat marginal; batasnya tidak dijumlahkan menjadi rentang total.",
            "Porsi titik menu direkonsiliasi ke total; SBA mentah berubah sesudah rekonsiliasi.",
            "Menu tanpa baris penjualan sama sekali tidak dapat ditemukan dari CSV ini.",
            "Hari tanpa penjualan diisi nol; ketersediaan, hari tutup, dan stok habis tidak diketahui.",
            "Label kepercayaan memakai heuristik jumlah data dan coverage, bukan probabilitas.",
            "Data dummy hanya menguji pipeline; hasil bukan bukti akurasi pada penjualan nyata.",
        ],
    }


def report(result):
    f = result["forecast"]
    level = result["configuration"]["interval_level"]
    def bounds(value):
        return "belum cukup data" if value is None else f"{value[0]}-{value[1]}"
    def cov(summary):
        return "belum tersedia" if not summary["n"] else f"{summary['coverage']:.1%} ({summary['n']} hari uji)"
    lines = ["JIMNY SMART ASSISTANT - FASE 2", "=" * 72,
             f"Tanggal prediksi: {f['date']} | Model: {f['model']}",
             f"Pemilihan: {f['selection_reason']}",
             f"Total: {f['total']['point']:.2f} porsi | bulat: {f['total']['quantity']}",
             f"Rentang target {level:.0%}: {bounds(f['total']['interval'])}",
             f"Cakupan total aktual: {cov(f['total']['evaluation'])}",
             f"Label total: {f['total']['confidence']}", "",
             "MENU | PORSI BULAT | RENTANG | LABEL | CAKUPAN AKTUAL"]
    for m in f["menus"]:
        lines.append(f"{m['menu_id']} {m['nama_menu']} | {m['quantity']} | "
                     f"{bounds(m['interval'])} | {m['confidence']} | {cov(m['evaluation'])}")
    lines += ["", "CATATAN:", *["- " + note for note in result["notes"]]]
    return "\n".join(lines)


def write_outputs(result, out):
    out = Path(out)
    out.mkdir(parents=True, exist_ok=True)
    (out / "forecast_result.json").write_text(
        json.dumps(result, ensure_ascii=False, indent=2, allow_nan=False), encoding="utf-8")
    (out / "forecast_report.txt").write_text(report(result) + "\n", encoding="utf-8")
    with (out / "forecast_menu.csv").open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.writer(handle)
        writer.writerow(["tanggal", "menu_id", "nama_menu", "model_total", "metode_menu",
                         "prediksi", "porsi_bulat", "batas_bawah", "batas_atas", "target_cakupan",
                         "label", "cakupan_aktual", "hari_uji_interval", "mae"])
        for m in result["forecast"]["menus"]:
            e = m["evaluation"]
            writer.writerow([result["forecast"]["date"], m["menu_id"], m["nama_menu"],
                             result["forecast"]["model"], m["method"], m["point"], m["quantity"],
                             *(m["interval"] or [None, None]),
                             result["configuration"]["interval_level"], m["confidence"],
                             e["coverage"], e["n"], e["point_mae"]])
    with (out / "forecast_backtest.csv").open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.writer(handle)
        writer.writerow(["tanggal", "seri", "model", "metode", "aktual", "prediksi",
                         "batas_bawah", "batas_atas", "tercakup"])
        for key, rows in [("total", result["backtest"]["total"]),
                          *result["backtest"]["menus"].items()]:
            for r in rows:
                bound = r["interval"]
                covered = int(bound[0] <= r["actual"] <= bound[1]) if bound else None
                writer.writerow([r["date"], key, r["model"], r["method"], r["actual"],
                                 r["point"], *(bound or [None, None]), covered])


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    root = Path(__file__).resolve().parents[1]
    parser.add_argument("--total", type=Path, default=root / "storage/app/jsa/daily_total.csv")
    parser.add_argument("--menu", type=Path, default=root / "storage/app/jsa/daily_menu.csv")
    parser.add_argument("--out", type=Path, default=root / "storage/app/jsa/benchmark/phase2")
    parser.add_argument("--model", choices=["auto", *bench.MODELS], default="auto")
    parser.add_argument("--initial-train", type=int, default=42)
    parser.add_argument("--level", type=float, default=0.8)
    parser.add_argument("--today", type=date.fromisoformat, help="Tanggal acuan untuk replay historis")
    args = parser.parse_args(argv)
    try:
        today = args.today or bench.today_jakarta()
        start, total = bench.load_daily_total(args.total, today=today)
        if start + timedelta(days=len(total)) != today:
            raise ForecastError("Data terakhir harus kemarin. Ekspor ulang atau gunakan --today untuk replay historis.")
        ids, names, matrix = load_menu(args.menu, start, total)
        result = run_forecast(start, total, ids, names, matrix, args.model,
                              args.initial_train, args.level)
        result["historical_replay"] = args.today is not None
        write_outputs(result, args.out)
        print(report(result))
        print(f"\nKeluaran: {args.out}")
        return 0
    except (ForecastError, OSError, UnicodeError, csv.Error) as exc:
        print(f"GALAT: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
