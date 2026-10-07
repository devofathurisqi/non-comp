<?php
require_once __DIR__ . '/config/bootstrap.php';

if (chemnama_current_user()) {
    $current = chemnama_current_user();
    if (($current['role'] ?? '') === 'guru') {
        header('Location: guru_pilih_kelas.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$role = $_GET['role'] ?? 'siswa';
$role = in_array($role, ['guru', 'siswa'], true) ? $role : 'siswa';
$error = null;
$loginIdValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chemnama_validate_csrf();
    $role = $_POST['role'] ?? 'siswa';
    $role = in_array($role, ['guru', 'siswa'], true) ? $role : 'siswa';
    $loginIdValue = chemnama_normalize_login_id((string) ($_POST['login_id'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!chemnama_auth_attempt($pdo, 'login', $role . ':' . $loginIdValue)) {
        $error = 'Terlalu banyak percobaan. Tunggu 15 menit sebelum mencoba kembali.';
    } elseif (!chemnama_is_valid_login_id($role, $loginIdValue)) {
        $error = chemnama_role_login_label($role) . ' tidak valid.';
    } else {
        $statement = $pdo->prepare('SELECT * FROM users WHERE login_id = :login_id AND role = :role LIMIT 1');
        $statement->execute([
            'login_id' => $loginIdValue,
            'role' => $role,
        ]);

        $user = $statement->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $rehash = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $rehash->execute(['hash' => $user['password_hash'], 'id' => $user['id']]);
            }
            chemnama_login_user($user);

            if ($user['role'] === 'guru') {
                header('Location: guru_pilih_kelas.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }

        $error = chemnama_role_login_label($role) . ', kata sandi, atau peran tidak sesuai.';
    }
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - Nom Comp</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="auth-page">
<div class="ambient ambient-one"></div>
<div class="ambient ambient-two"></div>
<main class="auth-modal-shell">
    <section class="auth-modal">
        <div class="auth-modal-header">
            <div>
                <h1>Masuk</h1>
                <p>Masuk dengan NIP/NIS dan password akun Anda.</p>
            </div>
            <a class="modal-close" href="index.php" aria-label="Kembali ke beranda">&times;</a>
        </div>


        <div class="role-switch role-switch-modal" data-role-switch>
            <button class="role-btn <?= $role === 'siswa' ? 'is-active' : ''; ?>" type="button" data-role-value="siswa">Siswa</button>
            <button class="role-btn <?= $role === 'guru' ? 'is-active' : ''; ?>" type="button" data-role-value="guru">Guru</button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= chemnama_e($error); ?></div>
        <?php endif; ?>

        <form method="post" class="auth-form auth-form-modal">
            <input type="hidden" name="csrf_token" value="<?= chemnama_e(chemnama_csrf_token()); ?>">
            <input type="hidden" name="role" value="<?= chemnama_e($role); ?>" data-role-input>
            <label>
                <span data-auth-login-label><?= chemnama_e(chemnama_role_login_label($role)); ?></span>
                <input type="text" name="login_id" value="<?= chemnama_e($loginIdValue); ?>" placeholder="<?= $role === 'guru' ? 'Contoh: 198712102010011001' : 'Contoh: 22004567'; ?>" autocomplete="username" required data-auth-login-id data-placeholder-siswa="Contoh: 22004567" data-placeholder-guru="Contoh: 198712102010011001">
            </label>
            <label>
                <span>Password</span>
                <div class="password-field">
                    <input type="password" name="password" placeholder="Password" autocomplete="current-password" required data-auth-password>
                    <button class="password-toggle" type="button" data-password-toggle data-eye-on="<?= chemnama_e(chemnama_icon('eye', '#64748b')); ?>" data-eye-off="<?= chemnama_e(chemnama_icon('eye-off', '#0f9d58')); ?>" aria-label="Tampilkan password">
                        <?= chemnama_icon('eye', '#64748b'); ?>
                    </button>
                </div>
            </label>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Masuk</button>
        </form>

        <div style="display: flex; gap: 1rem; margin-top: 1.5rem; font-size: 0.9rem;">
            <a href="register.php" style="flex: 1; text-align: center; color: #667eea; text-decoration: none;">Daftar</a>
            <span style="color: #cbd5e0;">|</span>
            <a href="forgot-password.php?role=<?= chemnama_e($role); ?>" style="flex: 1; text-align: center; color: #667eea; text-decoration: none;" data-forgot-password-link>Lupa Password?</a>
        </div>
    </section>
</main>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
