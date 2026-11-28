# Installs a second, isolated monthly Qwen3-0.6B LoRA sandbox trainer at 05:10.
# Existing task JSA-Qwen3-AutoLearning at 03:40 remains untouched.
param([string]$TaskName = 'JSA-Qwen3-Small-FineTune', [switch]$AllowSandbox)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
if (-not $AllowSandbox) { throw 'Eksperimen memakai data asal belum diverifikasi. Jalankan dengan -AllowSandbox untuk menyetujuinya.' }
$Project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$Py = Join-Path $env:LOCALAPPDATA 'JSA-Qwen3-Small-Venv\Scripts\python.exe'
if (-not (Test-Path -LiteralPath $Py)) { throw 'Setup-JsaQwen3Small.ps1 belum berhasil.' }
$Runner = Join-Path $PSScriptRoot 'Run-JsaQwen3Small.ps1'
if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) {
    throw "Task $TaskName sudah ada, tidak ditimpa."
}
$Args = '-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "' + $Runner + '" -Sandbox'
$Action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $Args -WorkingDirectory $Project
$Trigger = New-ScheduledTaskTrigger -Daily -At '05:10'
$Settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Hours 8) -StartWhenAvailable
$User = "$env:USERDOMAIN\$env:USERNAME"
$Principal = New-ScheduledTaskPrincipal -UserId $User -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $Trigger -Settings $Settings -Principal $Principal -Description 'JSA small Qwen3 LoRA sandbox; runner only trains on day 1 of each month' | Out-Null
Write-Host "Task $TaskName dipasang: pemeriksaan harian 05:10, training hanya tanggal 1." -ForegroundColor Green
Get-ScheduledTask -TaskName $TaskName | Select-Object TaskName, State
