<?php
/**
 * Cycler test suite: FIFO pool maths, ad gate, deposits, withdrawals,
 * advertising, pool top-ups and batch payouts, two-factor authentication,
 * password resets, rate limits and schema migrations, with global
 * invariants checked after each step.
 *
 *   BUBBLE_TEST_DB=bubble_test BUBBLE_TEST_USER=root BUBBLE_TEST_PASS=secret php tests/cycler_test.php
 */
require __DIR__ . '/bootstrap.php';

echo "== install\n";
$admin = fresh_install();
eq(setting('site_name'), 'TestBubbles', 'site name saved');
eq(setting_int('bubble_price'), 1000000, 'default price 1.00');
eq((int) val("SELECT COUNT(*) FROM users WHERE role = 'admin'"), 1, 'admin created');
eq((int) val('SELECT COUNT(*) FROM ad_campaigns WHERE is_house = 1'), 1, 'house ad seeded');
eq((int) val("SELECT COUNT(*) FROM payment_methods WHERE status = 'inactive'"), 3, 'example payment methods seeded inactive');

echo "== members\n";
$a = register_user('alice', 'alice@example.com', TEST_PASSWORD);
$b = register_user('bob_1', 'bob@example.com', TEST_PASSWORD);
$c = register_user('carol', 'carol@example.com', TEST_PASSWORD, $a);
throws(fn () => register_user('alice', 'x@example.com', TEST_PASSWORD), 'duplicate username rejected', 'taken');
throws(fn () => register_user('ALICE', 'x2@example.com', TEST_PASSWORD), 'case-insensitive duplicate rejected', 'taken');
throws(fn () => register_user('admin', 'x3@example.com', TEST_PASSWORD), 'reserved username rejected', 'reserved');
throws(fn () => register_user('dave', 'not-an-email', TEST_PASSWORD), 'bad email rejected', 'email');
throws(fn () => register_user('dave', 'dave@example.com', 'short'), 'short password rejected', '8 characters');
throws(fn () => register_user('dave', 'dave@example.com', 'password123'), 'common password rejected', 'too easy');
throws(fn () => register_user('dave', 'dave@example.com', 'Dave-2026-x'), 'password containing the username rejected', 'too easy');

foreach ([$a, $b, $c] as $id) { deposit_manual($admin, $id, u('20'), 'test funds'); }
eq((int) member($a)['purchase_balance'], u('20'), 'manual deposit credited');

echo "== ad gate\n";
throws(fn () => buy_bubbles($a, 1, 'purchase', ''), 'purchase without ad refused', 'sponsored');
$ad = ad_for($a);
check((int) ($ad['campaign']['is_house'] ?? 0) === 1, 'house ad served when no member campaigns');
$token = $ad['view']['token'];
throws(fn () => buy_bubbles($a, 1, 'purchase', $token), 'purchase before countdown refused', 'unlocks in');
$again = ad_for($a);
eq($again['view']['id'], $ad['view']['id'], 'refresh re-uses the same ad view');
q('UPDATE ad_views SET started_at = ? WHERE token = ?', [gmdate('Y-m-d H:i:s', time() - 11), $token]);
throws(fn () => buy_bubbles($a, 1, 'purchase', 'deadbeef' . str_repeat('0', 24)), 'unknown token refused', 'find');
throws(fn () => buy_bubbles($b, 1, 'purchase', $token), "someone else's token refused", 'find');

echo "== FIFO cycle\n";
$r1 = buy_bubbles($a, 1, 'purchase', $token);
eq($r1['first'], 1, 'first bubble is #1');
eq(count($r1['popped']), 0, 'nothing expires on first purchase');
throws(fn () => buy_bubbles($a, 1, 'purchase', $token), 'ad view cannot be reused', 'already unlocked');
$pool = pool_state();
eq((int) $pool['balance'], u('0.80'), 'pool holds 0.80');
$s1 = bubble_state(row_required('SELECT * FROM bubbles WHERE id = 1'), $pool);
eq($s1['state'], 'filling', '#1 is filling');
eq(round($s1['fill']), 50.0, '#1 is 50% full');
eq((int) val('SELECT views FROM ad_campaigns WHERE is_house = 1'), 1, 'house ad view counted');

