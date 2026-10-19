import importlib.util
import unittest
from datetime import date, timedelta
from pathlib import Path


SCRIPT_PATH = (
    Path(__file__).resolve().parents[1]
    / "jsa_random_forest.py"
)

spec = importlib.util.spec_from_file_location(
    "jsa_random_forest",
    SCRIPT_PATH,
)

jsa_random_forest = importlib.util.module_from_spec(spec)
spec.loader.exec_module(jsa_random_forest)


def make_trend(days, start=date(2026, 1, 1)):
    return [
        {
            "date": (start + timedelta(days=index)).isoformat(),
            "qty": 10 + (index % 7) + (index // 15),
        }
        for index in range(days)
    ]


class RandomForestForecastTest(unittest.TestCase):
    def test_insufficient_history(self):
        result = jsa_random_forest.evaluate({
            "sales_trend": make_trend(30)
        })

        self.assertEqual(
            "insufficient_data",
            result["status"],
        )

        self.assertIsNone(
            result["model_evaluation"]
        )

    def test_valid_history_returns_experimental_forecast(self):
        trend = make_trend(90)

        result = jsa_random_forest.evaluate({
            "sales_trend": trend
        })

        expected_date = (
            date.fromisoformat(trend[-1]["date"])
            + timedelta(days=1)
        ).isoformat()

        self.assertEqual(
            "experimental",
            result["status"],
        )

        self.assertEqual(
            "RandomForestRegressor",
            result["forecast"]["method"],
        )

        self.assertEqual(
            expected_date,
            result["forecast"]["values"][0]["date"],
        )

        self.assertEqual(
            14,
            result["model_evaluation"]["evaluation_days"],
        )

        metrics = result["model_evaluation"]["metrics"]

        self.assertIn("random_forest", metrics)
        self.assertIn("wma_7", metrics)
        self.assertIn("naive", metrics)

        self.assertIn(
            result["model_evaluation"]["best_by_mae"],
            {"random_forest", "wma_7", "naive"},
        )

    def test_rejects_gap_in_calendar_dates(self):
        payload = {
            "sales_trend": [
                {"date": "2026-01-01", "qty": 10},
                {"date": "2026-01-03", "qty": 12},
            ]
        }

        with self.assertRaisesRegex(
            ValueError,
            "Tanggal harus berurutan",
        ):
            jsa_random_forest.evaluate(payload)


if __name__ == "__main__":
    unittest.main()
