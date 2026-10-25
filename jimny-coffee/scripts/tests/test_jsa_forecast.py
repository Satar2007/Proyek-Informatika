"""Fase 2: validasi input, konservasi total, dan audit tanpa kebocoran waktu."""
import contextlib
import csv
import importlib.util
import io
import json
import math
import tempfile
import unittest
from datetime import date, timedelta
from pathlib import Path
from unittest.mock import patch

import numpy as np

SCRIPT = Path(__file__).resolve().parents[1] / "jsa_forecast.py"
spec = importlib.util.spec_from_file_location("jsa_forecast_tested", SCRIPT)
f = importlib.util.module_from_spec(spec)
spec.loader.exec_module(f)
START = date(2026, 1, 5)
FAST = {key: f.bench.MODELS[key] for key in ("naive", "seasonal_mean4", "ridge_dow_trend")}


def sample(n=90):
    menu = np.zeros((n, 3))
    menu[:, 0] = np.asarray([10, 12, 8, 15, 18, 30, 25] * math.ceil(n / 7))[:n]
    menu[:, 1] = 3
    menu[::9, 2] = 4
    return menu.sum(axis=1), menu


def run(n=90, **kwargs):
    total, menu = sample(n)
    return f.run_forecast(START, total, [1, 2, 3], ["A", "B", "C"], menu,
                          models=FAST, **kwargs)


class InputTest(unittest.TestCase):
    def csv_file(self, text):
        folder = tempfile.TemporaryDirectory()
        self.addCleanup(folder.cleanup)
        path = Path(folder.name) / "daily_menu.csv"
        path.write_text(text, encoding="utf-8")
        return path

    def test_sparse_zero_fill_and_consistent_total(self):
        p = self.csv_file("tanggal,menu_id,nama_menu,porsi\n2026-01-05,2,B,3\n2026-01-07,1,A,4\n")
        ids, names, matrix = f.load_menu(p, START, np.array([3, 0, 4]))
        self.assertEqual(ids, [1, 2])
        np.testing.assert_array_equal(matrix, [[0, 3], [0, 0], [4, 0]])

    def test_duplicate_menu_day_rejected(self):
        p = self.csv_file("tanggal,menu_id,nama_menu,porsi\n2026-01-05,1,A,2\n2026-01-05,1,A,3\n")
        with self.assertRaisesRegex(f.ForecastError, "duplikat"):
            f.load_menu(p, START, np.array([5]))

    def test_daily_mismatch_rejected(self):
        p = self.csv_file("tanggal,menu_id,nama_menu,porsi\n2026-01-05,1,A,2\n")
        with self.assertRaisesRegex(f.ForecastError, "tidak cocok"):
            f.load_menu(p, START, np.array([3]))

    def test_invalid_menu_rows_rejected(self):
        for row in ("2026-01-04,1,A,2", "2026-01-05,0,A,2",
                    "2026-01-05,1,A,-1", "2026-01-05,1,A,1.5",
                    "2026-01-05,1,,2", "invalid,1,A,2"):
            with self.subTest(row=row):
                p = self.csv_file("tanggal,menu_id,nama_menu,porsi\n" + row + "\n")
                with self.assertRaises(f.ForecastError):
                    f.load_menu(p, START, np.array([2]))

    def test_missing_columns_empty_and_missing_file_rejected(self):
        for text in ("tanggal,porsi\n", "tanggal,menu_id,nama_menu,porsi\n"):
            with self.assertRaises(f.ForecastError):
                f.load_menu(self.csv_file(text), START, np.array([0]))
        with self.assertRaises(f.ForecastError):
            f.load_menu("not-a-file.csv", START, np.array([0]))

    def test_invalid_direct_matrix_rejected(self):
        total, menu = sample()
        menu[0, 0] += 1
        with self.assertRaises(f.ForecastError):
            f.run_forecast(START, total, [1, 2, 3], ["A", "B", "C"], menu, models=FAST)


