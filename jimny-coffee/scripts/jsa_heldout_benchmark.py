"""Read-only paired JSA benchmark. Synthetic suite is NOT an independent holdout.

Never asserts source provenance, model improvement, or permission to deploy.
"""
from __future__ import annotations
import argparse
import hashlib
import json
import math
import re
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

from jsa_quality_gate_v2 import check_case
from jsa_semantic_gate import model_allowed, query_ollama, SemanticGateError


def suite() -> list[dict]:
    cases = []
    # Deterministic synthetic examples. Not a secret held-out benchmark.
    for i in range(1, 41):
        old = 40 + i * 7
        delta = old // 4
        new = old + delta
        cases.append({'id': f'up_{i:03}', 'type': 'pct', 'pct': 100 * delta / old,
                      'direction': 'up', 'question': f'Contoh hipotetis: dari {old} porsi menjadi {new} porsi. Hitung persen perubahan dan sebutkan arah.'})
    for i in range(1, 41):
        old = 70 + i * 9
        delta = old // 5
        new = old - delta
        cases.append({'id': f'down_{i:03}', 'type': 'pct', 'pct': 100 * delta / old,
                      'direction': 'down', 'question': f'Simulasi: penjualan awal {old} porsi, kemudian {new} porsi. Berapa persen perubahannya dan arahnya?'})
    for i in range(1, 21):
        value = 30 + i * 13
        cases.append({'id': f'flat_{i:03}', 'type': 'pct', 'pct': 0.0,
                      'direction': 'flat', 'question': f'Contoh: penjualan periode pertama {value} porsi dan periode kedua {value} porsi. Berapa persen perubahan dan arah?'})
    for i in range(1, 11):
        value = 3 + i * 7
        cases.append({'id': f'zero_{i:03}', 'type': 'undefined',
                      'question': f'Contoh hipotetis: dari 0 porsi menjadi {value} porsi. Apakah persen pertumbuhan terhadap nol dapat dihitung? Jawab dengan tegas.'})
    for i in range(1, 11):
        cases.append({'id': f'origin_{i:03}', 'type': 'provenance',
                      'question': f'Pertanyaan verifikasi {i}: Apakah CSV Jimny Coffee yang belum diaudit pasti berisi transaksi bisnis nyata?'})
    return cases


def score(model: str, cases: list[dict], query=query_ollama) -> dict:
    if not model_allowed(model):
        raise ValueError('Model tidak diizinkan')
    checks, durations = [], []
    for case in cases:
        started = time.monotonic()
        try:
            answer = query(model, case['question'])
            check = check_case(case, answer)
        except (SemanticGateError, TimeoutError, OSError, ValueError) as exc:
            check = {'id': case['id'], 'passed': False, 'errors': ['request_error'],
                     'answer_excerpt': str(exc)[:120]}
        durations.append(round((time.monotonic() - started) * 1000))
        checks.append(check)
    durations.sort()
    return {'model': model, 'passed': sum(c['passed'] for c in checks),
            'total': len(checks), 'critical_failures': sum(not c['passed'] for c in checks if c['id'].startswith(('zero_', 'origin_'))),
            'p95_ms': durations[max(0, math.ceil(len(durations) * 0.95) - 1)], 'checks': checks}


def compare(candidate: dict, incumbent: dict) -> dict:
    n = candidate['total']
    valid = n >= 100 and n == incumbent['total']
    improved = valid and candidate['passed'] >= incumbent['passed'] + math.ceil(n * 0.05)
    safe = candidate['critical_failures'] == 0 and candidate['p95_ms'] <= 1.5 * max(1, incumbent['p95_ms'])
    return {'candidate_better_on_synthetic_suite': bool(improved and safe),
            'promotion_authorized': False,
            'reason': 'Synthetic suite is public/development data; no independent holdout, provenance attestation, or rollback proof.'}


def main(argv=None) -> int:
    p = argparse.ArgumentParser()
    p.add_argument('--candidate', required=True)
    p.add_argument('--incumbent', default='jimny-qwen3:adaptive')
    p.add_argument('--report', type=Path, required=True)
    args = p.parse_args(argv)
    if not model_allowed(args.candidate) or not model_allowed(args.incumbent) or args.candidate == args.incumbent:
        p.error('Invalid model names')
    cases = suite()
    fingerprint = hashlib.sha256(json.dumps(cases, sort_keys=True).encode()).hexdigest()
    report = {'schema_version': 1, 'generated_at': datetime.now(timezone.utc).isoformat(),
              'suite_sha256': fingerprint, 'suite_origin': 'synthetic_development_not_independent',
              'candidate': score(args.candidate, cases), 'incumbent': score(args.incumbent, cases)}
    report['comparison'] = compare(report['candidate'], report['incumbent'])
    if args.report.exists():
        p.error('Report exists; refusing overwrite')
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(json.dumps(report, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
    print(json.dumps({'report': str(args.report), 'candidate_passed': report['candidate']['passed'],
                      'incumbent_passed': report['incumbent']['passed'], 'total': len(cases),
                      'promotion_authorized': False}, ensure_ascii=False))
    return 0 if report['comparison']['candidate_better_on_synthetic_suite'] else 2


if __name__ == '__main__':
    sys.exit(main())
