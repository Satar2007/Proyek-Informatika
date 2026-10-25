"""
JIMNY SMART ASSISTANT
FASE 1 - BENCHMARK METODE PREDIKSI TOTAL PORSI HARIAN

TUJUAN:
Membandingkan beberapa metode prediksi pada data penjualan harian
secara adil, lalu memilih metode berdasarkan bukti (backtest), bukan
berdasarkan tebakan.

VALIDASI:
Rolling-origin, expanding window, prediksi 1 hari ke depan.
Setiap model hanya boleh melihat y[:t] saat memprediksi hari ke-t
(tidak ada kebocoran data dari masa depan).

PEMILIHAN:
1. Peringkat MAE.
2. Aturan satu galat baku (1-SE): pilih model paling sederhana yang
   MAE-nya masih dalam 1 SE dari MAE terbaik.
3. Bootstrap berpasangan: apakah selisih MAE benar-benar nyata?

DEPENDENSI:
numpy dan scikit-learn (matplotlib hanya opsional, untuk grafik).

BATASAN:
Data harian sangat berisik. Hasil pada data dummy hanya membuktikan
pipeline bekerja, bukan akurasi di dunia nyata.
"""

import argparse
import csv
import json
import math
import sys
from datetime import date, datetime, timedelta, timezone
from pathlib import Path

import numpy as np
from sklearn.ensemble import GradientBoostingRegressor, RandomForestRegressor
from sklearn.linear_model import Ridge


# ============================================================
# KONFIGURASI
# ============================================================

SEED = 42
DEFAULT_INITIAL_TRAIN = 42
MIN_INITIAL_TRAIN = 28
MIN_TEST_POINTS = 14
BOOTSTRAP_ROUNDS = 5000

# Urut dari paling sederhana ke paling kompleks (dipakai aturan 1-SE
# dan pemutus seri).
SIMPLICITY_ORDER = [
    "naive",
    "seasonal_naive",
    "seasonal_mean4",
    "wma7",
    "ridge_dow_trend",
    "holt_winters",
    "random_forest",
    "gradient_boosting",
]

MODEL_LABELS = {
    "naive": "Naive (kemarin)",
    "seasonal_naive": "Seasonal-naive (lag-7)",
    "seasonal_mean4": "Rata-rata 4 hari-yang-sama",
    "wma7": "WMA-7 (metode PHP)",
    "ridge_dow_trend": "Ridge hari + tren",
    "holt_winters": "Holt-Winters aditif",
    "random_forest": "Random Forest",
    "gradient_boosting": "Gradient Boosting",
}

JAKARTA = timezone(timedelta(hours=7))


class BenchmarkError(Exception):
    """Kesalahan data atau parameter yang dapat dijelaskan ke pengguna."""


# ============================================================
# MEMBACA & MEMVALIDASI DATA
# ============================================================

def today_jakarta():
    return datetime.now(JAKARTA).date()


def load_daily_total(path, today=None):
    """Baca daily_total.csv -> (tanggal_awal, array porsi harian)."""
    path = Path(path)

    if not path.is_file():
        raise BenchmarkError(f"File tidak ditemukan: {path}")

    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        fields = reader.fieldnames or []

        for column in ("tanggal", "total_porsi"):
            if column not in fields:
                raise BenchmarkError(
                    f"Kolom '{column}' tidak ada di {path.name}."
                )

        rows = list(reader)

    if not rows:
        raise BenchmarkError("Data kosong.")

    dates = []
    values = []

    for line_no, row in enumerate(rows, start=2):
        try:
            current = date.fromisoformat((row["tanggal"] or "").strip())
        except ValueError:
            raise BenchmarkError(
                f"Baris {line_no}: tanggal tidak valid "
                f"('{row['tanggal']}')."
            )

        raw = (row["total_porsi"] or "").strip()

        try:
            qty = int(raw)
        except ValueError:
            raise BenchmarkError(
                f"Baris {line_no}: total_porsi harus bilangan bulat "
                f"('{raw}')."
            )

        if qty < 0:
            raise BenchmarkError(
                f"Baris {line_no}: total_porsi tidak boleh negatif."
            )

        dates.append(current)
        values.append(qty)

    for index in range(1, len(dates)):
        if dates[index] - dates[index - 1] != timedelta(days=1):
            raise BenchmarkError(
                "Tanggal harus berurutan harian tanpa celah "
                f"(masalah di {dates[index].isoformat()})."
            )

    reference = today if today is not None else today_jakarta()

    if dates[-1] >= reference:
        raise BenchmarkError(
            "Data harus berakhir sebelum hari ini "
            f"(data terakhir {dates[-1].isoformat()}, "
            f"hari ini {reference.isoformat()}). "
            "Hari berjalan belum lengkap dan membiaskan hasil."
        )

    return dates[0], np.asarray(values, dtype=float)


