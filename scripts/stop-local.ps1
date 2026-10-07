$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path $PSScriptRoot -Parent
$taskStatePath = Join-Path $taskRoot '.tools/runtime/processes.json'
if (-not (Test-Path $taskStatePath)) { Write-Output 'Tidak ada server lokal tercatat.'; exit }
$taskState = Get-Content $taskStatePath -Raw | ConvertFrom-Json
# Stop only the processes created for this workspace.
$taskWeb = Get-Process -Id $taskState.'8000' -ErrorAction SilentlyContinue
if ($taskWeb -and $taskWeb.Path -eq (Join-Path $taskRoot '.tools/php/php.exe')) { Stop-Process -Id $taskWeb.Id }
$taskDb = Get-Process -Id $taskState.'3307' -ErrorAction SilentlyContinue
if ($taskDb -and $taskDb.Path.StartsWith((Join-Path $taskRoot '.tools/mariadb'), [StringComparison]::OrdinalIgnoreCase)) {
    $taskAdmin = Get-ChildItem (Join-Path $taskRoot '.tools/mariadb') -Filter mariadb-admin.exe -Recurse | Select-Object -First 1
    & $taskAdmin.FullName "--defaults-extra-file=$(Join-Path $taskRoot '.tools/admin-client.ini')" '--skip-ssl' shutdown
    if ($LASTEXITCODE -ne 0) { throw 'Database gagal shutdown. Periksa log.' }
}
Write-Output 'Server lokal dihentikan; data database tetap tersimpan.'
