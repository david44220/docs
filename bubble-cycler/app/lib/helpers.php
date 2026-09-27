<?php
/**
 * Core helpers: configuration, escaping, URLs, sessions, CSRF, flash
 * messages, request input, dates, view rendering and pagination.
 */
declare(strict_types=1);

/** Error that is safe to show to the visitor (validation, business rules). */
class AppError extends RuntimeException
{
}

/* -------------------------------------------------------------------------
 * Configuration
 * ---------------------------------------------------------------------- */

/** app/config.php, or the file named by the BUBBLE_CONFIG environment variable. */
function config_file(): string
{
    $custom = getenv('BUBBLE_CONFIG');
    return is_string($custom) && $custom !== '' ? $custom : APP_DIR . '/config.php';
}

function config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $file = config_file();
        $config = is_file($file) ? (array) require $file : [];
    }
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function is_installed(): bool
{
    return is_file(config_file()) && is_file(STORAGE_DIR . '/installed.lock');
}

/* -------------------------------------------------------------------------
 * Output & URLs
 * ---------------------------------------------------------------------- */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL path of the public/ folder as seen by the browser, without a trailing
 * slash ("" when public/ is the document root).
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = trim((string) config('base_url', ''));
    if ($configured !== '') {
        return $base = rtrim((string) parse_url($configured, PHP_URL_PATH), '/');
    }
    if (PHP_SAPI === 'cli') {
        return $base = '';
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $file = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
    $public = realpath(PUBLIC_DIR) ?: PUBLIC_DIR;
    $relative = ltrim(str_replace('\\', '/', substr($file, strlen($public))), '/');

    if ($relative !== '' && str_ends_with($script, '/' . $relative)) {
        $base = substr($script, 0, -strlen($relative) - 1);
    } else {
        $base = rtrim(dirname($script), '/');
    }

    // Requests routed through the project-root .htaccess never show /public.
    $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (str_ends_with($base, '/public') && !str_starts_with($requestPath, $base . '/') && $requestPath !== $base) {
        $base = substr($base, 0, -strlen('/public'));
    }

    return $base;
}

function url(string $path = '', array $query = []): string
{
    $url = base_path() . '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }
    return $url;
}

function asset(string $path): string
{
    $file = PUBLIC_DIR . '/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : APP_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    return config('trust_proxy', false)
        && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Absolute URL (scheme + host) for links people copy and share. */
function absolute_url(string $path = '', array $query = []): string
{
    $configured = trim((string) config('base_url', ''));
    if ($configured !== '' && preg_match('#^https?://#i', $configured)) {
        $origin = preg_replace('#^(https?://[^/]+).*$#i', '$1', $configured);
    } else {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host) ?: 'localhost';
        $origin = (is_https() ? 'https://' : 'http://') . $host;
    }
    return $origin . url($path, $query);
}

function redirect(string $to, int $status = 302): never
{
    header('Location: ' . $to, true, $status);
    exit;
}

/** Only allow redirects to paths on this site ("next" parameters). */
function safe_next(string $next, string $fallback): string
{
    if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, '\\')) {
        return $fallback;
    }
    return $next;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function abort(int $status, string $message = ''): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    echo view_capture('errors/error', ['code' => $status, 'message' => $message]);
    exit;
}

