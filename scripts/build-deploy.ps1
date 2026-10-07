$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path $PSScriptRoot -Parent
$taskOutput = Join-Path $taskRoot ('.tools/deploy-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
$taskWebsite = Join-Path $taskOutput 'website'
New-Item -ItemType Directory -Path $taskWebsite -Force | Out-Null
Get-ChildItem -LiteralPath $taskRoot -File -Filter '*.php' | Where-Object { $_.Name -ne 'migrate.php' -and $_.Name -notlike '*.backup.php' } | ForEach-Object {
    Copy-Item -LiteralPath $_.FullName -Destination $taskWebsite
}
foreach ($taskFolder in @('app','config','assets','vendor','storage')) {
    Get-ChildItem -LiteralPath (Join-Path $taskRoot $taskFolder) -File -Recurse | Where-Object { $_.FullName -notmatch '\\.git(?:\\|$)' -and $_.Name -notlike '.env*' -and $_.Extension -notin @('.log','.md','.sql','.sqlite','.sqlite3','.db','.bak') -and $_.Name -ne 'README.txt' } | ForEach-Object {
        $taskRelative = $_.FullName.Substring($taskRoot.Length + 1)
        $taskTarget = Join-Path $taskWebsite $taskRelative
        New-Item -ItemType Directory -Path (Split-Path $taskTarget -Parent) -Force | Out-Null
        Copy-Item -LiteralPath $_.FullName -Destination $taskTarget
    }
}
New-Item -ItemType Directory -Path (Join-Path $taskWebsite 'database') -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $taskRoot 'database/schema.sql') -Destination (Join-Path $taskWebsite 'database/schema.sql')
Copy-Item -LiteralPath (Join-Path $taskRoot '.htaccess') -Destination $taskWebsite
Copy-Item -LiteralPath (Join-Path $taskRoot '.env.example') -Destination $taskOutput
$taskDump = Get-ChildItem (Join-Path $taskRoot '.tools/mariadb') -Filter mariadb-dump.exe -Recurse | Select-Object -First 1
& $taskDump.FullName "--defaults-extra-file=$(Join-Path $taskRoot '.tools/admin-client.ini')" '--skip-ssl' '--single-transaction' '--skip-add-locks' '--no-tablespaces' '--ignore-table=nom_comp_local.auth_attempts' "--result-file=$(Join-Path $taskOutput 'database-import.sql')" 'nom_comp_local'
if ($LASTEXITCODE -ne 0) { throw 'Database export failed' }
$taskSqlPath = Join-Path $taskOutput 'database-import.sql'
$taskSqlText = [IO.File]::ReadAllText($taskSqlPath)
# MariaDB client directives are not understood by older phpMyAdmin/MySQL hosts.
$taskSqlText = $taskSqlText -replace '(?m)^/\*M!999999.*\r?\n','' -replace '(?m)^/\*M!100616.*\r?\n',''
[IO.File]::WriteAllText($taskSqlPath, $taskSqlText, [Text.UTF8Encoding]::new($false))
# Keep database exports and environment values OUTSIDE the public website archive.
Compress-Archive -Path (Join-Path $taskWebsite '*') -DestinationPath (Join-Path $taskOutput 'website.zip')
$taskArchive = [System.IO.Compression.ZipFile]::OpenRead((Join-Path $taskOutput 'website.zip'))
try {
    $taskNames = @($taskArchive.Entries | ForEach-Object FullName)
    if ($taskNames | Where-Object { $_ -match '(^|/)(\.env|\.tools|\.git)(/|$)|database-import\.sql|backup' }) { throw 'Private file entered website archive' }
    if ($taskNames -notcontains '.htaccess') { throw '.htaccess missing from deployment archive' }
} finally { $taskArchive.Dispose() }
Write-Output "Deployment package: $taskOutput"
Write-Output 'website.zip is public application code; database-import.sql is PRIVATE and must be imported through phpMyAdmin.'
