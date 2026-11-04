<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/question_images.php';
$passed = 0;
function check_image(bool $condition, string $description): void {
    global $passed;
    if (!$condition) throw new RuntimeException($description);
    $passed++;
}
function expect_image_error(callable $callback, string $description): void {
    try { $callback(); } catch (RuntimeException $exception) { check_image(true, $description); return; }
    throw new RuntimeException($description);
}
$temporary = tempnam(sys_get_temp_dir(), 'quiz-image-');
try {
    file_put_contents($temporary, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
    check_image(chemnama_validate_question_image($temporary) === 'png', 'PNG detected from bytes');
    file_put_contents($temporary, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
    check_image(chemnama_validate_question_image($temporary) === 'gif', 'GIF detected from bytes');
    foreach (['<?php echo "not an image";', '<svg xmlns="http://www.w3.org/2000/svg"></svg>', ''] as $invalid) {
        file_put_contents($temporary, $invalid);
        expect_image_error(fn() => chemnama_validate_question_image($temporary), 'Non-image rejected');
    }
    file_put_contents($temporary, str_repeat('x', 5 * 1024 * 1024 + 1));
    expect_image_error(fn() => chemnama_validate_question_image($temporary), 'Oversized file rejected');
    check_image(chemnama_store_question_image(null) === null, 'Optional upload');
    check_image(chemnama_store_question_image(['error' => UPLOAD_ERR_NO_FILE]) === null, 'No file keeps image');
    foreach ([UPLOAD_ERR_PARTIAL, UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE] as $error) {
        expect_image_error(fn() => chemnama_store_question_image(['error' => $error]), 'Upload error handled');
    }
    expect_image_error(fn() => chemnama_store_question_image(['error' => UPLOAD_ERR_OK, 'tmp_name' => $temporary]), 'Non-HTTP upload rejected');
    $valid = 'storage/uploads/question_images/' . str_repeat('a', 32) . '.png';
    check_image(chemnama_question_image_url($valid) === $valid, 'Stored image URL allowed');
    foreach (['../../.env', 'https://example.com/a.png', 'javascript:alert(1)', 'storage/uploads/question_images/../../.env', $valid . '.php', null] as $invalidUrl) {
        check_image(chemnama_question_image_url($invalidUrl) === null, 'Untrusted URL rejected');
    }
    echo 'PASS: ' . $passed . ' question image checks.' . PHP_EOL;
} finally { @unlink($temporary); }
