"""Fail-closed review gate. Never calls Ollama, modifies aliases or deploys a model.

Evidence is a proposed evaluation contract, not an attestation verifier. A caller
must establish authenticity, dataset independence and rollback success externally.
"""
from __future__ import annotations
import argparse
import json
import re
from pathlib import Path

REQUIRED_BOOLEANS = ('source_verified', 'candidate_integrity_verified',
                     'rollback_verified', 'independent_heldout', 'provenance_attested')
REQUIRED_INTS = ('schema_version', 'train_eval_overlap', 'case_count',
                 'candidate_passed', 'incumbent_passed', 'critical_failures',
                 'incumbent_critical_failures', 'candidate_latency_p95_ms',
                 'incumbent_latency_p95_ms')
REQUIRED_STRINGS = ('candidate', 'incumbent', 'evaluation_id', 'candidate_run_id')


def decide(evidence: dict) -> dict:
    reasons = []
    if not isinstance(evidence, dict):
        return {'eligible_for_review': False, 'promotion_executed': False,
                'reasons': ['invalid_evidence']}
    for key in REQUIRED_BOOLEANS:
        if type(evidence.get(key)) is not bool:
            reasons.append('missing_or_invalid_' + key)
        elif not evidence[key]:
            reasons.append(key + '_not_satisfied')
    for key in REQUIRED_INTS:
        if type(evidence.get(key)) is not int or evidence[key] < 0:
            reasons.append('missing_or_invalid_' + key)
    for key in REQUIRED_STRINGS:
        if not isinstance(evidence.get(key), str) or not evidence[key].strip():
            reasons.append('missing_or_invalid_' + key)
    if reasons:
        return {'eligible_for_review': False, 'promotion_executed': False,
                'reasons': sorted(set(reasons))}
    if evidence['schema_version'] != 1:
        reasons.append('unsupported_schema')
    if evidence['candidate'] == evidence['incumbent']:
        reasons.append('same_model')
    if not re.fullmatch(r'jimny-qwen3:small-[a-f0-9]{8}', evidence['candidate']):
        reasons.append('candidate_alias_invalid')
    if evidence['incumbent'] != 'jimny-qwen3:adaptive':
        reasons.append('incumbent_alias_invalid')
    if not re.fullmatch(r'qwen3s_[a-f0-9]{20}', evidence['candidate_run_id']):
        reasons.append('run_id_invalid')
    if evidence['train_eval_overlap'] != 0:
        reasons.append('train_eval_overlap')
    if evidence['case_count'] < 100:
        reasons.append('insufficient_heldout_cases')
    if evidence['candidate_passed'] > evidence['case_count'] or evidence['incumbent_passed'] > evidence['case_count']:
        reasons.append('invalid_pass_counts')
    if evidence['critical_failures'] != 0:
        reasons.append('critical_failure')
    # At least 5 percentage points absolute improvement, plus no loss of critical behavior.
    if evidence['candidate_passed'] - evidence['incumbent_passed'] < max(1, (evidence['case_count'] + 19) // 20):
        reasons.append('insufficient_improvement')
    if evidence['incumbent_latency_p95_ms'] == 0 or evidence['candidate_latency_p95_ms'] > 1.5 * evidence['incumbent_latency_p95_ms']:
        reasons.append('latency_regression')
    return {'eligible_for_review': not reasons, 'promotion_executed': False,
            'reasons': sorted(set(reasons)), 'note': 'Eligibility only. No production switch or rollback executed.'}


def main(argv=None) -> int:
    parser = argparse.ArgumentParser(description='Offline promotion eligibility, no deployment')
    parser.add_argument('--evidence', required=True, type=Path)
    parser.add_argument('--report', required=True, type=Path)
    args = parser.parse_args(argv)
    if args.evidence.resolve() == args.report.resolve():
        parser.error('Evidence and report must be different paths')
    try:
        e = json.loads(args.evidence.read_text(encoding='utf-8'))
        result = decide(e)
    except (OSError, ValueError, TypeError) as exc:
        result = {'eligible_for_review': False, 'promotion_executed': False,
                  'reasons': ['unreadable_evidence'], 'detail': str(exc)[:180]}
    args.report.parent.mkdir(parents=True, exist_ok=True)
    if args.report.exists():
        parser.error('Report exists; refusing overwrite')
    args.report.write_text(json.dumps(result, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
    print(json.dumps(result, ensure_ascii=False))
    return 0 if result['eligible_for_review'] else 2

if __name__ == '__main__':
    raise SystemExit(main())
