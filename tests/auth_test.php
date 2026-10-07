<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/auth.php';

function check(bool $condition, string $description): void
{
    if (!$condition) {
        throw new RuntimeException($description);
    }
}

chemnama_start_session();
$_ENV['CHEMNAMA_TEST_FILE_ENV'] = 'file-setting';
check(chemnama_env('CHEMNAMA_TEST_FILE_ENV') === 'file-setting', 'File configuration works when putenv is unavailable');
putenv('CHEMNAMA_TEST_FILE_ENV=runtime-setting');
check(chemnama_env('CHEMNAMA_TEST_FILE_ENV') === 'runtime-setting', 'Runtime environment overrides file configuration');
putenv('CHEMNAMA_TEST_FILE_ENV');
unset($_ENV['CHEMNAMA_TEST_FILE_ENV']);
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, role TEXT, login_id TEXT, avatar_color TEXT, avatar_path TEXT, password_hash TEXT)');
$insert = $pdo->prepare('INSERT INTO users VALUES (?, ?, ?, ?, ?, ?, NULL, ?)');
$password = bin2hex(random_bytes(16));
$hash = password_hash($password, PASSWORD_DEFAULT);
$insert->execute([1, 'Teacher', 'teacher@example.test', 'guru', '12345678', '#abc', $hash]);
$insert->execute([2, 'Student', 'student@example.test', 'siswa', '12345', '#def', $hash]);
$user = $pdo->query('SELECT * FROM users WHERE id = 1')->fetch();
$beforeId = session_id();
$_SESSION['guru_active_class'] = 'old class';
$beforeToken = chemnama_csrf_token();
chemnama_login_user($user);
check(session_id() !== $beforeId, 'Login must rotate the session ID');
check(!isset($_SESSION['guru_active_class']), 'Login must clear previous account context');
check(!isset($_SESSION['user']['password_hash']), 'Session must not contain password hashes');
check(chemnama_csrf_token() !== $beforeToken, 'Login must rotate CSRF token');
$_POST['csrf_token'] = chemnama_csrf_token();
chemnama_validate_csrf();
$teacherBrowser = $_SESSION;
chemnama_login_user($pdo->query('SELECT * FROM users WHERE id = 2')->fetch());
$studentBrowser = $_SESSION;
check((int) chemnama_current_user()['id'] === 2, 'Student browser must see its own account');
$_SESSION = $teacherBrowser;
check((int) chemnama_current_user()['id'] === 1, 'Teacher browser must retain its own account');
$pdo->exec("UPDATE users SET role = 'siswa' WHERE id = 1");
check(chemnama_current_user()['role'] === 'siswa', 'Role changes must be read from database');
$update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = 1');
$update->execute([password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT)]);
check(chemnama_current_user() === null, 'Password reset must revoke the old session');
$_SESSION = $studentBrowser;
check((int) chemnama_current_user()['id'] === 2, 'Reset must not revoke another account');
$_SESSION['auth_last_activity'] = time() - 7201;
check(chemnama_current_user() === null, 'Inactive session must expire');
$_SESSION = $studentBrowser;
$pdo->exec('DELETE FROM users WHERE id = 2');
check(chemnama_current_user() === null, 'Deleted account must lose access');
putenv('CHEMNAMA_GURU_ACCESS_CODE');
putenv('CHEMNAMA_SISWA_ACCESS_CODE');
check(chemnama_role_access_code('guru') === '', 'Teacher invitation must not have a built-in secret');
check(chemnama_role_access_code('siswa') === '', 'Student invitation must not have a built-in secret');
check(password_verify($password, $hash), 'Stored hashes must verify the account password');
session_destroy();
echo "PASS: 16 authentication checks (SQLite fixture; MySQL throttle requires integration testing)\n";
