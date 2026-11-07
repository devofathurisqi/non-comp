<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/module_catalog.php';

function chemnama_db_config(): array
{
    return [
        'host' => chemnama_env('CHEMNAMA_DB_HOST') ?: '127.0.0.1',
        'port' => (int) (chemnama_env('CHEMNAMA_DB_PORT') ?: 3306),
        'name' => chemnama_env('CHEMNAMA_DB_NAME') ?: 'tata_senyawa',
        'user' => chemnama_env('CHEMNAMA_DB_USER') ?: 'root',
        'pass' => chemnama_env('CHEMNAMA_DB_PASS') ?: '',
    ];
}

function chemnama_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('Ekstensi pdo_mysql belum aktif. Aktifkan di php.ini XAMPP.');
    }

    $config = chemnama_db_config();

    if (chemnama_env('CHEMNAMA_DB_AUTO_CREATE') !== '0') {
    $adminDsn = sprintf(
        'mysql:host=%s;port=%d;charset=utf8mb4',
        $config['host'],
        $config['port']
    );

    $adminPdo = new PDO($adminDsn, $config['user'], $config['pass']);
    $adminPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $adminPdo->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        str_replace('`', '``', $config['name'])
    ));

    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['name']
    );

    $pdo = new PDO($dsn, $config['user'], $config['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    chemnama_initialize_database($pdo);

    return $pdo;
}

function chemnama_initialize_database(PDO $pdo): void
{
    $schemaPath = APP_ROOT . '/database/schema.sql';
    if (is_file($schemaPath)) {
        $pdo->exec(file_get_contents($schemaPath));
    }

    $classLength = (int) $pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'user_profiles' AND column_name = 'kelas'")->fetchColumn();
    if ($classLength > 0 && $classLength < 255) {
        $pdo->exec('ALTER TABLE user_profiles MODIFY kelas VARCHAR(255) NOT NULL');
    }
    chemnama_migrate_essay_tasks_add_class($pdo);
    chemnama_migrate_homework_tasks_add_class($pdo);
    chemnama_migrate_materials_add_class($pdo);
    chemnama_migrate_quiz_sets_add_class($pdo);
    chemnama_migrate_users_add_avatar_path($pdo);
    chemnama_migrate_users_add_login_id($pdo);
    chemnama_migrate_questions_add_quiz_set($pdo);
    chemnama_migrate_questions_add_image($pdo);
    chemnama_migrate_quiz_sets_add_question_timer($pdo);
    chemnama_migrate_quiz_attempt_answers_selected_option_nullable($pdo);

    chemnama_seed_modules_if_empty($pdo);
    chemnama_normalize_modules($pdo);
    if (chemnama_env('CHEMNAMA_SEED_SAMPLE_CONTENT') === '1') {
    chemnama_seed_materials_if_empty($pdo);
    chemnama_seed_questions_if_empty($pdo);
    chemnama_seed_quiz_sets_if_empty($pdo);
    chemnama_seed_quiz_attempts_if_empty($pdo);
    chemnama_seed_quiz_attempt_runs_if_empty($pdo);
    chemnama_seed_essay_if_empty($pdo);
    chemnama_seed_homework_if_empty($pdo);
    chemnama_seed_forum_if_empty($pdo);
    }

    $siteCount = (int) $pdo->query('SELECT COUNT(*) FROM site_stats')->fetchColumn();
    if ($siteCount > 0) {
        return;
    }

    chemnama_seed_database($pdo);
}

function chemnama_migrate_essay_tasks_add_class(PDO $pdo): void
{
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'essay_tasks'"
    )->fetchColumn();
    if ($tableExists === 0) {
        return;
    }

    $columnExistsStmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'essay_tasks' AND column_name = 'class_name'"
    );
    $columnExists = (int) $columnExistsStmt->fetchColumn();
    if ($columnExists === 0) {
        $pdo->exec("ALTER TABLE essay_tasks ADD COLUMN class_name VARCHAR(60) NULL AFTER created_by");
        $pdo->exec("ALTER TABLE essay_tasks ADD KEY idx_essay_tasks_class_name (class_name)");

    $pdo->exec(
        'UPDATE essay_tasks et
         JOIN user_profiles up ON up.user_id = et.created_by
         SET et.class_name = TRIM(SUBSTRING_INDEX(up.kelas, ",", 1))
         WHERE et.class_name IS NULL OR TRIM(et.class_name) = ""'
    );
    }
}

function chemnama_migrate_homework_tasks_add_class(PDO $pdo): void
{
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'homework_tasks'"
    )->fetchColumn();
    if ($tableExists === 0) {
        return;
    }

    $columnExistsStmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'homework_tasks' AND column_name = 'class_name'"
    );
    $columnExists = (int) $columnExistsStmt->fetchColumn();
    if ($columnExists === 0) {
        $pdo->exec("ALTER TABLE homework_tasks ADD COLUMN class_name VARCHAR(60) NULL AFTER created_by");
        $pdo->exec("ALTER TABLE homework_tasks ADD KEY idx_homework_tasks_class_name (class_name)");

    $pdo->exec(
        'UPDATE homework_tasks ht
         JOIN user_profiles up ON up.user_id = ht.created_by
         SET ht.class_name = TRIM(SUBSTRING_INDEX(up.kelas, ",", 1))
         WHERE ht.class_name IS NULL OR TRIM(ht.class_name) = ""'
    );
    }
}