# ============================================================
# MODEL (setiap model menerima riwayat y dan tanggal awal)
# ============================================================

def target_weekday(start, n):
    """Hari dalam minggu (Senin=0) untuk hari ke-n (indeks dari 0)."""
    return (start + timedelta(days=n)).weekday()


def predict_naive(y, start):
    return float(y[-1])


def predict_seasonal_naive(y, start):
    return float(y[-7])


def predict_seasonal_mean4(y, start):
    return float(np.mean([y[-7], y[-14], y[-21], y[-28]]))


def predict_wma7(y, start):
    weights = np.arange(1, 8, dtype=float)
    return float(np.dot(y[-7:], weights) / weights.sum())


def _hw_run(y, alpha, beta, gamma):
    """Jalankan Holt-Winters aditif (musim 7). Kembalikan (SSE, ramalan)."""
    season = 7
    first = y[:season]
    second = y[season:2 * season]

    level = float(first.mean())
    trend = float((second.mean() - first.mean()) / season)
    seasonal = list(first - level)

    sse = 0.0

    for t in range(season, len(y)):
        forecast = level + trend + seasonal[t % season]
        error = y[t] - forecast
        sse += error * error

        previous_level = level
        level = alpha * (y[t] - seasonal[t % season]) + (
            1 - alpha
        ) * (level + trend)
        trend = beta * (level - previous_level) + (1 - beta) * trend
        seasonal[t % season] = gamma * (y[t] - level) + (
            1 - gamma
        ) * seasonal[t % season]

    next_forecast = level + trend + seasonal[len(y) % season]
    return sse, next_forecast


def predict_holt_winters(y, start):
    best_sse = math.inf
    best_forecast = float(y[-1])

    for alpha in (0.1, 0.3, 0.5):
        for beta in (0.0, 0.05, 0.1):
            for gamma in (0.1, 0.3, 0.5):
                sse, forecast = _hw_run(y, alpha, beta, gamma)

                if sse < best_sse:
                    best_sse = sse
                    best_forecast = forecast

    return float(best_forecast)


def _dow_trend_row(index, start):
    row = np.zeros(8)
    row[target_weekday(start, index)] = 1.0
    row[7] = index / 30.0
    return row


def predict_ridge_dow_trend(y, start):
    n = len(y)
    x_train = np.vstack([_dow_trend_row(i, start) for i in range(n)])
    model = Ridge(alpha=1.0)
    model.fit(x_train, y)
    return float(model.predict(_dow_trend_row(n, start).reshape(1, -1))[0])


def _tree_features(y, index, start):
    weekday = target_weekday(start, index)
    window = y[index - 7:index]

    return [
        weekday,
        1.0 if weekday >= 5 else 0.0,
        y[index - 1],
        y[index - 7],
        float(window.mean()),
        index,
    ]


def _fit_tree_model(model, y, start):
    n = len(y)
    x_train = np.asarray(
        [_tree_features(y, i, start) for i in range(7, n)]
    )
    model.fit(x_train, y[7:n])
    return float(
        model.predict(np.asarray([_tree_features(y, n, start)]))[0]
    )


def predict_random_forest(y, start):
    model = RandomForestRegressor(
        n_estimators=300,
        min_samples_leaf=2,
        random_state=SEED,
        n_jobs=1,
    )
    return _fit_tree_model(model, y, start)


def predict_gradient_boosting(y, start):
    model = GradientBoostingRegressor(
        n_estimators=150,
        max_depth=2,
        learning_rate=0.05,
        subsample=0.8,
        random_state=SEED,
    )
    return _fit_tree_model(model, y, start)


