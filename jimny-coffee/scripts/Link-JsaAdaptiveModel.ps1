# Conservative opt-in: change only literal qwen3:4b-instruct fallback strings in two JSA controllers.
# Backup outside Git worktree; PHP syntax check with rollback. Does not change Laravel route.
param([switch]$Apply)
$ErrorActionPreference='Stop'
Set-StrictMode -Version Latest
$Project=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
Set-Location -LiteralPath $Project
$Base='qwen3:4b-instruct'; $Alias='jimny-qwen3:adaptive'
$Controllers=@(
    'app\Http\Controllers\JimnyLocalAiController.php',
    'app\Http\Controllers\JimnyOwnerV20Controller.php'
)
$ModelShow = & ollama show $Alias 2>&1
if ($LASTEXITCODE -ne 0) { throw "Model $Alias belum tersedia di Ollama; jalankan runner Qwen3 dulu." }
$Found=@()
foreach ($File in $Controllers) {
    if (-not (Test-Path -LiteralPath $File)) { continue }
    $Text=[System.IO.File]::ReadAllText((Join-Path $Project $File))
    if ($Text.Contains($Base)) {
        Write-Host "Akan ubah literal model dalam: $File" -ForegroundColor Yellow
        $Found += $File
    }
}
if ($Found.Count -eq 0) {
    throw 'Tidak ditemukan literal model yang aman diganti. Controller lokal mungkin memakai konfigurasi lain; tidak mengubah source code.'
}
if (-not $Apply) {
    Write-Host 'PREVIEW SAJA. Untuk terapkan, jalankan: .\scripts\Link-JsaAdaptiveModel.ps1 -Apply' -ForegroundColor Cyan
    exit 0
}
$BackupDir=Join-Path $env:LOCALAPPDATA ('JSA-Controller-Backup-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
$Backups=@{}
try {
    $UTF8= [System.Text.UTF8Encoding]::new($false)
    foreach ($File in $Found) {
        $Src=Join-Path $Project $File
        $Backup=Join-Path $BackupDir ([System.IO.Path]::GetFileName($File))
        Copy-Item -LiteralPath $Src -Destination $Backup -ErrorAction Stop
        $Backups[$Src]=$Backup
        $Text=[System.IO.File]::ReadAllText($Src)
        # Replace only exact quoted model-name literals.
        $SingleQuotedBase = "'" + $Base + "'"
        $DoubleQuotedBase = '"' + $Base + '"'

        $NewText = $Text.Replace(
            $SingleQuotedBase,
            "'" + $Alias + "'"
        ).Replace(
            $DoubleQuotedBase,
            '"' + $Alias + '"'
        )

        if ($NewText -eq $Text) {
            throw "Tidak ada literal konfigurasi model yang cocok pada $File"
        }
        [System.IO.File]::WriteAllText($Src,$NewText,$UTF8)
        & php -l $Src
        if ($LASTEXITCODE -ne 0) { throw "PHP syntax check gagal: $File" }
    }
} catch {
    foreach ($Src in $Backups.Keys) { Copy-Item -LiteralPath $Backups[$Src] -Destination $Src -Force }
    throw
}
Write-Host 'Literal fallback JSA diarahkan ke jimny-qwen3:adaptive. Controller lama dicadangkan.' -ForegroundColor Green
Write-Host "Backup: $BackupDir"
Write-Host 'PENTING: jika controller memakai environment/config model lain, perubahan literal ini mungkin belum mengalihkan request.'