function chemnama_migrate_materials_add_class(PDO $pdo): void
{
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'materials'"
    )->fetchColumn();
    if ($tableExists === 0) {
        return;
    }

    $columnExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'materials' AND column_name = 'class_name'"
    )->fetchColumn();
    if ($columnExists === 0) {
        $pdo->exec("ALTER TABLE materials ADD COLUMN class_name VARCHAR(60) NULL AFTER created_by");
        $pdo->exec("ALTER TABLE materials ADD KEY idx_materials_class_name (class_name)");

    $pdo->exec(
        'UPDATE materials m
         JOIN user_profiles up ON up.user_id = m.created_by
         SET m.class_name = TRIM(SUBSTRING_INDEX(up.kelas, ",", 1))
         WHERE m.class_name IS NULL OR TRIM(m.class_name) = ""'
    );
    }
}

function chemnama_migrate_quiz_sets_add_class(PDO $pdo): void
{
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'quiz_sets'"
    )->fetchColumn();
    if ($tableExists === 0) {
        return;
    }

    $columnExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'quiz_sets' AND column_name = 'class_name'"
    )->fetchColumn();
    if ($columnExists === 0) {
        $pdo->exec("ALTER TABLE quiz_sets ADD COLUMN class_name VARCHAR(60) NULL AFTER created_by");
        $pdo->exec("ALTER TABLE quiz_sets ADD KEY idx_quiz_sets_class_name (class_name)");

    $pdo->exec(
        'UPDATE quiz_sets qs
         JOIN user_profiles up ON up.user_id = qs.created_by
         SET qs.class_name = TRIM(SUBSTRING_INDEX(up.kelas, ",", 1))
         WHERE qs.class_name IS NULL OR TRIM(qs.class_name) = ""'
    );
    }
}

function chemnama_migrate_users_add_avatar_path(PDO $pdo): void
{
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'"
    )->fetchColumn();
    if ($tableExists === 0) {
        return;
    }

    $columnExistsStmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'avatar_path'"
    );
    $columnExists = (int) $columnExistsStmt->fetchColumn();
    if ($columnExists > 0) {
        return;
    }

    $pdo->exec("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER avatar_color");
}

function chemnama_migrate_users_add_login_id(PDO $pdo): void
{
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'"
    )->fetchColumn();
    if ($tableExists === 0) {
        return;
    }

    $columnExistsStmt = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'login_id'"
    );
    $columnExists = (int) $columnExistsStmt->fetchColumn();
    if ($columnExists === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN login_id VARCHAR(40) NULL AFTER name");
    }

    $missingRowsStmt = $pdo->query('SELECT id, role FROM users WHERE login_id IS NULL OR login_id = ""');
    $missingRows = $missingRowsStmt->fetchAll();
    if (!empty($missingRows)) {
        $updateLoginIdStmt = $pdo->prepare('UPDATE users SET login_id = :login_id WHERE id = :id');
        foreach ($missingRows as $row) {
            $generatedId = chemnama_generate_login_id_from_user((string) ($row['role'] ?? 'siswa'), (int) $row['id']);
            $updateLoginIdStmt->execute([
                'login_id' => $generatedId,
                'id' => (int) $row['id'],
            ]);
        }
    }

    $isNullable = (string) $pdo->query(
        "SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'login_id' LIMIT 1"
    )->fetchColumn();
    if (strtoupper($isNullable) === 'YES') {
        $pdo->exec('ALTER TABLE users MODIFY COLUMN login_id VARCHAR(40) NOT NULL');
    }

    $indexExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'uq_users_login_id'"
    )->fetchColumn();
    if ($indexExists === 0) {
        $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_login_id (login_id)');
    }
}

function chemnama_migrate_questions_add_quiz_set(PDO $pdo): void
{
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'questions'"
    )->fetchColumn();
    if ($tableExists === 0) {
        return;
    }

    $columnExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'questions' AND column_name = 'quiz_set_id'"
    )->fetchColumn();
    if ($columnExists === 0) {
        $pdo->exec("ALTER TABLE questions ADD COLUMN quiz_set_id BIGINT UNSIGNED NULL AFTER module_id");
        $pdo->exec("ALTER TABLE questions ADD KEY idx_questions_quiz_set (quiz_set_id)");
    }
}

function chemnama_migrate_questions_add_image(PDO $pdo): void
{
    $columnExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'questions' AND column_name = 'image_url'"
    )->fetchColumn();
    if ($columnExists === 0) {
        try {
            $pdo->exec('ALTER TABLE questions ADD COLUMN image_url VARCHAR(255) NULL AFTER question_text');
        } catch (PDOException $exception) {
            // Another request may have added the column while this request waited.
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1060) {
                throw $exception;
            }
        }
    }
}

function chemnama_migrate_quiz_sets_add_question_timer(PDO $pdo): void
{
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'quiz_sets'"
    )->fetchColumn();
    if ($tableExists === 0) {
        return;
    }

    $columnExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'quiz_sets' AND column_name = 'question_time_limit_seconds'"
    )->fetchColumn();
    if ($columnExists > 0) {
        return;
    }

    $pdo->exec("ALTER TABLE quiz_sets ADD COLUMN question_time_limit_seconds INT UNSIGNED NOT NULL DEFAULT 0 AFTER description");
}

