# JSA Self-Learning runner. PowerShell 5.1. Jalankan dari scripts/ atau Scheduler.
# Ekspor harian, refresh rolling dataset, training 1x/bulan (atau -ForceTrain).
# TIDAK menyentuh stok, transaksi, migrations, atau bobot Ollama.
param([switch]$ForceTrain)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
$Project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot "..")).Path
Set-Location -LiteralPath $Project

$Stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$LogDir = Join-Path $Project "storage\logs"
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
$Log = Join-Path $LogDir "jsa-learning-$Stamp.log"
$TranscriptStarted = $false
try {
    Start-Transcript -Path $Log -ErrorAction Stop | Out-Null
    $TranscriptStarted = $true
    Write-Host "===== JSA SELF LEARNING =====" -ForegroundColor Cyan
    Write-Host "Project: $Project"
    Write-Host "Log: $Log"
    if (-not (Get-Command php -ErrorAction SilentlyContinue)) { throw "php tidak ditemukan pada PATH" }
    if (-not (Get-Command python -ErrorAction SilentlyContinue)) { throw "python tidak ditemukan pada PATH" }

    $RelativeOut = "storage/app/private/jsa/learning/exports/export-$Stamp"
    $FullOut = Join-Path $Project ($RelativeOut -replace '/', '\')
    if (Test-Path -LiteralPath $FullOut) {
        throw "Folder ekspor sudah ada: $FullOut"
    }

    Write-Host "`n[1/3] Export data POS ke folder terpisah" -ForegroundColor Cyan
    & php artisan jsa:export --days=365 "--out=$RelativeOut"
    if ($LASTEXITCODE -ne 0) { throw "php artisan jsa:export gagal" }

    $Total = Join-Path $FullOut "daily_total.csv"
    $Menu = Join-Path $FullOut "daily_menu.csv"
    if (-not (Test-Path -LiteralPath $Total) -or -not (Test-Path -LiteralPath $Menu)) {
        throw "File CSV export tidak lengkap"
    }
    Write-Host "`n[2/3] Build verified rolling dataset 365 hari" -ForegroundColor Cyan
    & python -B scripts/jsa_dataset_manager.py --total $Total --menu $Menu --days 365 --keep 5
    if ($LASTEXITCODE -ne 0) { throw "Dataset Manager gagal" }

    # Setiap tanggal 1: training. Manual -ForceTrain: training langsung sekarang.
    $IsTrainDay = ((Get-Date).Day -eq 1)
    if ($ForceTrain -or $IsTrainDay) {
        Write-Host "`n[3/3] AUTO TRAIN MODEL FORECASTING" -ForegroundColor Cyan
        & python -B scripts/jsa_auto_train.py
        if ($LASTEXITCODE -ne 0) { throw "Auto Trainer gagal; model lama tidak diubah" }
    } else {
        Write-Host "`n[3/3] Training dijadwalkan setiap tanggal 1 (gunakan -ForceTrain untuk training sekarang)" -ForegroundColor DarkYellow
    }

    # Cleanup terbatas hanya folder hasil EKSPOR yang dibuat wrapper ini.
    $ExportParent = Join-Path $Project "storage\app\private\jsa\learning\exports"
    $Managed = @(Get-ChildItem -LiteralPath $ExportParent -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match '^export-\d{8}-\d{6}$' } |
        Sort-Object Name -Descending)
    $ExportParentResolved = (Resolve-Path -LiteralPath $ExportParent -ErrorAction Stop).Path.TrimEnd('\')
    foreach ($Old in ($Managed | Select-Object -Skip 3)) {
        $Item = Get-Item -LiteralPath $Old.FullName -Force -ErrorAction Stop
        if (($Item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw "Cleanup ditolak: target merupakan symlink/junction: $($Item.FullName)"
        }
        $ResolvedOld = (Resolve-Path -LiteralPath $Item.FullName -ErrorAction Stop).Path.TrimEnd('\')
        $ResolvedParent = Split-Path -Parent $ResolvedOld
        if ($ResolvedParent -ine $ExportParentResolved -or $Item.Name -notmatch '^export-\d{8}-\d{6}$') {
            throw "Cleanup ditolak: target berada di luar direktori ekspor terkelola: $ResolvedOld"
        }
        Remove-Item -LiteralPath $ResolvedOld -Recurse -Force -ErrorAction Stop
    }
    Write-Host "`nBERHASIL: Dataset diperbarui; kandidat training disimpan terpisah." -ForegroundColor Green
    Write-Host "Catatan: model kandidat BELUM menggantikan forecast POS dan Ollama belum fine-tuned."
} catch {
    Write-Error "JSA SELF LEARNING GAGAL: $($_.Exception.Message)"
    throw
} finally {
    if ($TranscriptStarted) { Stop-Transcript | Out-Null }
}
