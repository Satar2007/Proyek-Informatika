"""Conservative read-only QA and paired comparison. No model mutation/promotion."""
from __future__ import annotations
import argparse
import json
import re
from datetime import datetime, timezone
from pathlib import Path
import jsa_semantic_gate as legacy

# Evaluate the final conclusion, not arithmetic work such as 'x 100%'.
PERCENT = re.compile(r'(?<![\w])([+-]?\d+(?:[.,]\d+)?)\s*%')
FORMULA = re.compile(r'(?:[×*x]\s*100\s*%|/\s*\d+(?:[.,]\d+)?\s*[×*x]\s*100\s*%)', re.I)
UP = re.compile(r'\b(?:naik|meningkat|kenaikan|bertambah)\b', re.I)
DOWN = re.compile(r'\b(?:turun|menurun|penurunan|berkurang)\b', re.I)
FLAT = re.compile(r'\b(?:tetap|stabil|tidak berubah|sama|tidak naik (?:atau|maupun|dan) turun)\b', re.I)
NEGATED = re.compile(r'\b(?:tidak|bukan|tak)\s+(?:naik|meningkat|turun|menurun)\b', re.I)
UNCERTAIN = re.compile(r'(?:tidak (?:dapat|bisa) (?:dipastikan|memastikan|dikonfirmasi|mengonfirmasi)|belum terverifikasi|tidak (?:punya|memiliki) akses|belum dapat dipastikan)', re.I)
UNDEFINED = re.compile(r'(?:tidak (?:dapat|bisa) dihitung|tidak terdefinisi|tidak dapat ditentukan|tak terdefinisi|tidak bermakna|pembagian dengan nol)', re.I)
AFFIRM = re.compile(r'\b(?:ya\s*,?\s*(?:persentase|kenaikan|dapat dihitung)|(?:persentase|kenaikan)\s+(?:bisa|dapat)\s+dihitung)\b', re.I)


def conclusions(text: str) -> str:
    text = legacy.final_answer(text)
    # Remove mathematical workings before checking asserted percentages.
    lines = []
    for line in text.splitlines():
        if ('=' in line or '×' in line or '*' in line) and ('100%' in line or '100 %' in line):
            line = FORMULA.sub('', line)
        lines.append(line)
    return '\n'.join(lines)


def check_case(case: dict, response: str) -> dict:
    text = conclusions(response)
    errors = []
    if not text.strip():
        errors.append('empty_answer')
    elif case['type'] == 'pct':
        values = [float(x.replace(',', '.')) for x in PERCENT.findall(text)]
        target = case['pct']
        if not values or any(abs(abs(v) - target) > 0.2 for v in values):
            errors.append('percentage')
        normalized = re.sub(r'\btidak naik (?:atau|maupun|dan) turun\b', '', text, flags=re.I)
        normalized = NEGATED.sub('', normalized)
        up = bool(UP.search(normalized))
        down = bool(DOWN.search(normalized))
        flat = bool(FLAT.search(text))
        direction = case['direction']
        if ((direction == 'up' and (not up or down or flat)) or
            (direction == 'down' and (not down or up or flat)) or
            (direction == 'flat' and (not flat or up or down))):
            errors.append('direction')
        # Signed negative percent cannot describe a rise; positive may describe magnitude of a fall.
        if direction == 'up' and any(v < 0 for v in values):
            errors.append('sign')
    elif case['type'] == 'undefined':
        if not UNDEFINED.search(text) or AFFIRM.search(text):
            errors.append('undefined_base')
        # Explicit percentage conclusions are not permitted; 100% formula multiplier is ignored.
        if PERCENT.search(text):
            errors.append('undefined_base')
    elif case['type'] == 'provenance':
        if not UNCERTAIN.search(text) or re.match(r'^\s*ya\b', text, re.I):
            errors.append('unverified_source')
    else:
        raise ValueError('Unknown case')
    return {'id':case['id'], 'passed':not errors, 'errors':sorted(set(errors)), 'answer_excerpt':text[:450]}


def evaluate(model: str, cases=None, timeout=90) -> dict:
    if not legacy.model_allowed(model):
        raise ValueError('Model not allowed')
    specs = legacy.CASES if cases is None else cases
    checks = []
    for case in specs:
        try:
            checks.append(check_case(case, legacy.query_ollama(model, case['question'], timeout)))
        except legacy.SemanticGateError as exc:
            checks.append({'id':case['id'], 'passed':False, 'errors':['request_error'], 'answer_excerpt':str(exc)[:240]})
    return {'schema_version':2,'model':model,'evaluated_at_utc':datetime.now(timezone.utc).isoformat(),
            'checks':checks,'passed':bool(checks) and all(c['passed'] for c in checks),
            'passed_count':sum(c['passed'] for c in checks),'total_count':len(checks),
            'note':'Smoke test only. Does not authorize automatic model promotion.'}


def compare(candidate: dict, incumbent: dict) -> dict:
    a = {x['id']:x for x in candidate['checks']}
    b = {x['id']:x for x in incumbent['checks']}
    matching = bool(a) and a.keys() == b.keys()
    candidate_clean = matching and all(x['passed'] for x in a.values())
    incumbent_pass = sum(x['passed'] for x in b.values()) if matching else 0
    improvement = matching and candidate['passed_count'] > incumbent_pass
    # 6-case smoke tests are never sufficient for unattended production promotion.
    return {'candidate':candidate['model'],'incumbent':incumbent['model'],
            'same_cases':matching,'candidate_passed_all':candidate_clean,
            'improved_on_smoke_test':improvement,'promotion_authorized':False,
            'reason':'Requires independent held-out evaluation, provenance verification, and production rollback tests.'}


def main(argv=None):
    p=argparse.ArgumentParser()
    p.add_argument('--candidate',required=True)
    p.add_argument('--incumbent',default='jimny-qwen3:adaptive')
    p.add_argument('--report',type=Path)
    args=p.parse_args(argv)
    if args.candidate == args.incumbent:
        p.error('Candidate and incumbent must differ')
    cand=evaluate(args.candidate)
    base=evaluate(args.incumbent)
    result={'candidate_report':cand,'incumbent_report':base,'comparison':compare(cand,base)}
    if args.report:
        legacy.write_report(args.report,result)
    print(json.dumps(result,indent=2,ensure_ascii=False))
    return 0 if cand['passed'] else 2

if __name__=='__main__':
    raise SystemExit(main())