function chemnama_migrate_quiz_attempt_answers_selected_option_nullable(PDO $pdo): void
{
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'quiz_attempt_answers'"
    )->fetchColumn();
    if ($tableExists === 0) {
        return;
    }

    $isNullable = (string) $pdo->query(
        "SELECT IS_NULLABLE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'quiz_attempt_answers' AND column_name = 'selected_option' LIMIT 1"
    )->fetchColumn();
    if (strtoupper($isNullable) === 'YES') {
        return;
    }

    $pdo->exec("ALTER TABLE quiz_attempt_answers MODIFY COLUMN selected_option ENUM('a', 'b', 'c', 'd') NULL");
}

function chemnama_seed_database(PDO $pdo): void
{
    $insertRows = function (string $table, array $rows) use ($pdo): void {
        if (count($rows) === 0) {
            return;
        }

        $columns = array_keys($rows[0]);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $statement = $pdo->prepare($sql);
        foreach ($rows as $row) {
            $statement->execute($row);
        }
    };

    $pdo->beginTransaction();

    $insertRows('site_stats', [
        ['label' => 'Siswa Aktif', 'value' => '1,200+', 'subtitle' => 'Telah bergabung', 'sort_order' => 1],
        ['label' => 'Tingkat Selesai', 'value' => '98%', 'subtitle' => 'Materi dibuka rutin', 'sort_order' => 2],
        ['label' => 'Modul Inti', 'value' => '3', 'subtitle' => 'Tata nama senyawa', 'sort_order' => 3],
    ]);

    $insertRows('hero_updates', [
        ['title' => 'Senyawa Biner - Modul 2', 'owner' => 'Pak Budi', 'meta' => '2 jam lalu', 'icon' => 'file', 'accent' => '#10b981', 'sort_order' => 1],
        ['title' => 'Video Tata Nama Asam', 'owner' => 'Bu Sari', 'meta' => '5 jam lalu', 'icon' => 'video', 'accent' => '#ef4444', 'sort_order' => 2],
        ['title' => 'PR Senyawa Poliatomik', 'owner' => 'Pak Budi', 'meta' => '1 hari lalu', 'icon' => 'task', 'accent' => '#2563eb', 'sort_order' => 3],
    ]);

    $insertRows('featured_modules', [
        ['badge' => 'Modul 1', 'title' => 'Animasi Ikatan Kimia', 'description' => 'Visualisasi pembentukan ikatan ion dan kovalen.', 'accent' => '#2dd4bf', 'icon' => 'atom', 'sort_order' => 1],
        ['badge' => 'Modul 2', 'title' => 'Animasi Senyawa Biner', 'description' => 'Simulasi penamaan senyawa biner.', 'accent' => '#fb923c', 'icon' => 'link', 'sort_order' => 2],
        ['badge' => 'Modul 6', 'title' => 'Animasi Ion vs Kovalen', 'description' => 'Perbandingan visual senyawa ionik dan kovalen.', 'accent' => '#8b5cf6', 'icon' => 'compare', 'sort_order' => 3],
    ]);

    // The module catalog is seeded once by chemnama_seed_modules_if_empty().

    $insertRows('use_cases', [
        ['title' => 'Obat yang Kamu Minum', 'description' => 'Paracetamol itu C8H9NO2. Vitamin C yang kamu minum saat flu adalah asam askorbat (C6H8O6). Tanpa tata nama, apoteker tidak bisa meracik obat dengan benar.', 'summary' => 'C8H9NO2 -> Paracetamol', 'accent' => '#10b981', 'icon' => 'pill', 'sort_order' => 1],
        ['title' => 'Makanan Favoritmu', 'description' => 'Garam dapur yang bikin makanan enak itu NaCl, natrium klorida. Soda favoritmu mengandung asam karbonat (H2CO3). Bahkan roti pakai natrium bikarbonat (NaHCO3).', 'summary' => 'NaHCO3 -> Soda Kue', 'accent' => '#f59e0b', 'icon' => 'burger', 'sort_order' => 2],
        ['title' => 'Peralatan Rumah Tangga', 'description' => 'Cairan pembersih lantai biasanya mengandung natrium hipoklorit (NaClO). Kapur barus di lemari adalah naftalena (C10H8). Bahkan pasta gigi punya kalsium karbonat (CaCO3).', 'summary' => 'NaClO -> Pemutih', 'accent' => '#2563eb', 'icon' => 'home', 'sort_order' => 3],
        ['title' => 'Teknologi Masa Depan', 'description' => 'Baterai HP-mu pakai lithium kobalt oksida (LiCoO2). Panel surya terbuat dari silikon (Si). Roket SpaceX membakar hidrogen peroksida (H2O2).', 'summary' => 'LiCoO2 -> Baterai Li-Ion', 'accent' => '#ec4899', 'icon' => 'rocket', 'sort_order' => 4],
        ['title' => 'Dunia Biologi', 'description' => 'Tulangmu tersusun dari kalsium fosfat (Ca3(PO4)2). Hemoglobin yang membawa oksigen dalam darah mengandung besi (Fe). Fotosintesis menghasilkan glukosa (C6H12O6).', 'summary' => 'Ca3(PO4)2 -> Tulang', 'accent' => '#14b8a6', 'icon' => 'dna', 'sort_order' => 5],
        ['title' => 'Jalan Menuju Karir', 'description' => 'Ingin jadi dokter? Farmasis? Insinyur kimia? Semua harus menguasai tata nama senyawa. Ini bahasa universal yang digunakan ilmuwan di seluruh dunia.', 'summary' => 'IUPAC -> Bahasa Kimia Dunia', 'accent' => '#8b5cf6', 'icon' => 'cap', 'sort_order' => 6],
    ]);

    $insertRows('knowledge_tips', [
        ['title' => 'Tahukah Kamu?', 'description' => 'Air yang kamu minum setiap hari, H2O, punya nama resmi dihidrogen monoksida menurut IUPAC. Tapi karena terlalu sering dipakai, dunia lebih kenal dengan "air". Namun untuk senyawa yang lebih kompleks, tata nama IUPAC menjadi satu-satunya cara agar semua ilmuwan di bumi sepaham.', 'accent' => '#10b981', 'icon' => 'idea', 'sort_order' => 1],
    ]);

    $pdo->commit();

    chemnama_seed_materials_if_empty($pdo);
    chemnama_seed_essay_if_empty($pdo);
    chemnama_seed_homework_if_empty($pdo);
    chemnama_seed_forum_if_empty($pdo);
    chemnama_seed_quiz_attempts_if_empty($pdo);
}

