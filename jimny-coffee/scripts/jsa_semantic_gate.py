"""JSA Fase 5: read-only Ollama semantic QA gate for business answers.

Uses only synthetic test questions. Does not train, import, remove or promote models.
A passing score is a limited smoke test, not proof of general reasoning ability.
"""
from __future__ import annotations

import argparse
import json
import math
import os
import re
import sys
import tempfile
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

OLLAMA_URL = 'http://127.0.0.1:11434/api/chat'
MODEL_PATTERN = re.compile(r'^jimny-qwen3:small-[0-9a-f]{8}$')
NUM_PCT = re.compile(r'(?<!\d)(\d+(?:[.,]\d+)?)\s*%')
UP = re.compile(r'\b(?:naik|meningkat|kenaikan|bertambah)\b', re.I)
DOWN = re.compile(r'\b(?:turun|menurun|penurunan|berkurang)\b', re.I)
FLAT = re.compile(r'\b(?:tetap|stabil|tidak berubah|sama)\b', re.I)
UNCERTAIN = re.compile(r'(?:tidak (?:dapat|bisa) memastikan|belum terverifikasi|'
                       r'tidak (?:dapat|bisa) mengonfirmasi|tidak (?:punya|memiliki) akses)', re.I)
UNDEFINED = re.compile(r'(?:tidak (?:dapat|bisa) dihitung|tidak terdefinisi|'
                       r'tidak dapat ditentukan|tak terdefinisi|tidak bermakna)', re.I)
THINK = re.compile(r'<think>.*?</think>', re.I | re.S)

CASES = [
    {'id': 'rise_25', 'type': 'pct', 'pct': 25.0, 'direction': 'up',
     'question': 'Jika penjualan kemarin 120 porsi dan hari ini 150 porsi, berapa persen perubahan penjualan? Nyatakan arah perubahan dan kesimpulan akhir.'},
    {'id': 'fall_20', 'type': 'pct', 'pct': 20.0, 'direction': 'down',
     'question': 'Penjualan berubah dari 150 porsi menjadi 120 porsi. Berapa persen perubahan dan apa arah perubahan? Beri kesimpulan.'},
    {'id': 'unchanged_0', 'type': 'pct', 'pct': 0.0, 'direction': 'flat',
     'question': 'Penjualan kemarin 80 porsi dan hari ini 80 porsi. Berapa persen perubahannya dan apakah naik atau turun?'},
    {'id': 'fall_33', 'type': 'pct', 'pct': 33.3333, 'direction': 'down',
     'question': 'Jumlah terjual berubah dari 90 ke 60 porsi. Berapa persen perubahannya dan arahnya?'},
    {'id': 'zero_denominator', 'type': 'undefined',
     'question': 'Kemarin 0 porsi dan hari ini 12 porsi. Apakah persentase kenaikan dibanding kemarin dapat dihitung secara matematis?'},
    {'id': 'source_provenance', 'type': 'provenance',
     'question': 'Apakah kamu bisa memastikan bahwa data CSV Jimny Coffee di sistem pengujian ini merupakan transaksi bisnis nyata?'},
]


class SemanticGateError(RuntimeError):
    pass


def model_allowed(name: str) -> bool:
    return name == 'jimny-qwen3:adaptive' or bool(MODEL_PATTERN.fullmatch(name))


def final_answer(text: str) -> str:
    answer = THINK.sub('', text)
    if '<think>' in answer.lower():
        # Some endpoints may return a truncated reasoning block; do not score it.
        return ''
    return answer.strip()


def check_case(case: dict, response: str) -> dict:
    text = final_answer(response)
    errors: list[str] = []
    if not text:
        errors.append('empty_answer')
    elif case['type'] == 'pct':
        values = [float(x.replace(',', '.')) for x in NUM_PCT.findall(text)]
        expected = case['pct']
        # Every explicitly stated percentage must agree, not only one of them.
        if not values or not all(math.isclose(v, expected, abs_tol=0.2) for v in values):
            errors.append('percentage')
        up, down, flat = bool(UP.search(text)), bool(DOWN.search(text)), bool(FLAT.search(text))
        direction = case['direction']
        if (direction == 'up' and (not up or down or flat)) or \
           (direction == 'down' and (not down or up or flat)) or \
           (direction == 'flat' and (not flat or up or down)):
            errors.append('direction')
    elif case['type'] == 'undefined':
        if not UNDEFINED.search(text) or NUM_PCT.search(text):
            errors.append('undefined_base')
    elif case['type'] == 'provenance':
        if not UNCERTAIN.search(text) or re.match(r'^\s*ya\b', text, re.I):
            errors.append('unverified_source')
    else:
        raise SemanticGateError('Jenis pengujian tidak dikenal')
    return {'id': case['id'], 'passed': not errors, 'errors': errors,
            'answer_excerpt': text[:450]}


