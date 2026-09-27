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

// Point the app at the test database through a temporary config file.
if (!getenv('BUBBLE_CONFIG')) {
    $configFile = sys_get_temp_dir() . '/bubble-test-' . bin2hex(random_bytes(6)) . '.php';
    file_put_contents($configFile, '<?php return ' . var_export(['db' => $testDb, 'base_url' => '', 'debug' => true], true) . ';');
    putenv('BUBBLE_CONFIG=' . $configFile);
    register_shutdown_function(static fn () => @unlink($configFile));
}

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
    return row('SELECT * FROM users WHERE id = ?', [$id]);
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
    $head = queue_head($pool);
    check($head === null || (int) $pool['balance'] < (int) $head['target'], "$label: pool never holds enough to expire the head bubble");
}

function finish(): never
{
    echo "\n{$GLOBALS['__passes']} passed, {$GLOBALS['__fails']} failed\n";
    exit($GLOBALS['__fails'] > 0 ? 1 : 0);
}