MODELS = {
    "naive": predict_naive,
    "seasonal_naive": predict_seasonal_naive,
    "seasonal_mean4": predict_seasonal_mean4,
    "wma7": predict_wma7,
    "ridge_dow_trend": predict_ridge_dow_trend,
    "holt_winters": predict_holt_winters,
    "random_forest": predict_random_forest,
    "gradient_boosting": predict_gradient_boosting,
}


# ============================================================
# BACKTEST & METRIK
# ============================================================

def check_parameters(n, initial_train):
    if initial_train < MIN_INITIAL_TRAIN:
        raise BenchmarkError(
            f"--initial-train minimal {MIN_INITIAL_TRAIN} hari."
        )

    if n - initial_train < MIN_TEST_POINTS:
        raise BenchmarkError(
            f"Data terlalu pendek: {n} hari. Dibutuhkan minimal "
            f"{initial_train + MIN_TEST_POINTS} hari "
            f"({initial_train} latih + {MIN_TEST_POINTS} uji)."
        )


def rolling_origin(y, start, initial_train=DEFAULT_INITIAL_TRAIN,
                   models=None):
    """
    Prediksi 1 hari ke depan untuk setiap titik uji.
    Model hanya melihat y[:t]. Kembalikan {model: array prediksi}.
    """
    models = models or MODELS
    check_parameters(len(y), initial_train)

    predictions = {name: [] for name in models}

    for t in range(initial_train, len(y)):
        history = y[:t].copy()

        for name, function in models.items():
            predictions[name].append(function(history, start))

    return {name: np.asarray(v) for name, v in predictions.items()}


def smape(actual, predicted):
    denominator = np.abs(actual) + np.abs(predicted)
    safe = np.where(denominator == 0, 1.0, denominator)
    value = np.where(denominator == 0, 0.0, 2 * np.abs(predicted - actual) / safe)
    return float(value.mean() * 100)


def metrics_for(actual, predicted):
    error = predicted - actual
    absolute = np.abs(error)
    n = len(actual)

    se = float(absolute.std(ddof=1) / math.sqrt(n)) if n > 1 else 0.0

    return {
        "mae": float(absolute.mean()),
        "rmse": float(math.sqrt(np.mean(error ** 2))),
        "smape": smape(actual, predicted),
        "bias": float(error.mean()),
        "se_mae": se,
    }


def rank_models(all_metrics):
    return sorted(
        all_metrics,
        key=lambda name: (
            all_metrics[name]["mae"],
            SIMPLICITY_ORDER.index(name),
        ),
    )


def select_one_se(all_metrics):
    """Model paling sederhana dengan MAE <= MAE terbaik + SE terbaik."""
    ranking = rank_models(all_metrics)
    best = ranking[0]
    threshold = all_metrics[best]["mae"] + all_metrics[best]["se_mae"]

    for name in SIMPLICITY_ORDER:
        if name in all_metrics and all_metrics[name]["mae"] <= threshold:
            return name, best, threshold

    return best, best, threshold


def paired_bootstrap(actual, predictions, reference,
                     rounds=BOOTSTRAP_ROUNDS, seed=SEED):
    """
    Selisih MAE (reference - lainnya) dengan CI 95%.
    Negatif = reference lebih baik. Nyata bila CI tidak mencakup 0.
    """
    rng = np.random.default_rng(seed)
    n = len(actual)
    reference_error = np.abs(predictions[reference] - actual)
    indices = rng.integers(0, n, size=(rounds, n))

    result = {}

    for name, predicted in predictions.items():
        if name == reference:
            continue

        difference = reference_error - np.abs(predicted - actual)
        boot = difference[indices].mean(axis=1)
        low, high = np.percentile(boot, [2.5, 97.5])

        result[name] = {
            "diff": float(difference.mean()),
            "ci_low": float(low),
            "ci_high": float(high),
            "significant": bool(low > 0 or high < 0),
        }

    return result


# ============================================================
# BENCHMARK UTAMA
# ============================================================

