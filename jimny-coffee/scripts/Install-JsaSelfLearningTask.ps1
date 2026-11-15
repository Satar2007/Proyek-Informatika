# Harus dieksekusi sengaja oleh pemilik; tidak otomatis memasang scheduled task.
# Windows PowerShell 5.1 dan modul ScheduledTasks.
param([string]$TaskName = "JSA-SelfLearning-Daily")
$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest
$Runner = Join-Path $PSScriptRoot 'Run-JsaSelfLearning.ps1'
if (-not (Test-Path -LiteralPath $Runner)) { throw "Runner tidak ditemukan: $Runner" }
if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) {
    throw "Scheduled Task '$TaskName' sudah ada. Tidak ditimpa."
}
$Arguments = '-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "' + $Runner + '"'
$Action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument $Arguments -WorkingDirectory (Split-Path $PSScriptRoot -Parent)
$Trigger = New-ScheduledTaskTrigger -Daily -At '03:10'
$Settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -StartWhenAvailable
$UserId = "$env:USERDOMAIN\$env:USERNAME"
$Principal = New-ScheduledTaskPrincipal -UserId $UserId -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $Trigger -Settings $Settings -Principal $Principal -Description 'JSA daily dataset + monthly ML candidate training. No POS/stock writes.' | Out-Null
Write-Host "Task dibuat: $TaskName. Harian 03:10 waktu lokal, saat user log-in. Training pada tanggal 1." -ForegroundColor Green
Get-ScheduledTask -TaskName $TaskName | Select-Object TaskName, State
