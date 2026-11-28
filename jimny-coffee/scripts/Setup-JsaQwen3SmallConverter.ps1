# Optional setup for GGUF export of a passing Qwen3-0.6B QLoRA candidate.
# Uses GitHub official ggml-org/llama.cpp repository; does not change Ollama base.
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$Project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
Set-Location -LiteralPath $Project
$Py = Join-Path $env:LOCALAPPDATA 'JSA-Qwen3-Small-Venv\Scripts\python.exe'
if (-not (Test-Path -LiteralPath $Py)) { throw 'Jalankan Setup-JsaQwen3Small.ps1 lebih dulu.' }
if (-not (Get-Command git -ErrorAction SilentlyContinue)) { throw 'Git belum tersedia.' }
$Dir = Join-Path $env:LOCALAPPDATA 'JSA-llama.cpp-small'
if (-not (Test-Path -LiteralPath $Dir)) {
    & git clone --depth 1 https://github.com/ggml-org/llama.cpp.git $Dir
    if ($LASTEXITCODE -ne 0) { throw 'Clone llama.cpp gagal.' }
}
$Converter = Join-Path $Dir 'convert_hf_to_gguf.py'
if (-not (Test-Path -LiteralPath $Converter)) {
    throw "Converter tidak ditemukan: $Converter"
}
$Req = Join-Path $Dir 'requirements\requirements-convert_hf_to_gguf.txt'
if (Test-Path -LiteralPath $Req) {
    & $Py -m pip install -r $Req
    if ($LASTEXITCODE -ne 0) { throw 'Dependensi GGUF converter gagal diinstal.' }
}
else { Write-Warning 'requirements GGUF tidak ditemukan; converter perlu dependensi tambahan bila gagal.' }
Write-Host "Converter tersedia: $Converter" -ForegroundColor Green