def run_benchmark(y, start, initial_train=DEFAULT_INITIAL_TRAIN,
                  models=None, rounds=BOOTSTRAP_ROUNDS):
    models = models or MODELS
    y = np.asarray(y, dtype=float)

    predictions = rolling_origin(y, start, initial_train, models)
    actual = y[initial_train:]
    test_dates = [
        (start + timedelta(days=i)).isoformat()
        for i in range(initial_train, len(y))
    ]

    all_metrics = {
        name: metrics_for(actual, predicted)
        for name, predicted in predictions.items()
    }

    ranking = rank_models(all_metrics)
    recommended, best, threshold = select_one_se(all_metrics)

    pairwise = paired_bootstrap(actual, predictions, best, rounds=rounds)

    next_date = (start + timedelta(days=len(y))).isoformat()
    next_day = {
        name: float(function(y.copy(), start))
        for name, function in models.items()
    }

    return {
        "data": {
            "start": start.isoformat(),
            "end": (start + timedelta(days=len(y) - 1)).isoformat(),
            "days": int(len(y)),
            "total_porsi": int(y.sum()),
            "mean": float(y.mean()),
            "std": float(y.std(ddof=1)) if len(y) > 1 else 0.0,
        },
        "validation": {
            "scheme": "rolling-origin, expanding window, horizon 1 hari",
            "initial_train": int(initial_train),
            "test_points": int(len(actual)),
            "test_start": test_dates[0],
            "test_end": test_dates[-1],
            "seed": SEED,
        },
        "metrics": all_metrics,
        "ranking": ranking,
        "best_by_mae": best,
        "recommended_one_se": recommended,
        "one_se_threshold": threshold,
        "pairwise_vs_best": pairwise,
        "next_day": {"date": next_date, "predictions": next_day},
        "test_dates": test_dates,
        "actual": [float(v) for v in actual],
        "predictions": {
            name: [float(v) for v in values]
            for name, values in predictions.items()
        },
    }


# ============================================================
# LAPORAN & KELUARAN
# ============================================================

def fmt(value, digits=2):
    return f"{value:.{digits}f}".replace(".", ",")


def build_report(result):
    data = result["data"]
    validation = result["validation"]
    metrics = result["metrics"]
    best = result["best_by_mae"]
    recommended = result["recommended_one_se"]

    lines = []
    lines.append("JIMNY SMART ASSISTANT - BENCHMARK METODE (FASE 1)")
    lines.append("=" * 60)
    lines.append(
        f"Data      : {data['start']} s/d {data['end']} "
        f"({data['days']} hari, {data['total_porsi']} porsi, "
        f"rata-rata {fmt(data['mean'])}/hari, "
        f"simpangan baku {fmt(data['std'])})"
    )
    lines.append(
        f"Validasi  : {validation['scheme']}; latih awal "
        f"{validation['initial_train']} hari; "
        f"{validation['test_points']} titik uji "
        f"({validation['test_start']} s/d {validation['test_end']})"
    )
    lines.append("")
    lines.append(
        f"{'#':<3}{'Model':<30}{'MAE':>8}{'RMSE':>8}"
        f"{'sMAPE%':>9}{'Bias':>8}{'SE-MAE':>8}"
    )
    lines.append("-" * 74)

    for rank, name in enumerate(result["ranking"], start=1):
        m = metrics[name]
        lines.append(
            f"{rank:<3}{name:<30}{fmt(m['mae']):>8}{fmt(m['rmse']):>8}"
            f"{fmt(m['smape'], 1):>9}{fmt(m['bias']):>8}"
            f"{fmt(m['se_mae']):>8}"
        )

    lines.append("")
    lines.append(f"MAE terendah           : {best}")
    lines.append(
        f"Dianjurkan (aturan 1-SE): {recommended} "
        f"(ambang MAE <= {fmt(result['one_se_threshold'])})"
    )
    lines.append("")
    lines.append(f"Perbandingan berpasangan terhadap '{best}' "
                 "(selisih MAE, CI 95% bootstrap; negatif = lebih baik):")

    for name in result["ranking"]:
        if name == best:
            continue

        p = result["pairwise_vs_best"][name]
        verdict = "BEDA NYATA" if p["significant"] else "tidak beda nyata"
        lines.append(
            f"  vs {name:<20} {fmt(p['diff']):>7} "
            f"[{fmt(p['ci_low'])}; {fmt(p['ci_high'])}]  {verdict}"
        )

    lines.append("")
    lines.append(
        f"Prediksi {result['next_day']['date']} (porsi, 1 hari ke depan):"
    )

    for name in SIMPLICITY_ORDER:
        if name in result["next_day"]["predictions"]:
            lines.append(
                f"  {name:<20} "
                f"{fmt(result['next_day']['predictions'][name])}"
            )

    lines.append("")
    lines.append("CATATAN JUJUR:")
    lines.append(
        "- Data harian berisik; tidak ada model yang menebak angka pasti."
    )
    lines.append(
        "- Jika data dummy dibuat dengan pola tertentu, pemenang "
        "mengikuti pola itu. Ini membuktikan pipeline, bukan akurasi nyata."
    )
    lines.append(
        "- Selisih yang CI-nya mencakup 0 tidak boleh diklaim "
        "sebagai 'lebih baik'."
    )

    return "\n".join(lines)


