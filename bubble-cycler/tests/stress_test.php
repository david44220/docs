<?php
/**
 * Concurrency stress test: 12 buyers and 1 admin hammer the pool in parallel
 * processes, then every accounting invariant is checked.
 *
 *   BUBBLE_TEST_DB=bubble_test BUBBLE_TEST_USER=root BUBBLE_TEST_PASS=secret php tests/stress_test.php
 */
require __DIR__ . '/bootstrap.php';

$admin = fresh_install(['ad_required' => '0', 'min_withdrawal' => (string) u('1'), 'campaign_approval' => '0']);
payment_method_save($admin, null, 'withdrawal', ['name' => 'Test payout', 'min_amount' => '1', 'status' => 'active']);
$buyers = [];
for ($i = 1; $i <= 12; $i++) {
    $buyers[] = $id = register_user('user' . $i, "user$i@example.com", TEST_PASSWORD, $i > 1 ? $buyers[0] : null);
    deposit_manual($admin, $id, u('150'), 'seed');
}

$pipes = [];
$processes = [];
$start = microtime(true);
foreach ([...array_map(static fn (int $id): array => ['buyer', $id], $buyers), ['admin', $admin]] as $n => [$role, $id]) {
    $process = proc_open([PHP_BINARY, __DIR__ . '/stress_worker.php', $role, (string) $id, '60'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$n]);
    if ($process === false) {
        fwrite(STDERR, "Could not start a worker process.\n");
        exit(1);
    }
    $processes[] = $process;
}
$totals = ['buy' => 0, 'refused' => 0, 'errors' => 0, 'popped' => 0];
$stderr = '';
foreach ($pipes as $pipe) {
    $out = stream_get_contents($pipe[1]);
    $stderr .= stream_get_contents($pipe[2]);
    foreach ((array) json_decode(trim($out), true) as $key => $count) {
        $totals[$key] += $count;
    }
}
foreach ($processes as $process) {
    proc_close($process);
}

printf("13 workers finished in %.1fs: %s\n", microtime(true) - $start, json_encode($totals));
if ($stderr !== '') {
    echo "Worker errors:\n$stderr\n";
}
eq($totals['errors'], 0, 'no unexpected errors (deadlocks are retried)');
$pool = pool_state();
printf("pool: %d sold, %d expired, balance %s, platform revenue %s\n", $pool['bubbles_sold'], $pool['bubbles_expired'], money($pool['balance']), money($pool['site_revenue']));
check((int) $pool['bubbles_sold'] > 300, 'hundreds of bubbles sold under contention');
check_invariants('after stress');
finish();