settings_save(['ad_required' => '0']);
$r2 = buy_bubbles($b, 1, 'purchase');
eq(array_column($r2['popped'], 'id'), [1], "bob's purchase expires #1");
eq((int) member($a)['cash_balance'], u('1.60'), 'alice paid 1.60');
eq((int) pool_state()['balance'], 0, 'pool emptied');

$r3 = buy_bubbles($c, 3, 'purchase');
eq([$r3['first'], $r3['last']], [3, 5], 'carol gets #3-#5');
eq(array_column($r3['popped'], 'id'), [2], "carol's purchase expires #2");
eq((int) member($b)['cash_balance'], u('1.60'), 'bob paid 1.60');
eq((int) member($a)['cash_balance'], u('1.75'), 'alice got 3 x 0.05 referral');
eq((int) member($c)['ad_credits'], 150, 'carol got 150 ad credits');
$pool = pool_state();
eq((int) $pool['balance'], u('0.80'), 'pool holds 0.80 for #3');
eq((int) $pool['site_revenue'], u('0.85'), 'platform share = 5 x 0.20 - 0.15');
eq((int) $pool['referral_paid'], u('0.15'), 'referral paid tracked');
$s4 = bubble_state(row_required('SELECT * FROM bubbles WHERE id = 4'), $pool);
eq([$s4['state'], $s4['position'], $s4['needed'], $s4['sales']], ['rising', 2, u('2.40'), 3], '#4 rising, needs 2.40 = 3 sales');
$q = queue_quote($pool, 1);
eq([$q['first_id'], $q['ahead'], $q['needed'], $q['sales']], [6, 3, u('4.80'), 6], 'quote for next buyer (0.80+1.60+1.60+1.60-0.80)');
check_invariants('after cycle');

echo "== limits\n";
throws(fn () => buy_bubbles($b, 0, 'purchase'), 'zero quantity refused');
throws(fn () => buy_bubbles($b, 26, 'purchase'), 'over max per purchase refused', 'up to 25');

$r = buy_bubbles($b, 1, 'cash');
eq((int) member($b)['cash_balance'], u('0.60'), 're-buy with cash balance works');
throws(fn () => buy_bubbles($b, 2, 'cash'), 'insufficient cash refused', 'cash balance');
settings_save(['allow_cash_purchase' => '0']);
throws(fn () => buy_bubbles($b, 1, 'cash'), 'cash purchase can be disabled', 'disabled');
settings_save(['allow_cash_purchase' => '1', 'max_active_bubbles' => '2']);
throws(fn () => buy_bubbles($c, 1, 'purchase'), 'max active bubbles enforced', 'up to 2');
settings_save(['max_active_bubbles' => '0']);
check_invariants('after limits');

echo "== deposits\n";
$dm = payment_method_save($admin, null, 'deposit', ['name' => 'USDT TRC20', 'currency' => 'usdt', 'account_value' => 'TXYZ123', 'min_amount' => '5', 'max_amount' => '1000', 'fee_percent' => '2', 'fee_fixed' => '0.10', 'status' => 'active']);
$method = payment_method($dm, 'deposit') ?? throw new RuntimeException('Deposit method missing.');
eq($method['currency'], 'USDT', 'currency upper-cased');
eq(method_fee($method, u('10')), u('0.30'), 'fee = 2% + 0.10');
throws(fn () => deposit_create($a, $dm, '2', 'abcd1234', '', null), 'below minimum refused', 'minimum');
throws(fn () => deposit_create($a, $dm, 'abc', 'abcd1234', '', null), 'garbage amount refused');
throws(fn () => deposit_create($a, $dm, '10', 'ab', '', null), 'short reference refused', 'reference');
$d1 = deposit_create($a, $dm, '10', 'tx-0001', 'my wallet', null);
throws(fn () => deposit_create($b, $dm, '10', 'tx-0001', '', null), 'duplicate reference refused', 'already');
$dep = row_required('SELECT * FROM deposits WHERE id = ?', [$d1]);
eq([(int) $dep['amount'], (int) $dep['fee'], (int) $dep['credit_amount'], $dep['status']], [u('10'), u('0.30'), u('9.70'), 'pending'], 'deposit pending with fee');
$before = (int) member($a)['purchase_balance'];
deposit_approve($admin, $d1, null, 'ok');
eq((int) member($a)['purchase_balance'] - $before, u('9.70'), 'approved deposit credited');
throws(fn () => deposit_approve($admin, $d1, null), 'double approval refused', 'no longer pending');
$d2 = deposit_create($b, $dm, '50', 'tx-0002', '', null);
deposit_approve($admin, $d2, u('45'), 'only 45 received');
eq((int) row_required('SELECT credit_amount FROM deposits WHERE id = ?', [$d2])['credit_amount'], u('45'), 'credit override stored');
$d3 = deposit_create($c, $dm, '20', 'tx-0003', '', null);
deposit_reject($admin, $d3, 'not found on chain');
eq(row_required('SELECT status FROM deposits WHERE id = ?', [$d3])['status'], 'rejected', 'deposit rejected');
q("UPDATE payment_methods SET require_proof = 1 WHERE id = ?", [$dm]);
throws(fn () => deposit_create($c, $dm, '20', 'tx-0004', '', null), 'proof required when configured', 'screenshot');
check_invariants('after deposits');

