import importlib.util
import json
import tempfile
import unittest

from datetime import datetime, timezone
from pathlib import Path


SCRIPT_PATH = (
    Path(__file__).resolve().parents[1]
    / "jsa_handoff.py"
)

spec = importlib.util.spec_from_file_location(
    "jsa_handoff",
    SCRIPT_PATH,
)

handoff = importlib.util.module_from_spec(spec)
spec.loader.exec_module(handoff)


class HandoffTest(unittest.TestCase):

    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)

        self.forecast = self.root / "forecast.json"
        self.stock = self.root / "stock.json"
        self.policy = self.root / "policy.json"
        self.restock = self.root / "restock.json"

        self.forecast.write_text(
            json.dumps({
                "status": "ok",
                "forecast_date": "2026-10-26",
            }),
            encoding="utf-8",
        )

        self.stock.write_text(
            json.dumps({
                "snapshot_date": "2026-10-25",
                "menus": [
                    {"id": 1, "stok": 10},
                ],
            }),
            encoding="utf-8",
        )

        self.policy.write_text(
            json.dumps({
                "service_level": 0.95,
            }),
            encoding="utf-8",
        )

        self.restock.write_text(
            json.dumps({
                "status": "ok",
                "recommendations": [
                    {
                        "menu_id": 1,
                        "restock_qty": 5,
                    }
                ],
            }),
            encoding="utf-8",
        )

        self.output_root = (
            self.root
            / "storage"
            / "app"
            / "private"
            / "jsa"
            / "runs"
        )

        self.now = datetime(
            2026,
            10,
            25,
            8,
            30,
            0,
            tzinfo=timezone.utc,
        )

    def tearDown(self):
        self.temp.cleanup()

    def build(self):
        return handoff.build_handoff(
            forecast_path=self.forecast,
            stock_path=self.stock,
            policy_path=self.policy,
            restock_path=self.restock,
            output_root=self.output_root,
            now=self.now,
        )

    def test_builds_immutable_handoff_with_hashes(self):
        manifest = self.build()

        self.assertEqual(
            handoff.READY,
            manifest["status"],
        )

        self.assertTrue(
            manifest["read_only"]
        )

        self.assertFalse(
            manifest["auto_restock"]
        )

        self.assertEqual(
            64,
            len(
                manifest["artifacts"]
                ["forecast"]["sha256"]
            ),
        )

        run_dir = (
            self.output_root
            / manifest["run_id"]
        )

        self.assertTrue(
            (run_dir / "manifest.json").is_file()
        )

        self.assertTrue(
            (
                self.output_root.parent
                / "latest.json"
            ).is_file()
        )

    def test_same_run_cannot_be_overwritten(self):
        self.build()

        with self.assertRaises(
            handoff.HandoffError
        ):
            self.build()

    def test_warning_payload_requires_review(self):
        self.forecast.write_text(
            json.dumps({
                "status": "insufficient_data",
            }),
            encoding="utf-8",
        )

        manifest = self.build()

        self.assertEqual(
            handoff.REVIEW,
            manifest["status"],
        )

        self.assertTrue(
            manifest["review_flags"]
        )

    def test_invalid_json_is_blocked_before_publish(self):
        self.stock.write_text(
            "{broken-json",
            encoding="utf-8",
        )

        with self.assertRaises(
            handoff.HandoffError
        ):
            self.build()

        self.assertFalse(
            self.output_root.exists()
        )

    def test_verify_detects_tampering(self):
        manifest = self.build()

        run_dir = (
            self.output_root
            / manifest["run_id"]
        )

        verified = handoff.verify_run(
            run_dir
        )

        self.assertEqual(
            manifest["run_id"],
            verified["run_id"],
        )

        packaged_restock = (
            run_dir
            / "artifacts"
            / "restock.json"
        )

        packaged_restock.write_text(
            "{}",
            encoding="utf-8",
        )

        with self.assertRaises(
            handoff.HandoffError
        ):
            handoff.verify_run(
                run_dir
            )

    def test_hash_is_raw_file_deterministic(self):
        first = handoff.sha256_file(
            self.policy
        )

        second = handoff.sha256_file(
            self.policy
        )

        self.assertEqual(
            first,
            second,
        )


if __name__ == "__main__":
    unittest.main()
