<?php
/**
 * Application bootstrap — every entry script in public/ starts with:
 *
 *     require __DIR__ . '/../app/bootstrap.php';
 *
 * CLI scripts can require it too: they get the libraries without the
 * session, headers or CSRF handling.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('PUBLIC_DIR', APP_ROOT . '/public');
define('STORAGE_DIR', APP_ROOT . '/storage');
define('APP_VERSION', '1.0.0');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/money.php';
require APP_DIR . '/lib/settings.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/ledger.php';
require APP_DIR . '/lib/cycler.php';
require APP_DIR . '/lib/ads.php';
require APP_DIR . '/lib/payments.php';
require APP_DIR . '/lib/uploads.php';
require APP_DIR . '/lib/ui.php';
require APP_DIR . '/lib/admin.php';

set_exception_handler('handle_exception');

if (config('debug')) {
    ini_set('display_errors', '1');
}

if (PHP_SAPI === 'cli') {
    return;
}

if (!defined('INSTALLER') && !is_installed()) {
    redirect(url('install.php'));
}

start_session();
send_security_headers();

if (is_post()) {
    // An upload bigger than post_max_size empties $_POST: say so instead of
    // reporting a confusing CSRF failure.
    if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        abort(413, 'The data you sent is too large. Please use a smaller file.');
    }
    if (!csrf_valid()) {
        abort(419, 'Your session expired. Go back, refresh the page and try again.');
    }
}

if (!defined('INSTALLER')) {
    enforce_maintenance();
    maybe_housekeeping();
}
