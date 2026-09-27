<?php
/**
 * Payment proof uploads. Files are stored outside the web root under
 * storage/uploads/proofs with a random name and are only served to admins
 * through public/admin/proof.php.
 */
declare(strict_types=1);

const PROOF_MAX_BYTES = 4 * 1024 * 1024; // hard cap; the server's PHP limits may be lower
const PROOF_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

/** "8M" / "512K" / "1G" from php.ini → bytes (0 when unlimited or unknown). */
function ini_bytes(string $key): int
{
    $value = trim((string) ini_get($key));
    if ($value === '' || !preg_match('/^(\d+)\s*([KMG]?)/i', $value, $m)) {
        return 0;
    }
    return (int) $m[1] * match (strtoupper($m[2])) {
        'G' => 1024 ** 3,
        'M' => 1024 ** 2,
        'K' => 1024,
        default => 1,
    };
}

/** Largest proof upload this server accepts. */
function proof_max_bytes(): int
{
    return min(array_filter([PROOF_MAX_BYTES, ini_bytes('upload_max_filesize'), ini_bytes('post_max_size')], static fn (int $v): bool => $v > 0));
}

function proof_max_label(): string
{
    $mb = proof_max_bytes() / 1024 / 1024;
    return rtrim(rtrim(number_format($mb, 1), '0'), '.') . ' MB';
}

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
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The screenshot is too large (' . proof_max_label() . ' maximum).',
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
            default => 'The screenshot could not be uploaded. Please try again.',
        });
    }
    if ((int) $file['size'] > proof_max_bytes()) {
        throw new AppError('The screenshot is too large (' . proof_max_label() . ' maximum).');
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
