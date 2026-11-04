<?php
declare(strict_types=1);

function chemnama_question_quiz_set(PDO $pdo, array $module, int $teacherId, string $className, ?int $preferredId = null): int
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Question assignment requires a transaction.');
    }
    // Serialize saves by this teacher so simultaneous first questions share one quiz.
    $lock = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'guru' FOR UPDATE");
    $lock->execute([$teacherId]);
    if (!$lock->fetchColumn()) {
        throw new RuntimeException('Akun guru tidak valid.');
    }
    if ($preferredId !== null) {
        $existing = $pdo->prepare('SELECT id FROM quiz_sets WHERE id = ? AND module_id = ? AND created_by = ? AND (class_name = ? OR class_name IS NULL)');
        $existing->execute([$preferredId, $module['id'], $teacherId, $className]);
        $id = $existing->fetchColumn();
        if (!$id) {
            throw new RuntimeException('Quiz tidak ditemukan atau tidak dapat diubah di kelas ini.');
        }
        return (int) $id;
    }
    $find = $pdo->prepare(
        'SELECT id FROM quiz_sets WHERE module_id = ? AND created_by = ? AND (class_name = ? OR class_name IS NULL)
         ORDER BY is_published DESC, CASE WHEN class_name = ? THEN 0 ELSE 1 END, sort_order, id LIMIT 1'
    );
    $find->execute([$module['id'], $teacherId, $className, $className]);
    $id = $find->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    // Follow the normal new-quiz defaults: a class-scoped draft, ready to publish later.
    $create = $pdo->prepare('INSERT INTO quiz_sets (module_id, created_by, class_name, title) VALUES (?, ?, ?, ?)');
    $create->execute([$module['id'], $teacherId, $className, 'Quiz ' . $module['badge']]);
    return (int) $pdo->lastInsertId();
}