function chemnama_seed_modules_if_empty(PDO $pdo): void
{
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'modules'"
    )->fetchColumn();
    if ($tableExists === 0) {
        return;
    }

    $moduleCount = (int) $pdo->query('SELECT COUNT(*) FROM modules')->fetchColumn();
    if ($moduleCount > 0) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO modules (badge, title, description, accent, icon, sort_order)
         VALUES (:badge, :title, :description, :accent, :icon, :sort_order)'
    );

    $rows = chemnama_module_catalog();

    foreach ($rows as $row) {
        $insert->execute($row);
    }
}

function chemnama_seed_materials_if_empty(PDO $pdo): void
{
    $tableExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'materials'")->fetchColumn();
    if ($tableExists === 0) {
        return;
    }

    $materialCount = (int) $pdo->query('SELECT COUNT(*) FROM materials')->fetchColumn();
    if ($materialCount > 0) {
        return;
    }

    $guruId = (int) $pdo->query("SELECT id FROM users WHERE role = 'guru' ORDER BY id LIMIT 1")->fetchColumn();
    if ($guruId === 0) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO materials (module_id, created_by, type, title, description, content_text, youtube_url, animation_style, file_name, file_path)
         VALUES (:module_id, :created_by, :type, :title, :description, :content_text, :youtube_url, :animation_style, :file_name, :file_path)'
    );

    $rows = [
        [
            'module_id' => chemnama_catalog_module_id($pdo, 1),
            'created_by' => $guruId,
            'type' => 'teks',
            'title' => 'Pengantar Senyawa Kimia',
            'description' => 'Definisi, perbedaan unsur dan senyawa.',
            'content_text' => 'Materi pengantar senyawa: unsur, molekul, dan contoh sehari-hari.',
            'youtube_url' => null,
            'animation_style' => null,
            'file_name' => null,
            'file_path' => null,
        ],
        [
            'module_id' => chemnama_catalog_module_id($pdo, 2),
            'created_by' => $guruId,
            'type' => 'presentasi',
            'title' => 'Aturan Tata Nama Senyawa Biner',
            'description' => 'Aturan IUPAC untuk senyawa biner.',
            'content_text' => null,
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'animation_style' => null,
            'file_name' => 'Presentasi_Biner.pptx',
            'file_path' => null,
        ],
        [
            'module_id' => chemnama_catalog_module_id($pdo, 1),
            'created_by' => $guruId,
            'type' => 'pdf',
            'title' => 'Ion Poliatomik Wajib Hafal',
            'description' => 'Daftar ion poliatomik penting.',
            'content_text' => null,
            'youtube_url' => null,
            'animation_style' => null,
            'file_name' => 'Ion_Poliatomik.pdf',
            'file_path' => null,
        ],
        [
            'module_id' => chemnama_catalog_module_id($pdo, 3),
            'created_by' => $guruId,
            'type' => 'animasi',
            'title' => 'Animasi Asam dan Basa',
            'description' => 'Visualisasi konsep ion H+ dan OH-.',
            'content_text' => null,
            'youtube_url' => null,
            'animation_style' => 'Orbit Elektron',
            'file_name' => null,
            'file_path' => null,
        ],
    ];

    foreach ($rows as $row) {
        $insert->execute($row);
    }
}

