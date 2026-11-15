import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import jsa_qwen3_lora as l

class LoraTests(unittest.TestCase):
    def test_requirements_missing_blocks_training(self):
        with patch.object(l,'gpu_report',return_value={'ready':False,'reason':'CUDA not available'}):
            with self.assertRaises(l.FineTuneError):l.require_gpu()

    def test_min_examples_policy(self):
        with self.assertRaises(l.FineTuneError):l.check_manifest({'train_count':10,'eval_count':3,'source_verified':False,'stage':'sandbox_candidate'},True)

    def test_unverified_cannot_train_without_sandbox_flag(self):
        with self.assertRaises(l.FineTuneError):l.check_manifest({'train_count':200,'eval_count':40,'source_verified':False,'stage':'sandbox_candidate'},False)

    def test_training_approval_does_not_change_production(self):
        m=l.candidate_status(False, 'Qwen/Qwen3-4B-Instruct-2507', 1.6, 1.2, 'abcd')
        self.assertTrue(m['evaluation_pass'])
        self.assertFalse(m['production_promoted'])
        self.assertFalse(m['ollama_original_model_modified'])

    def test_no_auto_promotion_for_worse_loss(self):
        m=l.candidate_status(False,'Qwen/Qwen3-4B-Instruct-2507',1.2,1.5,'abcd')
        self.assertFalse(m['evaluation_pass'])
        self.assertFalse(m['production_promoted'])

    def test_safe_model_id(self):
        with self.assertRaises(l.FineTuneError):l.validate_model_id('random/user-example')

if __name__=='__main__':unittest.main()
