import copy
import importlib.util
import json
import subprocess
import sys
import tempfile
import unittest
from datetime import date, timedelta
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / 'jsa_restock.py'
spec = importlib.util.spec_from_file_location('restock', SCRIPT)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)


def inputs():
    day = date(2026, 10, 25)
    history = [{'date': (day - timedelta(days=40-i)).isoformat(), 'actual': 8, 'point': 5.0} for i in range(40)]
    forecast = {'schema_version': 1, 'phase': 2, 'historical_replay': False,
                'data': {'end': '2026-10-24'},
                'forecast': {'date': str(day), 'menus': [{'menu_id': 1, 'nama_menu': 'Kopi', 'point': 5.0}]},
                'backtest': {'menus': {'1': history}}}
    stock = {'schema_version': 1, 'date': str(day), 'source': 'actual',
             'stock_basis': 'available_after_reservations', 'timing': 'before_service',
             'menus': [{'menu_id': 1, 'category_id': 1, 'nama_menu': 'Kopi',
                        'stok': 3, 'minimum_stok': 5, 'is_active': True, 'deleted_at': None}]}
    policy = {'schema_version': 1, 'horizon_days': 1, 'lead_time_days': 0,
              'minimum_stock_rule': 'floor', 'default_service_level': 0.9,
              'category_service_levels': {}, 'minimum_calibration': 20, 'calibration_window': 60}
    return forecast, stock, policy