class ForecastTest(unittest.TestCase):
    def test_sba_known_spacing_and_zero(self):
        self.assertEqual(f.croston_sba(np.zeros(50)), 0)
        y = np.tile([0, 0, 0, 8], 15)
        self.assertAlmostEqual(f.croston_sba(y), 1.9)

    def test_allocation_conserves_total_and_uses_intermittent_method(self):
        _, menu = sample()
        points, methods, _, _ = f.allocate_menu(menu, 47.5)
        self.assertAlmostEqual(points.sum(), 47.5)
        self.assertTrue((points >= 0).all())
        self.assertEqual(methods[0], "top_down_28d")
        self.assertEqual(methods[2], "sba_reconciled")

    def test_integer_menu_quantities_sum_to_total(self):
        for points in ([1.4, 2.4, 3.4], [0, 0, 0], [0.5, 0.5, 0.5], [4.9]):
            quantities, total = f.integer_allocation(np.array(points))
            self.assertEqual(sum(quantities), total)
            self.assertEqual(total, math.floor(sum(points) + 0.5))
            self.assertTrue(all(x >= 0 for x in quantities))

    def test_all_zero_history_has_zero_forecast(self):
        out = f.run_forecast(START, np.zeros(90), [1], ["A"], np.zeros((90, 1)), models=FAST)
        self.assertEqual(out["forecast"]["total"]["quantity"], 0)
        self.assertEqual(out["forecast"]["menus"][0]["interval"], [0, 0])
        self.assertEqual(out["forecast"]["total"]["confidence"], "data belum cukup")

    def test_absent_menu_never_gets_invented_demand(self):
        total, menu = sample()
        menu[:, 2] = 0
        total = menu.sum(axis=1)
        out = f.run_forecast(START, total, [1, 2, 3], ["A", "B", "C"], menu, models=FAST)
        self.assertEqual(out["forecast"]["menus"][2]["point"], 0)
        self.assertEqual(out["forecast"]["menus"][2]["confidence"], "data belum cukup")

    def test_interval_rank_clipping_and_warmup(self):
        self.assertIsNone(f.interval(3, [1] * 19))
        self.assertEqual(f.interval(3, list(range(1, 21))), [0, 20])
        self.assertEqual(f.interval(3, [0] * 20), [3, 3])

    def test_interval_ignores_errors_older_than_window(self):
        self.assertEqual(f.interval(10, [10000] * 10 + [1] * 60), [9, 11])

    def test_coverage_excludes_warmup(self):
        result = run()
        total = result["forecast"]["total"]
        self.assertEqual(total["evaluation"]["n"], 28)
        rows = result["backtest"]["total"]
        self.assertTrue(all(r["interval"] is None for r in rows[:20]))
        counted = [r for r in rows if r["interval"] is not None]
        covered = sum(r["interval"][0] <= r["actual"] <= r["interval"][1] for r in counted)
        self.assertEqual(total["evaluation"]["coverage"], covered / 28)

    def test_short_calibration_returns_no_interval_or_confident_label(self):
        result = run(56)
        self.assertIsNone(result["forecast"]["total"]["interval"])
        self.assertEqual(result["forecast"]["total"]["confidence"], "data belum cukup")

    def test_fixed_ridge_policy_and_determinism(self):
        a = run(model="ridge_dow_trend")
        b = run(model="ridge_dow_trend")
        self.assertEqual(a, b)
        self.assertTrue(all(r["model"] == "ridge_dow_trend" for r in a["backtest"]["total"]))

    def test_future_sales_cannot_change_earlier_forecasts_or_intervals(self):
        total, menu = sample(90)
        before = f.run_forecast(START, total, [1, 2, 3], ["A", "B", "C"], menu, models=FAST)
        menu[75:] *= 20
        total = menu.sum(axis=1)
        after = f.run_forecast(START, total, [1, 2, 3], ["A", "B", "C"], menu, models=FAST)
        # Hari ke-75 juga harus sama prediksinya meski aktualnya diubah.
        for key in ["total", "1", "2", "3"]:
            a = before["backtest"]["total"] if key == "total" else before["backtest"]["menus"][key]
            b = after["backtest"]["total"] if key == "total" else after["backtest"]["menus"][key]
            for old, new in zip(a[:34], b[:34]):
                for field in ("date", "point", "interval", "model", "method"):
                    self.assertEqual(old[field], new[field])

    def test_actual_day_does_not_calibrate_its_own_interval(self):
        total, menu = sample(90)
        before = f.run_forecast(START, total, [1, 2, 3], ["A", "B", "C"], menu, models=FAST)
        t = 62  # interval pertama: persis 20 residual sebelumnya
        menu[t, 0] += 100000
        after = f.run_forecast(START, menu.sum(axis=1), [1, 2, 3], ["A", "B", "C"], menu, models=FAST)
        self.assertEqual(before["backtest"]["total"][20]["interval"],
                         after["backtest"]["total"][20]["interval"])

    def test_model_selection_only_uses_past_prediction_errors(self):
        total, _ = sample()
        predictions = {key: np.ones(48) * 20 for key in FAST}
        before = f.select_at(65, total, predictions, 42, "auto")
        for values in predictions.values():
            values[23:] = 100000
        self.assertEqual(before, f.select_at(65, total, predictions, 42, "auto"))


class CliTest(unittest.TestCase):
    def fixture(self, root):
        total, menu = sample()
        tp, mp = root / "daily_total.csv", root / "daily_menu.csv"
        with tp.open("w", newline="") as h:
            w = csv.writer(h)
            w.writerow(["tanggal", "total_porsi"])
            for i, value in enumerate(total):
                w.writerow([START + timedelta(days=i), int(value)])
        with mp.open("w", newline="") as h:
            w = csv.writer(h)
            w.writerow(["tanggal", "menu_id", "nama_menu", "porsi"])
            for i, row in enumerate(menu):
                for j, value in enumerate(row):
                    if value:
                        w.writerow([START + timedelta(days=i), j + 1, f"Menu {j}", int(value)])
        return ["--total", str(tp), "--menu", str(mp), "--out", str(root / "out"),
                "--model", "seasonal_mean4"]

    def test_cli_writes_replay_outputs_with_real_coverage(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            args = self.fixture(root) + ["--today", str(START + timedelta(days=90))]
            with contextlib.redirect_stdout(io.StringIO()):
                self.assertEqual(f.main(args), 0)
            result = json.loads((root / "out/forecast_result.json").read_text())
            self.assertTrue(result["historical_replay"])
            self.assertEqual(result["forecast"]["total"]["evaluation"]["n"], 28)
            for name in ("forecast_menu.csv", "forecast_backtest.csv", "forecast_report.txt"):
                self.assertTrue((root / "out" / name).is_file())

    def test_stale_data_rejected_without_outputs(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            args = self.fixture(root)
            with patch.object(f.bench, "today_jakarta", return_value=date(2026, 12, 31)):
                with contextlib.redirect_stderr(io.StringIO()) as err:
                    self.assertEqual(f.main(args), 1)
            self.assertIn("kemarin", err.getvalue())
            self.assertFalse((root / "out").exists())

    def test_bad_interval_level_returns_exit_1(self):
        with tempfile.TemporaryDirectory() as folder:
            args = self.fixture(Path(folder)) + ["--today", str(START + timedelta(days=90)), "--level", "1"]
            with contextlib.redirect_stderr(io.StringIO()):
                self.assertEqual(f.main(args), 1)


if __name__ == "__main__":
    unittest.main()
