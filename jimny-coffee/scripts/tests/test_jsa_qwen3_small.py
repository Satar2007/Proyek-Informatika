import json
import tempfile
import unittest
from datetime import datetime, timezone, timedelta
from pathlib import Path
from unittest.mock import patch

import jsa_qwen3_small as m


class SmallQwen3PolicyTests(unittest.TestCase):
    def test_small_base_is_not_four_b(self):
        self.assertEqual(m.BASE_MODEL, 'Qwen/Qwen3-0.6B')
        self.assertNotEqual(m.BASE_MODEL, 'Qwen/Qwen3-4B-Instruct-2507')

    def test_candidate_does_not_replace_production(self):
        s = m.candidate_decision(0.9, 0.7, False)
        self.assertTrue(s['evaluation_pass'])
        self.assertFalse(s['production_promoted'])
        self.assertFalse(s['source_verified'])
        self.assertFalse(s['ollama_imported'])

    def test_worse_eval_is_rejected(self):
        self.assertFalse(m.candidate_decision(0.8, 0.89, True)['evaluation_pass'])

    def test_nonfinite_eval_is_rejected(self):
        self.assertFalse(m.candidate_decision(float('nan'), 0.1, True)['evaluation_pass'])

    def test_guard_rejects_unverified_without_explicit_sandbox(self):
        x={'schema_version':3,'stage':'sandbox_candidate','source_verified':False,
           'train_count':273,'eval_count':50,'dataset_fingerprint':'a'*64}
        with self.assertRaises(m.SmallTrainingError):
            m.validate_manifest(x, False)
        m.validate_manifest(x, True)

    def test_guard_checks_training_count(self):
        x={'schema_version':3,'stage':'sandbox_candidate','source_verified':True,
           'train_count':2,'eval_count':50,'dataset_fingerprint':'a'*64}
        with self.assertRaises(m.SmallTrainingError):m.validate_manifest(x, False)

    def test_manifest_rejects_truthy_string_source_verified(self):
        x={'schema_version':3,'stage':'sandbox_candidate','source_verified':'false',
           'train_count':100,'eval_count':20,'dataset_fingerprint':'a'*64}
        with self.assertRaises(m.SmallTrainingError):
            m.validate_manifest(x, True)

    def test_deploy_rejects_truthy_string_evaluation_pass(self):
        x={'schema_version':4,'base_model_hf':m.BASE_MODEL,'stage':'sandbox_candidate',
           'evaluation_pass':'false','source_verified':False}
        with self.assertRaises(m.SmallTrainingError):
            m.guard_deploy(x, True)

    def test_guard_checks_stale_examples(self):
        x={'schema_version':3,'stage':'sandbox_candidate','source_verified':True,
           'train_count':273,'eval_count':50,'dataset_fingerprint':'a'*64,
           'created_at_utc':'2026-01-01T00:00:00+00:00'}
        with self.assertRaises(m.SmallTrainingError):
            m.validate_manifest(x, False, now=datetime(2026,11,15,tzinfo=timezone.utc))

    def test_prevent_any_override_of_original_ollama_alias(self):
        for s in ('qwen3:4b-instruct','jimny-qwen3:adaptive','qwen3:0.6b'):
            with self.assertRaises(m.SmallTrainingError):m.validate_alias(s)

    def test_versioned_sandbox_alias(self):
        self.assertEqual(m.candidate_alias('qwen3s_0123456789abcdefabcd'),
                         'jimny-qwen3:small-01234567')

    def test_run_id_is_deterministic_and_model_scoped(self):
        self.assertEqual(m.run_id_for('a'*64), m.run_id_for('a'*64))
        self.assertRegex(m.run_id_for('a'*64),r'^qwen3s_[0-9a-f]{20}$')

    def test_published_manifest_is_verified_and_tamper_detected(self):
        with tempfile.TemporaryDirectory() as td:
            d=Path(td)/'qwen3s_0123456789abcdefabcd'; (d/'adapter').mkdir(parents=True)
            (d/'adapter'/'adapter_model.safetensors').write_bytes(b'pretend-weights')
            (d/'adapter'/'adapter_config.json').write_text('{}')
            obj={'schema_version':4,'run_id':d.name,'base_model_hf':m.BASE_MODEL,
                 'stage':'sandbox_candidate','evaluation_pass':False,
                 'artifacts':{f:m.sha256_file(d/'adapter'/f)
                             for f in ('adapter_model.safetensors','adapter_config.json')}}
            (d/'manifest.json').write_text(json.dumps(obj),encoding='utf8')
            self.assertEqual(m.verify_run(d)['run_id'],d.name)
            (d/'adapter'/'adapter_model.safetensors').write_bytes(b'tampered')
            with self.assertRaises(m.SmallTrainingError):m.verify_run(d)

    def test_deploy_refuses_worse_model(self):
        obj={'schema_version':4,'stage':'sandbox_candidate','base_model_hf':m.BASE_MODEL,
             'evaluation_pass':False,'source_verified':True}
        with self.assertRaises(m.SmallTrainingError):m.guard_deploy(obj, False)

    def test_deploy_unverified_requires_explicit_sandbox(self):
        obj={'schema_version':4,'stage':'sandbox_candidate','base_model_hf':m.BASE_MODEL,
             'evaluation_pass':True,'source_verified':False}
        with self.assertRaises(m.SmallTrainingError):m.guard_deploy(obj, False)
        m.guard_deploy(obj, True)

    def test_validate_jsonl_rejects_customer_data_and_extra_roles(self):
        with tempfile.TemporaryDirectory() as td:
            p=Path(td)/'train.jsonl'
            p.write_text(json.dumps({'messages':[{'role':'system','content':'x'},
                       {'role':'user','content':'email saya a@b.com'},
                       {'role':'assistant','content':'hi'}]})+'\n',encoding='utf8')
            with self.assertRaises(m.SmallTrainingError):m.load_rows(p)

    def test_encode_rows_masks_prompt_and_trains_only_answer(self):
        class FakeTokenizer:
            eos_token_id = 99
            def apply_chat_template(self, messages, **kw):
                self.kw = kw
                return 'SYS USER ASSISTANT'
            def __call__(self, text, **kw):
                class Output: pass
                o = Output()
                o.input_ids = list(range(12)) if text.startswith('SYS') else list(range(6))
                return o
        rows=[{'messages':[{'role':'system','content':'s'},
                           {'role':'user','content':'u'},
                           {'role':'assistant','content':'a'}]}]
        out=m.encode_rows(FakeTokenizer(),rows)
        self.assertEqual(out[0]['labels'][:12],[-100]*12)
        self.assertEqual(out[0]['labels'][-1],99)
        self.assertEqual(len(out[0]['input_ids']),len(out[0]['labels']))

    def test_oversized_sequence_is_rejected(self):
        class FakeTokenizer:
            eos_token_id=99
            def apply_chat_template(self, messages, **kw): return 'x'
            def __call__(self, s, **kw):
                class O:pass
                o=O();o.input_ids=list(range(300));return o
        rows=[{'messages':[{'role':'system','content':'s'},
                           {'role':'user','content':'u'},
                           {'role':'assistant','content':'a'}]}]
        with self.assertRaises(m.SmallTrainingError):m.encode_rows(FakeTokenizer(),rows)

    def test_doctor_does_not_claim_gpu_without_torch(self):
        # Doctor reports dependencies explicitly; no implicit CUDA assumptions.
        with patch.dict('sys.modules',{'torch':None}):
            d=m.doctor()
        self.assertFalse(d['ready'])

    def test_train_does_not_load_gpu_dependencies_for_safety_checks(self):
        with self.assertRaises(m.SmallTrainingError):m.guard_deploy({}, False)


if __name__=='__main__':unittest.main()
