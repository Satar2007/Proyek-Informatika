"""JSA: isolated *real* Qwen3-0.6B QLoRA candidate training for 6 GiB GPUs.

Never changes Qwen3-4B, Laravel endpoints, original database or stock.
An unverified JSA dataset is only usable with --sandbox; resulting models are
strictly sandbox candidates. Ollama deployment needs a passing holdout.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import math
import re
import shutil
import subprocess
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path

from jsa_qwen3_learning import DEFAULT_EXAMPLES, LearningError, verify_examples

BASE_MODEL = 'Qwen/Qwen3-0.6B'
RUNS = Path('storage/app/private/jsa/learning/qwen3/small_runs')
PUBLISHED = Path('storage/app/private/jsa/learning/qwen3/small_published')
MIN_TRAIN = 100
MIN_EVAL = 20
MIN_VRAM_GIB = 5.0
RUN_RE = re.compile(r'^qwen3s_[0-9a-f]{20}$')
PII_EMAIL = re.compile(r'\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b')
PHONE = re.compile(r'(?<!\d)(?:\+62|08)[0-9\s().-]{9,16}(?!\d)')


class SmallTrainingError(RuntimeError):
    pass


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with Path(path).open('rb') as f:
        for b in iter(lambda: f.read(1 << 20), b''):
            h.update(b)
    return h.hexdigest()


def json_bytes(obj: dict) -> bytes:
    return (json.dumps(obj, ensure_ascii=False, sort_keys=True, indent=2) + '\n').encode('utf8')


def run_id_for(dataset_fingerprint: str) -> str:
    if not re.fullmatch(r'[0-9a-f]{64}', dataset_fingerprint):
        raise SmallTrainingError('Fingerprint dataset tidak valid')
    h = hashlib.sha256((dataset_fingerprint + '|' + BASE_MODEL + '|qlora-v4').encode()).hexdigest()
    return 'qwen3s_' + h[:20]


def candidate_alias(run_id: str) -> str:
    if not RUN_RE.fullmatch(run_id):
        raise SmallTrainingError('Run ID tidak aman')
    return 'jimny-qwen3:small-' + run_id.split('_')[1][:8]


def validate_alias(alias: str) -> None:
    if (not re.fullmatch(r'jimny-qwen3:small-[0-9a-f]{8}', alias) or
        alias in {'qwen3:4b-instruct', 'qwen3:0.6b', 'jimny-qwen3:adaptive'}):
        raise SmallTrainingError('Hanya alias kandidat versi baru yang boleh dipakai')


def validate_manifest(m: dict, sandbox: bool, now: datetime | None = None):
    if m.get('schema_version') != 3 or m.get('stage') != 'sandbox_candidate':
        raise SmallTrainingError('Manifest contoh tidak sesuai Fase 3')
    if m.get('train_count', 0) < MIN_TRAIN or m.get('eval_count', 0) < MIN_EVAL:
        raise SmallTrainingError('Contoh training/evaluasi belum cukup')
    if not re.fullmatch(r'[0-9a-f]{64}', m.get('dataset_fingerprint', '')):
        raise SmallTrainingError('Fingerprint dataset tidak valid')
    if not m.get('source_verified', False) and not sandbox:
        raise SmallTrainingError('Sumber belum terverifikasi: --sandbox wajib untuk eksperimen')
    created = m.get('created_at_utc')
    if created:
        try:
            d = datetime.fromisoformat(created.replace('Z', '+00:00'))
            if d.tzinfo is None:
                raise ValueError('timezone missing')
            clock = now or datetime.now(timezone.utc)
            if d > clock + timedelta(hours=1) or clock - d > timedelta(days=14):
                raise SmallTrainingError('Contoh sudah terlalu lama atau bertanggal masa depan')
        except ValueError as e:
            raise SmallTrainingError('Tanggal manifest tidak valid') from e


def candidate_decision(before: float, after: float, source_verified: bool):
    good = all(map(math.isfinite, (before, after))) and before > 0 and after < before * 0.99
    return {
        'evaluation_pass': bool(good),
        'evaluation_loss_before': round(before, 6) if math.isfinite(before) else None,
        'evaluation_loss_after': round(after, 6) if math.isfinite(after) else None,
        'source_verified': bool(source_verified),
        'production_promoted': False,
        'ollama_imported': False,
        'notes': 'Token-loss improvement on arithmetic holdout is NOT evidence of better business reasoning.',
    }


def load_rows(file: Path):
    rows = []
    try:
        with Path(file).open('r', encoding='utf8') as fh:
            for n, line in enumerate(fh, 1):
                if not line.strip():
                    continue
                x = json.loads(line)
                msgs = x.get('messages')
                if not isinstance(msgs, list) or [v.get('role') for v in msgs] != ['system','user','assistant']:
                    raise SmallTrainingError(f'Skema percakapan baris {n} tidak valid')
                for v in msgs:
                    content = v.get('content')
                    if not isinstance(content, str) or not content.strip() or len(content) > 1600:
                        raise SmallTrainingError(f'Konten baris {n} tidak valid')
                    if PII_EMAIL.search(content) or PHONE.search(content):
                        raise SmallTrainingError(f'Deteksi PII sederhana pada baris {n}; hentikan training')
                rows.append(x)
    except (OSError, ValueError, TypeError) as e:
        raise SmallTrainingError(f'Tidak dapat membaca contoh: {e}') from e
    return rows


def encode_rows(tokenizer, rows, max_len=320):
    prepared = []
    for x in rows:
        prompt = tokenizer.apply_chat_template(
            x['messages'][:-1], tokenize=False, add_generation_prompt=True,
            enable_thinking=False,
        )
        prompt_ids = tokenizer(prompt, add_special_tokens=False).input_ids
        response_ids = tokenizer(x['messages'][-1]['content'], add_special_tokens=False).input_ids
        eos = tokenizer.eos_token_id
        if not isinstance(eos,int):
            raise SmallTrainingError('Tokenizer tidak memiliki EOS token')
        ids = prompt_ids + response_ids + [eos]
        if len(ids) > max_len or len(response_ids) < 5:
            raise SmallTrainingError(f'Contoh terlalu panjang atau jawaban kosong ({len(ids)} token)')
        prepared.append({'input_ids': ids, 'labels': [-100]*len(prompt_ids) + response_ids + [eos]})
    return prepared


def doctor():
    info = {'ready': False, 'base_model': BASE_MODEL}
    try:
        import torch
        info['torch'] = torch.__version__
        if not torch.cuda.is_available():
            info['reason'] = 'CUDA tidak tersedia pada Python environment ini'
            return info
        prop = torch.cuda.get_device_properties(0)
        vram = round(prop.total_memory / (1024**3), 2)
        info.update(gpu=prop.name, vram_gib=vram)
        if vram < MIN_VRAM_GIB:
            info['reason'] = f'Butuh GPU dengan >= {MIN_VRAM_GIB} GiB VRAM'
            return info
        for pkg in ('transformers', 'peft', 'bitsandbytes', 'accelerate'):
            __import__(pkg)
        info['ready'] = True
    except (ImportError, OSError) as e:
        info['reason'] = f'Dependency belum siap: {type(e).__name__}: {e}'[:350]
    return info


def verify_run(folder: Path):
    p = Path(folder)
    try:
        m = json.loads((p/'manifest.json').read_text(encoding='utf8'))
        if (m.get('schema_version') != 4 or m.get('run_id') != p.name or
            m.get('base_model_hf') != BASE_MODEL or not RUN_RE.fullmatch(p.name)):
            raise SmallTrainingError('Manifest model kandidat tidak valid')
        artifacts = m['artifacts']
        if set(artifacts) != {'adapter_config.json', 'adapter_model.safetensors'}:
            raise SmallTrainingError('Manifest file adapter tidak lengkap')
        for f, sha in artifacts.items():
            if sha256_file(p/'adapter'/f) != sha:
                raise SmallTrainingError('SHA-256 adapter berubah: ' + f)
        return m
    except (OSError, ValueError, TypeError, KeyError) as e:
        raise SmallTrainingError('Verifikasi model gagal: ' + str(e)) from e


def guard_deploy(m: dict, sandbox: bool):
    if (m.get('schema_version') != 4 or m.get('base_model_hf') != BASE_MODEL or
        m.get('stage') != 'sandbox_candidate' or not m.get('evaluation_pass')):
        raise SmallTrainingError('Model belum lulus evaluasi kandidat yang sesuai')
    if not m.get('source_verified', False) and not sandbox:
        raise SmallTrainingError('Sumber belum terverifikasi; deployment hanya sandbox')


def train(examples_dir: Path, runs_dir: Path, sandbox: bool):
    try:
        m = verify_examples(examples_dir)['manifest']
    except (LearningError, OSError) as e:
        raise SmallTrainingError(str(e)) from e
    validate_manifest(m, sandbox)
    ident = run_id_for(m['dataset_fingerprint'])
    dest = runs_dir / ident
    if (dest/'manifest.json').exists():
        return {'status': 'unchanged', 'manifest': verify_run(dest)}
    if dest.exists():
        raise SmallTrainingError('Folder run parsial ditemukan; tidak ditimpa: ' + str(dest))
    gpu = doctor()
    if not gpu['ready']:
        raise SmallTrainingError('GPU belum siap: ' + gpu.get('reason','unknown'))
    train_raw = load_rows(examples_dir/'train.jsonl')
    eval_raw = load_rows(examples_dir/'eval.jsonl')
    if len(train_raw) != m['train_count'] or len(eval_raw) != m['eval_count']:
        raise SmallTrainingError('Jumlah baris dataset tidak sesuai manifest')
    import torch
    from transformers import AutoTokenizer, AutoModelForCausalLM, BitsAndBytesConfig, Trainer, TrainingArguments
    from peft import LoraConfig, get_peft_model, prepare_model_for_kbit_training

    runs_dir.mkdir(parents=True, exist_ok=True)
    dest.mkdir()  # Partial training kept for diagnosis; never promoted.
    try:
        dtype = torch.bfloat16 if torch.cuda.is_bf16_supported() else torch.float16
        tokenizer = AutoTokenizer.from_pretrained(BASE_MODEL, trust_remote_code=False)
        if tokenizer.pad_token_id is None:
            tokenizer.pad_token = tokenizer.eos_token
        tr = encode_rows(tokenizer, train_raw)
        ev = encode_rows(tokenizer, eval_raw)
        quant = BitsAndBytesConfig(load_in_4bit=True, bnb_4bit_quant_type='nf4',
                                  bnb_4bit_compute_dtype=dtype, bnb_4bit_use_double_quant=True)
        base = AutoModelForCausalLM.from_pretrained(
            BASE_MODEL, quantization_config=quant, device_map={'':0}, trust_remote_code=False)
        base.config.use_cache = False
        base = prepare_model_for_kbit_training(base)
        adapter = get_peft_model(base, LoraConfig(
            r=4, lora_alpha=8, lora_dropout=0.05,
            target_modules=['q_proj','v_proj'], bias='none', task_type='CAUSAL_LM'))

        class Rows:
            def __init__(self, entries):self.items=entries
            def __len__(self):return len(self.items)
            def __getitem__(self,i):return self.items[i]

        def collate(batch):
            maxlen = max(len(x['input_ids']) for x in batch)
            return {
                'input_ids': torch.tensor([x['input_ids'] + [tokenizer.pad_token_id]*(maxlen-len(x['input_ids'])) for x in batch], dtype=torch.long),
                'labels': torch.tensor([x['labels'] + [-100]*(maxlen-len(x['labels'])) for x in batch], dtype=torch.long),
                'attention_mask': torch.tensor([[1]*len(x['input_ids']) + [0]*(maxlen-len(x['input_ids'])) for x in batch], dtype=torch.long),
            }
        args = TrainingArguments(
            output_dir=str(dest/'checkpoints'), num_train_epochs=1,
            per_device_train_batch_size=1, per_device_eval_batch_size=1,
            gradient_accumulation_steps=8, learning_rate=1e-4,
            save_strategy='no', eval_strategy='no', logging_steps=10,
            optim='adamw_torch', fp16=dtype==torch.float16,
            bf16=dtype==torch.bfloat16, gradient_checkpointing=True,
            report_to='none', remove_unused_columns=False, dataloader_num_workers=0,
        )
        trainer = Trainer(model=adapter, args=args, train_dataset=Rows(tr), eval_dataset=Rows(ev), data_collator=collate)
        before = float(trainer.evaluate()['eval_loss'])
        trainer.train()
        after = float(trainer.evaluate()['eval_loss'])
        decision = candidate_decision(before, after, m.get('source_verified', False))
        adapter_dir = dest/'adapter'
        adapter.save_pretrained(str(adapter_dir), safe_serialization=True)
        tokenizer.save_pretrained(str(adapter_dir))
        files = ('adapter_config.json','adapter_model.safetensors')
        output = {
            'schema_version':4,'stage':'sandbox_candidate',
            'run_id':ident, 'base_model_hf':BASE_MODEL,
            'dataset_fingerprint':m['dataset_fingerprint'],
            'created_at_utc':datetime.now(timezone.utc).isoformat(),
            'source_label':m.get('source_label','unknown'),
            'train_count':len(tr),'eval_count':len(ev),'gpu':gpu,
            **decision,
            'artifacts':{f:sha256_file(adapter_dir/f) for f in files},
        }
        (dest/'manifest.json').write_bytes(json_bytes(output))
        return {'status':'trained','manifest':output}
    except Exception as e:
        (dest/'failure.txt').write_text(f'{type(e).__name__}: {str(e)[:1000]}', encoding='utf8')
        raise


def _run(argv, timeout=3600):
    try:
        p = subprocess.run(argv, capture_output=True, text=True, encoding='utf8', errors='replace', timeout=timeout)
    except (OSError, subprocess.TimeoutExpired) as e:
        raise SmallTrainingError('Command gagal: ' + str(e)) from e
    if p.returncode != 0:
        raise SmallTrainingError('Command gagal: ' + str(argv[:2]) + ' ' + p.stderr[-1200:])
    return p


def deploy(run_id: str, runs_dir: Path, publish_dir: Path, converter: Path, sandbox: bool):
    if not RUN_RE.fullmatch(run_id):
        raise SmallTrainingError('Run ID tidak valid')
    dest = runs_dir/run_id
    manifest = verify_run(dest)
    guard_deploy(manifest, sandbox)
    alias = candidate_alias(run_id)
    validate_alias(alias)
    if not converter.is_file() or converter.name != 'convert_hf_to_gguf.py':
        raise SmallTrainingError('Konverter llama.cpp belum tersedia')
    out = publish_dir/run_id
    if (out/'deploy-manifest.json').exists():
        status = json.loads((out/'deploy-manifest.json').read_text(encoding='utf8'))
        if status.get('run_id') == run_id:
            return status
        raise SmallTrainingError('Manifest deployment bertentangan')
    out.mkdir(parents=True, exist_ok=True)
    if shutil.disk_usage(out).free < 6*(1024**3):
        raise SmallTrainingError('Ruang disk kurang dari 6 GiB untuk merge dan GGUF')
    from transformers import AutoModelForCausalLM, AutoTokenizer
    from peft import PeftModel
    import torch
    merged_dir = out/'merged-hf'
    gguf = out/'merged-qwen3-06b-f16.gguf'
    if not gguf.is_file():
        base = AutoModelForCausalLM.from_pretrained(
            BASE_MODEL, torch_dtype=torch.float16, device_map='cpu', low_cpu_mem_usage=True, trust_remote_code=False)
        peft = PeftModel.from_pretrained(base, str(dest/'adapter'), is_trainable=False)
        merged = peft.merge_and_unload()
        merged_dir.mkdir(parents=True, exist_ok=True)
        merged.save_pretrained(str(merged_dir), safe_serialization=True)
        AutoTokenizer.from_pretrained(BASE_MODEL, trust_remote_code=False).save_pretrained(str(merged_dir))
        del base, peft, merged
        _run([sys.executable, str(converter), str(merged_dir), '--outfile', str(gguf), '--outtype', 'f16'], timeout=2400)
    if not gguf.is_file() or gguf.stat().st_size < 50_000_000:
        raise SmallTrainingError('GGUF tidak valid')
    modelfile = out/'Modelfile'
    note = 'Data latih belum terverifikasi sebagai transaksi nyata. Jangan klaim hasil ini fakta aktual.' if not manifest['source_verified'] else 'Jangan mengarang angka; ambil data bisnis terbaru dari konteks.'
    modelfile.write_text('FROM '+gguf.resolve().as_posix()+'\nPARAMETER num_ctx 2048\nSYSTEM """Kamu JSA kandidat eksperimen. '+note+' Tidak boleh mengubah transaksi atau stok."""\n',encoding='utf8')
    _run(['ollama','create',alias,'-f',str(modelfile)], timeout=900)
    _run(['ollama','show',alias], timeout=60)
    status = {
        'schema_version':4, 'run_id':run_id,'alias':alias,'sandbox_candidate':True,
        'source_verified':manifest['source_verified'],'ollama_finetuned':True,
        'production_promoted':False, 'original_qwen3_4b_unchanged':True,
        'gguf_sha256':sha256_file(gguf), 'deployed_at_utc':datetime.now(timezone.utc).isoformat(),
    }
    (out/'deploy-manifest.json').write_bytes(json_bytes(status))
    return status


def main(argv=None):
    p = argparse.ArgumentParser(description='Qwen3-0.6B experimental real LoRA/QLoRA, never changes JSA production')
    p.add_argument('command', choices=('doctor','identify','train','verify','deploy'))
    p.add_argument('--examples', type=Path, default=DEFAULT_EXAMPLES)
    p.add_argument('--runs',type=Path,default=RUNS)
    p.add_argument('--published',type=Path,default=PUBLISHED)
    p.add_argument('--run-id',default='')
    p.add_argument('--converter',type=Path,default=Path.home()/'JSA-llama.cpp-small'/'convert_hf_to_gguf.py')
    p.add_argument('--sandbox',action='store_true',help='Hanya uji coba dari dataset sumber belum terverifikasi')
    a = p.parse_args(argv)
    try:
        if a.command == 'doctor': outcome = doctor()
        elif a.command == 'identify':
            ex = verify_examples(a.examples)['manifest']
            validate_manifest(ex, a.sandbox)
            outcome = {'run_id': run_id_for(ex['dataset_fingerprint']),
                       'source_verified': bool(ex.get('source_verified', False))}
        elif a.command == 'train': outcome = train(a.examples,a.runs,a.sandbox)
        elif a.command == 'verify':
            if not RUN_RE.fullmatch(a.run_id):
                raise SmallTrainingError('Wajib --run-id yang valid')
            outcome = verify_run(a.runs/a.run_id)
        else:
            if not RUN_RE.fullmatch(a.run_id):
                raise SmallTrainingError('Wajib --run-id yang valid')
            outcome = deploy(a.run_id,a.runs,a.published,a.converter,a.sandbox)
        print(json.dumps(outcome,indent=2,ensure_ascii=False))
        if a.command == 'doctor':return 0 if outcome['ready'] else 2
        return 0
    except (SmallTrainingError, OSError, ValueError, ImportError) as e:
        print('JSA QWEN3 SMALL GAGAL: ' + str(e),file=sys.stderr)
        return 1

if __name__ == '__main__':
    raise SystemExit(main())
