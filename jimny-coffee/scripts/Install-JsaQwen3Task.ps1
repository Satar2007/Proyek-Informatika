# Installs automatic JSA pipeline at 03:40, interactive current user, no admin required.
param([string]$TaskName='JSA-Qwen3-AutoLearning')
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$Script = Join-Path $PSScriptRoot 'Run-JsaQwen3All.ps1'
if (-not (Test-Path -LiteralPath $Script)) { throw 'Runner Qwen3 tidak ditemukan' }
if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) { throw "Task '$TaskName' sudah ada, tidak ditimpa" }
$OldTask=Get-ScheduledTask -TaskName 'JSA-SelfLearning-Daily' -ErrorAction SilentlyContinue
if ($OldTask -and $OldTask.State -ne 'Disabled') {
    throw 'Task Fase 2 JSA-SelfLearning-Daily masih aktif. Nonaktifkan secara sadar sebelum memasang task gabungan agar tidak dobel ekspor.'
}
$Args = '-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "' + $Script + '"'
$Action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $Args -WorkingDirectory ((Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path)
$Trigger = New-ScheduledTaskTrigger -Daily -At '03:40'
$Settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Hours 8) -StartWhenAvailable
$User = "$env:USERDOMAIN\$env:USERNAME"
$Principal = New-ScheduledTaskPrincipal -UserId $User -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $TaskName -Action $Action -Trigger $Trigger -Settings $Settings -Principal $Principal -Description 'JSA daily data + Ollama contextual memory, monthly sandbox Qwen3 LoRA if CUDA is available' | Out-Null
Write-Host "Task terpasang: $TaskName. Pukul 03:40 (waktu Windows), user harus log-in dan MySQL hidup." -ForegroundColor Green
Get-ScheduledTask -TaskName $TaskName | Select-Object TaskName,State