function chemnama_seed_questions_if_empty(PDO $pdo): void
{
    $tableExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'questions'")->fetchColumn();
    if ($tableExists === 0) {
        return;
    }

    try {
        $questionCount = (int) $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
    } catch (PDOException $exception) {
        $message = $exception->getMessage();
        if (str_contains($message, "doesn't exist in engine") || $exception->getCode() === '42S02') {
            chemnama_recreate_table_from_schema($pdo, 'questions');
            $questionCount = (int) $pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
        } else {
            throw $exception;
        }
    }
    if ($questionCount > 0) {
        return;
    }

    $guruId = (int) $pdo->query("SELECT id FROM users WHERE role = 'guru' ORDER BY id LIMIT 1")->fetchColumn();
    if ($guruId === 0) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO questions (module_id, created_by, question_text, option_a, option_b, option_c, option_d, correct_option, difficulty, points)
         VALUES (:module_id, :created_by, :question_text, :option_a, :option_b, :option_c, :option_d, :correct_option, :difficulty, :points)'
    );

    $rows = [
        [
            'module_id' => chemnama_catalog_module_id($pdo, 1),
            'created_by' => $guruId,
            'question_text' => 'Senyawa kimia adalah...',
            'option_a' => 'Zat tunggal yang terdiri dari satu unsur',
            'option_b' => 'Zat hasil perpaduan dua atau lebih unsur dengan perbandingan tertentu',
            'option_c' => 'Zat yang mudah bereaksi dengan oksigen',
            'option_d' => 'Zat yang dapat menyetrum',
            'correct_option' => 'b',
            'difficulty' => 'mudah',
            'points' => 10,
        ],
        [
            'module_id' => chemnama_catalog_module_id($pdo, 1),
            'created_by' => $guruId,
            'question_text' => 'Perbedaan antara unsur dan senyawa adalah...',
            'option_a' => 'Unsur hanya ada di alam, senyawa hanya dibuat manusia',
            'option_b' => 'Unsur bisa dipisahkan, senyawa tidak bisa dipisahkan dengan reaksi kimia',
            'option_c' => 'Unsur terdiri dari satu macam atom, senyawa terdiri dari dua atau lebih macam atom',
            'option_d' => 'Unsur selalu gas, senyawa selalu padat',
            'correct_option' => 'c',
            'difficulty' => 'mudah',
            'points' => 10,
        ],
        [
            'module_id' => chemnama_catalog_module_id($pdo, 2),
            'created_by' => $guruId,
            'question_text' => 'Rumus molekul yang benar untuk gas nitrogen adalah...',
            'option_a' => 'N',
            'option_b' => 'N2',
            'option_c' => 'NO',
            'option_d' => 'NaN',
            'correct_option' => 'b',
            'difficulty' => 'mudah',
            'points' => 10,
        ],
        [
            'module_id' => chemnama_catalog_module_id($pdo, 2),
            'created_by' => $guruId,
            'question_text' => 'Senyawa dengan rumus NaCl disebut...',
            'option_a' => 'Natrium klor',
            'option_b' => 'Natrium klorida',
            'option_c' => 'Garam dapur',
            'option_d' => 'B dan C benar',
            'correct_option' => 'd',
            'difficulty' => 'mudah',
            'points' => 10,
        ],
        [
            'module_id' => chemnama_catalog_module_id($pdo, 2),
            'created_by' => $guruId,
            'question_text' => 'Rumas kimia untuk gas karbon dioksida adalah...',
            'option_a' => 'CO',
            'option_b' => 'CO2',
            'option_c' => 'C2O',
            'option_d' => 'C2O2',
            'correct_option' => 'b',
            'difficulty' => 'sedang',
            'points' => 15,
        ],
        [
            'module_id' => chemnama_catalog_module_id($pdo, 1),
            'created_by' => $guruId,
            'question_text' => 'Ion poliatomik adalah...',
            'option_a' => 'Ion yang terdiri dari satu atom',
            'option_b' => 'Ion yang terdiri dari dua atom dengan muatan berbeda',
            'option_c' => 'Ion yang terdiri dari dua atom atau lebih dengan muatan keseluruhan',
            'option_d' => 'Ion yang tidak stabil',
            'correct_option' => 'c',
            'difficulty' => 'sedang',
            'points' => 15,
        ],
        [
            'module_id' => chemnama_catalog_module_id($pdo, 1),
            'created_by' => $guruId,
            'question_text' => 'Contoh ion poliatomik adalah...',
            'option_a' => 'OH-, NO3-, SO42-',
            'option_b' => 'Na+, Cl-, K+',
            'option_c' => 'H2, O2, N2',
            'option_d' => 'H+, H-, e-',
            'correct_option' => 'a',
            'difficulty' => 'sedang',
            'points' => 15,
        ],
        [
            'module_id' => chemnama_catalog_module_id($pdo, 3),
            'created_by' => $guruId,
            'question_text' => 'Asam biner adalah...',
            'option_a' => 'Asam yang terdiri dari dua unsur',
            'option_b' => 'Asam yang terdiri dari hidrogen dan oksigen',
            'option_c' => 'Asam yang larut dalam air',
            'option_d' => 'Asam yang berwarna',
            'correct_option' => 'a',
            'difficulty' => 'sedang',
            'points' => 15,
        ],
    ];

    foreach ($rows as $row) {
        $insert->execute($row);
    }
}

function chemnama_recreate_table_from_schema(PDO $pdo, string $tableName): void
{
    $schemaPath = APP_ROOT . '/database/schema.sql';
    if (!is_file($schemaPath)) {
        return;
    }

    $safeTable = str_replace('`', '``', $tableName);
    $pdo->exec("DROP TABLE IF EXISTS `{$safeTable}`");
    $pdo->exec(file_get_contents($schemaPath));
}

