import unittest
from jsa_promotion_gate import decide


def evidence():
    return {
        'schema_version': 1, 'candidate': 'jimny-qwen3:small-12345678',
        'incumbent': 'jimny-qwen3:adaptive', 'source_verified': True,
        'candidate_integrity_verified': True, 'rollback_verified': True,
        'independent_heldout': True, 'train_eval_overlap': 0,
        'case_count': 120, 'candidate_passed': 118, 'incumbent_passed': 110,
        'critical_failures': 0, 'incumbent_critical_failures': 0,
        'candidate_latency_p95_ms': 1500, 'incumbent_latency_p95_ms': 2000,
        'evaluation_id': 'eval-unique-001', 'candidate_run_id': 'qwen3s_12345678901234567890',
        'provenance_attested': True,
    }

class PromotionGateTests(unittest.TestCase):
    def test_qualified_evidence_is_eligible_not_deployed(self):
        r = decide(evidence())
        self.assertTrue(r['eligible_for_review'])
        self.assertFalse(r['promotion_executed'])
    def test_missing_evidence_fails_closed(self):
        e = evidence(); del e['rollback_verified']
        self.assertFalse(decide(e)['eligible_for_review'])
    def test_unverified_origin_blocks(self):
        e = evidence(); e['source_verified'] = False
        self.assertFalse(decide(e)['eligible_for_review'])
    def test_six_smoke_cases_block(self):
        e = evidence(); e['case_count'] = 6
        self.assertFalse(decide(e)['eligible_for_review'])
    def test_regression_blocks(self):
        e = evidence(); e['candidate_passed'] = 109
        self.assertFalse(decide(e)['eligible_for_review'])
    def test_critical_failure_blocks(self):
        e = evidence(); e['critical_failures'] = 1
        self.assertFalse(decide(e)['eligible_for_review'])
    def test_overlap_blocks(self):
        e = evidence(); e['train_eval_overlap'] = 1
        self.assertFalse(decide(e)['eligible_for_review'])
    def test_rollback_blocks(self):
        e = evidence(); e['rollback_verified'] = False
        self.assertFalse(decide(e)['eligible_for_review'])
    def test_identity_blocks(self):
        e = evidence(); e['candidate'] = e['incumbent']
        self.assertFalse(decide(e)['eligible_for_review'])
    def test_wrong_types_block(self):
        e = evidence(); e['candidate_passed'] = True
        self.assertFalse(decide(e)['eligible_for_review'])
    def test_latency_regression_blocks(self):
        e = evidence(); e['candidate_latency_p95_ms'] = 5000
        self.assertFalse(decide(e)['eligible_for_review'])
    def test_attestation_blocks(self):
        e = evidence(); e['provenance_attested'] = False
        self.assertFalse(decide(e)['eligible_for_review'])

if __name__ == '__main__': unittest.main()
