$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path $PSScriptRoot -Parent
$taskRuntime = Join-Path $taskRoot '.tools/runtime'
$taskPhp = Join-Path $taskRoot '.tools/php/php.exe'
$taskMaria = Get-ChildItem (Join-Path $taskRoot '.tools/mariadb') -Filter mariadbd.exe -Recurse | Select-Object -First 1
if (-not $taskMaria -or -not (Test-Path $taskPhp)) { throw 'Runtime lokal belum tersedia.' }
foreach ($taskRequired in @('config/database.php', 'scripts/local-router.php', '.tools/db-data/my.ini', '.env')) {
    if (-not (Test-Path (Join-Path $taskRoot $taskRequired))) { throw "File $taskRequired tidak tersedia. Periksa karantina antivirus sebelum start." }
}
New-Item -ItemType Directory -Force -Path $taskRuntime | Out-Null
$taskState = @{}
function Test-LocalPort([int] $taskPortNumber) {
    $taskSocket = [System.Net.Sockets.TcpClient]::new()
    try { $taskSocket.Connect('127.0.0.1', $taskPortNumber); return $true }
    catch { return $false }
    finally { $taskSocket.Dispose() }
}
foreach ($taskPort in @(3307, 8000)) {
    if (Test-LocalPort $taskPort) {
        $taskExpected = if ($taskPort -eq 3307) { $taskMaria.FullName } else { $taskPhp }
        $taskProcess = Get-Process | Where-Object { $_.Path -eq $taskExpected } | Select-Object -First 1
        if (-not $taskProcess) { throw "Port $taskPort digunakan aplikasi lain." }
        $taskState["$taskPort"] = $taskProcess.Id
        continue
    }
    if ($taskPort -eq 3307) {
        $taskArguments = '--defaults-file="{0}"' -f (Join-Path $taskRoot '.tools/db-data/my.ini')
        $taskStarted = Start-Process -FilePath $taskMaria.FullName -ArgumentList $taskArguments -WorkingDirectory $taskRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $taskRuntime 'database.log') -RedirectStandardError (Join-Path $taskRuntime 'database-errors.log')
    } else {
        $taskArguments = '-c "{0}" -S 127.0.0.1:8000 -t "{1}" "{2}"' -f (Join-Path $taskRoot '.tools/php/php.ini'), $taskRoot, (Join-Path $PSScriptRoot 'local-router.php')
        $taskStarted = Start-Process -FilePath $taskPhp -ArgumentList $taskArguments -WorkingDirectory $taskRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $taskRuntime 'web.log') -RedirectStandardError (Join-Path $taskRuntime 'web-errors.log')
    }
    $taskState["$taskPort"] = $taskStarted.Id
    $taskReady = $false
    for ($taskIndex = 0; $taskIndex -lt 100; $taskIndex++) {
        Start-Sleep -Milliseconds 100
        if (Test-LocalPort $taskPort) { $taskReady = $true; break }
        if ($taskStarted.HasExited) { throw "Server port $taskPort berhenti. Periksa log di .tools/runtime." }
    }
    if (-not $taskReady) { throw "Server port $taskPort belum siap." }
}
$taskState | ConvertTo-Json | Set-Content (Join-Path $taskRuntime 'processes.json')
Write-Output 'Nom Comp berjalan: http://127.0.0.1:8000'
