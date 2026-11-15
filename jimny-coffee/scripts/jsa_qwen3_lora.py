"""Train a real Qwen3-4B LoRA adapter (not Ollama prompt tuning).

Training uses a separate full-precision Hugging Face base checkpoint with 4-bit QLoRA
for training. A trained adapter must be merged+converted to GGUF before use in Ollama.
Never overwrites qwen3:4b-instruct or production JSA code. GPU-only by design.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import math
import os
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

from jsa_qwen3_learning import DEFAULT_EXAMPLES, LearningError, verify_examples, json_bytes

BASE_HF = 'Qwen/Qwen3-4B-Instruct-2507'
MODEL_RE = re.compile(r'^Qwen/Qwen3-4B-Instruct-2507$')
DEFAULT_RUNS = Path('storage/app/private/jsa/learning/qwen3/finetuned')
MIN_TRAIN=100
MIN_VALID=20
VRAM_MIN_GIB=10.0

class FineTuneError(RuntimeError):
    pass


def validate_model_id(model_id):
    if not MODEL_RE.fullmatch(model_id):
        raise FineTuneError('Model HF hanya boleh Qwen/Qwen3-4B-Instruct-2507 (checkpoint harus cocok)')


def check_manifest(m:dict,allow_unverified_sandbox:bool):
    if m.get('stage') != 'sandbox_candidate':
        raise FineTuneError('Dataset contoh bukan kandidat sandbox yang dikenal')
    if m.get('train_count',0)<MIN_TRAIN or m.get('eval_count',0)<MIN_VALID:
        raise FineTuneError(f'Contoh berlabel belum cukup (minimum train={MIN_TRAIN}, eval={MIN_VALID})')
    if not m.get('source_verified',False) and not allow_unverified_sandbox:
        raise FineTuneError('Sumber data belum terverifikasi; hanya dapat mencoba training dengan --sandbox')


def gpu_report():
    try:
        import torch
    except ImportError:
        return {'ready':False,'reason':'PyTorch tidak terpasang di environment training'}
    if not torch.cuda.is_available():
        return {'ready':False,'reason':'CUDA GPU tidak tersedia; 4B QLoRA CPU tidak diaktifkan'}
    props=torch.cuda.get_device_properties(0)
    gib=round(props.total_memory/(1024**3),2)
    if gib<VRAM_MIN_GIB:
        return {'ready':False,'gpu':props.name,'vram_gib':gib,
                'reason':f'VRAM minimal {VRAM_MIN_GIB} GiB, disarankan >= 16 GiB'}
    missing=[]
    for name in ('transformers','peft','bitsandbytes','accelerate'):
        try:__import__(name)
        except (ImportError,OSError):missing.append(name)
    if missing:return {'ready':False,'gpu':props.name,'vram_gib':gib,'reason':'Dependency belum ada: '+','.join(missing)}
    return {'ready':True,'gpu':props.name,'vram_gib':gib,'torch':torch.__version__}


def require_gpu():
    info=gpu_report()
    if not info.get('ready'):raise FineTuneError(info.get('reason','GPU tidak siap'))
    return info


def candidate_status(source_verified,base_loss_model,baseline_loss,trained_loss,dataset_sha):
    # At least 1% relative improvement, not a business safety guarantee.
    safe=(math.isfinite(baseline_loss) and math.isfinite(trained_loss) and
          trained_loss < baseline_loss*.99)
    return {'stage':'sandbox_candidate','base_model_hf':base_loss_model,
            'source_verified':bool(source_verified),'train_eval_loss_before':round(baseline_loss,6),
            'train_eval_loss_after':round(trained_loss,6),'evaluation_pass':bool(safe),
            'production_promoted':False,'ollama_original_model_modified':False,
            'dataset_sha256':dataset_sha, 'ollama_finetuned':False,
            'note':'LoRA adapter trained if adapter artifacts are present; not imported into Ollama yet.'}


def _load_jsonl(path:Path):
    data=[]
    with path.open('r',encoding='utf8') as f:
        for i,line in enumerate(f,1):
            if not line.strip():continue
            obj=json.loads(line)
            messages=obj.get('messages')
            if not isinstance(messages,list) or len(messages)!=3 or [x.get('role') for x in messages]!=['system','user','assistant']:
                raise FineTuneError(f'Contoh baris {i} tidak sesuai skema')
            if any(not isinstance(x.get('content'),str) or len(x['content'])>2000 for x in messages):
                raise FineTuneError(f'Contoh baris {i} terlalu panjang atau bukan teks')
            data.append(obj)
    return data


def _tokenize_chat(tokenizer,rows,max_length=512):
    prepared=[]
    for r in rows:
        messages=r['messages']
        prefix=tokenizer.apply_chat_template(messages[:-1],tokenize=True,add_generation_prompt=True)
        full=tokenizer.apply_chat_template(messages,tokenize=True,add_generation_prompt=False)
        if not isinstance(prefix,list) or not isinstance(full,list) or full[:len(prefix)]!=prefix:
            raise FineTuneError('Chat template model tidak sesuai; training dihentikan')
        if len(full)>max_length or len(full)-len(prefix)<5:
            raise FineTuneError('Contoh terlalu panjang atau tidak mengandung jawaban assistant')
        prepared.append({'input_ids':full,'labels':[-100]*len(prefix)+full[len(prefix):]})
    return prepared


def train(examples_dir:Path=DEFAULT_EXAMPLES,out_dir:Path=DEFAULT_RUNS,
          model_id:str=BASE_HF,allow_unverified_sandbox:bool=False)->dict:
    validate_model_id(model_id)
    try:manifest=verify_examples(Path(examples_dir))['manifest']
    except (LearningError,OSError) as exc:raise FineTuneError(str(exc)) from exc
    check_manifest(manifest,allow_unverified_sandbox)
    gpu=require_gpu()
    # Lazy dependency imports allow doctor and unit tests without GPU packages.
    import torch
    from transformers import (AutoTokenizer,AutoModelForCausalLM,BitsAndBytesConfig,
                              Trainer,TrainingArguments)
    from peft import LoraConfig,get_peft_model,prepare_model_for_kbit_training
    out_dir=Path(out_dir)
    run_key=hashlib.sha256((manifest['dataset_fingerprint']+model_id+'-qwen3-v3').encode()).hexdigest()[:20]
    run_id='qwen3ft_'+run_key
    dest=out_dir/run_id
    if (dest/'manifest.json').exists():
        return verify_candidate(dest)
    if dest.exists():raise FineTuneError('Ada folder training parsial; tidak akan menimpanya: '+str(dest))
    out_dir.mkdir(parents=True,exist_ok=True)
    dest.mkdir()  # a failed run is retained for inspection; never promoted
    try:
        dtype=torch.bfloat16 if torch.cuda.is_bf16_supported() else torch.float16
        tokenizer=AutoTokenizer.from_pretrained(model_id,trust_remote_code=False)
        if tokenizer.pad_token_id is None:tokenizer.pad_token=tokenizer.eos_token
        train_rows=_tokenize_chat(tokenizer,_load_jsonl(Path(examples_dir)/'train.jsonl'))
        eval_rows=_tokenize_chat(tokenizer,_load_jsonl(Path(examples_dir)/'eval.jsonl'))
        quant=BitsAndBytesConfig(load_in_4bit=True,bnb_4bit_quant_type='nf4',
                                bnb_4bit_compute_dtype=dtype,bnb_4bit_use_double_quant=True)
        base=AutoModelForCausalLM.from_pretrained(model_id,quantization_config=quant,
                                                  device_map={'':0},trust_remote_code=False)
        base.config.use_cache=False
        base=prepare_model_for_kbit_training(base)
        adapter=get_peft_model(base,LoraConfig(r=8,lora_alpha=16,lora_dropout=.05,
            target_modules=['q_proj','k_proj','v_proj','o_proj','gate_proj','up_proj','down_proj'],
            bias='none',task_type='CAUSAL_LM'))
        class Rows:
            def __init__(self,items):self.items=items
            def __getitem__(self,i):return self.items[i]
            def __len__(self):return len(self.items)
        def collate(batch):
            maxlen=max(len(x['input_ids']) for x in batch)
            input_ids=[x['input_ids']+[tokenizer.pad_token_id]*(maxlen-len(x['input_ids'])) for x in batch]
            labels=[x['labels']+[-100]*(maxlen-len(x['labels'])) for x in batch]
            masks=[[1]*len(x['input_ids'])+[0]*(maxlen-len(x['input_ids'])) for x in batch]
            return {'input_ids':torch.tensor(input_ids,dtype=torch.long),
                    'attention_mask':torch.tensor(masks,dtype=torch.long),
                    'labels':torch.tensor(labels,dtype=torch.long)}
        args=TrainingArguments(output_dir=str(dest/'temp-checkpoints'),num_train_epochs=1,
            per_device_train_batch_size=1,per_device_eval_batch_size=1,gradient_accumulation_steps=8,
            learning_rate=1e-4,weight_decay=.01,warmup_ratio=.03,
            logging_steps=10,save_strategy='no',eval_strategy='no',
            fp16=dtype==torch.float16,bf16=dtype==torch.bfloat16,
            gradient_checkpointing=True,report_to='none',remove_unused_columns=False,
            dataloader_num_workers=0)
        trainer=Trainer(model=adapter,args=args,train_dataset=Rows(train_rows),
                        eval_dataset=Rows(eval_rows),data_collator=collate)
        baseline=float(trainer.evaluate()['eval_loss'])
        trainer.train()
        after=float(trainer.evaluate()['eval_loss'])
        status=candidate_status(manifest['source_verified'],model_id,baseline,after,
                                hashlib.sha256((Path(examples_dir)/'train.jsonl').read_bytes()).hexdigest())
        (dest/'adapter').mkdir()
        adapter.save_pretrained(str(dest/'adapter'),safe_serialization=True)
        tokenizer.save_pretrained(str(dest/'adapter'))
        artifacts={name:hashlib.sha256((dest/'adapter'/name).read_bytes()).hexdigest()
          for name in ('adapter_config.json','adapter_model.safetensors')}
        output={**status,'schema_version':3,'run_id':run_id,
           'created_at_utc':datetime.now(timezone.utc).isoformat(), 'gpu':gpu,
           'dataset_fingerprint':manifest['dataset_fingerprint'],'artifacts':artifacts}
        (dest/'manifest.json').write_bytes(json_bytes(output))
        return output
    except Exception as exc:
        (dest/'failure.txt').write_text(type(exc).__name__+': '+str(exc)[:600],encoding='utf8')
        raise


def verify_candidate(dest:Path)->dict:
    dest=Path(dest)
    try:
        m=json.loads((dest/'manifest.json').read_text(encoding='utf8'))
        if m.get('schema_version')!=3 or m.get('run_id')!=dest.name:
            raise FineTuneError('Identitas hasil training tidak cocok')
        for f,expected in m['artifacts'].items():
            if f not in ('adapter_config.json','adapter_model.safetensors'):
                raise FineTuneError('Artifact tidak diizinkan')
            if hashlib.sha256((dest/'adapter'/f).read_bytes()).hexdigest()!=expected:
                raise FineTuneError('Artifact training berubah: '+f)
        return m
    except (OSError,ValueError,KeyError,TypeError) as exc:
        raise FineTuneError('Gagal memverifikasi adapter: '+str(exc)) from exc


def main(argv=None):
    p=argparse.ArgumentParser(description='Real Qwen3-4B LoRA training (requires NVIDIA CUDA GPU)')
    p.add_argument('command',choices=['doctor','train','verify'])
    p.add_argument('--examples',type=Path,default=DEFAULT_EXAMPLES)
    p.add_argument('--runs',type=Path,default=DEFAULT_RUNS)
    p.add_argument('--run-id',default=None)
    p.add_argument('--model-id',default=BASE_HF)
    p.add_argument('--sandbox',action='store_true',help='Izinkan belajar dari CSV asal belum terverifikasi untuk eksperimen saja')
    a=p.parse_args(argv)
    try:
        if a.command=='doctor':o=gpu_report()
        elif a.command=='train':o=train(a.examples,a.runs,a.model_id,a.sandbox)
        else:
            if not a.run_id or not re.fullmatch(r'qwen3ft_[0-9a-f]{20}',a.run_id):
                raise FineTuneError('Diperlukan --run-id qwen3ft_...')
            o=verify_candidate(a.runs/a.run_id)
        print(json.dumps(o,indent=2,ensure_ascii=False))
        return 0
    except (FineTuneError,ImportError,OSError) as exc:
        print('FINE TUNING QWEN3 GAGAL:',exc,file=sys.stderr)
        return 1

if __name__=='__main__':raise SystemExit(main())
