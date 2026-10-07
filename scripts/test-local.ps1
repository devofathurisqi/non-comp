$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path $PSScriptRoot -Parent
$taskSecrets = Get-Content (Join-Path $taskRoot '.tools/local-secrets.private') -Raw | ConvertFrom-Json
$taskBase = 'http://127.0.0.1:8000'
$taskPassed = 0
function Assert-Local([bool] $taskCondition, [string] $taskDescription) {
    if (-not $taskCondition) { throw $taskDescription }
    $script:taskPassed++
}
function Get-LocalCsrf([string] $taskHtml) {
    $taskMatch = [regex]::Match($taskHtml, 'name="csrf_token" value="([a-f0-9]+)"')
    if (-not $taskMatch.Success) { throw 'CSRF token missing' }
    return $taskMatch.Groups[1].Value
}
$taskHome = Invoke-WebRequest "$taskBase/" -SkipHttpErrorCheck
Assert-Local ($taskHome.StatusCode -eq 200) 'Homepage failed'
foreach ($taskPrivatePath in @('/.env','/.tools/AKUN-LOKAL.md','/config/database.php','/database/schema.sql','/scripts/setup-local.php','/siswa_materi_detail.backup.php')) {
    $taskResponse = Invoke-WebRequest "$taskBase$taskPrivatePath" -SkipHttpErrorCheck
    Assert-Local ($taskResponse.StatusCode -in @(403,404)) "Private file exposed: $taskPrivatePath"
}
$taskBrowsers = @{}
foreach ($taskAccount in $taskSecrets.accounts) {
    $taskBrowser = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
    $taskLogin = Invoke-WebRequest "$taskBase/login.php?role=$($taskAccount.role)" -WebSession $taskBrowser
    $taskOldSession = $taskBrowser.Cookies.GetCookies([uri]$taskBase)['PHPSESSID'].Value
    $taskBody = @{ csrf_token=Get-LocalCsrf $taskLogin.Content; role=$taskAccount.role; login_id=$taskAccount.login_id; password=$taskAccount.password }
    $taskResponse = Invoke-WebRequest "$taskBase/login.php" -Method Post -Body $taskBody -WebSession $taskBrowser -SkipHttpErrorCheck
    Assert-Local ($taskResponse.StatusCode -eq 200 -and $taskResponse.Content -notmatch '<h1>Masuk</h1>') "Login failed for $($taskAccount.role)"
    $taskCookie = $taskBrowser.Cookies.GetCookies([uri]$taskBase)['PHPSESSID']
    Assert-Local ($taskCookie.Value -ne $taskOldSession -and $taskCookie.HttpOnly) 'Session ID was not rotated or cookie is not HttpOnly'
    $taskBrowsers[$taskAccount.role] = $taskBrowser
    $taskPaths = if ($taskAccount.role -eq 'guru') {
        @('/guru_pilih_kelas.php','/guru_materi.php','/guru_soal_pg.php','/guru_essay.php','/guru_homework.php','/guru_forum.php','/guru_profil.php','/guru_data_siswa.php','/api_get_module_leaderboard.php')
    } else {
        @('/dashboard.php','/siswa_materi.php','/siswa_quiz.php','/siswa_essay.php','/siswa_homework.php','/siswa_forum.php','/siswa_profil.php','/siswa_games.php')
    }
    foreach ($taskPath in $taskPaths) {
        $taskResponse = Invoke-WebRequest "$taskBase$taskPath" -WebSession $taskBrowser -SkipHttpErrorCheck
        Assert-Local ($taskResponse.StatusCode -in @(200,400)) "Page failed: $taskPath ($($taskResponse.StatusCode))"
    }
    $taskViewer = Invoke-WebRequest "$taskBase/phet_viewer.php" -WebSession $taskBrowser
    Assert-Local ($taskViewer.Content -match '<iframe src="https://phet.colorado.edu/sims/html/') 'PhET viewer missing'
}
$taskDenied = Invoke-WebRequest "$taskBase/guru_materi.php" -WebSession $taskBrowsers.siswa -SkipHttpErrorCheck
Assert-Local ($taskDenied.StatusCode -eq 403 -or ($taskDenied.StatusCode -eq 200 -and $taskDenied.Content -notmatch 'Kelola Materi')) 'Student accessed a teacher page'
$taskResponse = Invoke-WebRequest "$taskBase/login.php" -Method Post -Body @{ csrf_token='invalid'; role='siswa'; login_id='12345'; password='invalid' } -SkipHttpErrorCheck
Assert-Local ($taskResponse.StatusCode -eq 419) 'Invalid CSRF was accepted'
$taskRegisterBrowser = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
$taskRegister = Invoke-WebRequest "$taskBase/register.php" -WebSession $taskRegisterBrowser
$taskRegistrationId = "$(Get-Random -Minimum 100000000 -Maximum 999999999)"
$taskNewPassword = [guid]::NewGuid().ToString('N')
$taskBody = @{ csrf_token=Get-LocalCsrf $taskRegister.Content; role='siswa'; name='Siswa Uji Registrasi'; login_id=$taskRegistrationId; email="uji-$taskRegistrationId@example.test"; password=$taskNewPassword; kelas='X IPA A'; role_code='' }
$taskResult = Invoke-WebRequest "$taskBase/register.php" -Method Post -Body $taskBody -WebSession $taskRegisterBrowser
Assert-Local ($taskResult.Content -match 'Akun berhasil dibuat') 'Database registration failed'
$taskLogin = Invoke-WebRequest "$taskBase/login.php" -WebSession $taskRegisterBrowser
$taskResult = Invoke-WebRequest "$taskBase/login.php" -Method Post -Body @{csrf_token=Get-LocalCsrf $taskLogin.Content;role='siswa';login_id=$taskRegistrationId;password=$taskNewPassword} -WebSession $taskRegisterBrowser
Assert-Local ($taskResult.Content -notmatch '<h1>Masuk</h1>') 'New account login failed'
Invoke-WebRequest "$taskBase/logout.php" -WebSession $taskBrowsers.siswa | Out-Null
$taskTeacher = Invoke-WebRequest "$taskBase/guru_materi.php" -WebSession $taskBrowsers.guru
Assert-Local ($taskTeacher.Content -match 'Kelola Materi') 'Student logout affected teacher browser'
$taskLoggedOut = Invoke-WebRequest "$taskBase/siswa_materi.php" -WebSession $taskBrowsers.siswa
Assert-Local ($taskLoggedOut.Content -match '<h1>Masuk</h1>') 'Logout did not revoke student access'
Write-Output "PASS: $taskPassed local HTTP checks using MariaDB and separate browser sessions."
