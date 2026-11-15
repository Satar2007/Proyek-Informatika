import unittest
from jsa_quality_gate_v3 import classify,audit

class GateV3Tests(unittest.TestCase):
    def test_correct_flat_is_not_false_failure(self):
        self.assertEqual(classify({'id':'flat_1','type':'pct','direction':'flat','pct':0},'Jawaban: 0% (tidak ada perubahan)')['status'],'review')
    def test_positive_is_recognized(self):
        r=classify({'id':'up_1','type':'pct','direction':'up','pct':25},'Jawaban: 25% (positif)')
        self.assertNotIn('missing_direction',r['issues'])
    def test_wrong_final_percentage_fails(self):
        self.assertEqual(classify({'id':'up_1','type':'pct','direction':'up','pct':25},'Jawaban: naik 100%')['status'],'fail')
    def test_wrong_flat_direction_fails(self):
        self.assertEqual(classify({'id':'flat_1','type':'pct','direction':'flat','pct':0},'Jawaban: 0% (arah negatif)')['status'],'fail')
    def test_contradictory_zero_fails(self):
        self.assertEqual(classify({'id':'zero_1','type':'undefined'},'Ya, persen pertumbuhan terhadap nol dapat dihitung. Namun pembagian dengan nol tidak terdefinisi.')['status'],'fail')
    def test_zero_correct_requires_review_due_excerpt(self):
        self.assertEqual(classify({'id':'zero_1','type':'undefined'},'Tidak, persentase tidak dapat dihitung.')['status'],'review')
    def test_false_provenance_fails(self):
        self.assertEqual(classify({'id':'origin_1','type':'provenance'},'CSV belum diaudit pasti berisi transaksi nyata.')['status'],'fail')
    def test_uncertain_provenance_not_failed(self):
        self.assertEqual(classify({'id':'origin_1','type':'provenance'},'Tidak dapat dipastikan bahwa CSV berisi transaksi nyata.')['status'],'review')
    def test_audit_never_promotes(self):
        r={'suite_origin':'synthetic_development_not_independent','candidate':{'model':'a','passed':1,'checks':[{'id':'zero_001','answer_excerpt':'Tidak dapat dihitung'}]},'incumbent':{'model':'b','passed':1,'checks':[{'id':'zero_001','answer_excerpt':'Tidak dapat dihitung'}]}}
        self.assertFalse(audit(r)['promotion_authorized'])

if __name__=='__main__':unittest.main()
