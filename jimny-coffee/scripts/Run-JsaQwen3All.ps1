# PowerShell 5.1: update POS-derived dataset, refresh Ollama aggregate context,
# generate instruction pairs; run actual Qwen3 LoRA on day 1 if CUDA is ready.
# Does not migrate DB, mutate stock/transactions, or replace original Ollama model.
param(
    [switch]$SkipExport,
    [switch]$ForceFineTune,
    [switch]$ForceForecast
)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$Project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
Set-Location -LiteralPath $Project
$env:PYTHONDONTWRITEBYTECODE = '1'

$LogDir = Join-Path $Project 'storage\logs'
New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
$Stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$Log = Join-Path $LogDir ('jsa-qwen3-' + $Stamp + '.log')
$TranscriptStarted = $false
try {
    Start-Transcript -LiteralPath $Log -ErrorAction Stop | Out-Null
    $TranscriptStarted = $true
    Write-Host '===== JSA QWEN3 ADAPTIVE LEARNING =====' -ForegroundColor Cyan
    Write-Host "Project: $Project"
    Write-Host "Log: $Log"

    if (-not (Get-Command python -ErrorAction SilentlyContinue)) { throw 'Python tidak tersedia di PATH' }
    if (-not (Get-Command ollama -ErrorAction SilentlyContinue)) { throw 'Ollama tidak tersedia di PATH' }
    if (-not $SkipExport) {
        $OldRunner = Join-Path $PSScriptRoot 'Run-JsaSelfLearning.ps1'
        if (-not (Test-Path -LiteralPath $OldRunner)) { throw 'Runner Fase 2 belum terpasang' }
        Write-Host '[1/4] Ekspor dan bangun rolling dataset baru' -ForegroundColor Cyan
        $Args = @('-NoProfile','-NonInteractive','-ExecutionPolicy','Bypass','-File',$OldRunner)
        if ($ForceForecast) { $Args += '-ForceTrain' }
        & powershell.exe @Args
        if ($LASTEXITCODE -ne 0) { throw 'Fase 2 gagal, pembaruan Ollama dibatalkan' }
    } else {
        Write-Host '[1/4] Menggunakan dataset Fase 2 yang sudah ada' -ForegroundColor DarkYellow
    }

    Write-Host '[2/4] Update Ollama Qwen3 menggunakan ringkasan harian (bukan fine-tuning)' -ForegroundColor Cyan
    & python -B scripts/jsa_qwen3_learning.py refresh --apply
    if ($LASTEXITCODE -ne 0) { throw 'Ollama refresh gagal; model lama tetap tersedia' }

    Write-Host '[3/4] Bangun training dataset dari agregat yang diverifikasi hash' -ForegroundColor Cyan
    & python -B scripts/jsa_qwen3_learning.py prepare
    if ($LASTEXITCODE -ne 0) { throw 'Menyiapkan contoh Qwen3 gagal' }
    & python -B scripts/jsa_qwen3_learning.py verify
    if ($LASTEXITCODE -ne 0) { throw 'Verifikasi data Qwen3 gagal' }

    Write-Host '[4/4] Evaluasi jadwal fine-tuning LoRA Qwen3' -ForegroundColor Cyan
    $TrainDue = ((Get-Date).Day -eq 1) -or $ForceFineTune
    if ($TrainDue) {
        $FtPython = Join-Path $env:LOCALAPPDATA 'JSA-Qwen3-Venv\Scripts\python.exe'
        if (-not (Test-Path -LiteralPath $FtPython)) {
            if ($ForceFineTune) { throw 'Virtualenv Qwen3 belum ada. Jalankan Setup-JsaQwen3Gpu.ps1 dahulu.' }
            Write-Warning 'Fine-tuning Qwen3 dilewati: environment GPU belum diinstal.'
        } else {
            $DoctorRaw = & $FtPython -B scripts/jsa_qwen3_lora.py doctor
            if ($LASTEXITCODE -ne 0) { throw 'GPU diagnostic gagal' }
            $Doctor = ($DoctorRaw | Out-String | ConvertFrom-Json)
            if (-not $Doctor.ready) {
                if ($ForceFineTune) { throw ("GPU belum siap: " + $Doctor.reason) }
                Write-Warning ("Fine-tuning Qwen3 dilewati: " + $Doctor.reason)
            } else {
                Write-Host 'Menjalankan fine-tuning LoRA Qwen3 yang sesungguhnya...' -ForegroundColor Cyan
                $TrainRaw = & $FtPython -B scripts/jsa_qwen3_lora.py train --sandbox
                if ($LASTEXITCODE -ne 0) { throw 'Training LoRA gagal; tidak ada promosi model' }
                $Trained = ($TrainRaw | Out-String | ConvertFrom-Json)
                Write-Host ("Kandidat LoRA: " + $Trained.run_id + '; evaluation_pass=' + $Trained.evaluation_pass)
                if ($Trained.evaluation_pass) {
                    $LlamaCpp = Join-Path $env:LOCALAPPDATA 'JSA-llama.cpp'
                    if (Test-Path -LiteralPath (Join-Path $LlamaCpp 'convert_hf_to_gguf.py')) {
                        Write-Host 'Merge dan impor kandidat LoRA ke Ollama (tidak mengubah model JSA aktif)...' -ForegroundColor Cyan
                        & $FtPython -B scripts/jsa_qwen3_deploy.py --run-id $Trained.run_id --llama-cpp-dir $LlamaCpp --sandbox
                        if ($LASTEXITCODE -ne 0) { throw 'Impor kandidat GGUF ke Ollama gagal; alias aktif tidak diubah' }
                    } else {
                        Write-Warning 'LoRA lulus evaluasi tetapi konverter llama.cpp belum terpasang; adapter tetap disimpan.'
                    }
                } else {
                    Write-Warning 'Evaluasi LoRA tidak membaik >=1%; tidak ada impor ke Ollama.'
                }
            }
        }
    } else {
        Write-Host 'Fine-tuning LoRA dijadwalkan tanggal 1 tiap bulan. -ForceFineTune untuk uji manual.' -ForegroundColor DarkYellow
    }

    Write-Host 'SELESAI: model jimny-qwen3:adaptive memakai agregat terbaru.' -ForegroundColor Green
    Write-Host 'Bobot Qwen3 asli tidak berubah; LoRA kandidat hanya apabila GPU, data, dan evaluasi cukup.'
} catch {
    Write-Host ("JSA QWEN3 GAGAL: " + $_.Exception.Message) -ForegroundColor Red
    exit 1
} finally {
    if ($TranscriptStarted) { Stop-Transcript | Out-Null }
}
