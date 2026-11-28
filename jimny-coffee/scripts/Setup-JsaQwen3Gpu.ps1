# Explicit optional setup for real Qwen3-4B LoRA. Not required for daily knowledge updates.
$ErrorActionPreference='Stop'
$Project=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
Set-Location -LiteralPath $Project
$Gpu=Get-Command 'nvidia-smi.exe' -ErrorAction SilentlyContinue
if (-not $Gpu) { throw 'GPU NVIDIA CUDA belum terdeteksi. Daily adaptive Ollama bisa jalan, tetapi LoRA 4B tidak.' }
$MemText = & nvidia-smi.exe --query-gpu=memory.total --format=csv,noheader,nounits
if ($LASTEXITCODE -ne 0) { throw 'Tidak dapat memeriksa VRAM NVIDIA' }
$MemMiB = [int](($MemText | Select-Object -First 1).Trim())
if ($MemMiB -lt 10240) { throw "VRAM $MemMiB MiB; minimal 10240 MiB, disarankan 16 GiB. LoRA tidak dipaksakan." }
$Venv = Join-Path $env:LOCALAPPDATA 'JSA-Qwen3-Venv'
$Py = Join-Path $Venv 'Scripts\python.exe'
if (-not (Test-Path -LiteralPath $Py)) {
    & python -m venv $Venv
    if ($LASTEXITCODE -ne 0) { throw 'Gagal membuat venv' }
}
& $Py -m pip install --upgrade pip
if ($LASTEXITCODE -ne 0) { throw 'Upgrade pip gagal' }
& $Py -m pip install -r (Join-Path $PSScriptRoot 'requirements-qwen3-lora.txt')
if ($LASTEXITCODE -ne 0) { throw 'Instalasi dependency GPU gagal. Periksa kompatibilitas PyTorch CUDA / driver.' }
& $Py -B scripts/jsa_qwen3_lora.py doctor
if ($LASTEXITCODE -ne 0) { throw 'GPU diagnostic gagal' }
Write-Host 'Virtualenv Qwen3 tersedia. Hanya menjalankan LoRA jika doctor.ready=true.' -ForegroundColor Green
