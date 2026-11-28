# Monthly Qwen3-0.6B weight training in a separate sandbox candidate.
# Independent of the existing 03:40 JSA daily refresh task.
param([switch]$ForceTrain,[switch]$Sandbox,[switch]$SkipDeploy,[switch]$AllowSandboxImport,[switch]$AllowStopActiveModels)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$Project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
Set-Location -LiteralPath $Project
$env:PYTHONDONTWRITEBYTECODE = '1'
$LogDir = Join-Path $Project 'storage\logs'
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
$Log = Join-Path $LogDir ('jsa-qwen3-small-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.log')
$Started = $false
try {
    Start-Transcript -LiteralPath $Log | Out-Null
    $Started = $true
    if (-not $ForceTrain -and (Get-Date).Day -ne 1) {
        Write-Host 'Bukan tanggal 1, pelatihan Qwen3 kecil dilewati.'
        return
    }
    if (-not $Sandbox) { throw 'Data JSA belum diverifikasi: jalankan dengan -Sandbox agar hanya menjadi kandidat eksperimen.' }
    $Py = Join-Path $env:LOCALAPPDATA 'JSA-Qwen3-Small-Venv\Scripts\python.exe'
    if (-not (Test-Path -LiteralPath $Py)) { throw 'Environment training kecil belum ada. Jalankan Setup-JsaQwen3Small.ps1.' }

    Write-Host "===== QWEN3 0.6B QLORA SANDBOX =====`nLog: $Log" -ForegroundColor Cyan
    & $Py -B scripts/jsa_qwen3_small.py doctor
    if ($LASTEXITCODE -ne 0) { throw 'GPU doctor gagal. Periksa CUDA, memory atau dependensi.' }
    & $Py -B scripts/jsa_qwen3_learning.py verify
    if ($LASTEXITCODE -ne 0) { throw 'Integrity check examples Fase 3 gagal.' }
    $IdRaw = & $Py -B scripts/jsa_qwen3_small.py identify --sandbox
    if ($LASTEXITCODE -ne 0) { throw 'Manifest contoh tidak siap.' }
    $Info = ($IdRaw | Out-String | ConvertFrom-Json)
    $Id = [string]$Info.run_id
    if (-not $Id.StartsWith('qwen3s_')) { throw 'Run ID tidak valid.' }

    # Never interrupt active JSA inference unless the operator explicitly opts in.
    if ($AllowStopActiveModels) {
        Write-Warning 'Maintenance mode: model Ollama aktif akan di-unload sementara untuk membebaskan VRAM.'
        foreach ($Model in @('jimny-qwen3:adaptive', 'qwen3:4b-instruct')) {
            & ollama stop $Model
            if ($LASTEXITCODE -ne 0) {
                throw ("Gagal menghentikan model " + $Model + ". Training dibatalkan agar status layanan jelas.")
            }
        }
    } else {
        Write-Warning 'Model Ollama aktif tidak dihentikan. Jika VRAM tidak cukup, training akan gagal dengan aman. Untuk maintenance terencana, gunakan -AllowStopActiveModels.'
    }

    Write-Host "`n[TRAIN] Kandidat Qwen3 kecil: $Id" -ForegroundColor Cyan
    & $Py -B scripts/jsa_qwen3_small.py train --sandbox
    if ($LASTEXITCODE -ne 0) { throw 'Training gagal. Model utama tidak berubah.' }
    Write-Host "`n[VERIFY] $Id" -ForegroundColor Cyan
    & $Py -B scripts/jsa_qwen3_small.py verify --run-id $Id
    if ($LASTEXITCODE -ne 0) { throw 'Verifikasi adapter hasil training gagal.' }
    $ManifestPath = Join-Path $Project "storage\app\private\jsa\learning\qwen3\small_runs\$Id\manifest.json"
    $Result = Get-Content -LiteralPath $ManifestPath -Raw | ConvertFrom-Json
    if ($Result.evaluation_pass -isnot [bool] -or -not $Result.evaluation_pass) {
        Write-Warning 'Evaluation loss tidak lulus atau bertipe tidak valid. Kandidat disimpan tanpa impor ke Ollama.'
        return
    }
    if ($Result.source_verified -isnot [bool]) {
        throw 'source_verified pada manifest bukan boolean JSON. Deployment ditolak.'
    }
    if ($SkipDeploy) { Write-Host 'Deployment sengaja dilewati (-SkipDeploy).'; return }
    if (-not $AllowSandboxImport) {
        Write-Warning 'Token-loss bukan bukti kualitas bisnis. Kandidat tetap tersimpan dan tidak diimpor. Impor sandbox hanya setelah review manual dengan -AllowSandboxImport.'
        return
    }
    $Converter = Join-Path $env:LOCALAPPDATA 'JSA-llama.cpp-small\convert_hf_to_gguf.py'
    if (-not (Test-Path -LiteralPath $Converter)) {
        Write-Warning 'Training lulus, converter GGUF belum terpasang. Adapter tetap disimpan.'
        return
    }
    Write-Host '[IMPORT] Mengonversi kandidat Qwen3 0.6B ke Ollama' -ForegroundColor Cyan
    & $Py -B scripts/jsa_qwen3_small.py deploy --run-id $Id --converter $Converter --sandbox
    if ($LASTEXITCODE -ne 0) { throw 'GGUF/Ollama import gagal. Model aktif tidak disentuh.' }
    Write-Host 'Model kandidat kecil siap diuji; JSA produksi tetap memakai qwen3:adaptive.' -ForegroundColor Green
} catch {
    Write-Host ('JSA QWEN3 SMALL GAGAL: ' + $_.Exception.Message) -ForegroundColor Red
    exit 1
} finally {
    if ($Started) { Stop-Transcript | Out-Null }
}
