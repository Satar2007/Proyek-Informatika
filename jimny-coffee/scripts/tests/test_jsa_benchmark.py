import contextlib
import importlib.util
import io
import tempfile
import unittest
from datetime import date, timedelta
from pathlib import Path

import numpy as np


SCRIPT_PATH = Path(__file__).resolve().parents[1] / "jsa_benchmark.py"

spec = importlib.util.spec_from_file_location("jsa_benchmark", SCRIPT_PATH)
bench = importlib.util.module_from_spec(spec)
spec.loader.exec_module(bench)

START = date(2026, 1, 5)  # Senin
TODAY = date(2026, 12, 31)

FAST_MODELS = {
    name: bench.MODELS[name]
    for name in (
        "naive",
        "seasonal_naive",
        "seasonal_mean4",
        "wma7",
        "ridge_dow_trend",
        "holt_winters",
    )
}


def weekly_series(days, pattern=(10, 12, 11, 20, 18, 40, 35), noise=0.0,
                  seed=1):
    rng = np.random.default_rng(seed)
    base = np.asarray([pattern[i % 7] for i in range(days)], dtype=float)
    return np.maximum(0, base + rng.normal(0, noise, days)) if noise else base


def write_csv(directory, rows, header="tanggal,total_porsi,jumlah_transaksi"):
    path = Path(directory) / "daily_total.csv"
    path.write_text(header + "\n" + "\n".join(rows) + "\n", encoding="utf-8")
    return path


def make_rows(days, start=START, qty=lambda i: 10 + i % 7):
    return [
        f"{(start + timedelta(days=i)).isoformat()},{qty(i)},3"
        for i in range(days)
    ]


class LoadDataTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)

    def test_valid_csv(self):
        path = write_csv(self.tmp.name, make_rows(10))
        start, y = bench.load_daily_total(path, today=TODAY)

        self.assertEqual(START, start)
        self.assertEqual(10, len(y))
        self.assertEqual(10.0, y[0])

    def test_missing_file(self):
        with self.assertRaisesRegex(bench.BenchmarkError, "tidak ditemukan"):
            bench.load_daily_total(Path(self.tmp.name) / "x.csv", today=TODAY)

    def test_missing_column(self):
        path = write_csv(
            self.tmp.name, ["2026-01-05,3"], header="tanggal,jumlah"
        )

        with self.assertRaisesRegex(bench.BenchmarkError, "total_porsi"):
            bench.load_daily_total(path, today=TODAY)

    def test_gap_in_dates(self):
        path = write_csv(
            self.tmp.name,
            ["2026-01-05,10,3", "2026-01-07,12,3"],
        )

        with self.assertRaisesRegex(bench.BenchmarkError, "berurutan"):
            bench.load_daily_total(path, today=TODAY)

    def test_negative_and_non_integer(self):
        negative = write_csv(self.tmp.name, ["2026-01-05,-1,3"])

        with self.assertRaisesRegex(bench.BenchmarkError, "negatif"):
            bench.load_daily_total(negative, today=TODAY)

        decimal = write_csv(self.tmp.name, ["2026-01-05,2.5,3"])

        with self.assertRaisesRegex(bench.BenchmarkError, "bilangan bulat"):
            bench.load_daily_total(decimal, today=TODAY)

    def test_data_must_end_before_today(self):
        path = write_csv(self.tmp.name, make_rows(5))
        last = START + timedelta(days=4)

        with self.assertRaisesRegex(bench.BenchmarkError, "sebelum hari ini"):
            bench.load_daily_total(path, today=last)

        start, y = bench.load_daily_total(
            path, today=last + timedelta(days=1)
        )
        self.assertEqual(5, len(y))


