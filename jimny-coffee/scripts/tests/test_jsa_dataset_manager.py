"""Pengujian mandiri Dataset Manager tanpa MySQL/Ollama."""
from __future__ import annotations

import csv
import json
import sys
import tempfile
import unittest
from datetime import date
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from jsa_dataset_manager import (  # noqa: E402
    DataError,
    build_dataset,
    verify_latest,
)


def write_csv(path: Path, fields: list[str], rows: list[list[object]]) -> None:
    with path.open("w", encoding="utf-8", newline="") as stream:
        writer = csv.writer(stream)
        writer.writerow(fields)
        writer.writerows(rows)


class DatasetManagerTest(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.total = self.root / "daily_total.csv"
        self.menu = self.root / "daily_menu.csv"
        self.out = self.root / "out"

    def sample(self) -> None:
        write_csv(self.total, ["tanggal", "total_porsi"], [
            ["2026-10-01", "4"],
            ["2026-10-02", "3"],
            ["2026-10-03", "0"],
            ["2026-10-04", "5"],
            ["2026-10-05", "7"],  # masa depan untuk as-of 4 Oktober
        ])
        write_csv(self.menu, ["tanggal", "menu_id", "nama_menu", "porsi"], [
            ["2026-10-01", "1", "Kopi Susu", "4"],
            ["2026-10-02", "1", "Kopi Susu", "1"],
            ["2026-10-02", "2", "Americano", "2"],
            ["2026-10-04", "2", "Americano", "5"],
            ["2026-10-05", "2", "Americano", "7"],
        ])

    def test_rolling_window_filters_old_and_future_and_keeps_zero_days(self) -> None:
        self.sample()
        result = build_dataset(self.total, self.menu, self.out, date(2026, 10, 4), 3, dry_run=False)
        self.assertEqual(result["status"], "created")
        manifest = result["manifest"]
        self.assertEqual(manifest["totals"]["rows"], 3)
        self.assertEqual(manifest["totals"]["quantity"], 8)
        self.assertEqual(manifest["menus"]["rows"], 3)
        self.assertEqual(manifest["quality"]["missing_dates"], [])
        self.assertFalse(manifest["quality"]["ready_for_training"])
        folder = self.out / "runs" / result["run_id"]
        with (folder / "active_total.csv").open(newline="", encoding="utf-8") as stream:
            rows = list(csv.DictReader(stream))
        self.assertEqual([r["tanggal"] for r in rows], ["2026-10-02", "2026-10-03", "2026-10-04"])
        self.assertEqual([r["total_porsi"] for r in rows], ["3", "0", "5"])
        self.assertTrue(verify_latest(self.out)["valid"])

    def test_rejects_mismatched_daily_totals_without_publishing(self) -> None:
        write_csv(self.total, ["tanggal", "total_porsi"], [["2026-10-03", "9"]])
        write_csv(self.menu, ["tanggal", "menu_id", "nama_menu", "porsi"], [["2026-10-03", "1", "Kopi", "2"]])
        with self.assertRaisesRegex(DataError, "tidak cocok"):
            build_dataset(self.total, self.menu, self.out, date(2026, 10, 3), 30)
        self.assertFalse((self.out / "latest.json").exists())

    def test_rejects_duplicate_menu_rows(self) -> None:
        write_csv(self.total, ["tanggal", "total_porsi"], [["2026-10-03", "2"]])
        write_csv(self.menu, ["tanggal", "menu_id", "nama_menu", "porsi"], [
            ["2026-10-03", "1", "Kopi", "1"], ["2026-10-03", "1", "Kopi", "1"]])
        with self.assertRaisesRegex(DataError, "Duplikasi"):
            build_dataset(self.total, self.menu, self.out, date(2026, 10, 3), 30)

    def test_rejects_invalid_negative_quantity(self) -> None:
        write_csv(self.total, ["tanggal", "total_porsi"], [["2026-10-03", "-1"]])
        write_csv(self.menu, ["tanggal", "menu_id", "nama_menu", "porsi"], [])
        with self.assertRaisesRegex(DataError, "nonnegatif"):
            build_dataset(self.total, self.menu, self.out, date(2026, 10, 3), 30)

    def test_no_duplicate_runs_for_same_snapshot(self) -> None:
        self.sample()
        first = build_dataset(self.total, self.menu, self.out, date(2026, 10, 4), 30)
        second = build_dataset(self.total, self.menu, self.out, date(2026, 10, 4), 30)
        self.assertEqual(second["status"], "unchanged")
        self.assertEqual(first["run_id"], second["run_id"])
        self.assertEqual(len(list((self.out / "runs").glob("*/manifest.json"))), 1)

    def test_dry_run_creates_no_output(self) -> None:
        self.sample()
        result = build_dataset(self.total, self.menu, self.out, date(2026, 10, 4), 30, dry_run=True)
        self.assertEqual(result["status"], "dry_run")
        self.assertFalse(self.out.exists())

    def test_missing_dates_are_flagged_not_filled_with_fake_zeroes(self) -> None:
        write_csv(self.total, ["tanggal", "total_porsi"], [["2026-10-01", "2"], ["2026-10-03", "0"]])
        write_csv(self.menu, ["tanggal", "menu_id", "nama_menu", "porsi"], [["2026-10-01", "1", "Kopi", "2"]])
        result = build_dataset(self.total, self.menu, self.out, date(2026, 10, 3), 30, dry_run=True)
        self.assertEqual(result["manifest"]["quality"]["missing_dates"], ["2026-10-02"])
        self.assertFalse(result["manifest"]["quality"]["ready_for_training"])

    def test_rejects_tampered_published_artifact(self) -> None:
        self.sample()
        run = build_dataset(self.total, self.menu, self.out, date(2026, 10, 4), 30)
        path = self.out / "runs" / run["run_id"] / "active_total.csv"
        path.write_text(path.read_text(encoding="utf-8") + "\n", encoding="utf-8")
        with self.assertRaisesRegex(DataError, "integritas"):
            verify_latest(self.out)
        with self.assertRaisesRegex(DataError, "integritas"):
            build_dataset(self.total, self.menu, self.out, date(2026, 10, 4), 30)

    def test_tampered_manifest_is_detected(self) -> None:
        self.sample()
        run = build_dataset(self.total, self.menu, self.out, date(2026, 10, 4), 30)
        path = self.out / "runs" / run["run_id"] / "manifest.json"
        value = json.loads(path.read_text(encoding="utf-8"))
        value["quality"]["ready_for_training"] = True
        path.write_text(json.dumps(value), encoding="utf-8")
        with self.assertRaisesRegex(DataError, "integritas"):
            verify_latest(self.out)

    def test_retention_keeps_only_five_latest_generated_runs(self) -> None:
        self.sample()
        for day in range(1, 8):
            result = build_dataset(self.total, self.menu, self.out, date(2026, 10, day), 30, keep=5)
            self.assertEqual(result["status"], "created")
        runs = list((self.out / "runs").glob("*/manifest.json"))
        self.assertEqual(len(runs), 5)
        self.assertEqual(verify_latest(self.out)["run_id"], result["run_id"])

    def test_source_csv_is_never_modified(self) -> None:
        self.sample()
        before_total = self.total.read_bytes()
        before_menu = self.menu.read_bytes()
        build_dataset(self.total, self.menu, self.out, date(2026, 10, 4), 30)
        self.assertEqual(self.total.read_bytes(), before_total)
        self.assertEqual(self.menu.read_bytes(), before_menu)

    def test_english_aliases_are_supported(self) -> None:
        write_csv(self.total, ["date", "total_qty"], [["2026-10-04", "2"]])
        write_csv(self.menu, ["date", "menu_id", "menu_name", "qty"], [["2026-10-04", "3", "Latte", "2"]])
        result = build_dataset(self.total, self.menu, self.out, date(2026, 10, 4), 30, dry_run=True)
        self.assertEqual(result["manifest"]["menus"]["rows"], 1)


if __name__ == "__main__":
    unittest.main()
