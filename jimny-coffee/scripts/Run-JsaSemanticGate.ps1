# JSA Fase 5: semantic quality check for Ollama candidate (no training or POS writes).
param([string]$Model = 'jimny-qwen3:small-a6d71e8f')
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$Project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
Set-Location -LiteralPath $Project
$Gate = Join-Path $PSScriptRoot 'jsa_semantic_gate.py'
if (-not (Test-Path -LiteralPath $Gate)) { throw 'Script semantic gate belum dipasang.' }
$ReportDir = Join-Path $Project 'storage\app\private\jsa\learning\qwen3\qa-reports'
New-Item -ItemType Directory -Force -Path $ReportDir | Out-Null
if ($Model -notmatch '^(jimny-qwen3:adaptive|jimny-qwen3:small-[0-9a-f]{8})$') {
    throw 'Nama model tidak diizinkan. Gunakan jimny-qwen3:adaptive atau alias kandidat small yang valid.'
}
$SafeModel = $Model -replace '[^A-Za-z0-9._-]', '-'
$Stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$Report = Join-Path $ReportDir ("qa-$SafeModel-$Stamp.json")
Write-Host "Menguji model: $Model" -ForegroundColor Cyan
Write-Host "Laporan: $Report" -ForegroundColor DarkGray
& python -B $Gate --model $Model --report $Report
$Status = $LASTEXITCODE
if ($Status -eq 0) {
    Write-Host 'LULUS: semua pemeriksaan terbatas konsisten. BUKAN izin promosi produksi.' -ForegroundColor Green
} elseif ($Status -eq 2) {
    Write-Warning 'TIDAK LULUS: ditemukan jawaban numerik/arah yang tidak konsisten. Kandidat tetap eksperimen.'
} else {
    Write-Warning "Pengujian tidak selesai (kode: $Status). Periksa koneksi lokal Ollama."
}
exit $Status
