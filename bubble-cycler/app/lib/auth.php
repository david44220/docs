<?php
/**
 * Authentication: sessions, login throttling, two-factor sign-in,
 * registration and password resets.
 */
declare(strict_types=1);

const RESERVED_USERNAMES = ['admin', 'administrator', 'root', 'support', 'system', 'moderator', 'staff', 'owner'];
const LOGIN_MAX_FAILURES_IP = 8;       // per IP address …
const LOGIN_MAX_FAILURES_ACCOUNT = 20; // … and per account, in the window below
const LOGIN_WINDOW_SECONDS = 900;
const TWO_FACTOR_WINDOW_SECONDS = 300; // time allowed to type the code after the password
const COMMON_PASSWORDS = [
    '12345678', '123456789', '1234567890', 'password', 'password1', 'password123', 'qwertyuiop', 'qwerty123',
    'iloveyou', '11111111', '00000000', '12341234', 'abcd1234', 'azertyuiop', 'motdepasse', 'letmein123',
    'welcome1', 'admin123', 'administrator', 'bubblecycle', 'bubblecycler', 'sunshine1', '1q2w3e4r', 'princess1', 'football1',
];

/** Changes whenever the password changes, which signs out other sessions. */
function session_fingerprint(array $user): string
{
    return substr(hash('sha256', $user['password_hash'] . '|' . $user['id']), 0, 24);
}

function current_user(bool $refresh = false): ?array
{
    static $user = false;
    if ($user !== false && !$refresh) {
        return $user;
    }
    $user = null;
    $id = (int) ($_SESSION['uid'] ?? 0);
    if ($id > 0) {
        $idle = time() - (int) ($_SESSION['last_seen'] ?? 0);
        $found = $idle <= session_idle_limit() ? row('SELECT * FROM users WHERE id = ?', [$id]) : null;
        if ($found !== null && $found['status'] === 'active'
            && hash_equals(session_fingerprint($found), (string) ($_SESSION['ufp'] ?? ''))) {
            $user = $found;
            $_SESSION['last_seen'] = time();
        } else {
            unset($_SESSION['uid'], $_SESSION['ufp'], $_SESSION['last_seen']);
        }
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        $next = (string) ($_SERVER['REQUEST_URI'] ?? '');
        redirect(url('login.php', ['next' => is_post() ? '' : $next]));
    }
    return $user;
}

function require_admin(): array
{
    $user = current_user();
    if ($user === null) {
        redirect(url('login.php', ['next' => (string) ($_SERVER['REQUEST_URI'] ?? '')]));
    }
    if ($user['role'] !== 'admin') {
        abort(403, 'This area is reserved for administrators.');
    }
    if (admin_needs_2fa_setup($user)) {
        flash('info', 'Protect the admin panel first: set up two-factor authentication.');
        redirect(url('two-factor.php'));
    }
    return $user;
}

