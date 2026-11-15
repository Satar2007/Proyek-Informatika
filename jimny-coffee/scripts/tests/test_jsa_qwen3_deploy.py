import sys
import unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
import jsa_qwen3_deploy as d

class DeployTests(unittest.TestCase):
    def test_untrained_cannot_deploy(self):
        with self.assertRaises(d.DeployError):d.validate_candidate({'evaluation_pass':False,'stage':'sandbox_candidate','base_model_hf':d.BASE_HF},True)
    def test_wrong_hf_model_blocked(self):
        with self.assertRaises(d.DeployError):d.validate_candidate({'evaluation_pass':True,'stage':'sandbox_candidate','base_model_hf':'Qwen/Other','source_verified':True},True)
    def test_unverified_requires_sandbox(self):
        with self.assertRaises(d.DeployError):d.validate_candidate({'evaluation_pass':True,'stage':'sandbox_candidate','base_model_hf':d.BASE_HF,'source_verified':False},False)
    def test_passing_candidate_stays_separate(self):
        d.validate_candidate({'evaluation_pass':True,'stage':'sandbox_candidate','base_model_hf':d.BASE_HF,'source_verified':False},True)
    def test_cannot_overwrite_original_model(self):
        with self.assertRaises(d.DeployError):d.validate_alias('qwen3:4b-instruct')
if __name__=='__main__':unittest.main()