function chemnama_seed_quiz_sets_if_empty(PDO $pdo): void
{
    $tablesExist = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'quiz_sets'"
    )->fetchColumn();
    $questionsTableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'questions'"
    )->fetchColumn();
    if ($tablesExist === 0 || $questionsTableExists === 0) {
        return;
    }

    $quizSetCount = (int) $pdo->query('SELECT COUNT(*) FROM quiz_sets')->fetchColumn();
    if ($quizSetCount > 0) {
        return;
    }

    $guruId = (int) $pdo->query("SELECT id FROM users WHERE role = 'guru' ORDER BY id LIMIT 1")->fetchColumn();
    if ($guruId === 0) {
        return;
    }

    $modules = $pdo->query('SELECT id, badge, title, sort_order FROM modules ORDER BY sort_order, id')->fetchAll();
    $insertQuizSet = $pdo->prepare(
        'INSERT INTO quiz_sets (module_id, created_by, title, description, is_published, published_at, sort_order)
         VALUES (:module_id, :created_by, :title, :description, :is_published, :published_at, :sort_order)'
    );
    $updateQuestionSet = $pdo->prepare('UPDATE questions SET quiz_set_id = :quiz_set_id WHERE module_id = :module_id');

    foreach ($modules as $module) {
        $insertQuizSet->execute([
            'module_id' => (int) $module['id'],
            'created_by' => $guruId,
            'title' => (string) $module['badge'] . ' - Quiz Utama',
            'description' => 'Quiz bawaan untuk modul ' . (string) $module['title'],
            'is_published' => 1,
            'published_at' => '2025-01-01 08:00:00',
            'sort_order' => (int) $module['sort_order'],
        ]);
        $quizSetId = (int) $pdo->lastInsertId();
        $updateQuestionSet->execute([
            'quiz_set_id' => $quizSetId,
            'module_id' => (int) $module['id'],
        ]);
    }
}

function chemnama_seed_quiz_attempt_runs_if_empty(PDO $pdo): void
{
    $runTableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'quiz_attempt_runs'"
    )->fetchColumn();
    $answerTableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'quiz_attempt_answers'"
    )->fetchColumn();
    $legacyTableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'quiz_attempts'"
    )->fetchColumn();
    if ($runTableExists === 0 || $answerTableExists === 0 || $legacyTableExists === 0) {
        return;
    }

    $runCount = (int) $pdo->query('SELECT COUNT(*) FROM quiz_attempt_runs')->fetchColumn();
    if ($runCount > 0) {
        return;
    }

    $legacyRows = $pdo->query(
        'SELECT qa.student_id, qa.question_id, qa.selected_option, qa.is_correct, qa.answered_at,
                q.quiz_set_id, q.points
         FROM quiz_attempts qa
         JOIN questions q ON q.id = qa.question_id
         WHERE q.quiz_set_id IS NOT NULL
         ORDER BY qa.student_id, q.quiz_set_id, qa.answered_at, qa.id'
    )->fetchAll();
    if (count($legacyRows) === 0) {
        return;
    }

    $grouped = [];
    foreach ($legacyRows as $row) {
        $studentId = (int) $row['student_id'];
        $quizSetId = (int) $row['quiz_set_id'];
        $grouped[$studentId][$quizSetId][] = $row;
    }

    $insertRun = $pdo->prepare(
        'INSERT INTO quiz_attempt_runs (quiz_set_id, student_id, attempt_number, score, correct_count, total_questions, total_points, started_at, submitted_at)
         VALUES (:quiz_set_id, :student_id, :attempt_number, :score, :correct_count, :total_questions, :total_points, :started_at, :submitted_at)'
    );
    $insertAnswer = $pdo->prepare(
        'INSERT INTO quiz_attempt_answers (attempt_run_id, question_id, selected_option, is_correct, answered_at)
         VALUES (:attempt_run_id, :question_id, :selected_option, :is_correct, :answered_at)'
    );

    foreach ($grouped as $studentId => $quizSets) {
        foreach ($quizSets as $quizSetId => $answers) {
            $correctCount = 0;
            $totalPoints = 0;
            $startedAt = $answers[0]['answered_at'] ?? '2025-02-01 08:00:00';
            $submittedAt = $startedAt;

            foreach ($answers as $answer) {
                if ((int) $answer['is_correct'] === 1) {
                    $correctCount += 1;
                    $totalPoints += (int) $answer['points'];
                }
                $submittedAt = (string) $answer['answered_at'];
            }

            $totalQuestions = count($answers);
            $score = $totalQuestions > 0 ? (int) round(($correctCount / $totalQuestions) * 100) : 0;

            $insertRun->execute([
                'quiz_set_id' => (int) $quizSetId,
                'student_id' => (int) $studentId,
                'attempt_number' => 1,
                'score' => $score,
                'correct_count' => $correctCount,
                'total_questions' => $totalQuestions,
                'total_points' => $totalPoints,
                'started_at' => $startedAt,
                'submitted_at' => $submittedAt,
            ]);
            $attemptRunId = (int) $pdo->lastInsertId();

            foreach ($answers as $answer) {
                $insertAnswer->execute([
                    'attempt_run_id' => $attemptRunId,
                    'question_id' => (int) $answer['question_id'],
                    'selected_option' => (string) $answer['selected_option'],
                    'is_correct' => (int) $answer['is_correct'],
                    'answered_at' => (string) $answer['answered_at'],
                ]);
            }
        }
    }
}

