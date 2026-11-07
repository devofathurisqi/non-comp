<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/module_catalog.php';
$passed = 0;
function check_modules(bool $condition, string $message): void
{
    global $passed;
    if (!$condition) throw new RuntimeException($message);
    $passed++;
}
function module_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE modules (id INTEGER PRIMARY KEY AUTOINCREMENT, badge TEXT, title TEXT, description TEXT, accent TEXT, icon TEXT, sort_order INTEGER)');
    $titles = ['Konsep Dasar Senyawa', 'Tata Nama Senyawa Biner', 'Senyawa Poliatomik', 'Tata Nama Asam', 'Tata Nama Basa', 'Senyawa Ion & Kovalen'];
    foreach (array_merge($titles, $titles) as $index => $title) {
        $number = ($index % 6) + 1;
        $pdo->prepare('INSERT INTO modules VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$index + 1, 'Modul ' . $number, $title, 'Existing description', '#10b981', 'flask', $number]);
    }
    foreach (['materials', 'quiz_sets', 'questions', 'essay_tasks', 'homework_tasks', 'forum_threads', 'simulations', 'chem_match_games', 'word_search_games', 'quick_quizzes'] as $table) {
        $pdo->exec('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY, module_id INTEGER REFERENCES modules(id) ON DELETE CASCADE, content TEXT)');
        foreach (range(1, 12) as $id) $pdo->prepare('INSERT INTO ' . $table . ' VALUES (?, ?, ?)')->execute([$id, $id, 'Retain content ' . $id]);
    }
    $pdo->exec('CREATE TABLE quiz_answers (id INTEGER PRIMARY KEY, question_id INTEGER REFERENCES questions(id), score INTEGER)');
    $pdo->exec('INSERT INTO quiz_answers VALUES (1, 9, 100)');
    $pdo->exec("INSERT INTO forum_threads VALUES (13, NULL, 'General discussion')");
    return $pdo;
}
function module_snapshot(PDO $pdo): array
{
    $data = ['modules' => $pdo->query('SELECT * FROM modules ORDER BY id')->fetchAll()];
    foreach (chemnama_module_reference_tables($pdo) as $table) $data[$table] = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll();
    return $data;
}
$pdo = module_fixture();
chemnama_normalize_modules($pdo);
$labels = $pdo->query("SELECT badge || ' - ' || title FROM modules ORDER BY sort_order")->fetchAll(PDO::FETCH_COLUMN);
check_modules($labels === ['Modul 1 - Tata Nama Senyawa ION', 'Modul 2 - Tata Nama Senyawa Molekular', 'Modul 3 - Tata Nama Senyawa Asam dan Basa'], 'Exactly three names and order match the reference');
$mapping = [1 => 1, 2 => 2, 3 => 1, 4 => 4, 5 => 4, 6 => 1, 7 => 1, 8 => 2, 9 => 1, 10 => 4, 11 => 4, 12 => 1];
foreach (chemnama_module_reference_tables($pdo) as $table) {
    $rows = $pdo->query('SELECT * FROM ' . $table . ' WHERE module_id IS NOT NULL ORDER BY id')->fetchAll();
    check_modules(count($rows) === 12, $table . ' retains all records');
    foreach ($rows as $row) check_modules((int) $row['module_id'] === $mapping[$row['id']] && $row['content'] === 'Retain content ' . $row['id'], $table . ' preserves content and remaps duplicate IDs');
}
check_modules($pdo->query('SELECT module_id FROM forum_threads WHERE id = 13')->fetchColumn() === null, 'General discussion remains unassigned');
check_modules((int) $pdo->query('SELECT score FROM quiz_answers WHERE question_id = 9')->fetchColumn() === 100, 'Student answers and scores remain linked');
$before = module_snapshot($pdo);
chemnama_normalize_modules($pdo);
check_modules(module_snapshot($pdo) === $before, 'Repeated initialization changes nothing');
check_modules(chemnama_catalog_module_id($pdo, 3) === 4, 'Seeds resolve retained IDs by catalog number');

$pdo = module_fixture();
$pdo->exec("CREATE TRIGGER reject_move BEFORE UPDATE ON homework_tasks BEGIN SELECT RAISE(ABORT, 'Simulated failure'); END");
$before = module_snapshot($pdo);
try { chemnama_normalize_modules($pdo); throw new LogicException('Expected migration failure'); }
catch (PDOException $exception) { check_modules(module_snapshot($pdo) === $before && !$pdo->inTransaction(), 'Failed remapping rolls back every module and linked record'); }

$pdo = module_fixture();
$pdo->exec("UPDATE modules SET title = 'Custom module' WHERE id = 12");
$before = module_snapshot($pdo);
try { chemnama_normalize_modules($pdo); throw new LogicException('Expected unknown module rejection'); }
catch (RuntimeException $exception) { check_modules(module_snapshot($pdo) === $before, 'Unknown custom modules are preserved'); }

$pdo = module_fixture();
$pdo->exec('DELETE FROM quiz_answers');
$pdo->exec('DELETE FROM modules');
chemnama_normalize_modules($pdo);
check_modules((int) $pdo->query('SELECT COUNT(*) FROM modules')->fetchColumn() === 3, 'An empty catalog creates three modules');
check_modules(chemnama_catalog_module_id($pdo, 1) > 12, 'New IDs need not start at one');

$pdo = module_fixture();
$definition = chemnama_module_catalog()[1];
$pdo->prepare('INSERT INTO modules (id, badge, title, description, accent, icon, sort_order) VALUES (20, :badge, :title, :description, :accent, :icon, :sort_order)')->execute($definition);
chemnama_normalize_modules($pdo);
check_modules(chemnama_catalog_module_id($pdo, 1) === 20, 'Existing correctly named modules retain their identity');
echo 'PASS: ' . $passed . ' module catalog checks.' . PHP_EOL;
