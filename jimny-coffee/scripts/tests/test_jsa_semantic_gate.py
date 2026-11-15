"""Unit tests for deterministic JSA answer-quality checks; no Ollama required."""
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import jsa_semantic_gate as g

class SemanticGateTests(unittest.TestCase):
    def test_increase_rejects_contradictory_conclusion(self):
        r=g.check_case(g.CASES[0], 'Kenaikan 25%. Jadi penjualan menurun 25%.')
        self.assertFalse(r['passed']); self.assertIn('direction', r['errors'])

    def test_increase_correct(self):
        self.assertTrue(g.check_case(g.CASES[0], 'Penjualan meningkat 25% dari 120 ke 150.')['passed'])

    def test_decrease_correct(self):
        self.assertTrue(g.check_case(g.CASES[1], 'Penjualan turun 20% dari 150 ke 120.')['passed'])

    def test_decrease_wrong_direction(self):
        self.assertFalse(g.check_case(g.CASES[1], 'Penjualan naik 20% dari 150 ke 120.')['passed'])

    def test_same_correct(self):
        self.assertTrue(g.check_case(g.CASES[2], 'Tidak berubah, selisih 0%, tetap 80 porsi.')['passed'])

    def test_same_wrong_direction(self):
        self.assertFalse(g.check_case(g.CASES[2], 'Penjualan meningkat 0%.')['passed'])

    def test_decimal_comma(self):
        self.assertTrue(g.check_case(g.CASES[3], 'Penjualan menurun sekitar 33,33%.')['passed'])

    def test_wrong_numeric_percentage(self):
        self.assertFalse(g.check_case(g.CASES[0], 'Penjualan meningkat 20%.')['passed'])

    def test_zero_base_undefined(self):
        self.assertTrue(g.check_case(g.CASES[4], 'Persentase kenaikan tidak dapat dihitung karena nilai awal nol.')['passed'])
        self.assertFalse(g.check_case(g.CASES[4], 'Persentase kenaikan 100%.')['passed'])

    def test_provenance_needs_uncertainty(self):
        self.assertTrue(g.check_case(g.CASES[5], 'Tidak, saya tidak dapat memastikan data ini transaksi nyata; sumbernya belum terverifikasi.')['passed'])
        self.assertFalse(g.check_case(g.CASES[5], 'Ya, semua transaksi Jimny Coffee sudah terbukti nyata.')['passed'])

    def test_think_block_not_scored_as_answer(self):
        a='<think>penjualan meningkat 25%</think> Jawaban: penjualan menurun 25%.'
        self.assertFalse(g.check_case(g.CASES[0], a)['passed'])

    def test_model_validation(self):
        self.assertTrue(g.model_allowed('jimny-qwen3:small-a6d71e8f'))
        self.assertTrue(g.model_allowed('jimny-qwen3:adaptive'))
        self.assertFalse(g.model_allowed('qwen3:4b-instruct'))
        self.assertFalse(g.model_allowed('bad;rm -rf /'))

    def test_evaluate_no_side_effects_and_complete(self):
        answers=['Penjualan meningkat 25%.','Penjualan turun 20%.','Penjualan tetap 0%.',
                 'Penjualan menurun 33,33%.','Persentase tidak dapat dihitung karena pembagi nol.',
                 'Tidak, data masih belum terverifikasi sebagai transaksi nyata.']
        with patch.object(g,'query_ollama',side_effect=answers) as q:
            report=g.evaluate('jimny-qwen3:small-a6d71e8f', cases=g.CASES)
        self.assertTrue(report['passed']); self.assertEqual(len(report['checks']),6)
        self.assertEqual(q.call_count,6)
        self.assertEqual(report['trained_better_than_base'],False)

    def test_evaluate_fails_closed_on_request_error(self):
        with patch.object(g,'query_ollama',side_effect=g.SemanticGateError('offline')):
            report=g.evaluate('jimny-qwen3:small-a6d71e8f',cases=g.CASES[:1])
        self.assertFalse(report['passed']); self.assertIn('request_error',report['checks'][0]['errors'])

    def test_http_request_is_local_and_does_not_train(self):
        import io
        import json
        from unittest.mock import MagicMock
        recorded = {}
        class Reply:
            def __enter__(self):return self
            def __exit__(self,*args):return False
            def read(self):return json.dumps({'message':{'content':'Penjualan naik 25%.'}}).encode('utf8')
        def fake_urlopen(req, timeout):
            recorded['url'] = req.full_url
            recorded['payload'] = json.loads(req.data.decode('utf8'))
            return Reply()
        with patch.object(g.urllib.request,'urlopen',side_effect=fake_urlopen):
            answer = g.query_ollama('jimny-qwen3:small-a6d71e8f', 'Uji aman')
        self.assertEqual(recorded['url'],'http://127.0.0.1:11434/api/chat')
        self.assertFalse(recorded['payload']['stream'])
        self.assertFalse(recorded['payload']['think'])
        self.assertEqual(answer,'Penjualan naik 25%.')

    def test_report_saved_explicitly_only(self):
        with tempfile.TemporaryDirectory() as td:
            report={'model':'jimny-qwen3:small-01234567','passed':False,'checks':[]}
            g.write_report(Path(td)/'qa-report.json',report)
            self.assertTrue((Path(td)/'qa-report.json').exists())
            self.assertIn('false',(Path(td)/'qa-report.json').read_text().lower())

if __name__=='__main__':unittest.main()