class RestockTest(unittest.TestCase):
    def setUp(self):
        self.f, self.s, self.p = inputs()

    def run_case(self):
        return m.run_restock(self.f, self.s, self.p, today=date(2026, 10, 25))['recommendations'][0]

    def test_known_signed_quantile_and_no_double_reservation(self):
        r = self.run_case()
        self.assertEqual((r['demand_target'], r['target_stock'], r['restock_qty']), (8, 8, 5))
        self.assertEqual(r['evaluation'], {'n': 20, 'coverage': 1.0})

    def test_negative_errors_are_not_absolute_interval(self):
        for row in self.f['backtest']['menus']['1']:
            row['actual'] = 1
        self.s['menus'][0]['minimum_stok'] = 0
        self.assertEqual(self.run_case()['demand_target'], 1)

    def test_floor_is_max_not_sum(self):
        self.s['menus'][0]['minimum_stok'] = 12
        self.assertEqual(self.run_case()['restock_qty'], 9)

    def test_sufficient_stock_never_negative(self):
        self.s['menus'][0]['stok'] = 50
        self.assertEqual(self.run_case()['restock_qty'], 0)
        self.assertEqual(self.run_case()['status'], 'sufficient')

    def test_inactive_and_deleted(self):
        for changes in ({'is_active': False}, {'deleted_at': '2026-10-24 12:00:00'}):
            self.s['menus'][0].update(changes)
            self.assertEqual(self.run_case()['status'], 'inactive_or_deleted')
            self.assertEqual(self.run_case()['restock_qty'], 0)

    def test_missing_snapshot_is_not_zero_stock(self):
        self.s['menus'][0]['menu_id'] = 2
        result = m.run_restock(self.f, self.s, self.p, demo=True)['recommendations']
        self.assertEqual([r['status'] for r in result], ['missing_snapshot', 'missing_forecast'])
        self.assertTrue(all(r['restock_qty'] is None for r in result))

    def test_insufficient_calibration_is_not_zero_restock(self):
        self.f['backtest']['menus']['1'] = self.f['backtest']['menus']['1'][-19:]
        self.assertEqual(self.run_case()['status'], 'insufficient_calibration')
        self.assertIsNone(self.run_case()['restock_qty'])

    def test_category_override(self):
        self.p['category_service_levels'] = {'1': 0.95}
        self.assertEqual(self.run_case()['service_level'], 0.95)

    def test_current_actual_not_used_in_own_threshold(self):
        rows = self.f['backtest']['menus']['1']
        rows[20]['actual'] = 100
        self.assertLess(self.run_case()['evaluation']['coverage'], 1)

    def test_old_errors_outside_window_ignored(self):
        self.assertEqual(m.demand_target(5, [1000]*20 + [2]*60, .9), 7)

    def test_rank_overflow_has_no_false_confidence(self):
        self.assertIsNone(m.demand_target(5, [1]*20, .99))

    def test_zero_demand(self):
        self.f['forecast']['menus'][0]['point'] = 0
        for row in self.f['backtest']['menus']['1']:
            row.update(actual=0, point=0)
        self.s['menus'][0].update(stok=0, minimum_stok=0)
        self.assertEqual(self.run_case()['restock_qty'], 0)

    def test_duplicate_ids_rejected(self):
        self.s['menus'].append(copy.deepcopy(self.s['menus'][0]))
        with self.assertRaises(m.RestockError): self.run_case()

    def test_invalid_stock_rejected(self):
        for value in (-1, 1.5, True, float('nan')):
            self.s['menus'][0]['stok'] = value
            with self.subTest(value=value), self.assertRaises(m.RestockError): self.run_case()

    def test_invalid_forecast_rejected(self):
        for value in (-1, float('nan'), float('inf')):
            self.f['forecast']['menus'][0]['point'] = value
            with self.subTest(value=value), self.assertRaises(m.RestockError): self.run_case()

    def test_future_backtest_rejected(self):
        self.f['backtest']['menus']['1'][-1]['date'] = '2026-10-25'
        with self.assertRaises(m.RestockError): self.run_case()

    def test_gap_backtest_rejected(self):
        del self.f['backtest']['menus']['1'][10]
        with self.assertRaises(m.RestockError): self.run_case()

    def test_snapshot_date_and_basis_rejected(self):
        for key, value in [('date', '2026-10-24'), ('stock_basis', 'physical'), ('timing', 'midday')]:
            original = self.s[key]
            self.s[key] = value
            with self.subTest(key=key), self.assertRaises(m.RestockError): self.run_case()
            self.s[key] = original

    def test_horizon_and_lead_time_rejected(self):
        for key in ('horizon_days', 'lead_time_days'):
            original = self.p[key]
            self.p[key] = 2
            with self.subTest(key=key), self.assertRaises(m.RestockError): self.run_case()
            self.p[key] = original

    def test_demo_required_for_synthetic(self):
        self.s['source'] = 'synthetic'
        with self.assertRaises(m.RestockError): self.run_case()
        self.assertEqual(m.run_restock(self.f, self.s, self.p, demo=True)['mode'], 'DEMO_NOT_OPERATIONAL')

    def test_replay_and_stale_operational_rejected(self):
        self.f['historical_replay'] = True
        with self.assertRaises(m.RestockError): self.run_case()
        self.f['historical_replay'] = False
        with self.assertRaises(m.RestockError):
            m.run_restock(self.f, self.s, self.p, today=date(2026, 10, 26))

    def test_input_not_mutated(self):
        original = copy.deepcopy((self.f, self.s, self.p))
        self.run_case()
        self.assertEqual((self.f, self.s, self.p), original)

    def test_cli_success_outputs_and_failure_no_output(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for name, data in [('forecast', self.f), ('stock', self.s), ('policy', self.p)]:
                (root / (name+'.json')).write_text(json.dumps(data), encoding='utf-8')
            cmd = [sys.executable, str(SCRIPT), '--demo']
            for name in ('forecast', 'stock', 'policy'):
                cmd.extend(['--'+name, str(root / (name+'.json'))])
            out = root / 'ok'
            result = subprocess.run(cmd + ['--out', str(out)], capture_output=True, text=True)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual(len(list(out.iterdir())), 3)
            (root/'stock.json').write_text('{}', encoding='utf-8')
            result = subprocess.run(cmd + ['--out', str(root/'bad')], capture_output=True, text=True)
            self.assertEqual(result.returncode, 1)
            self.assertFalse((root/'bad').exists())


if __name__ == '__main__':
    unittest.main()
