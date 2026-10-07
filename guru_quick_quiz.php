<?php
declare(strict_types=1);
// Keep old bookmarks/forms working through the supported module-based Quick Quiz view.
require_once __DIR__ . '/config/bootstrap.php';
chemnama_require_auth('guru');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'save') {
        $teacher = chemnama_current_user();
        $class = chemnama_get_guru_active_class($pdo, (int) $teacher['id']);
        $material = $pdo->prepare('SELECT module_id FROM materials WHERE id=? AND created_by=? AND (class_name=? OR class_name IS NULL)');
        $material->execute([(int) ($_POST['material_id'] ?? 0), $teacher['id'], $class]);
        $_POST['module_id'] = (int) $material->fetchColumn();
        $_POST['action'] = 'save_quick_quiz';
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $_POST['action'] = 'delete_quick_quiz';
    }
    $_GET['tab'] = 'quick_quiz';
    require __DIR__ . '/guru_quiz_pg.php';
    exit;
}
$params = ['tab' => 'quick_quiz'];
if ((int) ($_GET['edit'] ?? 0) > 0) $params['edit_quick'] = (int) $_GET['edit'];
header('Location: guru_quiz_pg.php?' . http_build_query($params));
exit;