function log_error(Throwable $e): void
{
    $line = sprintf(
        "[%s] %s: %s in %s:%d\n%s\n\n",
        gmdate('Y-m-d H:i:s'),
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    $dir = STORAGE_DIR . '/logs';
    if (!@error_log($line, 3, $dir . '/app.log')) {
        error_log($line);
    }
}

function handle_exception(Throwable $e): void
{
    log_error($e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, (string) $e . PHP_EOL);
        exit(1);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    $debug = config('debug') ? $e::class . ': ' . $e->getMessage() . "\n\n" . $e->getTraceAsString() : '';
    try {
        echo view_capture('errors/error', ['code' => 500, 'message' => '', 'debug' => $debug]);
    } catch (Throwable) {
        echo 'Something went wrong. Please try again later.';
    }
}

/* -------------------------------------------------------------------------
 * Session, security headers, CSRF, flash
 * ---------------------------------------------------------------------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('bubble_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() === '' ? '/' : base_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    if (config('csp', true)) {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; "
            . "style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; connect-src 'self'; "
            . "frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'");
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $known = $_SESSION['_csrf'] ?? '';
    return is_string($sent) && is_string($known) && $sent !== '' && $known !== '' && hash_equals($known, $sent);
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($messages) ? $messages : [];
}

/* -------------------------------------------------------------------------
 * Request input
 * ---------------------------------------------------------------------- */

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Trimmed string from $_POST. */
function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/** Trimmed string from $_GET. */
function query(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function query_int(string $key, int $default = 0): int
{
    $value = query($key);
    return preg_match('/^-?\d{1,10}$/', $value) ? (int) $value : $default;
}

function client_ip(): string
{
    $header = (string) config('ip_header', '');
    if ($header !== '' && !empty($_SERVER[$header])) {
        $candidate = trim(explode(',', (string) $_SERVER[$header])[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/* -------------------------------------------------------------------------
 * Dates (stored in UTC, displayed in the configured timezone)
 * ---------------------------------------------------------------------- */

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function utc_ts(?string $datetime): int
{
    if ($datetime === null || $datetime === '') {
        return 0;
    }
    return (int) strtotime($datetime . ' UTC');
}

function display_tz(): DateTimeZone
{
    static $tz = null;
    if ($tz === null) {
        try {
            $tz = new DateTimeZone(setting('timezone', 'UTC') ?: 'UTC');
        } catch (Throwable) {
            $tz = new DateTimeZone('UTC');
        }
    }
    return $tz;
}

function fmt_date(?string $datetime, string $format = 'M j, Y · H:i'): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $date = new DateTimeImmutable($datetime, new DateTimeZone('UTC'));
    return $date->setTimezone(display_tz())->format($format);
}

function time_ago(?string $datetime): string
{
    $ts = utc_ts($datetime);
    if ($ts === 0) {
        return '—';
    }
    $diff = time() - $ts;
    if ($diff < 45) {
        return 'just now';
    }
    $units = [
        ['year', 31536000], ['month', 2592000], ['week', 604800],
        ['day', 86400], ['hour', 3600], ['minute', 60],
    ];
    foreach ($units as [$name, $seconds]) {
        if ($diff >= $seconds) {
            $count = (int) floor($diff / $seconds);
            return $count . ' ' . $name . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

/* -------------------------------------------------------------------------
 * Views
 * ---------------------------------------------------------------------- */

function view_capture(string $view, array $data = []): string
{
    $__file = APP_DIR . '/views/' . $view . '.php';
    if (!is_file($__file)) {
        throw new RuntimeException('View not found: ' . $view);
    }
    extract($data, EXTR_SKIP);
    ob_start();
    try {
        include $__file;
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
    return (string) ob_get_clean();
}

/** Render a view inside a layout (app, admin, public, auth) and stop. */
function render(string $view, array $data = [], string $layout = 'app'): never
{
    $data['content'] = view_capture($view, $data);
    echo view_capture('layouts/' . $layout, $data);
    exit;
}

function partial(string $name, array $data = []): string
{
    return view_capture('partials/' . $name, $data);
}

/* -------------------------------------------------------------------------
 * Pagination
 * ---------------------------------------------------------------------- */

function paginate(int $total, int $perPage = 20): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, query_int('page', 1)), $pages);
    return [
        'total'  => $total,
        'page'   => $page,
        'pages'  => $pages,
        'limit'  => $perPage,
        'offset' => ($page - 1) * $perPage,
    ];
}

/** Current query string with some keys replaced (for filters & paging links). */
function current_url_with(array $changes): string
{
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $params = array_merge($_GET, $changes);
    $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');
    return $path . ($params ? '?' . http_build_query($params) : '');
}

/* -------------------------------------------------------------------------
 * Misc
 * ---------------------------------------------------------------------- */

function str_limit(string $text, int $length): string
{
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
}

/** "david44" → "da•••4": used wherever other members are shown publicly. */
function mask_name(string $name): string
{
    $length = mb_strlen($name);
    if ($length <= 3) {
        return mb_substr($name, 0, 1) . '•••';
    }
    return mb_substr($name, 0, 2) . '•••' . mb_substr($name, -1);
}

function valid_http_url(string $url, bool $httpsOnly = false): bool
{
    if ($url === '' || mb_strlen($url) > 500 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return $httpsOnly ? $scheme === 'https' : in_array($scheme, ['http', 'https'], true);
}

function url_host(string $url): string
{
    $host = (string) parse_url($url, PHP_URL_HOST);
    return preg_replace('/^www\./i', '', $host) ?: $url;
}

function plural(int $count, string $singular, ?string $plural = null): string
{
    return number_format($count) . ' ' . ($count === 1 ? $singular : ($plural ?? $singular . 's'));
}

function enforce_maintenance(): void
{
    if (!setting_bool('maintenance_mode')) {
        return;
    }
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($script, ['login.php', 'logout.php'], true)) {
        return;
    }
    $user = current_user();
    if ($user !== null && $user['role'] === 'admin') {
        return;
    }
    http_response_code(503);
    header('Retry-After: 3600');
    echo view_capture('errors/error', ['code' => 503, 'message' => '']);
    exit;
}

/** Cheap periodic clean-up, run on ~1 % of requests. */
function maybe_housekeeping(): void
{
    if (random_int(1, 100) !== 1) {
        return;
    }
    try {
        q('DELETE FROM login_attempts WHERE attempted_at < ?', [gmdate('Y-m-d H:i:s', time() - 86400)]);
        q(
            'DELETE FROM ad_views WHERE completed_at IS NULL AND used_at IS NULL AND started_at < ?',
            [gmdate('Y-m-d H:i:s', time() - 3 * 86400)]
        );
    } catch (Throwable $e) {
        log_error($e);
    }
}
