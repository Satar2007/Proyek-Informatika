# JSA Fase 4: Qwen3 0.6B *weight training* (candidate sandbox, Windows 6 GiB)

The existing JSA continues using `jimny-qwen3:adaptive` (derived from `qwen3:4b-instruct`).
This package adds **actual QLoRA fine-tuning** of a distinct Hugging Face checkpoint `Qwen/Qwen3-0.6B`, which is smaller than Qwen3 4B. It uses a separate Python virtualenv. It **does not fine-tune the 4B model**, doesn't change the Laravel controllers, and never changes POS data.

## Prerequisites and safety

- RTX 4050 Laptop 6 GiB + CUDA-capable PyTorch; Python 3.11.9; internet for Hugging Face model and packages; sufficient disk (recommend >= 12 GiB free).
- Prior Fase 1, 2, 3 installed, including `scripts/jsa_qwen3_learning.py`, `scripts/jsa_dataset_manager.py`, and existing `storage/app/private/jsa/learning/qwen3/examples/{manifest.json,train.jsonl,eval.jsonl}`.
- Current data is `csv_unverified_origin` from `satar_acceptance`. Training requires `-Sandbox`. Candidate is not an authorized model for production decisions.
- `qwen3:4b-instruct`, `jimny-qwen3:adaptive` and the existing daily 03:40 task are never overwritten or deleted. The 0.6B trainer is a separate task.
- The *monthly* task is defined as a daily trigger at 05:10 whose runner does nothing except on the first of the month. This works with the PowerShell ScheduledTasks API.
- If the PC is off or not logged in, scheduled training may not run. Ollama/MySQL must be available to refresh the daily dataset; the trainer itself only reads prepared examples.

## Usage

From PowerShell in `jimny-coffee`:

```powershell
python -B -m unittest discover -s scripts/tests -p "test_jsa_qwen3_small.py" -v
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Setup-JsaQwen3Small.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Run-JsaQwen3Small.ps1 -ForceTrain -Sandbox -SkipDeploy
```

For importing a successful candidate into Ollama:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Setup-JsaQwen3SmallConverter.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Run-JsaQwen3Small.ps1 -ForceTrain -Sandbox
```

Train result is reused by fingerprint. If the first run evaluated worse, do **not** force automatic model deployment. Source dataset needs legitimate review and evaluation on distinct business QA examples, not only synthetic arithmetic token loss.

For automatic monthly sandbox trial (once GPU training succeeds manually):

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/Install-JsaQwen3SmallTask.ps1 -AllowSandbox
Get-ScheduledTask -TaskName JSA-Qwen3-Small-FineTune | Format-List TaskName,State
```

## Model workflow

- Input JSONL is prepared by Fase 3 and hash-checked before train.
- QLoRA: NF4 4-bit base loading, adapter rank=4 on attention query/value, single example batches, gradient checkpointing, 1 epoch.
- Evaluates before and after token loss on held-out chronological examples. Needs >1% improvement to deploy (heuristic, not proof of domain capability).
- Saves immutable candidate under `storage/app/private/jsa/learning/qwen3/small_runs/qwen3s_<hash>` with SHA-256 adapter integrity.
- Optional: merges LoRA into the **same** HF Qwen3 0.6B base on CPU, converts with official llama.cpp converter, imports a versioned Ollama alias `jimny-qwen3:small-<hash>`. The original 4B model is not modified.
- Source contains no customer names/IDs in auto-generated JSA aggregates; the script includes basic PII checks but not a comprehensive privacy audit.
- If LoRA cannot run on 6 GiB because of fragmentation or CUDA DLL mismatch, no fake training success is recorded. Stop other GPU applications and inspect logs.

## What this does **not** prove

- 0.6B is generally less capable than 4B; a better token loss on arithmetic exercises does not justify swapping it into JSA.
- An unverified acceptance database is **not** verified real sales.
- No chat logs are self-trained by default. No automatic model promotion to production.
- No automatic deleting history from MySQL; datasets/weights are separate experimental artifacts.

Sources: [Hugging Face Qwen3 0.6B](https://huggingface.co/Qwen/Qwen3-0.6B), [PEFT training](https://huggingface.co/docs/transformers/peft), [Ollama import](https://docs.ollama.com/import).
