"""Offline regression tests for the JSA audit patch; no Ollama/GPU/POS access."""
from __future__ import annotations
import tempfile
import unittest
import sys
from pathlib import Path

# Allow discovery from the Laravel project root as well as scripts/.
SCRIPTS_DIR = Path(__file__).resolve().parents[1] / 'scripts'
if str(SCRIPTS_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPTS_DIR))

from jsa_qwen3_small import candidate_decision
from jsa_qwen3_learning import LearningError, _remove_managed_example_archive
from jsa_promotion_gate import decide


class CandidateDecisionTests(unittest.TestCase):
    def test_loss_improvement_is_only_training_gate(self):
        result = candidate_decision(1.0, 0.98, True)
        self.assertTrue(result['evaluation_pass'])
        self.assertTrue(result['training_loss_pass'])
        self.assertEqual(result['evaluation_scope'], 'training_token_loss_only')
        self.assertFalse(result['business_quality_pass'])
        self.assertFalse(result['promotion_eligible'])
        self.assertFalse(result['production_promoted'])

    def test_no_improvement_does_not_pass(self):
        self.assertFalse(candidate_decision(1.0, 1.0, True)['training_loss_pass'])

    def test_negative_or_nonfinite_loss_does_not_pass(self):
        self.assertFalse(candidate_decision(1.0, -0.1, True)['training_loss_pass'])
        self.assertFalse(candidate_decision(float('nan'), 0.1, True)['training_loss_pass'])
        self.assertFalse(candidate_decision(float('inf'), 0.1, True)['training_loss_pass'])


class ManagedCleanupTests(unittest.TestCase):
    def test_removes_only_expected_direct_child_archive(self):
        with tempfile.TemporaryDirectory() as tmp:
            parent = Path(tmp) / 'examples'
            target = parent / 'examples-old-0123456789abcdef'
            target.mkdir(parents=True)
            (target / 'train.jsonl').write_text('{}\n', encoding='utf8')
            _remove_managed_example_archive(target, parent)
            self.assertFalse(target.exists())

    def test_rejects_wrong_archive_name(self):
        with tempfile.TemporaryDirectory() as tmp:
            parent = Path(tmp) / 'examples'
            target = parent / 'important-data'
            target.mkdir(parents=True)
            with self.assertRaises(LearningError):
                _remove_managed_example_archive(target, parent)
            self.assertTrue(target.exists())

    def test_rejects_symlink_archive(self):
        with tempfile.TemporaryDirectory() as tmp:
            parent = Path(tmp) / 'examples'
            outside = Path(tmp) / 'outside'
            parent.mkdir()
            outside.mkdir()
            target = parent / 'examples-old-0123456789abcdef'
            try:
                target.symlink_to(outside, target_is_directory=True)
            except (OSError, NotImplementedError):
                self.skipTest('Symlink creation is not permitted on this platform')
            with self.assertRaises(LearningError):
                _remove_managed_example_archive(target, parent)
            self.assertTrue(outside.exists())


class PromotionGateTests(unittest.TestCase):
    def test_synthetic_or_missing_independent_evidence_fails_closed(self):
        result = decide({})
        self.assertFalse(result['eligible_for_review'])
        self.assertFalse(result['promotion_executed'])


if __name__ == '__main__':
    unittest.main(verbosity=2)
