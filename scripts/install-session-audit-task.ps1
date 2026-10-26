$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$artisanPath = Join-Path $projectRoot 'artisan'
$phpPath = 'C:\xampp\php\php-win.exe'
$legacyPhpPath = 'C:\xampp\php\php.exe'
if (!(Test-Path -LiteralPath $artisanPath) -or !(Test-Path -LiteralPath $phpPath)) {
    throw 'PHP XAMPP atau artisan proyek tidak ditemukan.'
}

$taskName = 'RS-Vina-Accounting-SessionAudit'
$taskArguments = '"' + $artisanPath + '" sessions:expire-log'
$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existingTask) {
    if ($existingTask.Actions.Count -ne 1 -or $existingTask.Actions.Execute -notin @($phpPath, $legacyPhpPath) -or $existingTask.Actions.Arguments -ne $taskArguments) {
        throw 'Nama tugas sudah dipakai oleh konfigurasi lain; konfigurasi tersebut tidak diubah.'
    }
    # Task Scheduler's Hidden flag does not hide console applications. php-win has no console.
    $silentAction = New-ScheduledTaskAction -Execute $phpPath -Argument $taskArguments -WorkingDirectory $projectRoot
    Set-ScheduledTask -TaskName $taskName -Action $silentAction | Out-Null
    Start-ScheduledTask -TaskName $taskName
    Write-Output 'Tugas pemeriksaan sesi menggunakan php-win.exe tanpa jendela terminal.'
    exit 0
}

$action = New-ScheduledTaskAction -Execute $phpPath -Argument $taskArguments -WorkingDirectory $projectRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -Hidden -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 1) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$currentAccount = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$principal = New-ScheduledTaskPrincipal -UserId $currentAccount -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description 'Mencatat logout otomatis sesi accounting yang kedaluwarsa setiap menit selama akun Windows ini masuk.' | Out-Null
Start-ScheduledTask -TaskName $taskName
Get-ScheduledTask -TaskName $taskName | Select-Object TaskName, State