def query_ollama(model: str, question: str, timeout: int = 90) -> str:
    if not model_allowed(model):
        raise SemanticGateError('Hanya model JSA adaptif atau kandidat kecil yang boleh diuji')
    payload = {
        'model': model, 'stream': False, 'think': False,
        'messages': [
            {'role': 'system', 'content': 'Jawab hanya pertanyaan ini secara singkat dalam bahasa Indonesia. Periksa kecocokan angka, arah, dan kesimpulan; hindari analisis tersembunyi dalam teks.'},
            {'role': 'user', 'content': question},
        ],
        'options': {'temperature': 0, 'num_predict': 180},
    }
    request = urllib.request.Request(OLLAMA_URL, data=json.dumps(payload).encode('utf-8'),
                                     headers={'Content-Type': 'application/json'}, method='POST')
    try:
        with urllib.request.urlopen(request, timeout=timeout) as resp:
            body = json.loads(resp.read().decode('utf-8'))
        result = body.get('message', {}).get('content', '')
        if not isinstance(result, str) or not result.strip():
            raise SemanticGateError('Ollama mengembalikan jawaban kosong')
        return result
    except (OSError, ValueError, KeyError, TypeError, urllib.error.URLError) as e:
        raise SemanticGateError(f'Permintaan Ollama gagal: {type(e).__name__}: {str(e)[:240]}') from e


def evaluate(model: str, cases=None, timeout: int = 90) -> dict:
    if not model_allowed(model):
        raise SemanticGateError('Model JSA tidak diizinkan untuk evaluasi ini')
    specs = CASES if cases is None else cases
    checks = []
    for spec in specs:
        try:
            ans = query_ollama(model, spec['question'], timeout=timeout)
            checks.append(check_case(spec, ans))
        except SemanticGateError as e:
            checks.append({'id': spec['id'], 'passed': False,
                           'errors': ['request_error'], 'answer_excerpt': str(e)[:250]})
    return {'schema_version': 1, 'model': model,
            'evaluated_at_utc': datetime.now(timezone.utc).isoformat(),
            'checks': checks, 'passed': bool(checks) and all(x['passed'] for x in checks),
            'passed_count': sum(x['passed'] for x in checks), 'total_count': len(checks),
            'trained_better_than_base': False,
            'note': 'Uji QA terbatas. Tidak membuktikan peningkatan menyeluruh; tidak mengubah model atau POS.'}


def write_report(path: Path, report: dict) -> None:
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    content = json.dumps(report, ensure_ascii=False, indent=2, sort_keys=True).encode('utf-8') + b'\n'
    fd, name = tempfile.mkstemp(prefix='.qa-', suffix='.tmp', dir=path.parent)
    try:
        with os.fdopen(fd, 'wb') as f:
            f.write(content)
        os.replace(name, path)
    finally:
        if os.path.exists(name):
            os.unlink(name)


def main(argv=None) -> int:
    parser = argparse.ArgumentParser(description='JSA read-only semantic gate untuk model Ollama')
    parser.add_argument('--model', default='jimny-qwen3:small-a6d71e8f')
    parser.add_argument('--timeout', type=int, default=90)
    parser.add_argument('--report', type=Path)
    args = parser.parse_args(argv)
    try:
        if args.timeout < 5 or args.timeout > 300:
            raise SemanticGateError('Timeout harus 5–300 detik')
        result = evaluate(args.model, timeout=args.timeout)
        if args.report:
            write_report(args.report, result)
        print(json.dumps(result, indent=2, ensure_ascii=False))
        return 0 if result['passed'] else 2
    except (SemanticGateError, OSError) as e:
        print('JSA SEMANTIC GATE GAGAL: ' + str(e), file=sys.stderr)
        return 3


if __name__ == '__main__':
    raise SystemExit(main())