def write_outputs(result, out_dir):
    out_dir = Path(out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)

    with (out_dir / "benchmark_result.json").open(
        "w", encoding="utf-8"
    ) as handle:
        json.dump(result, handle, ensure_ascii=False, indent=2)

    names = [n for n in SIMPLICITY_ORDER if n in result["predictions"]]

    with (out_dir / "benchmark_predictions.csv").open(
        "w", encoding="utf-8", newline=""
    ) as handle:
        writer = csv.writer(handle)
        writer.writerow(["tanggal", "aktual"] + names)

        for i, day in enumerate(result["test_dates"]):
            writer.writerow(
                [day, result["actual"][i]]
                + [round(result["predictions"][n][i], 4) for n in names]
            )

    chart_path = None

    try:
        import matplotlib

        matplotlib.use("Agg")
        import matplotlib.pyplot as plt

        figure, axis = plt.subplots(figsize=(11, 5))
        x_axis = list(range(len(result["test_dates"])))

        axis.plot(x_axis, result["actual"], color="black",
                  linewidth=2, label="Aktual")

        highlight = {
            result["best_by_mae"],
            result["recommended_one_se"],
            "wma7",
        }

        for name in names:
            if name in highlight:
                axis.plot(x_axis, result["predictions"][name],
                          linewidth=1.3, label=name)

        step = max(1, len(x_axis) // 10)
        axis.set_xticks(x_axis[::step])
        axis.set_xticklabels(result["test_dates"][::step],
                             rotation=45, ha="right")
        axis.set_ylabel("Porsi per hari")
        axis.set_title("Backtest rolling-origin (prediksi 1 hari)")
        axis.legend()
        axis.grid(alpha=0.3)
        figure.tight_layout()

        chart_path = out_dir / "benchmark_chart.png"
        figure.savefig(chart_path, dpi=130)
        plt.close(figure)
    except ImportError:
        chart_path = None

    return chart_path


# ============================================================
# CLI
# ============================================================

def build_parser():
    parser = argparse.ArgumentParser(
        description="Benchmark metode prediksi total porsi harian JSA."
    )
    parser.add_argument(
        "--input", default="storage/app/jsa/daily_total.csv",
        help="File daily_total.csv hasil php artisan jsa:export.",
    )
    parser.add_argument(
        "--out", default="storage/app/jsa/benchmark",
        help="Folder keluaran.",
    )
    parser.add_argument(
        "--initial-train", type=int, default=DEFAULT_INITIAL_TRAIN,
        help=f"Hari latih awal (minimal {MIN_INITIAL_TRAIN}).",
    )
    parser.add_argument(
        "--today", default=None,
        help="Tanggal 'hari ini' (YYYY-MM-DD), untuk pengujian.",
    )
    return parser


def main(argv=None):
    args = build_parser().parse_args(argv)

    try:
        today = date.fromisoformat(args.today) if args.today else None
        start, y = load_daily_total(args.input, today=today)
        result = run_benchmark(y, start, args.initial_train)
    except (BenchmarkError, ValueError) as error:
        print(f"GALAT: {error}", file=sys.stderr)
        return 1

    print(build_report(result))

    chart = write_outputs(result, args.out)

    print("")
    print(f"Keluaran ditulis ke: {Path(args.out)}")

    if chart is None:
        print("(Grafik dilewati: matplotlib tidak terpasang.)")

    return 0


if __name__ == "__main__":
    sys.exit(main())