class BaselineModelTest(unittest.TestCase):
    def setUp(self):
        self.y = np.arange(1, 31, dtype=float)  # 1..30

    def test_naive_and_seasonal(self):
        self.assertEqual(30.0, bench.predict_naive(self.y, START))
        self.assertEqual(24.0, bench.predict_seasonal_naive(self.y, START))
        self.assertEqual(
            np.mean([24, 17, 10, 3]),
            bench.predict_seasonal_mean4(self.y, START),
        )

    def test_wma7_matches_php_weights(self):
        # PHP: bobot 1..7 dari terlama ke terbaru, dibagi 28.
        last_seven = self.y[-7:]
        expected = sum(
            value * weight
            for value, weight in zip(last_seven, range(1, 8))
        ) / 28

        self.assertAlmostEqual(expected, bench.predict_wma7(self.y, START))


class AdvancedModelTest(unittest.TestCase):
    def test_holt_winters_on_pure_weekly_pattern(self):
        y = weekly_series(70)
        start = START
        expected = y[70 % 7]  # pola berulang
        forecast = bench.predict_holt_winters(y, start)

        self.assertAlmostEqual(expected, forecast, delta=1.0)

    def test_ridge_learns_weekday_effect(self):
        y = weekly_series(56)
        forecast = bench.predict_ridge_dow_trend(y, START)

        self.assertAlmostEqual(y[56 % 7], forecast, delta=1.5)

    def test_constant_series_gives_near_zero_error(self):
        y = np.full(45, 20.0)
        predictions = bench.rolling_origin(y, START, initial_train=28)

        for name, values in predictions.items():
            self.assertLess(
                float(np.abs(values - 20.0).max()), 0.5, msg=name
            )


class BacktestTest(unittest.TestCase):
    def test_no_data_leakage(self):
        y = weekly_series(45, noise=3.0)
        changed = y.copy()
        k = 40
        changed[k] += 500.0

        base = bench.rolling_origin(y, START, 28, FAST_MODELS)
        other = bench.rolling_origin(changed, START, 28, FAST_MODELS)

        for name in FAST_MODELS:
            # titik uji ke-i memakai y[:28+i]; y[k] baru terlihat
            # mulai titik uji dengan 28+i > k.
            safe = k - 28 + 1
            np.testing.assert_array_equal(
                base[name][:safe], other[name][:safe], err_msg=name
            )

    def test_deterministic(self):
        y = weekly_series(45, noise=3.0)
        subset = {
            "random_forest": bench.MODELS["random_forest"],
            "gradient_boosting": bench.MODELS["gradient_boosting"],
        }

        first = bench.rolling_origin(y, START, 28, subset)
        second = bench.rolling_origin(y, START, 28, subset)

        for name in subset:
            np.testing.assert_array_equal(first[name], second[name])

    def test_too_short_data_rejected(self):
        with self.assertRaisesRegex(bench.BenchmarkError, "terlalu pendek"):
            bench.run_benchmark(weekly_series(50), START, 42)

    def test_initial_train_below_minimum_rejected(self):
        with self.assertRaisesRegex(bench.BenchmarkError, "minimal"):
            bench.run_benchmark(weekly_series(90), START, 20)


