<?php
/**
 * Test harness for the CLI test suites.
 *
 * Runs against a throw-away MySQL/MariaDB database whose name MUST end with
 * "_test" — every table in it is dropped before the tests start.
 *
 *   BUBBLE_TEST_DB=bubble_test BUBBLE_TEST_USER=root BUBBLE_TEST_PASS=secret php tests/cycler_test.php
 */
declare(strict_types=1);

$testDb = [
    'host' => getenv('BUBBLE_TEST_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('BUBBLE_TEST_PORT') ?: 3306),
    'name' => getenv('BUBBLE_TEST_DB') ?: 'bubble_test',
    'user' => getenv('BUBBLE_TEST_USER') ?: 'root',
    'pass' => getenv('BUBBLE_TEST_PASS') ?: '',
];
if (!str_ends_with($testDb['name'], '_test')) {
    fwrite(STDERR, "Refusing to run: the test database name must end with _test (got {$testDb['name']}).\n");
    exit(2);
}

// Point the app at the test database through a temporary config file, and
// keep its logs, mails and uploads in a temporary storage folder.
if (!getenv('BUBBLE_CONFIG')) {
    $configFile = sys_get_temp_dir() . '/bubble-test-' . bin2hex(random_bytes(6)) . '.php';
    file_put_contents($configFile, '<?php return ' . var_export([
        'db'       => $testDb,
        'base_url' => 'https://bubbles.test',
        'app_key'  => base64_encode(random_bytes(32)),
        'debug'    => true,
    ] + ($testConfigExtra ?? []), true) . ';');
    putenv('BUBBLE_CONFIG=' . $configFile);
    register_shutdown_function(static fn () => @unlink($configFile));
}
if (!getenv('BUBBLE_STORAGE')) {
    $storage = sys_get_temp_dir() . '/bubble-storage-' . bin2hex(random_bytes(6));
    mkdir($storage . '/logs', 0700, true);
    putenv('BUBBLE_STORAGE=' . $storage);
    register_shutdown_function(static function () use ($storage): void {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($storage);
    });
}

/** A password the strength rules accept. */
const TEST_PASSWORD = 'Bubbly-pass-26';

require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/lib/installer.php';

$GLOBALS['__passes'] = 0;
$GLOBALS['__fails'] = 0;

function check(bool $condition, string $what): void
{
    if ($condition) {
        $GLOBALS['__passes']++;
        echo "  ok   $what\n";
    } else {
        $GLOBALS['__fails']++;
        echo "  FAIL $what\n";
    }
}

function eq(mixed $actual, mixed $expected, string $what): void
{
    check($actual === $expected, $what . ($actual === $expected ? '' : ' (got ' . var_export($actual, true) . ', expected ' . var_export($expected, true) . ')'));
}

function throws(callable $fn, string $what, string $contains = ''): void
{
    try {
        $fn();
        check(false, $what . ' (no exception)');
    } catch (AppError $e) {
        check($contains === '' || str_contains($e->getMessage(), $contains), $what . ' → ' . $e->getMessage());
    }
}

/** Drop every table, then install a fresh schema with an admin account. Returns the admin id. */
function fresh_install(array $settings = []): int
{
    global $testDb;
    foreach (rows('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE()') as $table) {
        db()->exec('DROP TABLE `' . $table['t'] . '`');
    }
    install_database($testDb, ['site_name' => 'TestBubbles', 'base_url' => 'http://localhost'], [
        'username' => 'boss', 'email' => 'boss@example.com', 'password' => 'supersecret1',
    ]);
    settings_all(true);
    if ($settings !== []) {
        settings_save($settings);
    }
    return (int) val("SELECT id FROM users WHERE username = 'boss'");
}

function u(string $amount): int
{
    return (int) to_units($amount);
}

function member(int $id): array
{
    return row_required('SELECT * FROM users WHERE id = ?', [$id]);
}

/** The ad the buy page would show a member (fails the run when there is none). */
function ad_for(int $userId): array
{
    $ad = ad_start_view($userId);
    if ($ad === null) {
        throw new RuntimeException('Expected an ad to be served.');
    }
    return $ad;
}

/** Decoded text of the base64 MIME parts in raw email data. */
function mail_decode_parts(string $raw): string
{
    preg_match_all('~Content-Transfer-Encoding: base64\r\n\r\n([A-Za-z0-9+/=\r\n]+?)\r\n(?:--|$)~', $raw, $parts);
    return implode("\n", array_map(static fn (string $b): string => (string) base64_decode(str_replace("\r\n", '', $b)), $parts[1]));
}

/** Plain-text bodies of every email written by the "log" mail transport. */
function mail_log_text(): string
{
    return mail_decode_parts((string) @file_get_contents(STORAGE_DIR . '/logs/mail.log'));
}

/** Global invariants that must hold after any sequence of operations. */
function check_invariants(string $label): void
{
    $pool = pool_state();
    $moneyIn = (int) val("SELECT COALESCE(SUM(credit_amount), 0) FROM deposits WHERE status = 'approved'")
        + (int) val("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'admin_credit' AND wallet <> 'ads'")
        + (int) val("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'admin_debit' AND wallet <> 'ads'")
        + (int) $pool['total_injected'];
    $moneyHeld = (int) val('SELECT COALESCE(SUM(purchase_balance + cash_balance), 0) FROM users')
        + (int) $pool['balance'] + (int) $pool['site_revenue']
        + (int) val("SELECT COALESCE(SUM(amount), 0) FROM withdrawals WHERE status IN ('pending', 'paid')");
    eq($moneyHeld, $moneyIn, "$label: every cent is accounted for (" . money($moneyIn) . ')');

    $drift = rows(
        "SELECT u.id FROM users u
           LEFT JOIN (SELECT user_id,
                             SUM(CASE WHEN wallet = 'purchase' THEN amount ELSE 0 END) AS p,
                             SUM(CASE WHEN wallet = 'cash' THEN amount ELSE 0 END) AS c,
                             SUM(CASE WHEN wallet = 'ads' THEN amount ELSE 0 END) AS a
                        FROM transactions GROUP BY user_id) t ON t.user_id = u.id
          WHERE u.purchase_balance <> COALESCE(t.p, 0) OR u.cash_balance <> COALESCE(t.c, 0) OR u.ad_credits <> COALESCE(t.a, 0)"
    );
    eq(count($drift), 0, "$label: ledger matches every wallet");

    $sold = (int) val('SELECT COUNT(*) FROM bubbles');
    $expired = (int) val("SELECT COUNT(*) FROM bubbles WHERE status = 'expired'");
    eq($sold, (int) $pool['bubbles_sold'], "$label: bubbles_sold matches the rows");
    eq((int) val('SELECT COALESCE(MAX(id), 0) FROM bubbles'), $sold, "$label: bubble ids are dense");
    eq($expired, (int) $pool['bubbles_expired'], "$label: bubbles_expired matches the rows");
    eq((int) val("SELECT COUNT(*) FROM bubbles WHERE status = 'expired' AND id > ?", [$expired]), 0, "$label: bubbles expire strictly in order");
    eq((int) val('SELECT COALESCE(SUM(earned), 0) FROM bubbles'), (int) $pool['total_out'], "$label: total paid out = sum of bubble payouts");
    eq((int) val('SELECT COALESCE(SUM(target), 0) FROM bubbles'), (int) $pool['target_sold'], "$label: target_sold matches");
    eq((int) val('SELECT COUNT(*) FROM bubbles b WHERE b.cum_target <> (SELECT SUM(target) FROM bubbles x WHERE x.id <= b.id)'), 0, "$label: running targets are consistent");
    eq((int) $pool['total_in'], (int) $pool['total_out'] + (int) $pool['balance'], "$label: pool in = out + balance");
    eq((int) val(
        'SELECT COUNT(*) FROM transactions t
          WHERE t.balance_after <> t.amount + COALESCE((SELECT p.balance_after FROM transactions p
                 WHERE p.user_id = t.user_id AND p.wallet = t.wallet AND p.id < t.id ORDER BY p.id DESC LIMIT 1), 0)'
    ), 0, "$label: every ledger line carries the running balance");
    eq((int) val("SELECT COUNT(*) FROM transactions WHERE type = 'bubble_payout'"), (int) $pool['bubbles_expired'], "$label: one payout line per expired bubble");
    $head = queue_head($pool);
    check($head === null || (int) $pool['balance'] < (int) $head['target'], "$label: pool never holds enough to expire the head bubble");
}

function finish(): never
{
    echo "\n{$GLOBALS['__passes']} passed, {$GLOBALS['__fails']} failed\n";
    exit($GLOBALS['__fails'] > 0 ? 1 : 0);
}
