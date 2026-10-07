<?php
declare(strict_types=1);
// Administrative recovery for legacy demo accounts or hosts without SMTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$loginId = $argv[1] ?? '';
$password = getenv('CHEMNAMA_ADMIN_RESET_PASSWORD') ?: '';
if ($loginId === '' || strlen($password) < 8 || strlen($password) > 72) {
    fwrite(STDERR, "Set CHEMNAMA_ADMIN_RESET_PASSWORD (8–72 bytes), then run: php scripts/reset-password.php NIP_OR_NIS\n");
    exit(1);
}
require_once __DIR__ . '/../config/bootstrap.php';
$stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE login_id = :login_id');
$stmt->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'login_id' => $loginId]);
if ($stmt->rowCount() !== 1) {
    fwrite(STDERR, "Account not found.\n");
    exit(1);
}
echo "Password changed; previous login sessions are now invalid.\n";
