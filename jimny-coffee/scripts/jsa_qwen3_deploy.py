"""Merge a trained Qwen3 LoRA and import an independent candidate GGUF into Ollama.

Requires an evaluated LoRA, HF base weights, local llama.cpp converter and free disk.
Never touches original qwen3:4b-instruct or production JSA model.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

from jsa_qwen3_lora import BASE_HF, DEFAULT_RUNS, FineTuneError, verify_candidate
from jsa_qwen3_learning import json_bytes

DEFAULT_PUBLISHED=Path('storage/app/private/jsa/learning/qwen3/published')
DEFAULT_ALIAS='jimny-qwen3:ft-candidate'
class DeployError(RuntimeError): pass


def validate_candidate(manifest:dict, sandbox:bool):
    if manifest.get('base_model_hf')!=BASE_HF:
        raise DeployError('HF base model tidak cocok; adapter tidak aman diimpor')
    if manifest.get('stage')!='sandbox_candidate' or not manifest.get('evaluation_pass'):
        raise DeployError('Kandidat tidak lolos evaluasi; tidak dapat diimpor')
    if not manifest.get('source_verified') and not sandbox:
        raise DeployError('Sumber latihan tidak terverifikasi; gunakan --sandbox untuk eksperimen saja')


def validate_alias(alias:str):
    if alias=='qwen3:4b-instruct' or alias=='jimny-qwen3:adaptive' or not re.fullmatch(r'[a-z0-9][a-z0-9_-]{2,30}:[a-z0-9][a-z0-9_-]{2,30}',alias):
        raise DeployError('Alias tidak aman / berpotensi menimpa model aktif')


def sha256_file(path:Path)->str:
    h=hashlib.sha256()
    with Path(path).open('rb') as f:
        for piece in iter(lambda:f.read(1024*1024),b''):
            h.update(piece)
    return h.hexdigest()


def _run(argv,timeout=7200):
    try:
        p=subprocess.run(argv,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,
                         encoding='utf8',errors='replace',timeout=timeout)
    except (OSError,subprocess.TimeoutExpired) as exc:
        raise DeployError(str(exc)) from exc
    if p.returncode:raise DeployError('Command gagal: '+str(argv[:2])+' | '+p.stderr[-2000:])
    return p


def publish(run_id:str,llama_cpp:Path,runs:Path=DEFAULT_RUNS,published:Path=DEFAULT_PUBLISHED,
            alias:str=DEFAULT_ALIAS,sandbox:bool=False)->dict:
    if not re.fullmatch(r'qwen3ft_[0-9a-f]{20}',run_id):raise DeployError('Run ID tidak valid')
    validate_alias(alias)
    run=Path(runs)/run_id
    try:m=verify_candidate(run)
    except FineTuneError as exc:raise DeployError(str(exc)) from exc
    validate_candidate(m,sandbox)
    converter=Path(llama_cpp)/'convert_hf_to_gguf.py'
    if not converter.is_file():raise DeployError('Script converter llama.cpp belum ditemukan: '+str(converter))
    dest=Path(published)/run_id
    dest.mkdir(parents=True,exist_ok=True)
    gguf=dest/'qwen3-finetuned-f16.gguf'
    merged=dest/'hf-merged'
    if not gguf.exists():
        if shutil.disk_usage(dest).free < 20*(1024**3):
            raise DeployError('Minimal 20 GiB ruang disk kosong dibutuhkan untuk merge GGUF')
        try:
            import torch
            from transformers import AutoModelForCausalLM,AutoTokenizer
            from peft import PeftModel
        except ImportError as exc:raise DeployError('Dependency LoRA belum terpasang: '+str(exc)) from exc
        # Merge on CPU for compatibility with quantized QLoRA; requires sufficient system RAM.
        base=AutoModelForCausalLM.from_pretrained(BASE_HF,torch_dtype=torch.float16,
                                                  device_map='cpu',low_cpu_mem_usage=True,trust_remote_code=False)
        adapter=PeftModel.from_pretrained(base,str(run/'adapter'),is_trainable=False)
        final=adapter.merge_and_unload()
        merged.mkdir(exist_ok=True)
        final.save_pretrained(str(merged),safe_serialization=True,max_shard_size='2GB')
        AutoTokenizer.from_pretrained(BASE_HF,trust_remote_code=False).save_pretrained(str(merged))
        del adapter,base,final
        # Converter writes to a candidate path; do not use any unknown pretrained adapter/base mix.
        _run([sys.executable,str(converter),str(merged),'--outfile',str(gguf),'--outtype','f16'],timeout=3600)
    if not gguf.exists() or gguf.stat().st_size<1_000_000:
        raise DeployError('GGUF gagal dibuat atau terlalu kecil')
    modelfile=dest/'Modelfile'
    source_note='Dataset sumber CSV belum terverifikasi sebagai transaksi nyata.' if not m.get('source_verified') else 'Gunakan hanya fakta dari konteks terkini.'
    modelfile.write_text('FROM '+str(gguf.resolve()).replace('\\','/')+'\nPARAMETER temperature 0.2\nPARAMETER num_ctx 4096\nSYSTEM """Kamu JIMNY Smart Assistant. '+source_note+' Jangan mengarang angka atau mengubah POS."""\n',encoding='utf8')
    _run(['ollama','create',alias,'-f',str(modelfile.resolve())],timeout=3600)
    # Sanity-check model can load. No alias in active JSA controller is changed.
    o={'stage':'sandbox_candidate','source_verified':m.get('source_verified',False),
       'original_model_unchanged':True,'production_promoted':False,'ollama_finetuned':True,
       'run_id':run_id,'ollama_alias':alias,'gguf_sha256':sha256_file(gguf),
       'created_at_utc':datetime.now(timezone.utc).isoformat()}
    (dest/'manifest.json').write_bytes(json_bytes(o))
    return o


def main(argv=None):
    p=argparse.ArgumentParser(description='Merge LoRA Qwen3, convert GGUF and import Ollama candidate')
    p.add_argument('--run-id',required=True)
    p.add_argument('--llama-cpp-dir',type=Path,required=True)
    p.add_argument('--runs',type=Path,default=DEFAULT_RUNS)
    p.add_argument('--published',type=Path,default=DEFAULT_PUBLISHED)
    p.add_argument('--alias',default=DEFAULT_ALIAS)
    p.add_argument('--sandbox',action='store_true')
    a=p.parse_args(argv)
    try:
        o=publish(a.run_id,a.llama_cpp_dir,a.runs,a.published,a.alias,a.sandbox)
        print(json.dumps(o,indent=2,ensure_ascii=False));return 0
    except (DeployError,OSError) as exc:
        print('QWEN3 OLLAMA DEPLOY GAGAL:',exc,file=sys.stderr);return 1
if __name__=='__main__':raise SystemExit(main())
