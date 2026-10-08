<?php
/**
 * HTTP load test: a real web server (PHP's built-in one, with several
 * workers) under concurrent members who buy bubbles, deposit, withdraw and
 * browse, while an admin approves deposits, pays withdrawals and tops up the
 * pool, and visitors hammer the public pages. Afterwards: no server error, no
 * PHP warning, no failed CSRF check, and every accounting invariant holds.
 *
 *   BUBBLE_TEST_DB=bubble_test BUBBLE_TEST_USER=root BUBBLE_TEST_PASS=secret php tests/load_test.php [members] [seconds]
 */
declare(strict_types=1);

if (($argv[1] ?? '') === '--worker') {
    load_worker($argv[2], (int) $argv[3], (int) $argv[4], $argv[5]);
    exit(0);
}

require __DIR__ . '/bootstrap.php';

if (!function_exists('curl_init')) {
    fwrite(STDERR, "The curl extension is required for the load test.\n");
    exit(2);
}

$members = max(2, (int) ($argv[1] ?? 30));
$seconds = max(5, (int) ($argv[2] ?? 45));

echo "== setup: $members members, {$seconds}s\n";
$admin = fresh_install([
    'ad_required' => '0', 'max_registrations_per_ip' => '0', 'admin_2fa_required' => '0',
    'min_withdrawal' => (string) u('1'), 'max_pending_deposits' => '5', 'max_pending_withdrawals' => '3',
]);
$depositMethod = payment_method_save($admin, null, 'deposit', ['name' => 'Load USDT', 'currency' => 'USDT', 'account_label' => 'Wallet', 'account_value' => 'TLoadWallet', 'min_amount' => '1', 'status' => 'active']);
$withdrawMethod = payment_method_save($admin, null, 'withdrawal', ['name' => 'Load PayPal', 'account_label' => 'PayPal email', 'min_amount' => '1', 'status' => 'active']);
for ($i = 1; $i <= $members; $i++) {
    $id = register_user('load' . $i, "load$i@example.com", TEST_PASSWORD, $i > 1 ? 2 : null);
    deposit_manual($admin, $id, u('40'), 'seed');
}
q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash(TEST_PASSWORD, PASSWORD_DEFAULT), $admin]);
touch(STORAGE_DIR . '/installed.lock');
check_invariants('before load');

// Web server with several PHP workers, on a free port, using the test config.
$probe = stream_socket_server('tcp://127.0.0.1:0');
if ($probe === false) {
    fwrite(STDERR, "No free port.\n");
    exit(2);
}
$port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
$serverLog = STORAGE_DIR . '/logs/server.log';
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', PUBLIC_DIR], [1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']], $pipes, null, getenv() + ['PHP_CLI_SERVER_WORKERS' => '8']);
if ($server === false) {
    fwrite(STDERR, "Could not start the PHP web server.\n");
    exit(2);
}
register_shutdown_function(static function () use ($server): void {
    proc_terminate($server);
    proc_close($server);
});
$base = "http://127.0.0.1:$port";
for ($i = 0; $i < 50 && @file_get_contents("$base/health.php") === false; $i++) {
    usleep(100000);
}

echo "== load\n";
$workers = [];
for ($i = 1; $i <= $members; $i++) {
    $workers[] = ['member', $i];
}
$workers[] = ['admin', 0];
$workers[] = ['visitor', 1];
$workers[] = ['visitor', 2];
$procs = [];
$outs = [];
$start = microtime(true);
foreach ($workers as $n => [$role, $id]) {
    $proc = proc_open([PHP_BINARY, __FILE__, '--worker', $role, (string) $id, (string) $seconds, $base . '|' . $depositMethod . '|' . $withdrawMethod],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $outs[$n]);
    if ($proc === false) {
        fwrite(STDERR, "Could not start a worker process.\n");
        exit(2);
    }
    $procs[$n] = $proc;
}
$stats = [];
$stderr = '';
foreach ($procs as $n => $proc) {
    $line = stream_get_contents($outs[$n][1]);
    $stderr .= stream_get_contents($outs[$n][2]);
    proc_close($proc);
    $decoded = json_decode(trim((string) $line), true);
    $stats[] = is_array($decoded) ? $decoded : ['error' => 'worker output: ' . substr((string) $line, 0, 200)];
}
$elapsed = microtime(true) - $start;

