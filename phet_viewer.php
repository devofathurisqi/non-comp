<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
chemnama_require_auth();
$embedded = ($_GET['embed'] ?? '') === '1';

// Only official HTML5 simulations; never accept a caller-supplied iframe URL.
$simulations = [
    'build-an-atom' => 'Membangun Atom',
    'molecule-shapes' => 'Bentuk Molekul',
    'molecule-polarity' => 'Polaritas Molekul',
    'balancing-chemical-equations' => 'Menyetarakan Persamaan Reaksi',
    'acid-base-solutions' => 'Larutan Asam dan Basa',
    'ph-scale' => 'Skala pH',
];
$selected = $_GET['sim'] ?? 'build-an-atom';
if (!is_string($selected) || !isset($simulations[$selected])) {
    http_response_code(404);
    exit('Simulasi tidak ditemukan.');
}
$language = in_array($selected, ['build-an-atom', 'acid-base-solutions'], true) ? 'in' : 'en';
$url = 'https://phet.colorado.edu/sims/html/' . $selected . '/latest/' . $selected . '_' . $language . '.html';
$user = chemnama_current_user();
$isTeacher = $user['role'] === 'guru';
$menus = $isTeacher ? [
    ['Dashboard', 'chart', 'dashboard.php'],
    ['Kelola Materi', 'file', 'guru_materi.php'],
    ['Pengaturan Games', 'beaker', 'guru_games.php'],
    ['Bank Soal PG', 'stack', 'guru_soal_pg.php'],
    ['Quick / Quiz', 'stack', 'guru_quiz_pg.php'],
    ['Tugas Essay', 'edit', 'guru_essay.php'],
    ['PR / Homework', 'task', 'guru_homework.php'],
    ['Edit Intro Siswa', 'edit', 'guru_intro_siswa.php'],
    ['Forum Diskusi', 'chat', 'guru_forum.php'],
    ['Data Siswa', 'users', 'guru_data_siswa.php'],
    ['Profil', 'user', 'guru_profil.php'],
] : [
    ['Dashboard', 'chart', 'dashboard.php'],
    ['Materi', 'file', 'siswa_materi.php'],
    ['Games', 'beaker', 'siswa_games.php'],
    ['Exercise', 'edit', 'siswa_essay.php'],
    ['Forum Diskusi', 'chat', 'siswa_forum.php'],
    ['Profil', 'user', 'siswa_profil.php'],
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= chemnama_e($simulations[$selected]); ?> — Laboratorium PhET</title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
    <?php if ($embedded): ?>
    <style>
        body.phet-page { background: transparent; }
        .phet-embedded { padding: 12px; width: 100%; box-sizing: border-box; }
        .phet-embedded .phet-frame-shell { height: clamp(360px, 65vh, 640px); }
        .phet-embedded .phet-frame-shell iframe { height: 100%; min-height: 0; }
        .phet-embedded .phet-picker { flex-wrap: wrap; }
        .phet-embedded select { max-width: 100%; min-width: 0; }
    </style>
    <?php endif; ?>
</head>
<body class="phet-page dashboard-page">
<?php if (!$embedded): ?>
<main class="guru-page <?= $isTeacher ? '' : 'student-page'; ?>">
    <button class="guru-mobile-toggle" type="button" data-guru-sidebar-toggle aria-label="Buka navigasi" aria-controls="phetSidebar" aria-expanded="false"><span></span><span></span><span></span></button>
    <div class="guru-sidebar-overlay" data-guru-sidebar-overlay></div>
    <aside class="guru-sidebar" id="phetSidebar">
        <div class="guru-brand"><a class="brand" href="index.php"><?= chemnama_icon('brand', '#0f9d58'); ?><span>Nom Comp</span></a><p>Panel <?= $isTeacher ? 'Guru' : 'Siswa'; ?></p></div>
        <div class="guru-menu-block"><span class="guru-menu-title">MENU</span><nav class="guru-menu-list">
            <a class="guru-menu-item is-active" href="phet_viewer.php"><?= chemnama_icon('beaker', '#0f9d58'); ?><span>Simulasi PhET</span></a>
            <?php foreach ($menus as [$label, $icon, $href]): ?>
                <a class="guru-menu-item" href="<?= chemnama_e($href); ?>"><?= chemnama_icon($icon, '#6b7280'); ?><span><?= chemnama_e($label); ?></span></a>
            <?php endforeach; ?>
        </nav></div>
        <div class="guru-menu-block"><span class="guru-menu-title">NAVIGASI</span><nav class="guru-menu-list">
            <?php if ($isTeacher): ?><a class="guru-menu-item" href="guru_pilih_kelas.php?redirect_to=phet_viewer.php"><?= chemnama_icon('switch', '#6b7280'); ?><span>Ganti Kelas</span></a><?php endif; ?>
            <a class="guru-menu-item logout" href="logout.php"><?= chemnama_icon('logout', '#ef4444'); ?><span>Keluar</span></a>
        </nav></div>
    </aside>
    <section class="guru-content phet-content">
<?php else: ?>
<main class="phet-embedded">
<?php endif; ?>
    <div class="guru-headline-row"><div><h1>Simulasi PhET</h1></div></div>
    <p>Jelajahi konsep kimia melalui simulasi interaktif di dalam Nom Comp.</p>
    <form method="get" class="phet-picker">
        <?php if ($embedded): ?><input type="hidden" name="embed" value="1"><?php endif; ?>
        <label for="phet-sim">Pilih simulasi</label>
        <select id="phet-sim" name="sim">
            <?php foreach ($simulations as $slug => $title): ?>
                <option value="<?= chemnama_e($slug); ?>" <?= $selected === $slug ? 'selected' : ''; ?>><?= chemnama_e($title); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit">Buka simulasi</button>
    </form>
    <section class="phet-frame-shell" aria-label="<?= chemnama_e($simulations[$selected]); ?>">
        <iframe src="<?= chemnama_e($url); ?>" title="PhET: <?= chemnama_e($simulations[$selected]); ?>" allow="fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </section>
    <p>PhET Interactive Simulations, University of Colorado Boulder.
        <a href="https://phet.colorado.edu" target="_blank" rel="noopener noreferrer">phet.colorado.edu</a>.
        Digunakan untuk pendidikan nonkomersial sesuai <a href="https://phet.colorado.edu/en/licensing" target="_blank" rel="noopener noreferrer">ketentuan PhET</a>.
    </p>
    <p>Jika simulasi tidak tampil atau jaringan sekolah memblokirnya,
        <a href="<?= chemnama_e($url); ?>" target="_blank" rel="noopener noreferrer">buka simulasi resmi di tab baru</a>.
        Simulasi memerlukan koneksi internet; hasil aktivitas PhET tidak otomatis menjadi nilai LMS.
    </p>
    <?php if (!$embedded): ?></section><?php endif; ?>
</main>
<script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
