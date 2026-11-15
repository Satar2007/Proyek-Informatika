# Install only after manually testing Run-JsaQwen3Balanced.ps1.
# Existing old monthly task must be disabled first to avoid duplicate GPU training.
param([switch]$AllowSandbox)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
if (-not $AllowSandbox) { throw 'Training dari dataset belum terverifikasi hanya dapat dipasang dengan -AllowSandbox.' }
$Old = Get-ScheduledTask -TaskName 'JSA-Qwen3-Small-FineTune' -ErrorAction SilentlyContinue
if ($Old -and $Old.State -ne 'Disabled') {
    throw 'Task training lama masih aktif. Nonaktifkan dulu: Disable-ScheduledTask -TaskName "JSA-Qwen3-Small-FineTune"'
}
$TaskName = 'JSA-Qwen3-Balanced-FineTune'
if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) {
    throw "Task $TaskName sudah ada; tidak akan ditimpa."
}
$Project = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$Runner = Join-Path $PSScriptRoot 'Run-JsaQwen3Balanced.ps1'
if (-not (Test-Path -LiteralPath $Runner)) { throw 'Runner belum tersedia' }
$CmdArgs = '-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "' + $Runner + '" -Sandbox -SkipDeploy'
$Action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $CmdArgs -WorkingDirectory $Project
$Trigger = New-ScheduledTaskTrigger -Daily -At '05:10'
$Settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Hours 8) -StartWhenAvailable
$User = "$env:USERDOMAIN\$env:USERNAME"
$Principal = New-ScheduledTaskPrincipal -UserId $User -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $Trigger -Settings $Settings -Principal $Principal -Description 'JSA Qwen3 balanced QLoRA sandbox; train adapter day 1, sandbox, manual QA before import' | Out-Null
Get-ScheduledTask -TaskName $TaskName | Select-Object TaskName, State
Write-Host 'Terpasang: cek 05.10 setiap hari; training hanya tanggal 1. Model utama tetap 4B.' -ForegroundColor Green
