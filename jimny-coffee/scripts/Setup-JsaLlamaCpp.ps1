# Optional GGUF converter setup: git clone official llama.cpp into user local app data.
# Execute only after Setup-JsaQwen3Gpu.ps1 succeeds.
$ErrorActionPreference='Stop'
$LlamaCpp = Join-Path $env:LOCALAPPDATA 'JSA-llama.cpp'
$Py = Join-Path $env:LOCALAPPDATA 'JSA-Qwen3-Venv\Scripts\python.exe'
if (-not (Test-Path -LiteralPath $Py)) { throw 'Jalankan Setup-JsaQwen3Gpu.ps1 dulu.' }
if (-not (Test-Path -LiteralPath $LlamaCpp)) {
    & git clone --depth 1 https://github.com/ggml-org/llama.cpp $LlamaCpp
    if ($LASTEXITCODE -ne 0) { throw 'git clone llama.cpp gagal' }
}
$Convert=Join-Path $LlamaCpp 'convert_hf_to_gguf.py'
if (-not (Test-Path -LiteralPath $Convert)) { throw 'llama.cpp tidak lengkap' }
& $Py -m pip install 'gguf>=0.17' 'sentencepiece>=0.2' 'protobuf>=5' 'safetensors>=0.5' 'numpy>=1.26'
if ($LASTEXITCODE -ne 0) { throw 'Gagal memasang dependency konversi GGUF' }
Write-Host "Konverter tersedia di $Convert. Akan digunakan otomatis setelah LoRA lulus evaluasi." -ForegroundColor Green
