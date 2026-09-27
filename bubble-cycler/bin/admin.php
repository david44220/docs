<?php
/**
 * Command-line tools for the operator. Run from the project folder:
 *
 *   php bin/admin.php check                    health check of the server and configuration
 *   php bin/admin.php migrate                  apply pending database migrations
 *   php bin/admin.php housekeeping             delete expired data (add to cron, e.g. hourly)
 *   php bin/admin.php stats                    pool and queue figures
 *   php bin/admin.php unlock <username|ip>     clear failed sign-in attempts
 *   php bin/admin.php reset-2fa <username>     turn off two-factor authentication (lost phone)
 *   php bin/admin.php set-password <username>  set a new random password (or --stdin to read one)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

$command = $argv[1] ?? 'help';
$argument = $argv[2] ?? '';

function out(string $line = ''): void
{
    fwrite(STDOUT, $line . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, 'Error: ' . $message . PHP_EOL);
    exit(1);
}

function member_by_username(string $username): array
{
    if ($username === '') {
        fail('Give a username.');
    }
    return row('SELECT * FROM users WHERE username = ?', [$username]) ?? fail("No member called \"$username\".");
}

function cli_log(string $action, string $details): void
{
    // Audit log entries need an admin id: use the first admin account.
    $adminId = (int) val("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
    if ($adminId > 0) {
        admin_log($adminId, $action, $details . ' (command line)');
    }
}

try {
    switch ($command) {
        case 'check':
            $problems = 0;
            $report = static function (bool $ok, string $label, string $hint = '') use (&$problems): void {
                $problems += $ok ? 0 : 1;
                out(($ok ? '  ok    ' : '  FIX   ') . $label . ($ok || $hint === '' ? '' : ' — ' . $hint));
            };
            $report(PHP_VERSION_ID >= 80100, 'PHP ' . PHP_VERSION, 'PHP 8.1 or newer is required');
            foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'sodium'] as $extension) {
                $report(extension_loaded($extension), "extension $extension", 'enable it in php.ini');
            }
            $report(is_file(config_file()), 'config.php found', 'run the installer');
            $report(is_installed(), 'installed', 'run the installer');
            $report(app_key() !== null, 'app_key set', 'add a 32-byte base64 app_key to config.php');
            $report(mail_base_url() !== '', 'base_url set', "add 'base_url' => 'https://your-domain' to config.php");
            $report(!config('debug'), 'debug off', "set 'debug' => false in config.php");
            $report(str_starts_with(mail_base_url(), 'https://') && (bool) config('force_https', false), 'HTTPS enforced', "use an https base_url and 'force_https' => true");
            $version = (string) val('SELECT VERSION()');
            $report(true, 'database connection (' . $version . ')');
            $report(!migrations_pending(), 'database schema v' . db_version() . ' (code expects v' . DB_VERSION . ')', 'run: php bin/admin.php migrate');
            $skew = abs((int) val('SELECT UNIX_TIMESTAMP()') - time());
            $report($skew <= 30, "database and PHP clocks agree ({$skew}s apart)", 'sync the server clock (NTP) — two-factor codes depend on it');
            foreach (['logs', 'uploads/proofs', 'sessions'] as $folder) {
                $path = STORAGE_DIR . '/' . $folder;
                $report(is_dir($path) ? is_writable($path) : is_writable(dirname($path)), "storage/$folder writable", "chmod/chown $path for the web server user");
            }
            $report(mail_enabled(), 'email configured', 'Admin → Settings → Email (needed for password resets)');
            out();
            out($problems === 0 ? 'Everything looks good.' : "$problems item(s) to fix.");
            exit($problems === 0 ? 0 : 1);

        case 'migrate':
            $from = db_version();
            migrate();
            out($from === DB_VERSION ? 'Database already up to date (v' . DB_VERSION . ').' : "Database migrated from v$from to v" . DB_VERSION . '.');
            break;

        case 'housekeeping':
            foreach (housekeeping() as $table => $count) {
                out(sprintf('  %-16s %d deleted', $table, $count));
            }
            break;

        case 'stats':
            $pool = pool_state();
            out('Pool balance      ' . money($pool['balance']));
            out('Bubbles sold      ' . number_format((int) $pool['bubbles_sold']));
            out('Bubbles expired   ' . number_format((int) $pool['bubbles_expired']));
            out('Waiting in queue  ' . number_format(queue_length($pool)));
            out('Paid to members   ' . money($pool['total_out']));
            out('Platform revenue  ' . money($pool['site_revenue']));
            out('Queue gap         ' . money(max(0, (int) $pool['target_sold'] - (int) $pool['total_out'] - (int) $pool['balance'])) . '  (money the pool still needs to expire every waiting bubble)');
            break;

        case 'unlock':
            if ($argument === '') {
                fail('Give a username, email or IP address.');
            }
            $deleted = q('DELETE FROM login_attempts WHERE login = ? OR ip = ?', [$argument, $argument])->rowCount();
            out("Cleared $deleted failed sign-in attempt(s) for $argument.");
            break;

        case 'reset-2fa':
            $member = member_by_username($argument);
            user_disable_2fa((int) $member['id']);
            cli_log('user.2fa_reset', sprintf('Turned off two-factor authentication of member #%d', $member['id']));
            out("Two-factor authentication is off for {$member['username']}. They can set it up again after signing in.");
            break;

        case 'set-password':
            $member = member_by_username($argument);
            if (in_array('--stdin', $argv, true)) {
                $password = rtrim((string) fgets(STDIN), "\r\n");
            } else {
                $password = substr(strtr(base64_encode(random_bytes(18)), '+/', '-_'), 0, 20);
            }
            validate_password($password, $member['username']);
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $member['id']]);
            q('DELETE FROM login_attempts WHERE login IN (?, ?)', [$member['username'], $member['email']]);
            cli_log('user.password', sprintf('Reset the password of member #%d', $member['id']));
            out(in_array('--stdin', $argv, true) ? "Password updated for {$member['username']}." : "New password for {$member['username']}: $password");
            break;

        default:
            $help = (string) file_get_contents(__FILE__);
            preg_match('~/\*\*(.*?)\*/~s', $help, $m);
            out(trim((string) preg_replace('/^\s*\* ?/m', '', $m[1] ?? '')));
            exit($command === 'help' ? 0 : 1);
    }
} catch (AppError $e) {
    fail($e->getMessage());
}
