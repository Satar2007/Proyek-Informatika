import unittest
from jsa_heldout_benchmark import suite, compare, score

class BenchmarkTests(unittest.TestCase):
    def test_suite_is_120_unique_cases(self):
        s = suite()
        self.assertEqual(len(s), 120)
        self.assertEqual(len({x['id'] for x in s}), 120)
    def test_suite_has_expected_groups(self):
        s = suite()
        self.assertEqual([sum(x['type']==t for x in s) for t in ('pct','undefined','provenance')],[100,10,10])
    def test_no_production_promotion_even_if_better(self):
        a = {'total':120,'passed':120,'critical_failures':0,'p95_ms':100}
        b = {'total':120,'passed':100,'critical_failures':1,'p95_ms':100}
        r = compare(a,b)
        self.assertTrue(r['candidate_better_on_synthetic_suite'])
        self.assertFalse(r['promotion_authorized'])
    def test_critical_failure_blocks(self):
        a = {'total':120,'passed':119,'critical_failures':1,'p95_ms':100}
        b = {'total':120,'passed':100,'critical_failures':0,'p95_ms':100}
        self.assertFalse(compare(a,b)['candidate_better_on_synthetic_suite'])
    def test_latency_blocks(self):
        a = {'total':120,'passed':120,'critical_failures':0,'p95_ms':200}
        b = {'total':120,'passed':100,'critical_failures':0,'p95_ms':100}
        self.assertFalse(compare(a,b)['candidate_better_on_synthetic_suite'])
    def test_100_case_floor(self):
        a = {'total':99,'passed':99,'critical_failures':0,'p95_ms':100}
        b = {'total':99,'passed':80,'critical_failures':0,'p95_ms':100}
        self.assertFalse(compare(a,b)['candidate_better_on_synthetic_suite'])
    def test_request_failure_is_fail_closed(self):
        s = suite()[:1]
        def fail(*_): raise TimeoutError('timeout')
        result = score('jimny-qwen3:adaptive',s,fail)
        self.assertEqual(result['passed'],0)
        self.assertEqual(result['checks'][0]['errors'],['request_error'])

if __name__ == '__main__': unittest.main()