function chemnama_seed_essay_if_empty(PDO $pdo): void
{
    $tasksTableExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'essay_tasks'")->fetchColumn();
    $answersTableExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'essay_answers'")->fetchColumn();
    if ($tasksTableExists === 0 || $answersTableExists === 0) {
        return;
    }

    $taskCount = (int) $pdo->query('SELECT COUNT(*) FROM essay_tasks')->fetchColumn();
    if ($taskCount > 0) {
        return;
    }

    $guruId = (int) $pdo->query("SELECT id FROM users WHERE role = 'guru' ORDER BY id LIMIT 1")->fetchColumn();
    $siswaId = (int) $pdo->query("SELECT id FROM users WHERE role = 'siswa' ORDER BY id LIMIT 1")->fetchColumn();
    if ($guruId === 0 || $siswaId === 0) {
        return;
    }

    $insertTask = $pdo->prepare(
        'INSERT INTO essay_tasks (module_id, created_by, prompt_text, due_at, is_published)
         VALUES (:module_id, :created_by, :prompt_text, :due_at, :is_published)'
    );

    $insertTask->execute([
        'module_id' => chemnama_catalog_module_id($pdo, 1),
        'created_by' => $guruId,
        'prompt_text' => 'Jelaskan perbedaan unsur dan senyawa. Berikan masing-masing 3 contoh dalam kehidupan sehari-hari.',
        'due_at' => null,
        'is_published' => 1,
    ]);

    $taskId = (int) $pdo->lastInsertId();

    $insertAnswer = $pdo->prepare(
        'INSERT INTO essay_answers (task_id, student_id, answer_text, score, feedback, submitted_at, graded_at, graded_by)
         VALUES (:task_id, :student_id, :answer_text, :score, :feedback, :submitted_at, :graded_at, :graded_by)'
    );

    $insertAnswer->execute([
        'task_id' => $taskId,
        'student_id' => $siswaId,
        'answer_text' => 'Unsur adalah zat tunggal yang tidak bisa diuraikan lagi, contohnya Au, O2, Fe. Senyawa terbentuk dari dua atau lebih unsur yang berikatan kimia, contohnya H2O, NaCl, dan CO2.',
        'score' => 85,
        'feedback' => 'Jawaban lengkap dan tepat.',
        'submitted_at' => '2025-01-20 08:00:00',
        'graded_at' => '2025-01-20 09:00:00',
        'graded_by' => $guruId,
    ]);
}

function chemnama_seed_quiz_attempts_if_empty(PDO $pdo): void
{
    $attemptTableExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'quiz_attempts'")->fetchColumn();
    $questionTableExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'questions'")->fetchColumn();
    if ($attemptTableExists === 0 || $questionTableExists === 0) {
        return;
    }

    $attemptCount = (int) $pdo->query('SELECT COUNT(*) FROM quiz_attempts')->fetchColumn();
    if ($attemptCount > 0) {
        return;
    }

    $questions = $pdo->query('SELECT id, module_id, correct_option FROM questions WHERE is_published = 1 ORDER BY module_id, id')->fetchAll();
    if (count($questions) === 0) {
        return;
    }

    $questionsByModule = [];
    foreach ($questions as $question) {
        $moduleId = (int) $question['module_id'];
        if (!isset($questionsByModule[$moduleId])) {
            $questionsByModule[$moduleId] = [];
        }
        $questionsByModule[$moduleId][] = $question;
    }

    $studentEmailPlans = [
        'aisyah@chemnama.id' => ['ratio' => 0.92],
        'dewi@chemnama.id' => ['ratio' => 0.84],
        'fajar@chemnama.id' => ['ratio' => 0.58],
        'maya@chemnama.id' => ['ratio' => 0.72],
        'siswa@chemnama.id' => ['ratio' => 0.79],
    ];

    $findStudent = $pdo->prepare('SELECT id FROM users WHERE email = :email AND role = "siswa" LIMIT 1');
    $insertAttempt = $pdo->prepare(
        'INSERT INTO quiz_attempts (student_id, question_id, selected_option, is_correct, answered_at)
         VALUES (:student_id, :question_id, :selected_option, :is_correct, :answered_at)'
    );

    $wrongOptionMap = [
        'a' => 'b',
        'b' => 'c',
        'c' => 'd',
        'd' => 'a',
    ];

    foreach ($studentEmailPlans as $email => $plan) {
        $findStudent->execute(['email' => $email]);
        $studentId = (int) $findStudent->fetchColumn();
        if ($studentId === 0) {
            continue;
        }

        foreach ($questionsByModule as $moduleQuestions) {
            $moduleTotal = count($moduleQuestions);
            if ($moduleTotal === 0) {
                continue;
            }

            $answeredCount = (int) ceil($moduleTotal * (float) $plan['ratio']);
            $answeredCount = max(1, min($answeredCount, $moduleTotal));

            $correctCount = (int) round($answeredCount * (float) $plan['ratio']);
            $correctCount = max(0, min($correctCount, $answeredCount));

            for ($i = 0; $i < $answeredCount; $i++) {
                $question = $moduleQuestions[$i];
                $isCorrect = $i < $correctCount ? 1 : 0;
                $correctOption = (string) $question['correct_option'];
                $selectedOption = $isCorrect === 1 ? $correctOption : ($wrongOptionMap[$correctOption] ?? 'a');

                $insertAttempt->execute([
                    'student_id' => $studentId,
                    'question_id' => (int) $question['id'],
                    'selected_option' => $selectedOption,
                    'is_correct' => $isCorrect,
                    'answered_at' => '2025-02-01 08:00:00',
                ]);
            }
        }
    }
}