class MetricsAndSelectionTest(unittest.TestCase):
    def test_metrics_values(self):
        actual = np.array([10.0, 20.0, 30.0])
        predicted = np.array([12.0, 18.0, 36.0])
        m = bench.metrics_for(actual, predicted)

        self.assertAlmostEqual(10 / 3, m["mae"])
        self.assertAlmostEqual(np.sqrt((4 + 4 + 36) / 3), m["rmse"])
        self.assertAlmostEqual(2.0, m["bias"])
        self.assertGreater(m["se_mae"], 0)

    def test_smape_handles_zero_zero(self):
        value = bench.smape(np.array([0.0, 10.0]), np.array([0.0, 10.0]))
        self.assertEqual(0.0, value)

    def test_ranking_breaks_ties_by_simplicity(self):
        metrics = {
            "ridge_dow_trend": {"mae": 5.0, "se_mae": 1.0},
            "naive": {"mae": 5.0, "se_mae": 1.0},
        }
        self.assertEqual("naive", bench.rank_models(metrics)[0])

    def test_one_se_prefers_simpler_model(self):
        metrics = {
            "seasonal_mean4": {"mae": 11.4, "se_mae": 1.4},
            "ridge_dow_trend": {"mae": 10.6, "se_mae": 1.2},
            "random_forest": {"mae": 11.3, "se_mae": 1.2},
            "naive": {"mae": 15.6, "se_mae": 1.5},
        }
        recommended, best, threshold = bench.select_one_se(metrics)

        self.assertEqual("ridge_dow_trend", best)
        self.assertEqual("seasonal_mean4", recommended)
        self.assertAlmostEqual(11.8, threshold)

    def test_one_se_keeps_best_when_others_far(self):
        metrics = {
            "naive": {"mae": 20.0, "se_mae": 1.0},
            "ridge_dow_trend": {"mae": 5.0, "se_mae": 1.0},
        }
        recommended, best, _ = bench.select_one_se(metrics)

        self.assertEqual("ridge_dow_trend", recommended)

    def test_bootstrap_detects_clear_difference_only(self):
        rng = np.random.default_rng(3)
        actual = rng.uniform(20, 40, 60)
        good = actual + rng.normal(0, 1, 60)
        bad = actual + rng.normal(0, 10, 60)
        twin = good + rng.normal(0, 0.01, 60)

        result = bench.paired_bootstrap(
            actual,
            {"good": good, "bad": bad, "twin": twin},
            "good",
            rounds=1000,
        )

        self.assertTrue(result["bad"]["significant"])
        self.assertLess(result["bad"]["diff"], 0)
        self.assertFalse(result["twin"]["significant"])


class RunBenchmarkTest(unittest.TestCase):
    def test_result_structure_and_next_day(self):
        y = weekly_series(50, noise=2.0)
        result = bench.run_benchmark(
            y, START, 28, models=FAST_MODELS, rounds=200
        )

        self.assertEqual(22, result["validation"]["test_points"])
        self.assertEqual(
            (START + timedelta(days=50)).isoformat(),
            result["next_day"]["date"],
        )
        self.assertEqual(set(FAST_MODELS), set(result["metrics"]))
        self.assertIn(result["best_by_mae"], FAST_MODELS)
        self.assertIn(result["recommended_one_se"], FAST_MODELS)
        self.assertIn("Dianjurkan", bench.build_report(result))


class CliTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)

    def run_cli(self, argv):
        out, err = io.StringIO(), io.StringIO()

        with contextlib.redirect_stdout(out), contextlib.redirect_stderr(err):
            code = bench.main(argv)

        return code, out.getvalue(), err.getvalue()

    def test_cli_success_writes_outputs(self):
        rows = make_rows(
            50, qty=lambda i: [10, 12, 11, 20, 18, 40, 35][i % 7] + i % 3
        )
        path = write_csv(self.tmp.name, rows)
        out_dir = Path(self.tmp.name) / "out"

        code, stdout, _ = self.run_cli([
            "--input", str(path),
            "--out", str(out_dir),
            "--initial-train", "28",
            "--today", TODAY.isoformat(),
        ])

        self.assertEqual(0, code)
        self.assertIn("BENCHMARK METODE", stdout)
        self.assertTrue((out_dir / "benchmark_result.json").is_file())
        self.assertTrue((out_dir / "benchmark_predictions.csv").is_file())

    def test_cli_reports_error_with_exit_code_1(self):
        path = write_csv(self.tmp.name, make_rows(10))

        code, _, stderr = self.run_cli([
            "--input", str(path),
            "--out", str(Path(self.tmp.name) / "out"),
            "--today", TODAY.isoformat(),
        ])

        self.assertEqual(1, code)
        self.assertIn("GALAT", stderr)


if __name__ == "__main__":
    unittest.main()