"""Conservative offline audit of saved benchmark excerpts. Never authorizes promotion."""
from __future__ import annotations
import argparse
import json
import re
from datetime import datetime, timezone
from pathlib import Path

UP = re.compile(r'\b(?:naik|meningkat|kenaikan|peningkatan|bertambah|penambahan|positif)\b', re.I)
DOWN = re.compile(r'\b(?:turun|menurun|penurunan|berkurang|pengurangan|negatif)\b', re.I)
FLAT = re.compile(r'\b(?:tetap|stabil|tidak berubah|tidak ada perubahan|tidak naik (?:atau|maupun|dan) turun)\b', re.I)
NEGATED = re.compile(r'\b(?:tidak|bukan|tak)\s+(?:naik|meningkat|turun|menurun)\b', re.I)
PERCENT = re.compile(r'(?<![\w.,])([+-]?\d+(?:[.,]\d+)?)\s*%')
UNDEFINED = re.compile(r'(?:tidak (?:dapat|bisa) dihitung|tidak terdefinisi|tidak dapat ditentukan|pembagian dengan nol)', re.I)
AFFIRM = re.compile(r'(?:\bya\s*,?\s*(?:persen|persentase|pertumbuhan|kenaikan)[^.!\n]{0,65}(?:dapat|bisa) dihitung|\bya\s*,?\s*(?:dapat|bisa) dihitung|(?:persen|persentase|pertumbuhan|kenaikan)[^.!\n]{0,40}(?:dapat|bisa) dihitung)', re.I)
UNCERTAIN = re.compile(r'(?:tidak (?:dapat|bisa) (?:dipastikan|memastikan|dikonfirmasi|mengonfirmasi)|belum (?:dapat dipastikan|terverifikasi)|tidak pasti|tidak ada (?:bukti|dasar)|belum diaudit)', re.I)
CERTAIN = re.compile(r'(?:\bpasti\s+(?:berisi|merupakan|adalah)\s+transaksi|\bterbukti\s+(?:sebagai|merupakan)\s+transaksi)', re.I)


def classify(case: dict, answer: str) -> dict:
    """Three states: pass, fail, review. Excerpts are never trusted as complete responses."""
    text = answer.strip()
    issues = []
    if not text:
        issues.append('empty')
    elif case['type'] == 'pct':
        direction = case['direction']
        norm = NEGATED.sub('', re.sub(r'\btidak naik (?:atau|maupun|dan) turun\b', '', text, flags=re.I))
        up, down, flat = bool(UP.search(norm)), bool(DOWN.search(norm)), bool(FLAT.search(text))
        if direction == 'up' and (down or flat): issues.append('contradictory_direction')
        if direction == 'down' and (up or flat): issues.append('contradictory_direction')
        if direction == 'flat' and (up or down): issues.append('contradictory_direction')
        if direction == 'up' and not up: issues.append('missing_direction')
        if direction == 'down' and not down: issues.append('missing_direction')
        if direction == 'flat' and not flat: issues.append('missing_direction')
        # Only a clearly marked final answer may be used to assert a numeric pass.
        final = re.findall(r'(?:jawaban\s*:|kesimpulan\s*:|jadi\s*,?)[^\n]*', text, re.I)
        if not final:
            issues.append('unverified_final_percentage')
        else:
            nums = [float(n.replace(',', '.')) for n in PERCENT.findall(final[-1])]
            if not nums: issues.append('missing_final_percentage')
            elif any(abs(abs(n) - float(case['pct'])) > 0.2 for n in nums): issues.append('wrong_final_percentage')
    elif case['type'] == 'undefined':
        positive_text = re.sub(r'\btidak\s+(?:dapat|bisa)\s+dihitung\b', '', text, flags=re.I)
        if AFFIRM.search(positive_text): issues.append('contradictory_undefined')
        if not UNDEFINED.search(text): issues.append('missing_undefined')
    elif case['type'] == 'provenance':
        if CERTAIN.search(text) and not re.search(r'\b(?:tidak|belum)\s+pasti\b', text, re.I): issues.append('unsupported_provenance')
        if not UNCERTAIN.search(text): issues.append('missing_uncertainty')
    else:
        raise ValueError('unknown case type')
    # No 'pass' from excerpts: they can omit contradictions and intermediate claims.
    status = 'fail' if any(x.startswith(('wrong_', 'contradictory_', 'unsupported_')) for x in issues) else 'review'
    return {'id':case['id'], 'status':status, 'issues':issues}


def audit(report: dict) -> dict:
    if report.get('suite_origin') != 'synthetic_development_not_independent':
        raise ValueError('unexpected benchmark origin')
    out = {'schema_version':3, 'generated_at':datetime.now(timezone.utc).isoformat(),
           'original_suite_sha256':report.get('suite_sha256'), 'source':'saved_answer_excerpts_only',
           'limitations':'Excerpts truncated to 450 characters; no pass counts or promotion from rescoring',
           'promotion_authorized':False, 'models':{}}
    for key in ('candidate','incumbent'):
        model = report.get(key)
        if not isinstance(model,dict) or not isinstance(model.get('checks'),list):
            raise ValueError('missing checks')
        results = []
        for row in model['checks']:
            case_id = row['id']
            prefix = case_id.split('_')[0]
            typ = 'pct' if prefix in ('up','down','flat') else ('undefined' if prefix=='zero' else 'provenance' if prefix=='origin' else None)
            if typ is None: raise ValueError('unknown case id')
            # Original numeric target not in report; cannot reconstruct a reliable numeric evaluation.
            if typ == 'pct':
                results.append({'id':case_id,'status':'review','issues':['missing_original_case_target_and_full_response']})
            else:
                results.append(classify({'id':case_id,'type':typ},row.get('answer_excerpt','')))
        out['models'][key] = {'name':model.get('model'), 'original_passed':model.get('passed'),
            'review_required':sum(r['status']=='review' for r in results),
            'definite_failures_in_excerpt':sum(r['status']=='fail' for r in results), 'checks':results}
    return out


def main(argv=None):
    p=argparse.ArgumentParser(description='Read-only conservative offline excerpt audit')
    p.add_argument('--input',type=Path,required=True)
    p.add_argument('--output',type=Path,required=True)
    args=p.parse_args(argv)
    if args.output.exists(): p.error('output exists')
    report=json.loads(args.input.read_text(encoding='utf-8-sig'))
    result=audit(report)
    args.output.parent.mkdir(parents=True,exist_ok=True)
    args.output.write_text(json.dumps(result,indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
    print(json.dumps({'output':str(args.output),'promotion_authorized':False,
        'summary':{k:{x:v[x] for x in ('original_passed','review_required','definite_failures_in_excerpt')}
                   for k,v in result['models'].items()}},ensure_ascii=False))

if __name__=='__main__': main()
