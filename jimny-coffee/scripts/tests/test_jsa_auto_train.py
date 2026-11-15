"""Tes integrasi lokal learning engine, tidak menyentuh MySQL atau Ollama."""
import csv
import json
import sys
import tempfile
import unittest
from datetime import date, timedelta
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from jsa_dataset_manager import build_dataset
from jsa_auto_train import (
    TrainingError, train_latest, verify_latest_candidate, fit_ridge,
    forecast_ridge, rolling_predictions,
)


def csvfile(path, headers, rows):
    with open(path, 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh); w.writerow(headers); w.writerows(rows)


class JsaAutoTrainTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name)
        self.csv_total = self.root / 'daily_total.csv'
        self.csv_menu = self.root / 'daily_menu.csv'
        self.datasets = self.root / 'datasets'
        self.models = self.root / 'models'
        self.cutoff = date(2026, 11, 14)
        self.make_dataset(160)

    def make_dataset(self, n=160, ending=None):
        end = ending or self.cutoff
        start = end - timedelta(days=n - 1)
        rows = []
        menu = []
        for offset in range(n):
            day = start + timedelta(days=offset)
            amount = 20 + (day.weekday() * 3) + (offset // 12)
            rows.append([day.isoformat(), amount])
            menu.append([day.isoformat(), 1, 'Kopi Susu', amount])
        csvfile(self.csv_total, ['tanggal', 'total_porsi'], rows)
        csvfile(self.csv_menu, ['tanggal', 'menu_id', 'nama_menu', 'porsi'], menu)
        build_dataset(self.csv_total, self.csv_menu, self.datasets, self.cutoff, days=365)

    def test_train_creates_real_model_and_review_manifest(self):
        result = train_latest(self.datasets, self.models)
        self.assertEqual(result['status'], 'trained')
        manifest = result['manifest']
        self.assertEqual(manifest['stage'], 'sandbox_candidate')
        self.assertFalse(manifest['production_promoted'])
        self.assertFalse(manifest['source_verified'])
        self.assertEqual(manifest['training_rows'], 160)
        self.assertGreaterEqual(manifest['evaluation']['holdout_days'], 28)
        self.assertIn('mae', manifest['evaluation'])
        self.assertTrue((self.models / 'latest_candidate.json').is_file())
        self.assertTrue(verify_latest_candidate(self.models)['valid'])

    def test_ridge_weights_are_fitted_and_forecast_nonnegative(self):
        history = [(self.cutoff - timedelta(days=80-i), i+12) for i in range(81)]
        fit = fit_ridge(history, window=90, alpha=10)
        self.assertEqual(len(fit['coefficients']), 8)
        self.assertTrue(all(isinstance(x, float) for x in fit['coefficients']))
        self.assertGreaterEqual(forecast_ridge(fit, self.cutoff + timedelta(days=1)), 0)

    def test_no_look_ahead_on_rolling_predictions(self):
        history = [(self.cutoff - timedelta(days=120-i), 10+i % 7) for i in range(121)]
        first = rolling_predictions(history, 'ridge', 80, 100, window=90, alpha=10)
        changed = list(history)
        changed[90] = (changed[90][0], 9999)
        second = rolling_predictions(changed, 'ridge', 80, 100, window=90, alpha=10)
        self.assertEqual(first[10]['prediction'], second[10]['prediction'])
        self.assertNotEqual(first[11]['prediction'], second[11]['prediction'])

    def test_second_run_of_same_dataset_is_unchanged(self):
        first = train_latest(self.datasets, self.models)
        second = train_latest(self.datasets, self.models)
        self.assertEqual(second['status'], 'unchanged')
        self.assertEqual(first['run_id'], second['run_id'])
        self.assertEqual(len(list((self.models / 'runs').glob('*/manifest.json'))), 1)

    def test_no_source_csv_modification(self):
        before = self.csv_total.read_bytes(), self.csv_menu.read_bytes()
        train_latest(self.datasets, self.models)
        self.assertEqual(before, (self.csv_total.read_bytes(), self.csv_menu.read_bytes()))

    def test_unready_dataset_is_refused(self):
        self.make_dataset(160, ending=self.cutoff - timedelta(days=20))
        with self.assertRaisesRegex(TrainingError, 'belum siap'):
            train_latest(self.datasets, self.models)
        self.assertFalse((self.models / 'latest_candidate.json').exists())

    def test_tampered_dataset_is_refused(self):
        data = json.loads((self.datasets / 'latest.json').read_text(encoding='utf-8'))
        path = self.datasets / 'runs' / data['run_id'] / 'active_total.csv'
        path.write_text('bad,data\n', encoding='utf-8')
        with self.assertRaises(TrainingError):
            train_latest(self.datasets, self.models)
        self.assertFalse(self.models.exists())

    def test_tampered_model_is_refused(self):
        result = train_latest(self.datasets, self.models)
        target = self.models / 'runs' / result['run_id'] / 'model.json'
        target.write_text('{}', encoding='utf-8')
        with self.assertRaisesRegex(TrainingError, 'integritas'):
            verify_latest_candidate(self.models)

    def test_dry_run_does_not_publish(self):
        result = train_latest(self.datasets, self.models, dry_run=True)
        self.assertEqual(result['status'], 'dry_run')
        self.assertFalse(self.models.exists())

    def test_training_never_claims_ollama_fine_tuned(self):
        result = train_latest(self.datasets, self.models)
        manifest = result['manifest']
        self.assertFalse(manifest['ollama_finetuned'])
        self.assertFalse(manifest['pos_mutated'])


if __name__ == '__main__':
    unittest.main()
