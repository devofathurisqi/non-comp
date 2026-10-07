<?php
declare(strict_types=1);

function chemnama_csrf_token(): string
{
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function chemnama_validate_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(chemnama_csrf_token(), $token)) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Muat ulang halaman dan coba kembali.');
    }
}

// Shared database counters prevent bypassing limits by switching browsers.
function chemnama_auth_attempt(PDO $pdo, string $action, string $identifier): bool
{
    $keys = [
        hash('sha256', $action . ':account:' . $identifier),
        hash('sha256', $action . ':ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli')),
    ];
    $stmt = $pdo->prepare('INSERT INTO auth_attempts (attempt_key, attempts, window_start)
        VALUES (:key, 1, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE
        attempts = IF(window_start < UTC_TIMESTAMP() - INTERVAL 15 MINUTE, 1, attempts + 1),
        window_start = IF(window_start < UTC_TIMESTAMP() - INTERVAL 15 MINUTE, UTC_TIMESTAMP(), window_start)');
    $read = $pdo->prepare('SELECT attempts FROM auth_attempts WHERE attempt_key = :key');
    $allowed = true;
    foreach ($keys as $index => $key) {
        $stmt->execute(['key' => $key]);
        $read->execute(['key' => $key]);
        if ((int) $read->fetchColumn() > ($index === 0 ? 10 : 60)) {
            $allowed = false;
        }
    }
    $pdo->exec('DELETE FROM auth_attempts WHERE window_start < UTC_TIMESTAMP() - INTERVAL 1 DAY');
    return $allowed;
}

function chemnama_login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION = [
        'user' => array_intersect_key($user, array_flip(['id', 'name', 'email', 'role', 'avatar_color', 'avatar_path', 'login_id'])),
        'auth_fingerprint' => hash('sha256', $user['password_hash']),
        'auth_last_activity' => time(),
        'csrf_token' => bin2hex(random_bytes(32)),
    ];
}
