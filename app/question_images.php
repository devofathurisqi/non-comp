<?php
declare(strict_types=1);

function chemnama_question_image_url(?string $url): ?string
{
    return is_string($url) && preg_match('~\Astorage/uploads/question_images/[a-f0-9]{32}\.(?:jpg|png|gif|webp)\z~', $url)
        ? $url : null;
}

function chemnama_validate_question_image(string $path): string
{
    clearstatcache(true, $path);
    $size = is_file($path) ? filesize($path) : false;
    if ($size === false || $size === 0 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran gambar maksimal 5 MB dan file tidak boleh kosong.');
    }
    $image = @getimagesize($path);
    $formats = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $mime = $image['mime'] ?? '';
    if (!$image || !isset($formats[$mime])) {
        throw new RuntimeException('File harus berupa gambar JPG, PNG, GIF, atau WEBP yang valid.');
    }
    if ($image[0] > 12000 || $image[1] > 12000 || $image[0] * $image[1] > 40000000) {
        throw new RuntimeException('Resolusi gambar terlalu besar. Gunakan gambar maksimal 40 megapiksel dan 12.000 piksel per sisi.');
    }
    if (class_exists('finfo') && (new finfo(FILEINFO_MIME_TYPE))->file($path) !== $mime) {
        throw new RuntimeException('Isi file tidak sesuai dengan format gambar.');
    }
    return $formats[$mime];
}

function chemnama_store_question_image(?array $upload): ?string
{
    if ($upload === null || ($upload['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $error = $upload['error'] ?? null;
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new RuntimeException('Ukuran gambar melebihi batas upload server. Gunakan gambar maksimal 5 MB.');
    }
    if ($error !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) {
        throw new RuntimeException('Upload gambar gagal. Pilih kembali gambar dan coba lagi.');
    }
    $extension = chemnama_validate_question_image($upload['tmp_name']);
    $directory = APP_ROOT . '/storage/uploads/question_images';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder gambar tidak dapat dibuat. Coba lagi atau hubungi pengelola.');
    }
    $url = 'storage/uploads/question_images/' . bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($upload['tmp_name'], APP_ROOT . '/' . $url)) {
        throw new RuntimeException('Gambar tidak dapat disimpan. Silakan coba lagi.');
    }
    return $url;
}

function chemnama_delete_question_image(?string $url): void
{
    $safeUrl = chemnama_question_image_url($url);
    if ($safeUrl !== null && is_file(APP_ROOT . '/' . $safeUrl)) {
        @unlink(APP_ROOT . '/' . $safeUrl);
    }
}
