# JSA Fase 6: prepare balanced examples, train an isolated Qwen3-0.6B adapter,
# optionally deploy it to a sandbox Ollama alias, then enforce semantic QA.
# Never switches the 4B Laravel model or updates POS transactions.
param(
    [switch]$ForceTrain,
    [switch]$Sandbox,
    [switch]$SkipDeploy
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$Project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
Set-Location -LiteralPath $Project
$env:PYTHONDONTWRITEBYTECODE = '1'
$Started = $false
$LogDir = Join-Path $Project 'storage\logs'
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
$Log = Join-Path $LogDir ('jsa-qwen3-balanced-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.log')
try {
    Start-Transcript -LiteralPath $Log -ErrorAction Stop | Out-Null
    $Started = $true
    if (-not $Sandbox) { throw 'Sumber CSV belum terverifikasi: parameter -Sandbox wajib.' }
    if (-not $ForceTrain -and (Get-Date).Day -ne 1) {
        Write-Host 'Belum tanggal 1; training bulanan dilewati.'
        return
    }
    $Py = Join-Path $env:LOCALAPPDATA 'JSA-Qwen3-Small-Venv\Scripts\python.exe'
    if (-not (Test-Path -LiteralPath $Py)) { throw 'Virtualenv Qwen3 Small tidak tersedia.' }
    foreach ($name in @('jsa_dataset_manager.py','jsa_qwen3_learning.py','jsa_qwen3_small.py','jsa_qwen3_balanced.py','jsa_semantic_gate.py')) {
        if (-not (Test-Path -LiteralPath (Join-Path $PSScriptRoot $name))) {
            throw ('Dependency hilang: scripts\' + $name)
        }
    }
    Write-Host '===== JSA QWEN3 BALANCED V6 =====' -ForegroundColor Cyan
    Write-Host "Log: $Log"
    Write-Host '[1/5] Kurikulum latihan baru dari snapshot terverifikasi' -ForegroundColor Cyan
    $PreparedText = & python -B scripts/jsa_qwen3_balanced.py prepare
    if ($LASTEXITCODE -ne 0) { throw 'Gagal membangun dataset baru; periksa freshness/manifest.' }
    $Prepared = ($PreparedText | Out-String | ConvertFrom-Json)
    $ExamplesDir = [string]$Prepared.examples_dir
    if (-not (Test-Path -LiteralPath (Join-Path $ExamplesDir 'manifest.json'))) {
        throw 'Manifest contoh baru tidak ada'
    }
    & python -B scripts/jsa_qwen3_learning.py verify --examples $ExamplesDir
    if ($LASTEXITCODE -ne 0) { throw 'Verifikasi hash dataset latihan gagal.' }

    Write-Host '[2/5] Identifikasi kandidat model terpisah' -ForegroundColor Cyan
    $IdText = & $Py -B scripts/jsa_qwen3_small.py identify --examples $ExamplesDir --sandbox
    if ($LASTEXITCODE -ne 0) { throw 'Run ID tidak dapat diverifikasi.' }
    $IdInfo = ($IdText | Out-String | ConvertFrom-Json)
    $Id = [string]$IdInfo.run_id
    if ($Id -notmatch '^qwen3s_[a-f0-9]{20}$') { throw 'Run ID model tidak aman.' }
    Write-Host "Run ID: $Id"

    # Free GPU memory without deleting Ollama models.
    try { & ollama stop jimny-qwen3:adaptive | Out-Null } catch { Write-Warning 'Ollama 4B mungkin masih berada di VRAM.' }
    try { & ollama stop qwen3:4b-instruct | Out-Null } catch { Write-Warning 'Ollama base 4B mungkin masih berada di VRAM.' }

    Write-Host '[3/5] Fine-tuning QLoRA 0.6B' -ForegroundColor Cyan
    & $Py -B scripts/jsa_qwen3_small.py train --examples $ExamplesDir --sandbox
    if ($LASTEXITCODE -ne 0) { throw 'Training gagal. Model 4B tetap utuh.' }
    & $Py -B scripts/jsa_qwen3_small.py verify --run-id $Id
    if ($LASTEXITCODE -ne 0) { throw 'Adapter gagal verifikasi.' }
    $ModelManifest = Join-Path $Project ("storage\app\private\jsa\learning\qwen3\small_runs\$Id\manifest.json")
    $Result = Get-Content -LiteralPath $ModelManifest -Raw | ConvertFrom-Json
    if (-not $Result.evaluation_pass) {
        throw 'Loss evaluasi belum membaik. Kandidat tidak diimpor.'
    }
    if ($SkipDeploy) {
        Write-Host 'Training/evaluasi selesai; deployment dilewati (-SkipDeploy).' -ForegroundColor Green
        return
    }
    $Converter = Join-Path $env:LOCALAPPDATA 'JSA-llama.cpp-small\convert_hf_to_gguf.py'
    if (-not (Test-Path -LiteralPath $Converter)) {
        throw 'Konverter GGUF tidak ditemukan. Jalankan Setup-JsaQwen3SmallConverter.ps1.'
    }
    Write-Host '[4/5] Impor hanya sebagai kandidat sandbox, tanpa mengganti 4B' -ForegroundColor Cyan
    & $Py -B scripts/jsa_qwen3_small.py deploy --run-id $Id --converter $Converter --sandbox
    if ($LASTEXITCODE -ne 0) { throw 'Impor kandidat ke Ollama gagal.' }
    $Alias = 'jimny-qwen3:small-' + $Id.Substring(7,8)
    $ReportDir = Join-Path $Project 'storage\app\private\jsa\learning\qwen3\qa-reports'
    New-Item -ItemType Directory -Force -Path $ReportDir | Out-Null
    $Report = Join-Path $ReportDir ("qa-balanced-$Id-" + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.json')
    Write-Host '[5/5] Semantic QA (wajib lulus, fail-closed)' -ForegroundColor Cyan
    & python -B scripts/jsa_semantic_gate.py --model $Alias --report $Report
    $QaCode = $LASTEXITCODE
    Write-Host "QA report: $Report"
    if ($QaCode -ne 0) {
        throw ('Semantic QA belum lulus (kode '+$QaCode+'). Kandidat tidak boleh menjadi model utama JSA.')
    }
    Write-Host 'LULUS QA terbatas. Kandidat tetap terpisah dan tidak dipromosikan otomatis.' -ForegroundColor Green
} catch {
    Write-Host ('JSA BALANCED GAGAL: ' + $_.Exception.Message) -ForegroundColor Red
    exit 1
} finally {
    if ($Started) { Stop-Transcript | Out-Null }
}
