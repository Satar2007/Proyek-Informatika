import hashlib
import json
import tempfile
import unittest
from datetime import date, timedelta
from pathlib import Path
from unittest.mock import patch
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import jsa_qwen3_balanced as b

class BalancedExamplesTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root=Path(self.temp.name)
        self.total=self.root/'active_total.csv'
        from csv import writer
        with self.total.open('w',encoding='utf8', newline='') as f:
            w=writer(f);w.writerow(['tanggal','total_porsi'])
            start=date(2025,11,15)
            for i in range(365):
                w.writerow([(start+timedelta(days=i)).isoformat(), [0, 19, 30, 55, 42, 10, 80][i%7]])
        self.manifest={'fingerprint': 'a'*64, 'totals': {'last_date':'2026-11-14'},
                       'quality':{'source_label':'csv_unverified_origin','ready_for_training':True}}

    def tearDown(self):self.temp.cleanup()
    def fake_source(self, _:Path):return self.root,self.manifest
    def build(self):
        with patch.object(b,'source',self.fake_source):
            return b.build(self.root, now=date(2026,11,15))

    def test_math_direction_and_undefined(self):
        self.assertEqual(b.math_result(120,150)['direction'],'naik')
        self.assertEqual(b.math_result(120,150)['percentage'],25.0)
        self.assertEqual(b.math_result(150,120)['percentage'],20.0)
        self.assertEqual(b.math_result(80,80)['percentage'],0.0)
        self.assertIsNone(b.math_result(0,12)['percentage'])
        self.assertIsNone(b.math_result(0,0)['percentage'])

    def test_examples_balanced_and_disjoint(self):
        train,valid,metadata = self.build()
        self.assertGreaterEqual(len(train),350)
        self.assertGreaterEqual(len(valid),100)
        self.assertEqual(len({(x['initial'],x['final']) for x in train if x['case_type']!='source'} & {(x['initial'],x['final']) for x in valid if x['case_type']!='source'}),0)
        self.assertFalse({x['messages'][1]['content'] for x in train} & {x['messages'][1]['content'] for x in valid})
        for pairs in [train,valid]:
            groups={x['case_type'] for x in pairs}
            self.assertTrue({'rise','fall','flat','undefined','source'}.issubset(groups))
        self.assertFalse({(120,150),(150,120),(80,80),(90,60),(0,12)} & {(x['initial'],x['final']) for x in train+valid})
        self.assertEqual(metadata['source_label'],'csv_unverified_origin')

    def test_correct_outputs_match_cases(self):
        train,valid,_=self.build()
        for item in train+valid:
            answer=item['messages'][2]['content']
            if item['case_type']=='source':
                self.assertIn('belum terverifikasi',answer)
            elif item['case_type']=='undefined':
                self.assertIn('tidak dapat dihitung',answer)
                self.assertNotIn('%',answer)
            else:
                p=b.math_result(item['initial'],item['final'])
                self.assertIn(b.format_percent(p['percentage'])+'%',answer)
                self.assertIn(p['direction'],answer)
            self.assertEqual([m['role'] for m in item['messages']],['system','user','assistant'])

    def test_fingerprint_reproducible_and_data_labeled(self):
        first=self.build(); second=self.build()
        self.assertEqual(first,second)
        for row in first[0]+first[1]:
            self.assertIn('simulasi' if row['case_type']=='source' or row['synthetic'] else 'CSV', row['messages'][1]['content'])
            self.assertNotIn('@',json.dumps(row))

    def test_reject_stale(self):
        with patch.object(b,'source',self.fake_source):
            with self.assertRaisesRegex(b.BalancedError,'usang'):
                b.build(self.root,now=date(2026,12,2))

    def test_publish_idempotent_and_tamper_detected(self):
        with patch.object(b,'source',self.fake_source):
            a=b.publish(self.root,self.root/'output',now=date(2026,11,15))
            self.assertEqual(a['status'],'created')
            a2=b.publish(self.root,self.root/'output',now=date(2026,11,15))
            self.assertEqual(a2['status'],'unchanged')
            folder=Path(a['examples_dir'])
            self.assertTrue(b.verify_examples(folder)['valid'])
            (folder/'train.jsonl').write_text('changed',encoding='utf8')
            with self.assertRaises(Exception):
                b.verify_examples(folder)

    def test_end_to_end_with_dataset_manager_and_trainer_guards(self):
        from csv import writer
        from jsa_dataset_manager import build_dataset
        from jsa_qwen3_small import validate_manifest, load_rows, run_id_for
        raw_menu = self.root/'daily_menu.csv'
        with raw_menu.open('w', newline='', encoding='utf8') as f:
            w=writer(f);w.writerow(['tanggal','menu_id','nama_menu','porsi'])
            from csv import DictReader
            with self.total.open('r',encoding='utf8',newline='') as t:
                for row in DictReader(t):
                    w.writerow([row['tanggal'],1,'Kopi Susu',row['total_porsi']])
        datasets=self.root/'real-datasets'
        build_dataset(self.total,raw_menu,datasets,date(2026,11,14),365)
        result=b.publish(datasets,self.root/'published',now=date(2026,11,15))
        from jsa_qwen3_learning import verify_examples
        m=verify_examples(Path(result['examples_dir']))['manifest']
        validate_manifest(m, sandbox=True)
        self.assertGreaterEqual(len(load_rows(Path(result['examples_dir'])/'train.jsonl')),100)
        self.assertTrue(run_id_for(m['dataset_fingerprint']).startswith('qwen3s_'))

    def test_source_untouched(self):
        before=hashlib.sha256(self.total.read_bytes()).hexdigest()
        self.build()
        self.assertEqual(before,hashlib.sha256(self.total.read_bytes()).hexdigest())

if __name__=='__main__':unittest.main()