function chemnama_seed_homework_if_empty(PDO $pdo): void
{
    $tasksTableExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'homework_tasks'")->fetchColumn();
    $submissionsTableExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'homework_submissions'")->fetchColumn();
    if ($tasksTableExists === 0 || $submissionsTableExists === 0) {
        return;
    }

    $taskCount = (int) $pdo->query('SELECT COUNT(*) FROM homework_tasks')->fetchColumn();
    if ($taskCount > 0) {
        return;
    }

    $guruId = (int) $pdo->query("SELECT id FROM users WHERE role = 'guru' ORDER BY id LIMIT 1")->fetchColumn();
    $siswaId = (int) $pdo->query("SELECT id FROM users WHERE role = 'siswa' ORDER BY id LIMIT 1")->fetchColumn();
    if ($guruId === 0 || $siswaId === 0) {
        return;
    }

    $insertTask = $pdo->prepare(
        'INSERT INTO homework_tasks (module_id, created_by, title, description, due_at, is_published)
         VALUES (:module_id, :created_by, :title, :description, :due_at, :is_published)'
    );

    $insertTask->execute([
        'module_id' => chemnama_catalog_module_id($pdo, 2),
        'created_by' => $guruId,
        'title' => 'PR Senyawa Biner',
        'description' => 'Berikan 10 contoh senyawa biner beserta tata nama IUPAC dan bilangan oksidasinya.',
        'due_at' => '2025-02-15 23:59:00',
        'is_published' => 1,
    ]);

    $taskId = (int) $pdo->lastInsertId();

    $insertSubmission = $pdo->prepare(
        'INSERT INTO homework_submissions (task_id, student_id, file_name, file_path, teacher_feedback, is_checked, checked_at, checked_by, submitted_at)
         VALUES (:task_id, :student_id, :file_name, :file_path, :teacher_feedback, :is_checked, :checked_at, :checked_by, :submitted_at)'
    );

    $insertSubmission->execute([
        'task_id' => $taskId,
        'student_id' => $siswaId,
        'file_name' => 'pr_biner_aisyah.pdf',
        'file_path' => '',
        'teacher_feedback' => 'PR dicek, format penulisan sudah bagus.',
        'is_checked' => 1,
        'checked_at' => '2025-02-14 12:00:00',
        'checked_by' => $guruId,
        'submitted_at' => '2025-02-14 08:00:00',
    ]);
}

function chemnama_seed_forum_if_empty(PDO $pdo): void
{
    $threadsTableExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'forum_threads'")->fetchColumn();
    $repliesTableExists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'forum_replies'")->fetchColumn();
    if ($threadsTableExists === 0 || $repliesTableExists === 0) {
        return;
    }

    $threadCount = (int) $pdo->query('SELECT COUNT(*) FROM forum_threads')->fetchColumn();
    if ($threadCount > 0) {
        return;
    }

    $guruId = (int) $pdo->query("SELECT id FROM users WHERE role = 'guru' ORDER BY id LIMIT 1")->fetchColumn();
    $siswaId = (int) $pdo->query("SELECT id FROM users WHERE role = 'siswa' ORDER BY id LIMIT 1")->fetchColumn();
    if ($guruId === 0 || $siswaId === 0) {
        return;
    }

    $module2 = (int) $pdo->query('SELECT id FROM modules ORDER BY id LIMIT 1 OFFSET 1')->fetchColumn();
    if ($module2 === 0) {
        $module2 = null;
    }

    $insertThread = $pdo->prepare(
        'INSERT INTO forum_threads (module_id, class_name, author_id, title, body, thread_type, created_at)
         VALUES (:module_id, :class_name, :author_id, :title, :body, :thread_type, :created_at)'
    );

    $insertReply = $pdo->prepare(
        'INSERT INTO forum_replies (thread_id, author_id, body, created_at)
         VALUES (:thread_id, :author_id, :body, :created_at)'
    );

    $insertThread->execute([
        'module_id' => $module2,
        'class_name' => 'X IPA 1',
        'author_id' => $siswaId,
        'title' => 'Apakah FeCl3 bisa disebut ferri klorida?',
        'body' => 'Apakah FeCl3 bisa disebut ferri klorida? Bedanya apa dengan Besi(III) klorida?',
        'thread_type' => 'question',
        'created_at' => '2025-01-18 08:00:00',
    ]);

    $threadId = (int) $pdo->lastInsertId();

    $insertReply->execute([
        'thread_id' => $threadId,
        'author_id' => $guruId,
        'body' => 'Pertanyaan bagus. Fe(III) dalam sistem lama disebut ferri dan Fe(II) disebut fero. Jadi FeCl3 = ferri klorida = Besi(III) klorida.',
        'created_at' => '2025-01-18 10:00:00',
    ]);

    $insertThread->execute([
        'module_id' => $module2,
        'class_name' => 'X IPA 1',
        'author_id' => $siswaId,
        'title' => 'Kok AlCl3 termasuk kovalen?',
        'body' => 'Kok AlCl3 termasuk kovalen ya pak? Kan Al itu logam?',
        'thread_type' => 'question',
        'created_at' => '2025-01-20 08:00:00',
    ]);
}
