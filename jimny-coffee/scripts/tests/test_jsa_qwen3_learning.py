import csv
import hashlib
import json
import sys
import tempfile
import unittest
from datetime import date, timedelta
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import jsa_qwen3_learning as q


class JsaQwen3LearningTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.ds = self.root / 'datasets'
        self.run = self.ds / 'runs' / '20261115T022034921539Z_1f9e9b73'
        self.run.mkdir(parents=True)
        first = date(2025, 11, 15)
        with (self.run / 'active_total.csv').open('w', newline='', encoding='utf-8') as f:
            w = csv.writer(f)
            w.writerow(('tanggal','total_porsi'))
            for i in range(365):
                w.writerow(((first+timedelta(days=i)).isoformat(), 25+i%11))
        with (self.run / 'active_menu.csv').open('w', newline='', encoding='utf-8') as f:
            w = csv.writer(f)
            w.writerow(('tanggal','menu_id','nama_menu','porsi'))
            for i in range(365):
                w.writerow(((first+timedelta(days=i)).isoformat(), 1, 'Kopi Susu', 25+i%11))
        artifacts={x: hashlib.sha256((self.run/x).read_bytes()).hexdigest() for x in ('active_total.csv','active_menu.csv')}
        manifest={'schema_version':1,'run_id':self.run.name,'fingerprint':'abc123', 'totals':{'last_date':'2026-11-14'},'quality':{'ready_for_training':True, 'source_label':'csv_unverified_origin'},'artifacts':artifacts}
        (self.run/'manifest.json').write_text(json.dumps(manifest),encoding='utf-8')
        (self.ds/'latest.json').write_text(json.dumps({'run_id':self.run.name, 'fingerprint':'abc123', 'manifest_sha256':hashlib.sha256((self.run/'manifest.json').read_bytes()).hexdigest()}),encoding='utf-8')

    def tearDown(self):
        self.temp.cleanup()

    def test_build_business_summary_is_aggregate_and_unverified(self):
        ctx=q.read_verified_context(self.ds)
        self.assertEqual(ctx['as_of'],'2026-11-14')
        self.assertEqual(ctx['source_label'],'csv_unverified_origin')
        self.assertFalse(ctx['verified_real_sales'])
        self.assertEqual(ctx['last_28_days'], sum(25+i%11 for i in range(337,365)))
        self.assertNotIn('nama_pelanggan', json.dumps(ctx))

    def test_business_context_does_not_present_unverified_as_real(self):
        ctx=q.read_verified_context(self.ds)
        prompt=q.system_prompt(ctx)
        self.assertIn('belum terverifikasi',prompt.lower())
        self.assertIn('2026-11-14',prompt)
        self.assertNotIn('data transaksi nyata yang terverifikasi',prompt.lower())

    def test_learning_examples_generated_from_computations(self):
        ctx, train, valid=q.build_examples(self.ds, min_rows=60)
        self.assertGreaterEqual(len(train),100)
        self.assertGreaterEqual(len(valid),20)
        self.assertTrue(all(x['messages'][-1]['role']=='assistant' for x in train))
        self.assertTrue(all('source_label' in x and x['source_label']=='csv_unverified_origin' for x in train))
        self.assertTrue({x['date'] for x in train}.isdisjoint({x['date'] for x in valid}))

    def test_broken_csv_hash_rejected(self):
        with (self.run/'active_total.csv').open('a',encoding='utf-8') as f: f.write('\n')
        with self.assertRaises(q.LearningError): q.read_verified_context(self.ds)

    def test_short_dataset_rejected(self):
        with self.assertRaises(q.LearningError): q.build_examples(self.ds, min_rows=999)

    def test_publish_knowledge_creates_alias_without_changing_base(self):
        target=self.root/'ollama'
        commands=[]
        def fake_run(argv, *args, **kwargs):
            commands.append(argv)
            return type('Result',(),{'returncode':0,'stdout':'success','stderr':''})()
        with patch.object(q.subprocess,'run',fake_run):
            result=q.refresh_alias(self.ds,target,'qwen3:4b-instruct','jimny-qwen3:adaptive',apply=True)
        self.assertEqual(result['status'],'created')
        self.assertEqual(commands[0][0:3],['ollama','create','jimny-qwen3:adaptive'])
        self.assertTrue((target/'Modelfile').exists())
        self.assertIn('FROM qwen3:4b-instruct',(target/'Modelfile').read_text(encoding='utf8'))
        self.assertNotIn('ollama_finetuned": true', (target/'manifest.json').read_text(encoding='utf8'))

    def test_dry_run_does_not_create_files(self):
        target=self.root/'ollama'
        result=q.refresh_alias(self.ds,target,'qwen3:4b-instruct','jimny-qwen3:adaptive',apply=False)
        self.assertEqual(result['status'],'dry_run')
        self.assertFalse(target.exists())

    def test_alias_requires_safe_name(self):
        with self.assertRaises(q.LearningError):
            q.refresh_alias(self.ds,self.root/'out','qwen3:4b-instruct','jimny;rm -rf',apply=False)

    def test_unverified_training_is_sandbox_only(self):
        out=self.root/'examples'
        result=q.publish_examples(self.ds,out)
        self.assertEqual(result['status'],'created')
        self.assertFalse(result['manifest']['source_verified'])
        self.assertEqual(result['manifest']['stage'],'sandbox_candidate')
        self.assertGreaterEqual(result['manifest']['train_count'],100)
        self.assertTrue(q.verify_examples(out)['valid'])

    def test_tampered_examples_are_rejected(self):
        out=self.root/'examples'
        q.publish_examples(self.ds,out)
        (out/'train.jsonl').write_text('tamper',encoding='utf8')
        with self.assertRaises(q.LearningError):q.verify_examples(out)

    def test_no_duplicate_examples(self):
        out=self.root/'examples'
        q.publish_examples(self.ds,out)
        self.assertEqual(q.publish_examples(self.ds,out)['status'],'unchanged')

if __name__=='__main__': unittest.main()
