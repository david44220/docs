<?php
/**
 * Payment proof uploads. Files are stored outside the web root under
 * storage/uploads/proofs with a random name and are only served to admins
 * through public/admin/proof.php.
 */
declare(strict_types=1);

const PROOF_MAX_BYTES = 4 * 1024 * 1024;
const PROOF_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

function proof_dir(): string
{
    return STORAGE_DIR . '/uploads/proofs';
}

/** Validate and store an uploaded proof; null when no file was sent. */
function store_proof_upload(?array $file): ?string
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (is_array($file['error'])) {
        throw new AppError('Please attach a single image.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new AppError(match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The screenshot is too large (4 MB maximum).',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
            default => 'The screenshot could not be uploaded. Please try again.',
        });
    }
    if ((int) $file['size'] > PROOF_MAX_BYTES) {
        throw new AppError('The screenshot is too large (4 MB maximum).');
    }
    $tmp = (string) $file['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        throw new AppError('Invalid upload.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
    if (!isset(PROOF_TYPES[$mime]) || @getimagesize($tmp) === false) {
        throw new AppError('Upload a JPG, PNG, WebP or GIF image.');
    }

    $dir = proof_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Upload folder is not writable: ' . $dir);
    }
    $name = bin2hex(random_bytes(16)) . '.' . PROOF_TYPES[$mime];
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
        throw new RuntimeException('Could not store the uploaded file in ' . $dir);
    }
    @chmod($dir . '/' . $name, 0644);
    return $name;
}

/** Absolute path of a stored proof, or null if the name is not one of ours. */
function proof_path(?string $name): ?string
{
    if ($name === null || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $name)) {
        return null;
    }
    $path = proof_dir() . '/' . $name;
    return is_file($path) ? $path : null;
}
