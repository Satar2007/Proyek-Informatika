# Windows PowerShell 5.1. One orchestrator, sandbox training, no production promotion.
param([switch]$ForceMonthly, [switch]$SkipModelRefresh)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$Project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
Set-Location -LiteralPath $Project
$LogDir = Join-Path $Project 'storage\logs'
New-Item -ItemType Directory -Force -Path $LogDir | Out-Null
$Log = Join-Path $LogDir ('jsa-unified-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.log')
$Lock = Join-Path $Project 'storage\app\private\jsa\learning\.unified.lock'
$lockStream = $null
$transcript = $false
function Invoke-Checked([string]$Exe, [string[]]$Arguments, [string]$Step) {
    Write-Host ('[' + $Step + '] ' + $Exe + ' ' + ($Arguments -join ' ')) -ForegroundColor Cyan
    & $Exe @Arguments
    if ($LASTEXITCODE -ne 0) { throw ("$Step gagal (exit $LASTEXITCODE)") }
}
try {
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Lock) | Out-Null
    try { $lockStream = [System.IO.File]::Open($Lock, [System.IO.FileMode]::OpenOrCreate, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None) }
    catch [System.IO.IOException] { Write-Host 'Proses JSA lain sedang berjalan. Tidak memulai proses kedua.'; exit 0 }
    Start-Transcript -LiteralPath $Log -ErrorAction Stop | Out-Null
    $transcript = $true
    foreach ($cmd in @('php','python','ollama')) { if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) { throw "Dependensi tidak tersedia: $cmd" } }
    foreach ($file in @('scripts\Run-JsaSelfLearning.ps1','scripts\Run-JsaQwen3Balanced.ps1','scripts\jsa_qwen3_learning.py','scripts\jsa_dataset_manager.py')) {
        if (-not (Test-Path -LiteralPath $file)) { throw "Berkas tidak tersedia: $file" }
    }
    # Daily export. This runner only trains the forecast model on day 1; avoid it on day 1
    # by using direct export + dataset manager rather than Run-JsaSelfLearning.ps1.
    $Stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $RelOut = "storage/app/private/jsa/learning/exports/unified-$Stamp"
    $FullOut = Join-Path $Project ($RelOut -replace '/', '\')
    if (Test-Path -LiteralPath $FullOut) { throw 'Folder ekspor sudah ada' }
    Invoke-Checked 'php' @('artisan','jsa:export','--days=365',"--out=$RelOut") 'Ekspor POS'
    $Total = Join-Path $FullOut 'daily_total.csv'
    $Menu = Join-Path $FullOut 'daily_menu.csv'
    if (-not (Test-Path -LiteralPath $Total) -or -not (Test-Path -LiteralPath $Menu)) { throw 'CSV ekspor tidak lengkap' }
    Invoke-Checked 'python' @('-B','scripts/jsa_dataset_manager.py','--total',$Total,'--menu',$Menu,'--days','365','--keep','5') 'Snapshot'
    Invoke-Checked 'python' @('-B','scripts/jsa_dataset_manager.py','--verify') 'Verifikasi snapshot'
    if (-not $SkipModelRefresh) {
        Invoke-Checked 'python' @('-B','scripts/jsa_qwen3_learning.py','refresh','--apply') 'Konteks Qwen3 4B'
        Invoke-Checked 'python' @('-B','scripts/jsa_qwen3_learning.py','prepare') 'Contoh pembelajaran'
        Invoke-Checked 'python' @('-B','scripts/jsa_qwen3_learning.py','verify') 'Verifikasi contoh'
    }
    if ((Get-Date).Day -eq 1 -or $ForceMonthly) {
        # Runner handles content fingerprint, adapter verification, sandbox import and semantic QA.
        # Never updates Laravel production model or the 4B alias.
        Invoke-Checked 'powershell.exe' @('-NoProfile','-NonInteractive','-ExecutionPolicy','Bypass','-File',(Join-Path $PSScriptRoot 'Run-JsaQwen3Balanced.ps1'),'-ForceTrain','-Sandbox') 'QLoRA + sandbox QA'
    } else {
        Write-Host 'QLoRA bulanan dilewati: bukan tanggal 1.'
    }
    Write-Host 'JSA selesai. Tidak ada promosi model produksi.' -ForegroundColor Green
} catch {
    Write-Host ('JSA UNIFIED GAGAL: ' + $_.Exception.Message) -ForegroundColor Red
    exit 1
} finally {
    if ($transcript) { Stop-Transcript | Out-Null }
    if ($null -ne $lockStream) { $lockStream.Dispose() }
}
