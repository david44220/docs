<?php
/**
 * Security helpers: rate limiting, TOTP two-factor authentication
 * (RFC 6238, compatible with Google Authenticator, Authy, 1Password…),
 * recovery codes and encryption of secrets at rest.
 */
declare(strict_types=1);

/* -------------------------------------------------------------------------
 * Rate limiting
 * ---------------------------------------------------------------------- */

/** Number of hits recorded for $bucket/$subject during the last $window seconds. */
function rate_count(string $bucket, string $subject, int $window): int
{
    return (int) val(
        'SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND subject = ? AND created_at > ?',
        [$bucket, mb_substr($subject, 0, 190), gmdate('Y-m-d H:i:s', time() - $window)]
    );
}

function rate_hit(string $bucket, string $subject): void
{
    insert('rate_limits', ['bucket' => $bucket, 'subject' => mb_substr($subject, 0, 190), 'created_at' => now()]);
}

/** Throw when $max hits were already recorded in the window; otherwise record one more. */
function rate_limit(string $bucket, string $subject, int $max, int $window, string $message): void
{
    if ($max > 0 && rate_count($bucket, $subject, $window) >= $max) {
        throw new AppError($message);
    }
    rate_hit($bucket, $subject);
}

/* -------------------------------------------------------------------------
 * Secrets at rest (sodium secretbox with the app key from config.php)
 * ---------------------------------------------------------------------- */

function app_key(): ?string
{
    $key = base64_decode((string) config('app_key', ''), true);
    return is_string($key) && strlen($key) === 32 ? $key : null;
}

function seal_secret(string $plain): string
{
    $key = app_key();
    if ($key === null || !function_exists('sodium_crypto_secretbox')) {
        return 'plain:' . $plain;
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 'sb1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
}

function open_secret(?string $sealed): ?string
{
    if ($sealed === null || $sealed === '') {
        return null;
    }
    if (str_starts_with($sealed, 'plain:')) {
        return substr($sealed, 6);
    }
    $key = app_key();
    if (!str_starts_with($sealed, 'sb1:') || $key === null || !function_exists('sodium_crypto_secretbox_open')) {
        return null;
    }
    $raw = base64_decode(substr($sealed, 4), true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        return null;
    }
    $plain = sodium_crypto_secretbox_open(
        substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        $key
    );
    return $plain === false ? null : $plain;
}

/* -------------------------------------------------------------------------
 * TOTP (RFC 6238: HMAC-SHA1, 30-second steps, 6 digits)
 * ---------------------------------------------------------------------- */

const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/** RFC 4648 base32 without padding (the format authenticator apps expect). */
function base32_encode(string $binary): string
{
    $out = '';
    $buffer = 0;
    $bits = 0;
    for ($i = 0, $length = strlen($binary); $i < $length; $i++) {
        $buffer = ($buffer << 8) | ord($binary[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $out .= BASE32_ALPHABET[($buffer >> $bits) & 31];
        }
        $buffer &= (1 << $bits) - 1;
    }
    if ($bits > 0) {
        $out .= BASE32_ALPHABET[($buffer << (5 - $bits)) & 31];
    }
    return $out;
}

/** Decode base32 (spaces, dashes, padding and lower case allowed); '' when invalid. */
function base32_decode(string $text): string
{
    $text = strtoupper(preg_replace('/[\s=-]/', '', $text) ?? '');
    $out = '';
    $buffer = 0;
    $bits = 0;
    for ($i = 0, $length = strlen($text); $i < $length; $i++) {
        $index = strpos(BASE32_ALPHABET, $text[$i]);
        if ($index === false) {
            return '';
        }
        $buffer = ($buffer << 5) | $index;
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($buffer >> $bits) & 255);
            $buffer &= (1 << $bits) - 1;
        }
    }
    return $out;
}

function totp_new_secret(): string
{
    return base32_encode(random_bytes(20));
}

function totp_code(string $secret, int $step, int $digits = 6): string
{
    $hash = hash_hmac('sha1', pack('J', $step), base32_decode($secret), true);
    $offset = ord($hash[19]) & 0x0f;
    $value = ((ord($hash[$offset]) & 0x7f) << 24)
        | (ord($hash[$offset + 1]) << 16)
        | (ord($hash[$offset + 2]) << 8)
        | ord($hash[$offset + 3]);
    return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
}

