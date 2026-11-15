import sys
import unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import jsa_quality_gate_v2 as g
import jsa_semantic_gate as old

class GateV2Tests(unittest.TestCase):
    def check(self,i,text): return g.check_case(old.CASES[i],text)
    def test_formula_100_not_percentage_claim(self):
        self.assertTrue(self.check(1,'(120-150)/150 × 100% = -20%. Kesimpulan: penurunan 20%.')['passed'])
    def test_negated_flat(self):
        self.assertTrue(self.check(2,'(80-80)/80 × 100% = 0%. Tidak naik atau turun.')['passed'])
    def test_provenance_wording(self):
        self.assertTrue(self.check(5,'Tidak, data CSV tidak dapat dipastikan sebagai transaksi nyata.')['passed'])
    def test_contradictory_undefined(self):
        self.assertFalse(self.check(4,'Ya, persentase kenaikan dapat dihitung. Namun tidak terdefinisi karena nol.')['passed'])
    def test_wrong_percentage(self):
        self.assertFalse(self.check(0,'Penjualan naik 20%.')['passed'])
    def test_wrong_direction(self):
        self.assertFalse(self.check(1,'Penjualan naik 20%.')['passed'])
    def test_zero_base_correct(self):
        self.assertTrue(self.check(4,'Tidak dapat dihitung karena pembagian dengan nol.')['passed'])
    def test_comparison_never_authorizes_promotion(self):
        a={'model':'jimny-qwen3:small-12345678','checks':[{'id':'x','passed':True}],'passed_count':1}
        b={'model':'jimny-qwen3:adaptive','checks':[{'id':'x','passed':False}],'passed_count':0}
        self.assertTrue(g.compare(a,b)['improved_on_smoke_test'])
        self.assertFalse(g.compare(a,b)['promotion_authorized'])
    def test_fail_closed_on_request_error(self):
        with patch.object(g.legacy,'query_ollama',side_effect=old.SemanticGateError('offline')):
            result=g.evaluate('jimny-qwen3:small-12345678',cases=old.CASES[:1])
        self.assertFalse(result['passed'])

if __name__=='__main__':unittest.main()