// Aggregate
$lat = [];
$status = [];
$errors = [];
$outcomes = [];
foreach ($stats as $s) {
    if (isset($s['error'])) {
        $errors[] = $s['error'];
        continue;
    }
    foreach ($s['lat'] as $action => $values) {
        $lat[$action] = array_merge($lat[$action] ?? [], $values);
    }
    foreach ($s['status'] as $code => $count) {
        $status[$code] = ($status[$code] ?? 0) + $count;
    }
    foreach ($s['outcomes'] as $what => $count) {
        $outcomes[$what] = ($outcomes[$what] ?? 0) + $count;
    }
    array_push($errors, ...$s['errors']);
}
$requests = array_sum($status);
ksort($status);
printf("%d requests in %.1fs (%.0f req/s) · status %s\n", $requests, $elapsed, $requests / $elapsed, json_encode($status));
echo 'outcomes: ', json_encode($outcomes), "\n";
$pct = static function (array $v, float $p): float {
    sort($v);
    return $v === [] ? 0.0 : $v[(int) min(count($v) - 1, floor($p * count($v)))];
};
foreach ($lat as $action => $values) {
    printf("  %-10s %6d × · p50 %5.0f ms · p95 %5.0f ms · p99 %5.0f ms\n", $action, count($values), $pct($values, .5), $pct($values, .95), $pct($values, .99));
}
if ($stderr !== '') {
    echo "worker stderr:\n", substr($stderr, 0, 2000), "\n";
}

eq(count($errors), 0, 'no server errors, failed requests or CSRF failures' . ($errors !== [] ? ': ' . implode(' | ', array_slice(array_unique($errors), 0, 8)) : ''));
check(($outcomes['bought'] ?? 0) > $members, 'members bought bubbles under load (' . ($outcomes['bought'] ?? 0) . ' purchases)');
check(($outcomes['approved'] ?? 0) > 0 && ($outcomes['paid'] ?? 0) > 0, 'admin approved deposits and paid withdrawals during the run');
check(($status['200'] ?? 0) > $requests * 0.9, 'almost every response is a 200 page');
$log = (string) @file_get_contents($serverLog);
check(!preg_match('/(PHP )?(Warning|Fatal error|Notice|Deprecated|Parse error)/i', $log), 'no PHP warnings in the server log');
check(!is_file(STORAGE_DIR . '/logs/app.log') || trim((string) file_get_contents(STORAGE_DIR . '/logs/app.log')) === '', 'no application errors logged');
check_invariants('after load');
finish();

/* -------------------------------------------------------------------------
 * Worker processes (plain curl, no app code)
 * ---------------------------------------------------------------------- */