/**
 * Check a 6-digit code (±1 step for clock drift). Returns the matching time
 * step, or null. Codes at or before $lastStep are refused (no replay).
 */
function totp_verify(string $secret, string $code, ?int $lastStep = null, ?int $now = null): ?int
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return null;
    }
    $current = intdiv($now ?? time(), 30);
    for ($step = $current - 1; $step <= $current + 1; $step++) {
        if (($lastStep === null || $step > $lastStep) && hash_equals(totp_code($secret, $step), $code)) {
            return $step;
        }
    }
    return null;
}

function totp_uri(string $secret, string $account): string
{
    $issuer = site_name();
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
        . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
}

/** Ten one-time recovery codes like "7KQ4-M2XD". Returns [plain codes, hashes to store]. */
function recovery_codes_generate(): array
{
    $plain = [];
    for ($i = 0; $i < 10; $i++) {
        $raw = substr(base32_encode(random_bytes(5)), 0, 8);
        $plain[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
    }
    return [$plain, array_map('recovery_code_hash', $plain)];
}

function recovery_code_hash(string $code): string
{
    return hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? ''));
}

/* -------------------------------------------------------------------------
 * Two-factor state for a member
 * ---------------------------------------------------------------------- */

function user_has_2fa(array $user): bool
{
    return !empty($user['totp_enabled_at']) && !empty($user['totp_secret']);
}

/** Verify a TOTP or recovery code for a member and consume it. */
function user_verify_2fa(int $userId, string $code): bool
{
    return tx(function () use ($userId, $code): bool {
        $user = row('SELECT id, totp_secret, totp_recovery, totp_last_step, totp_enabled_at FROM users WHERE id = ? FOR UPDATE', [$userId]);
        if ($user === null || !user_has_2fa($user)) {
            return false;
        }
        $secret = open_secret($user['totp_secret']);
        if ($secret === null) {
            return false;
        }
        $step = totp_verify($secret, $code, $user['totp_last_step'] !== null ? (int) $user['totp_last_step'] : null);
        if ($step !== null) {
            q('UPDATE users SET totp_last_step = ? WHERE id = ?', [$step, $userId]);
            return true;
        }
        // Recovery codes are longer than 6 characters.
        $hashes = json_decode((string) $user['totp_recovery'], true);
        $hash = recovery_code_hash($code);
        if (is_array($hashes) && strlen(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '') === 8) {
            $index = array_search($hash, $hashes, true);
            if ($index !== false) {
                unset($hashes[$index]);
                q('UPDATE users SET totp_recovery = ? WHERE id = ?', [json_encode(array_values($hashes)), $userId]);
                return true;
            }
        }
        return false;
    });
}

/** $verifiedStep is the time step of the code used during setup; it cannot be replayed at sign-in. */
function user_enable_2fa(int $userId, string $secret, array $recoveryHashes, ?int $verifiedStep = null): void
{
    q(
        'UPDATE users SET totp_secret = ?, totp_recovery = ?, totp_last_step = ?, totp_enabled_at = ? WHERE id = ?',
        [seal_secret($secret), json_encode($recoveryHashes), $verifiedStep ?? intdiv(time(), 30), now(), $userId]
    );
}

function user_set_recovery_codes(int $userId, array $recoveryHashes): void
{
    q('UPDATE users SET totp_recovery = ? WHERE id = ?', [json_encode($recoveryHashes), $userId]);
}

function recovery_codes_left(array $user): int
{
    $hashes = json_decode((string) ($user['totp_recovery'] ?? ''), true);
    return is_array($hashes) ? count($hashes) : 0;
}

function user_disable_2fa(int $userId): void
{
    q('UPDATE users SET totp_secret = NULL, totp_recovery = NULL, totp_last_step = NULL, totp_enabled_at = NULL WHERE id = ?', [$userId]);
}

/** Admins must use two-factor authentication when the setting is on. */
function admin_needs_2fa_setup(array $user): bool
{
    return $user['role'] === 'admin' && setting_bool('admin_2fa_required') && !user_has_2fa($user);
}
