# Set up a separate, sandbox training environment for Qwen3-0.6B on 6 GiB NVIDIA GPU.
# No changes to Laravel, Ollama original models, or global Python packages.
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$Project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
Set-Location -LiteralPath $Project

if (-not (Get-Command 'nvidia-smi.exe' -ErrorAction SilentlyContinue)) {
    throw 'nvidia-smi tidak ditemukan. GPU NVIDIA CUDA diperlukan.'
}
$Mem = @(& nvidia-smi.exe --query-gpu=memory.total --format=csv,noheader,nounits)
if ($LASTEXITCODE -ne 0 -or $Mem.Count -eq 0) { throw 'Tidak bisa membaca VRAM GPU.' }
$MemMiB = [int]$Mem[0].Trim()
if ($MemMiB -lt 5120) { throw "VRAM $MemMiB MiB di bawah minimum 5120 MiB." }
Write-Host "GPU ditemukan, VRAM: $MemMiB MiB" -ForegroundColor Cyan

$Venv = Join-Path $env:LOCALAPPDATA 'JSA-Qwen3-Small-Venv'
$Py = Join-Path $Venv 'Scripts\python.exe'
if (-not (Test-Path -LiteralPath $Py)) {
    & python -m venv $Venv
    if ($LASTEXITCODE -ne 0) { throw 'Gagal membuat virtualenv.' }
}
& $Py -m pip install --upgrade pip
if ($LASTEXITCODE -ne 0) { throw 'Gagal memperbarui pip.' }

# Stable Windows CUDA wheels: version choice follows PyTorch's cu128 binary index.
& $Py -m pip install torch --index-url https://download.pytorch.org/whl/cu128
if ($LASTEXITCODE -ne 0) { throw 'Instalasi PyTorch CUDA gagal.' }

$Req = Join-Path $PSScriptRoot 'requirements-qwen3-small.txt'
& $Py -m pip install -r $Req
if ($LASTEXITCODE -ne 0) { throw 'Instalasi dependensi Qwen3 gagal.' }

Write-Host "`n===== QWEN3 SMALL DOCTOR =====" -ForegroundColor Cyan
& $Py -B scripts/jsa_qwen3_small.py doctor
if ($LASTEXITCODE -ne 0) {
    throw 'Doctor Qwen3 gagal. Jangan jalankan training sebelum GPU siap.'
}
Write-Host "Environment Qwen3 Small siap: $Py" -ForegroundColor Green
