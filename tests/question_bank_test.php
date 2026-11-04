<?php
declare(strict_types=1);
if (getenv('CHEMNAMA_DB_NAME') !== 'quiz_images_test') {
    fwrite(STDERR, "Use a separate MySQL test database: CHEMNAMA_DB_NAME=quiz_images_test.\n");
    exit(2);
}
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../app/question_bank.php';
$passed = 0;
function check_bank(bool $condition, string $description): void {
    global $passed;
    if (!$condition) throw new RuntimeException($description);
    $passed++;
}
$pdo->beginTransaction();
try {
    $modules = $pdo->query('SELECT id, badge FROM modules ORDER BY id LIMIT 2')->fetchAll();
    if (count($modules) < 2) throw new RuntimeException('Two test modules required');
    $teachers = [];
    foreach ([1, 2] as $i) {
        $key = bin2hex(random_bytes(6));
        $pdo->prepare("INSERT INTO users (name, login_id, email, role, password_hash) VALUES (?, ?, ?, 'guru', ?)")->execute(['Bank Test', 'BANK' . $key, $key . '@example.test', password_hash($key, PASSWORD_DEFAULT)]);
        $teachers[] = (int) $pdo->lastInsertId();
    }
    $create = static function(int $owner, int $module, ?string $class, int $published, int $sort = 0) use ($pdo): int {
        $pdo->prepare('INSERT INTO quiz_sets (module_id, created_by, class_name, title, is_published, sort_order) VALUES (?, ?, ?, ?, ?, ?)')->execute([$module, $owner, $class, 'Integration Test', $published, $sort]);
        return (int) $pdo->lastInsertId();
    };
    $foreign = $create($teachers[1], (int) $modules[0]['id'], 'Class A', 1);
    $automatic = chemnama_question_quiz_set($pdo, $modules[0], $teachers[0], 'Class A');
    $row = $pdo->query('SELECT * FROM quiz_sets WHERE id = ' . $automatic)->fetch();
    check_bank((int) $row['created_by'] === $teachers[0] && $row['class_name'] === 'Class A' && (int) $row['module_id'] === (int) $modules[0]['id'], 'Automatically created quiz stays in the correct teacher, class and module');
    check_bank((int) $row['is_published'] === 0, 'New automatic quiz uses the normal draft default');
    check_bank(chemnama_question_quiz_set($pdo, $modules[0], $teachers[0], 'Class A') === $automatic, 'Repeat save reuses the quiz');
    $otherClass = $create($teachers[0], (int) $modules[0]['id'], 'Class B', 1);
    check_bank(chemnama_question_quiz_set($pdo, $modules[0], $teachers[0], 'Class A') === $automatic, 'Another class is not selected');
    $published = $create($teachers[0], (int) $modules[0]['id'], 'Class A', 1, 9);
    check_bank(chemnama_question_quiz_set($pdo, $modules[0], $teachers[0], 'Class A') === $published, 'Existing published module quiz is preferred');
    $create($teachers[0], (int) $modules[0]['id'], null, 1, -100);
    check_bank(chemnama_question_quiz_set($pdo, $modules[0], $teachers[0], 'Class A') === $published, 'Published class quiz is preferred over a global quiz');
    check_bank(chemnama_question_quiz_set($pdo, $modules[0], $teachers[0], 'Class A', $automatic) === $automatic, 'Editing preserves the original quiz even when another quiz is published');
    foreach ([$foreign, $otherClass] as $invalid) {
        $rejected = false;
        try { chemnama_question_quiz_set($pdo, $modules[0], $teachers[0], 'Class A', $invalid); }
        catch (RuntimeException $exception) { $rejected = true; }
        check_bank($rejected, 'Unauthorized preferred quiz rejected');
    }
    $second = chemnama_question_quiz_set($pdo, $modules[1], $teachers[0], 'Class A');
    check_bank($second !== $automatic && $second !== $published, 'Changing modules selects a separate quiz');
    check_bank((int) $pdo->query('SELECT COUNT(*) FROM quiz_sets WHERE created_by = ' . $teachers[0] . ' AND module_id = ' . (int) $modules[1]['id'])->fetchColumn() === 1, 'Automatic creation does not duplicate quizzes');
    echo 'PASS: ' . $passed . ' automatic question assignment checks.' . PHP_EOL;
} finally {
    $pdo->rollBack();
}