function load_worker(string $role, int $id, int $seconds, string $config): void
{
    [$base, $depositMethod, $withdrawMethod] = explode('|', $config);
    $jar = tempnam(sys_get_temp_dir(), 'loadjar');
    if ($jar === false) {
        echo json_encode(['error' => 'cannot create a cookie jar']), "\n";
        return;
    }
    $w = ['lat' => [], 'status' => [], 'errors' => [], 'outcomes' => []];
    $deadline = microtime(true) + $seconds;
    $token = '';

    // One request (redirects followed); returns [final url, body].
    $request = static function (string $action, string $path, ?array $post = null) use ($base, $jar, &$w, &$token): array {
        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
            CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 60,
        ]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post + ['_token' => $token]));
        }
        $t = microtime(true);
        $body = (string) curl_exec($ch);
        $ms = (microtime(true) - $t) * 1000;
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $url = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $err = curl_error($ch);
        curl_close($ch);
        $w['lat'][$action][] = round($ms, 1);
        $w['status'][(string) $code] = ($w['status'][(string) $code] ?? 0) + 1;
        $expectedError = in_array($code, [403, 404], true) && str_contains($path, 'nope');
        if ($code === 0 || $code >= 500 || $code === 419 || ($code >= 400 && !$expectedError)) {
            $w['errors'][] = "$action $path → " . ($code === 0 ? $err : (string) $code);
        }
        if (preg_match('/<meta name="csrf-token" content="([a-f0-9]{64})">/', $body, $m)) {
            $token = $m[1];
        }
        return [(string) parse_url($url, PHP_URL_PATH), $body];
    };
    $field = static fn (string $body, string $name): string => preg_match('/name="' . preg_quote($name, '/') . '"[^>]*value="([^"]*)"/', $body, $m) ? html_entity_decode($m[1]) : '';
    $count = static function (string $what) use (&$w): void {
        $w['outcomes'][$what] = ($w['outcomes'][$what] ?? 0) + 1;
    };
    $login = static function (string $user, string $password) use ($request, &$w): void {
        $request('login', '/login.php');
        [$path] = $request('login', '/login.php', ['login' => $user, 'password' => $password, 'next' => '']);
        if (!str_ends_with($path, '/dashboard.php') && !str_ends_with($path, '/admin/index.php')) {
            $w['errors'][] = "sign-in of $user ended on $path";
        }
    };

    if ($role === 'member') {
        $login('load' . $id, 'Bubbly-pass-26');
        while (microtime(true) < $deadline) {
            $dice = random_int(1, 100);
            if ($dice <= 40) {
                [, $page] = $request('buy-page', '/buy.php');
                [$path] = $request('buy', '/buy.php', ['quantity' => (string) random_int(1, 3), 'wallet' => 'purchase', 'nonce' => $field($page, 'nonce')]);
                $count(str_ends_with($path, '/bubbles.php') ? 'bought' : 'buy-refused');
            } elseif ($dice <= 75) {
                $pages = ['/dashboard.php', '/bubbles.php', '/bubbles.php?tab=expired', '/transactions.php', '/referrals.php', '/account.php', '/advertise.php', '/api.php?a=pool'];
                $request('page', $pages[array_rand($pages)]);
            } elseif ($dice <= 85) {
                $request('deposit', '/deposit.php?method=' . $depositMethod);
                [, $body] = $request('deposit', '/deposit.php', ['method_id' => $depositMethod, 'amount' => (string) random_int(5, 20), 'reference' => bin2hex(random_bytes(10)), 'sender' => 'TSender' . $id]);
                $count(str_contains($body, 'toast--success') ? 'deposit-sent' : 'deposit-refused');
            } elseif ($dice <= 95) {
                $request('withdraw', '/withdraw.php?method=' . $withdrawMethod);
                [, $body] = $request('withdraw', '/withdraw.php', ['method_id' => $withdrawMethod, 'amount' => '1.00', 'account' => "load$id@pay.example"]);
                $count(str_contains($body, 'toast--success') ? 'withdraw-sent' : 'withdraw-refused');
            } else {
                $request('logout', '/logout.php', []);
                $login('load' . $id, 'Bubbly-pass-26');
                $count('relogin');
            }
        }
    } elseif ($role === 'admin') {
        $login('boss', 'Bubbly-pass-26');
        while (microtime(true) < $deadline) {
            [, $list] = $request('admin', '/admin/deposits.php?status=pending');
            if (preg_match('/review=(\d+)/', $list, $m)) {
                [, $review] = $request('admin', '/admin/deposits.php?status=pending&review=' . $m[1]);
                [, $body] = $request('approve', '/admin/deposits.php?status=pending', ['action' => 'approve', 'id' => $m[1], 'credit' => $field($review, 'credit'), 'note' => 'load']);
                $count(str_contains($body, 'toast--success') ? 'approved' : 'approve-refused');
            }
            [, $list] = $request('admin', '/admin/withdrawals.php?status=pending');
            if (preg_match('/review=(\d+)/', $list, $m)) {
                $request('admin', '/admin/withdrawals.php?status=pending&review=' . $m[1]);
                [, $body] = $request('pay', '/admin/withdrawals.php?status=pending', ['action' => 'paid', 'id' => $m[1], 'txid' => bin2hex(random_bytes(8)), 'note' => 'load']);
                $count(str_contains($body, 'toast--success') ? 'paid' : 'pay-refused');
            }
            if (random_int(1, 6) === 1) {
                [, $body] = $request('inject', '/admin/bubbles.php', ['action' => 'inject', 'amount' => '0.80', 'note' => 'load top-up']);
                $count(str_contains($body, 'toast--success') ? 'injected' : 'inject-refused');
            }
            $pages = ['/admin/index.php', '/admin/transactions.php', '/admin/users.php', '/admin/bubbles.php', '/admin/logs.php'];
            $request('admin', $pages[array_rand($pages)]);
        }
    } else {
        // Visitors: 8 parallel connections on the public pages.
        $pages = ['/', '/?lang=en', '/login.php', '/register.php', '/terms.php', '/privacy.php', '/api.php?a=pool', '/health.php'];
        while (microtime(true) < $deadline) {
            $multi = curl_multi_init();
            $handles = [];
            for ($k = 0; $k < 8; $k++) {
                $ch = curl_init($base . $pages[array_rand($pages)]);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
                curl_multi_add_handle($multi, $ch);
                $handles[] = [$ch, microtime(true)];
            }
            do {
                curl_multi_exec($multi, $running);
                curl_multi_select($multi, 0.05);
            } while ($running > 0);
            foreach ($handles as [$ch, $t]) {
                $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $w['lat']['public'][] = round((float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000, 1);
                $w['status'][(string) $code] = ($w['status'][(string) $code] ?? 0) + 1;
                if ($code !== 200) {
                    $w['errors'][] = 'public ' . curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) . ' → ' . $code;
                }
                curl_multi_remove_handle($multi, $ch);
                curl_close($ch);
            }
            curl_multi_close($multi);
        }
    }
    @unlink($jar);
    echo json_encode($w), "\n";
}