function is_admin(?array $user = null): bool
{
    $user ??= current_user();
    return $user !== null && $user['role'] === 'admin';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['2fa'], $_SESSION['_csrf']);
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['ufp'] = session_fingerprint($user);
    $_SESSION['last_seen'] = time();
    q('UPDATE users SET last_login_at = ?, last_ip = ? WHERE id = ?', [now(), client_ip(), (int) $user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie((string) session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

/**
 * Check a username/email + password pair with brute-force protection
 * (per IP and per account). Returns the member, or throws AppError.
 * Does not sign in: the caller decides whether a two-factor step follows.
 */
function check_credentials(string $login, string $password): array
{
    $ip = client_ip();
    $since = gmdate('Y-m-d H:i:s', time() - LOGIN_WINDOW_SECONDS);
    $byIp = (int) val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?', [$ip, $since]);
    $byAccount = $login === '' ? 0 : (int) val('SELECT COUNT(*) FROM login_attempts WHERE login = ? AND attempted_at > ?', [mb_substr($login, 0, 190), $since]);
    if ($byIp >= LOGIN_MAX_FAILURES_IP || $byAccount >= LOGIN_MAX_FAILURES_ACCOUNT) {
        throw new AppError('Too many failed sign-in attempts. Please wait 15 minutes and try again.');
    }

    $user = $login === '' ? null : row('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1', [$login, $login]);
    // Always run one hash verification so response time does not reveal accounts.
    $hash = $user['password_hash'] ?? dummy_password_hash();
    $valid = password_verify($password, $hash) && $user !== null;

    if (!$valid) {
        insert('login_attempts', ['ip' => $ip, 'login' => mb_substr($login, 0, 190), 'attempted_at' => now()]);
        throw new AppError('Incorrect username/email or password.');
    }
    if ($user['status'] !== 'active') {
        throw new AppError('This account is suspended. Please contact support.');
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        q('UPDATE users SET password_hash = ? WHERE id = ?', [$user['password_hash'], (int) $user['id']]);
    }
    q('DELETE FROM login_attempts WHERE ip = ? OR login = ?', [$ip, mb_substr($login, 0, 190)]);
    return $user;
}

/**
 * A throw-away hash with the same algorithm and cost as real ones, so a
 * sign-in for an unknown account takes as long as a wrong password.
 * Regenerated when PHP's default cost changes (e.g. PHP 8.4 moved to 12).
 */
function dummy_password_hash(): string
{
    $hash = setting('login_dummy_hash');
    if ($hash === '' || password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        settings_save(['login_dummy_hash' => $hash]);
    }
    return $hash;
}

/** Sign in with a password only (members without two-factor authentication). */
function attempt_login(string $login, string $password): array
{
    $user = check_credentials($login, $password);
    if (user_has_2fa($user)) {
        throw new AppError('This account uses two-factor authentication.');
    }
    login_user($user);
    return $user;
}

/* -------------------------------------------------------------------------
 * Two-factor sign-in (password accepted, waiting for the 6-digit code)
 * ---------------------------------------------------------------------- */

function begin_two_factor(array $user, string $next): void
{
    session_regenerate_id(true);
    unset($_SESSION['uid'], $_SESSION['ufp']);
    $_SESSION['2fa'] = [
        'uid'  => (int) $user['id'],
        'fp'   => session_fingerprint($user),
        'at'   => time(),
        'next' => $next,
    ];
}

/** The member who passed the password step and still owes a code, if any. */
/**
 * The member half-way through a two-factor sign-in (expires after a few minutes).
 *
 * @phpstan-impure
 */
function pending_two_factor(): ?array
{
    $pending = $_SESSION['2fa'] ?? null;
    if (!is_array($pending) || time() - (int) ($pending['at'] ?? 0) > TWO_FACTOR_WINDOW_SECONDS) {
        unset($_SESSION['2fa']);
        return null;
    }
    $user = row('SELECT * FROM users WHERE id = ?', [(int) $pending['uid']]);
    if ($user === null || $user['status'] !== 'active' || !hash_equals(session_fingerprint($user), (string) $pending['fp'])) {
        unset($_SESSION['2fa']);
        return null;
    }
    return $user;
}

/** Verify the code of the pending sign-in; signs in and returns the "next" URL. */
function complete_two_factor(string $code): string
{
    $user = pending_two_factor();
    if ($user === null) {
        throw new AppError('Your sign-in expired. Please enter your password again.');
    }
    rate_limit('2fa', (string) $user['id'], 6, LOGIN_WINDOW_SECONDS, 'Too many wrong codes. Please wait 15 minutes and sign in again.');
    if (!user_verify_2fa((int) $user['id'], $code)) {
        throw new AppError('That code is not valid. Check your authenticator app and try again.');
    }
    $next = (string) ($_SESSION['2fa']['next'] ?? '');
    login_user($user);
    return $next;
}

/* -------------------------------------------------------------------------
 * Registration
 * ---------------------------------------------------------------------- */

function validate_username(string $username): void
{
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        throw new AppError('Username must be 3–20 characters: letters, numbers and underscores only.');
    }
}

function validate_password(string $password, string $username = ''): void
{
    if (mb_strlen($password) < 8) {
        throw new AppError('Password must be at least 8 characters long.');
    }
    if (mb_strlen($password) > 200) {
        throw new AppError('Password is too long.');
    }
    $lower = mb_strtolower($password);
    $containsName = mb_strlen($username) >= 4 && str_contains($lower, mb_strtolower($username));
    if (in_array($lower, COMMON_PASSWORDS, true) || $containsName) {
        throw new AppError('This password is too easy to guess. Please choose another one.');
    }
}

function validate_email(string $email): void
{
    if (mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new AppError('Please enter a valid email address.');
    }
}

/** Create an account and return its id. */
function register_user(string $username, string $email, string $password, ?int $referrerId = null, string $role = 'user'): int
{
    validate_username($username);
    validate_email($email);
    validate_password($password, $username);
    if ($role !== 'admin' && in_array(strtolower($username), RESERVED_USERNAMES, true)) {
        throw new AppError('This username is reserved. Please choose another one.');
    }
    if (val('SELECT id FROM users WHERE username = ?', [$username]) !== null) {
        throw new AppError('This username is already taken.');
    }
    if (val('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
        throw new AppError('An account with this email already exists.');
    }
    if ($referrerId !== null && val('SELECT id FROM users WHERE id = ?', [$referrerId]) === null) {
        $referrerId = null;
    }

    try {
        return insert('users', [
            'username'      => $username,
            'email'         => mb_strtolower($email),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
            'referrer_id'   => $referrerId,
            'register_ip'   => PHP_SAPI === 'cli' ? null : client_ip(),
            'pops_seen_at'  => now(),
            'created_at'    => now(),
        ]);
    } catch (PDOException $e) {
        if (is_duplicate_key($e)) {
            throw new AppError('This username or email is already registered.');
        }
        throw $e;
    }
}

/** Remember ?ref=username for 30 days so the referral survives browsing. */
function capture_referral(): void
{
    $ref = query('ref');
    if ($ref === '' || !preg_match('/^[A-Za-z0-9_]{3,20}$/', $ref)) {
        return;
    }
    $_SESSION['ref'] = $ref;
    setcookie('bubble_ref', $ref, [
        'expires'  => time() + 30 * 86400,
        'path'     => base_path() === '' ? '/' : base_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function referral_username(): string
{
    $ref = (string) ($_SESSION['ref'] ?? ($_COOKIE['bubble_ref'] ?? ''));
    return preg_match('/^[A-Za-z0-9_]{3,20}$/', $ref) ? $ref : '';
}

/* -------------------------------------------------------------------------
 * Password resets (emailed one-time links, valid for one hour)
 * ---------------------------------------------------------------------- */

/** Always behaves the same whether or not the address exists (no account enumeration). */
function password_reset_request(string $email): void
{
    validate_email($email);
    rate_limit('reset-ip', client_ip(), 5, 3600, 'Too many reset requests. Please try again in an hour.');
    $user = row("SELECT id, username, status FROM users WHERE email = ?", [mb_strtolower($email)]);
    if ($user === null || $user['status'] !== 'active' || rate_count('reset-user', (string) $user['id'], 3600) >= 3) {
        return;
    }
    rate_hit('reset-user', (string) $user['id']);
    $token = bin2hex(random_bytes(32));
    insert('password_resets', [
        'user_id'    => (int) $user['id'],
        'token_hash' => hash('sha256', $token),
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
        'ip'         => client_ip(),
        'created_at' => now(),
    ]);
    notify_email(
        $email,
        'Reset your ' . site_name() . ' password',
        'Reset your password',
        [
            'Hi ' . $user['username'] . ', someone (hopefully you) asked to reset the password of your account.',
            'The link below works once and expires in one hour. If you did not ask for it, ignore this email: your password stays the same.',
        ],
        'reset.php?token=' . $token,
        'Choose a new password'
    );
}

/**
 * The member a reset token belongs to, or null when invalid, used or expired.
 * $lock takes row locks so two submissions of one link cannot both succeed.
 */
function password_reset_user(string $token, bool $lock = false): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return row(
        "SELECT u.* FROM password_resets r JOIN users u ON u.id = r.user_id
          WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > ? AND u.status = 'active'"
            . ($lock ? ' FOR UPDATE' : ''),
        [hash('sha256', $token), now()]
    );
}

function password_reset_complete(string $token, string $password, string $confirm): array
{
    return tx(function () use ($token, $password, $confirm): array {
        $user = password_reset_user($token, true);
        if ($user === null) {
            throw new AppError('This reset link is invalid or has expired. Please request a new one.');
        }
        validate_password($password, $user['username']);
        if ($password !== $confirm) {
            throw new AppError('The two passwords do not match.');
        }
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
        // Invalidate every other outstanding link for this member.
        q('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), (int) $user['id']]);
        return row_required('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
    });
}