echo "== withdrawals\n";
$wm = payment_method_save($admin, null, 'withdrawal', ['name' => 'PayPal', 'account_label' => 'Your PayPal email', 'min_amount' => '1', 'fee_fixed' => '0.05', 'status' => 'active']);
settings_save(['min_withdrawal' => (string) u('1')]);
$cashA = (int) member($a)['cash_balance'];
throws(fn () => withdrawal_create($a, $wm, '0.50', 'alice@pp.com'), 'below global minimum refused', 'minimum');
throws(fn () => withdrawal_create($a, $wm, '100', 'alice@pp.com'), 'more than cash refused', 'cash');
$w1 = withdrawal_create($a, $wm, '1.50', 'alice@pp.com');
eq((int) member($a)['cash_balance'], $cashA - u('1.50'), 'withdrawal held from cash');
eq((int) row_required('SELECT payout_amount FROM withdrawals WHERE id = ?', [$w1])['payout_amount'], u('1.45'), 'payout after fee');
withdrawal_refund($w1, 'rejected', $admin, 'wrong email');
eq((int) member($a)['cash_balance'], $cashA, 'rejected withdrawal refunded');
throws(fn () => withdrawal_refund($w1, 'rejected', $admin), 'double reject refused', 'no longer pending');
$w2 = withdrawal_create($a, $wm, '1.25', 'alice@pp.com');
throws(fn () => withdrawal_refund($w2, 'cancelled', null, '', $b), "other member can't cancel", 'not found');
withdrawal_mark_paid($admin, $w2, 'PP-123');
eq((int) member($a)['total_withdrawn'], u('1.25'), 'paid withdrawal tracked');
admin_adjust_balance($admin, $b, 'cash', 'credit', '2', 'test bonus');
throws(fn () => admin_adjust_balance($admin, $b, 'cash', 'debit', '999', 'too much'), 'admin debit cannot go negative', 'Insufficient');
throws(fn () => admin_adjust_balance($admin, $b, 'cash', 'credit', '1', ''), 'adjustment needs a note', 'note');
$w3 = withdrawal_create($b, $wm, '1', 'bob@pp.com');
withdrawal_refund($w3, 'cancelled', null, '', $b);
eq(row_required('SELECT status FROM withdrawals WHERE id = ?', [$w3])['status'], 'cancelled', 'member cancelled own withdrawal');
check_invariants('after withdrawals');

