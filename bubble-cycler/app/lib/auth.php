<?php
/**
 * Authentication: sessions, login throttling, registration.
 */
declare(strict_types=1);

const RESERVED_USERNAMES = ['admin', 'administrator', 'root', 'support', 'system', 'moderator', 'staff', 'owner'];
const LOGIN_MAX_FAILURES = 8;
const LOGIN_WINDOW_SECONDS = 900;

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
        $found = row('SELECT * FROM users WHERE id = ?', [$id]);
        if ($found !== null && $found['status'] === 'active'
            && hash_equals(session_fingerprint($found), (string) ($_SESSION['ufp'] ?? ''))) {
            $user = $found;
        } else {
            unset($_SESSION['uid'], $_SESSION['ufp']);
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
    $_SESSION['uid'] = (int) $user['id'];
    $_SESSION['ufp'] = session_fingerprint($user);
    unset($_SESSION['_csrf']);
    q('UPDATE users SET last_login_at = ?, last_ip = ? WHERE id = ?', [now(), client_ip(), (int) $user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}

/** Returns the user on success, throws AppError otherwise. */
function attempt_login(string $login, string $password): array
{
    $ip = client_ip();
    $since = gmdate('Y-m-d H:i:s', time() - LOGIN_WINDOW_SECONDS);
    $failures = (int) val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?', [$ip, $since]);
    if ($failures >= LOGIN_MAX_FAILURES) {
        throw new AppError('Too many failed sign-in attempts. Please wait 15 minutes and try again.');
    }

    $user = $login === '' ? null : row('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1', [$login, $login]);
    // Always run one hash verification so response time does not reveal accounts.
    $hash = $user['password_hash'] ?? '$2y$12$Dcj5rmGCW4M0XE5dE2z59O2HFuxlyuOEI0Gv4vlPay/C5WPaDjVou';
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
    q('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
    login_user($user);
    return $user;
}

function validate_username(string $username): void
{
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        throw new AppError('Username must be 3–20 characters: letters, numbers and underscores only.');
    }
}

function validate_password(string $password): void
{
    if (mb_strlen($password) < 8) {
        throw new AppError('Password must be at least 8 characters long.');
    }
    if (mb_strlen($password) > 200) {
        throw new AppError('Password is too long.');
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
    validate_password($password);
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