echo "== advertising\n";
throws(fn () => campaign_create($c, ['title' => 'Hi', 'url' => 'https://carol.example'], 20), 'short title refused', 'headline');
throws(fn () => campaign_create($c, ['title' => 'Carol shop', 'url' => 'javascript:alert(1)'], 20), 'js url refused', 'URL');
throws(fn () => campaign_create($c, ['title' => 'Carol shop', 'url' => 'https://carol.example', 'image_url' => 'http://img.example/a.png'], 20), 'http image refused', 'https');
throws(fn () => campaign_create($c, ['title' => 'Carol shop', 'url' => 'https://carol.example'], 5), 'below min credits refused', 'at least');
throws(fn () => campaign_create($c, ['title' => 'Carol shop', 'url' => 'https://carol.example'], 100000), 'more credits than owned refused', 'ad credits');
$credC = (int) member($c)['ad_credits'];
$camp = campaign_create($c, ['title' => 'Carol shop', 'description' => 'Handmade soap', 'url' => 'https://carol.example', 'image_url' => 'https://img.example/a.png'], 100);
eq((int) member($c)['ad_credits'], $credC - 100, 'credits moved into campaign');
eq(row_required('SELECT status FROM ad_campaigns WHERE id = ?', [$camp])['status'], 'pending', 'campaign awaits approval');
settings_save(['ad_required' => '1']);
$v = ad_for($a);
check((int) ($v['campaign']['is_house'] ?? 0) === 1 || (int) ($v['campaign']['id'] ?? 0) !== $camp, 'pending campaign not shown');
q('DELETE FROM ad_views WHERE user_id = ?', [$a]);
admin_campaign_set_status($admin, $camp, 'active');
$v = ad_for($a);
eq((int) ($v['campaign']['id'] ?? 0), $camp, 'member campaign shown before house ads');
$vc = ad_for($c);
check((int) ($vc['campaign']['id'] ?? 0) !== $camp, 'owner never sees own campaign');
check(ad_view_complete($a, $v['view']['token']) === false, 'complete refused before countdown');
q('UPDATE ad_views SET started_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 12), $v['view']['id']]);
check(ad_view_complete($a, $v['view']['token']) === true, 'complete accepted after countdown');
check(ad_view_complete($a, $v['view']['token']) === true, 'complete is idempotent');
$cr = row_required('SELECT * FROM ad_campaigns WHERE id = ?', [$camp]);
eq([(int) $cr['views'], (int) $cr['credits_remaining']], [1, 99], 'one view billed once');
eq(ad_click($a, $v['view']['token']), 'https://carol.example', 'click redirects to advertiser');
ad_click($a, $v['view']['token']);
eq((int) row_required('SELECT clicks FROM ad_campaigns WHERE id = ?', [$camp])['clicks'], 1, 'click counted once per view');
$r = buy_bubbles($a, 2, 'purchase', $v['view']['token']);
eq((int) row_required('SELECT credits_remaining FROM ad_campaigns WHERE id = ?', [$camp])['credits_remaining'], 99, 'purchase does not bill a completed view twice');
campaign_toggle($c, $camp);
eq(row_required('SELECT status FROM ad_campaigns WHERE id = ?', [$camp])['status'], 'paused', 'campaign paused');
campaign_toggle($c, $camp);
campaign_add_credits($c, $camp, 10);
eq((int) row_required('SELECT credits_remaining FROM ad_campaigns WHERE id = ?', [$camp])['credits_remaining'], 109, 'credits added');
throws(fn () => campaign_toggle($a, $camp), "others can't touch the campaign", 'not found');
q('UPDATE ad_campaigns SET credits_remaining = 1 WHERE id = ?', [$camp]);
q('DELETE FROM ad_views WHERE user_id = ?', [$b]);
$vb = ad_for($b);
q('UPDATE ad_views SET started_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 12), $vb['view']['id']]);
ad_view_complete($b, $vb['view']['token']);
eq(row_required('SELECT status FROM ad_campaigns WHERE id = ?', [$camp])['status'], 'completed', 'campaign completes when credits run out');
q('UPDATE ad_campaigns SET credits_remaining = 7 WHERE id = ?', [$camp]);
$credC = (int) member($c)['ad_credits'];
$refund = campaign_delete($c, $camp);
eq($refund, 7, 'delete refunds remaining credits');
settings_save(['ad_required' => '0']);

echo "== pool injection\n";
$pool = pool_state();
$queue = queue_length($pool);
$popped = pool_inject($admin, u('5'), 'marketing boost');
check(count($popped) >= 1, 'injection expires bubbles: ' . count($popped));
$pool = pool_state();
eq((int) $pool['total_injected'], u('5'), 'injection tracked');
check_invariants('after injection');
echo "== batch payouts\n";
settings_save(['ad_required' => '0', 'max_bubbles_per_purchase' => '0', 'max_active_bubbles' => '0']);
$whales = [];
foreach (['whale1', 'whale2', 'whale3', 'whale4'] as $name) {
    $id = register_user($name, $name . '@example.com', TEST_PASSWORD);
    deposit_manual($admin, $id, u('700'), 'batch test');
    $whales[] = $id;
}
$pool = pool_state();
$expected = min(queue_length($pool) + 700, intdiv((int) $pool['balance'] + 700 * u('0.80'), u('1.60')));
$big = buy_bubbles($whales[0], 700, 'purchase');
eq(count($big['popped']), $expected, "a 700-bubble purchase expires $expected bubbles in one go");
foreach (array_slice($whales, 1) as $id) {
    buy_bubbles($id, 700, 'purchase');
}
check_invariants('after bulk purchases');
$pool = pool_state();
$queue = queue_length($pool);
$inject = u('2000');
$expected = min($queue, intdiv((int) $pool['balance'] + $inject, u('1.60')));
check($expected > 2 * POOL_BATCH, "injection spans several batches ($expected bubbles, queue $queue)");
$started = microtime(true);
$popped = pool_inject($admin, $inject, 'batch test');
$ms = (int) round((microtime(true) - $started) * 1000);
eq(count($popped), $expected, "injection expires exactly $expected bubbles ({$ms} ms)");
eq(array_column($popped, 'id'), range((int) $pool['bubbles_expired'] + 1, (int) $pool['bubbles_expired'] + $expected), 'payouts follow queue order without gaps');
$pool = pool_state();
check((int) $pool['balance'] < u('1.60'), 'pool keeps less than one bubble target afterwards');
foreach ($whales as $id) {
    $earned = (int) val("SELECT COALESCE(SUM(earned), 0) FROM bubbles WHERE user_id = ? AND status = 'expired'", [$id]);
    eq((int) member($id)['total_earned'], $earned, "whale #$id total earned matches expired bubbles");
}
check_invariants('after batch payouts');

echo "== two-factor\n";
$secret = totp_new_secret();
eq(strlen(base32_decode($secret)), 20, '160-bit secret');
$step = intdiv(time(), 30);
eq(totp_verify($secret, totp_code($secret, $step)), $step, 'current code accepted');
check(totp_verify($secret, totp_code($secret, $step - 1)) !== null, 'previous code accepted (clock drift)');
check(totp_verify($secret, totp_code($secret, $step + 3)) === null, 'code from the future refused');
check(totp_verify($secret, substr(totp_code($secret, $step), 0, 5)) === null, 'short code refused');
check(str_starts_with(totp_uri($secret, 'alice'), 'otpauth://totp/TestBubbles%3Aalice?secret=' . $secret), 'otpauth URI for the QR code');
[$codes, $hashes] = recovery_codes_generate();
eq(count(array_unique($codes)), 10, 'ten distinct recovery codes');
check((bool) preg_match('/^[A-Z2-7]{4}-[A-Z2-7]{4}$/', $codes[0]), 'recovery code format XXXX-XXXX');
user_enable_2fa($a, $secret, $hashes, $step - 1);
check(user_has_2fa(member($a)), 'two-factor enabled');
check(str_starts_with((string) member($a)['totp_secret'], 'sb1:'), 'secret encrypted at rest');
eq(open_secret(member($a)['totp_secret']), $secret, 'secret decrypts with the app key');
check(user_verify_2fa($a, totp_code($secret, $step)), 'sign-in code accepted');
check(!user_verify_2fa($a, totp_code($secret, $step)), 'same code cannot be replayed');
check(!user_verify_2fa($a, totp_code($secret, $step - 1)), 'older code refused after a newer one');
check(user_verify_2fa($a, strtolower($codes[0])), 'recovery code accepted in any case');
check(!user_verify_2fa($a, $codes[0]), 'recovery code works only once');
eq(recovery_codes_left(member($a)), 9, 'nine recovery codes left');
check(!user_verify_2fa($a, 'AAAA-AAAA'), 'unknown recovery code refused');
settings_save(['admin_2fa_required' => '1']);
check(admin_needs_2fa_setup(member($admin)), 'admins must set up two-factor when required');
check(!admin_needs_2fa_setup(member($b)), 'members are not forced');
settings_save(['admin_2fa_required' => '0']);
check(!admin_needs_2fa_setup(member($admin)), 'requirement can be switched off');
user_disable_2fa($a);
check(!user_has_2fa(member($a)) && member($a)['totp_secret'] === null, 'two-factor disabled and secret erased');
eq(open_secret('sb1:' . base64_encode(random_bytes(40))), null, 'tampered secret refused');
eq(open_secret(seal_secret('smtp-pass')), 'smtp-pass', 'sealed secrets round-trip');

echo "== sign-in throttling\n";
$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
eq((int) check_credentials('bob_1', TEST_PASSWORD)['id'], $b, 'correct password accepted');
eq((int) check_credentials('BOB@example.com', TEST_PASSWORD)['id'], $b, 'email sign-in is case-insensitive');
throws(fn () => check_credentials('nobody', TEST_PASSWORD), 'unknown account refused with the generic message', 'Incorrect');
check(password_get_info(setting('login_dummy_hash'))['algoName'] === password_get_info((string) member($b)['password_hash'])['algoName'], 'unknown accounts cost one real hash check');
for ($i = 1; $i < LOGIN_MAX_FAILURES_IP; $i++) {
    try { check_credentials('bob_1', 'wrong-password'); } catch (AppError) {}
}
throws(fn () => check_credentials('bob_1', TEST_PASSWORD), 'IP locked out after repeated failures', 'Too many');
$_SERVER['REMOTE_ADDR'] = '198.51.100.8';
eq((int) check_credentials('bob_1', TEST_PASSWORD)['id'], $b, 'another IP can still sign in');
q('DELETE FROM login_attempts');

echo "== rate limits\n";
for ($i = 0; $i < 3; $i++) { rate_limit('test', 'one', 3, 60, 'Slow down.'); }
throws(fn () => rate_limit('test', 'one', 3, 60, 'Slow down.'), 'fourth hit refused', 'Slow down');
rate_limit('test', 'two', 3, 60, 'Slow down.');
eq(rate_count('test', 'two', 60), 1, 'other subjects are counted separately');
q("UPDATE rate_limits SET created_at = ? WHERE bucket = 'test'", [gmdate('Y-m-d H:i:s', time() - 120)]);
rate_limit('test', 'one', 3, 60, 'Slow down.');
check(true, 'limit resets once the window has passed');

echo "== email & password reset\n";
check(!mail_enabled(), 'email is off by default');
settings_save(['mail_transport' => 'log', 'mail_from' => 'noreply@bubbles.test', 'mail_from_name' => 'Bubbles Café']);
eq(mail_display_name('Bubbles, Inc.'), '"Bubbles, Inc."', 'display names with specials are quoted');
eq(mail_display_name('Say "hi"'), '"Say \\"hi\\""', 'quotes inside display names are escaped');
eq(mail_header_value("Hi\r\nBcc: x@evil.example"), 'Hi  Bcc: x@evil.example', 'header injection neutralised');
$folded = explode("\r\n", mail_encode_header(str_repeat('Größe ', 30)));
check(count($folded) > 1 && max(array_map('strlen', $folded)) <= 76 && str_starts_with($folded[1], ' '), 'long encoded headers are folded');
check(mail_enabled(), 'log transport enabled');
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
password_reset_request('Alice@Example.com');
check((bool) preg_match('~https://bubbles\.test/reset\.php\?token=([a-f0-9]{64})~', mail_log_text(), $m), 'reset email contains a one-time link');
$token = $m[1] ?? '';
$raw = (string) file_get_contents(STORAGE_DIR . '/logs/mail.log');
check((bool) preg_match('/^From: (.+) <noreply@bubbles\.test>\r?$/m', $raw, $from) && mb_decode_mimeheader($from[1]) === 'Bubbles Café' && !str_contains($from[1], 'é'), 'sender name is MIME-encoded');
eq(val('SELECT token_hash FROM password_resets ORDER BY id DESC LIMIT 1'), hash('sha256', $token), 'only the token hash is stored');
eq((int) (password_reset_user($token)['id'] ?? 0), $a, 'token belongs to alice');
throws(fn () => password_reset_complete($token, 'password1', 'password1'), 'weak new password refused', 'too easy');
throws(fn () => password_reset_complete($token, 'New-bubbly-26', 'New-bubbly-27'), 'mismatched confirmation refused', 'match');
$after = password_reset_complete($token, 'New-bubbly-26', 'New-bubbly-26');
check(password_verify('New-bubbly-26', $after['password_hash']), 'password changed');
check(password_reset_user($token) === null, 'link cannot be used twice');
throws(fn () => password_reset_complete($token, 'Other-bubbly-26', 'Other-bubbly-26'), 'used link refused', 'invalid or has expired');
$size = strlen((string) file_get_contents(STORAGE_DIR . '/logs/mail.log'));
password_reset_request('nobody@example.com');
eq(strlen((string) file_get_contents(STORAGE_DIR . '/logs/mail.log')), $size, 'unknown address: no email, no error');
q('UPDATE password_resets SET expires_at = ? ', [gmdate('Y-m-d H:i:s', time() - 1)]);
password_reset_request('bob@example.com');
check((bool) preg_match_all('~token=([a-f0-9]{64})~', mail_log_text(), $all), 'second reset link sent');
$bobToken = end($all[1]) ?: '';
q('UPDATE password_resets SET expires_at = ? WHERE token_hash = ?', [gmdate('Y-m-d H:i:s', time() - 1), hash('sha256', $bobToken)]);
check(password_reset_user($bobToken) === null, 'expired link refused');
q("DELETE FROM rate_limits WHERE bucket LIKE 'reset%'");
for ($i = 0; $i < 5; $i++) { password_reset_request('carol@example.com'); }
eq(substr_count((string) file_get_contents(STORAGE_DIR . '/logs/mail.log'), 'To: <carol@example.com>'), 3, 'at most three reset emails per member and hour');
throws(fn () => password_reset_request('carol@example.com'), 'reset requests are rate limited per IP', 'Too many');
settings_save(['notify_members' => '0']);
check(!notify_member($b, 'x', 'x', ['x']), 'member notifications can be switched off');
check(notify_member($b, 'Security', 'Security', ['x'], security: true), 'security notices are always sent');
settings_save(['mail_transport' => 'off', 'notify_members' => '1']);

echo "== helpers\n";
eq(safe_next("/\t/evil.example/x", '/home'), '/home', 'control characters in next refused (open redirect)');
eq(safe_next('//evil.example', '/home'), '/home', 'protocol-relative next refused');
eq(safe_next('/\\evil.example', '/home'), '/home', 'backslash next refused');
eq(safe_next('https://evil.example', '/home'), '/home', 'absolute next refused');
eq(safe_next('/bubbles.php?new=1', '/home'), '/bubbles.php?new=1', 'local next kept');
eq(csv_cell('=HYPERLINK("http://x")'), "'=HYPERLINK(\"http://x\")", 'CSV formula injection neutralised');
eq(csv_cell('-5'), '-5', 'negative numbers stay numbers in CSV');
eq(csv_cell('+1 555'), "'+1 555", 'leading plus neutralised in CSV');

echo "== migrations\n";
$tables = ['users', 'rate_limits', 'password_resets'];
$schema = static fn (): array => array_map(
    static fn (string $t): string => (string) preg_replace('/ AUTO_INCREMENT=\d+/', '', (string) (row_required("SHOW CREATE TABLE `$t`")['Create Table'] ?? '')),
    $tables
);
$fresh = $schema();
db()->exec('DROP TABLE rate_limits, password_resets');
db()->exec('ALTER TABLE users DROP INDEX idx_users_register_ip, DROP COLUMN register_ip, DROP COLUMN totp_secret, DROP COLUMN totp_recovery, DROP COLUMN totp_last_step, DROP COLUMN totp_enabled_at');
settings_save(['db_version' => '1']);
check(migrations_pending(), 'version 1 database needs migrating');
migrate();
check(!migrations_pending(), 'migrated to the current version');
eq($schema(), $fresh, 'migrated schema is identical to a fresh install');
migrate();
check(true, 'running migrations again is harmless');
check_invariants('after migrations');

echo "== money precision\n";
eq(to_payment_units('10.55'), u('10.55'), 'whole cents accepted');
eq(to_payment_units('10.555'), null, 'sub-cent payment amounts rejected');
eq(to_units('0,80'), u('0.80'), 'comma decimal separator accepted');
eq(to_units('abc'), null, 'garbage rejected');
eq(method_fee(['fee_fixed' => 0, 'fee_percent_bp' => 250], u('3.33')), u('0.08'), '2.5% of 3.33 rounds to the cent');
eq(money(u('1234.5')), '$1,234.50', 'money formatting');
eq(money(-u('1.6')), '−$1.60', 'negative money formatting');

finish();
