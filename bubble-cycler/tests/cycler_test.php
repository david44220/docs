<?php
/**
 * Cycler test suite: FIFO pool maths, ad gate, deposits, withdrawals,
 * advertising and pool top-ups, with global invariants checked after each step.
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
$a = register_user('alice', 'alice@example.com', 'password123');
$b = register_user('bob_1', 'bob@example.com', 'password123');
$c = register_user('carol', 'carol@example.com', 'password123', $a);
throws(fn () => register_user('alice', 'x@example.com', 'password123'), 'duplicate username rejected', 'taken');
throws(fn () => register_user('ALICE', 'x2@example.com', 'password123'), 'case-insensitive duplicate rejected', 'taken');
throws(fn () => register_user('admin', 'x3@example.com', 'password123'), 'reserved username rejected', 'reserved');
throws(fn () => register_user('dave', 'not-an-email', 'password123'), 'bad email rejected', 'email');
throws(fn () => register_user('dave', 'dave@example.com', 'short'), 'short password rejected', '8 characters');

foreach ([$a, $b, $c] as $id) { deposit_manual($admin, $id, u('20'), 'test funds'); }
eq((int) member($a)['purchase_balance'], u('20'), 'manual deposit credited');

echo "== ad gate\n";
throws(fn () => buy_bubbles($a, 1, 'purchase', ''), 'purchase without ad refused', 'sponsored');
$ad = ad_start_view($a);
check($ad !== null && $ad['campaign']['is_house'] == 1, 'house ad served when no member campaigns');
$token = $ad['view']['token'];
throws(fn () => buy_bubbles($a, 1, 'purchase', $token), 'purchase before countdown refused', 'unlocks in');
$again = ad_start_view($a);
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
$s1 = bubble_state(row('SELECT * FROM bubbles WHERE id = 1'), $pool);
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
$s4 = bubble_state(row('SELECT * FROM bubbles WHERE id = 4'), $pool);
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
$method = payment_method($dm, 'deposit');
eq($method['currency'], 'USDT', 'currency upper-cased');
eq(method_fee($method, u('10')), u('0.30'), 'fee = 2% + 0.10');
throws(fn () => deposit_create($a, $dm, '2', 'abcd1234', '', null), 'below minimum refused', 'minimum');
throws(fn () => deposit_create($a, $dm, 'abc', 'abcd1234', '', null), 'garbage amount refused');
throws(fn () => deposit_create($a, $dm, '10', 'ab', '', null), 'short reference refused', 'reference');
$d1 = deposit_create($a, $dm, '10', 'tx-0001', 'my wallet', null);
throws(fn () => deposit_create($b, $dm, '10', 'tx-0001', '', null), 'duplicate reference refused', 'already');
$dep = row('SELECT * FROM deposits WHERE id = ?', [$d1]);
eq([(int) $dep['amount'], (int) $dep['fee'], (int) $dep['credit_amount'], $dep['status']], [u('10'), u('0.30'), u('9.70'), 'pending'], 'deposit pending with fee');
$before = (int) member($a)['purchase_balance'];
deposit_approve($admin, $d1, null, 'ok');
eq((int) member($a)['purchase_balance'] - $before, u('9.70'), 'approved deposit credited');
throws(fn () => deposit_approve($admin, $d1, null), 'double approval refused', 'no longer pending');
$d2 = deposit_create($b, $dm, '50', 'tx-0002', '', null);
deposit_approve($admin, $d2, u('45'), 'only 45 received');
eq((int) row('SELECT credit_amount FROM deposits WHERE id = ?', [$d2])['credit_amount'], u('45'), 'credit override stored');
$d3 = deposit_create($c, $dm, '20', 'tx-0003', '', null);
deposit_reject($admin, $d3, 'not found on chain');
eq(row('SELECT status FROM deposits WHERE id = ?', [$d3])['status'], 'rejected', 'deposit rejected');
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
eq((int) row('SELECT payout_amount FROM withdrawals WHERE id = ?', [$w1])['payout_amount'], u('1.45'), 'payout after fee');
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
eq(row('SELECT status FROM withdrawals WHERE id = ?', [$w3])['status'], 'cancelled', 'member cancelled own withdrawal');
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
eq(row('SELECT status FROM ad_campaigns WHERE id = ?', [$camp])['status'], 'pending', 'campaign awaits approval');
settings_save(['ad_required' => '1']);
$v = ad_start_view($a);
check((int) $v['campaign']['is_house'] === 1 || (int) $v['campaign']['id'] !== $camp, 'pending campaign not shown');
q('DELETE FROM ad_views WHERE user_id = ?', [$a]);
admin_campaign_set_status($admin, $camp, 'active');
$v = ad_start_view($a);
eq((int) $v['campaign']['id'], $camp, 'member campaign shown before house ads');
$vc = ad_start_view($c);
check((int) $vc['campaign']['id'] !== $camp, 'owner never sees own campaign');
check(ad_view_complete($a, $v['view']['token']) === false, 'complete refused before countdown');
q('UPDATE ad_views SET started_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 12), $v['view']['id']]);
check(ad_view_complete($a, $v['view']['token']) === true, 'complete accepted after countdown');
check(ad_view_complete($a, $v['view']['token']) === true, 'complete is idempotent');
$cr = row('SELECT * FROM ad_campaigns WHERE id = ?', [$camp]);
eq([(int) $cr['views'], (int) $cr['credits_remaining']], [1, 99], 'one view billed once');
eq(ad_click($a, $v['view']['token']), 'https://carol.example', 'click redirects to advertiser');
ad_click($a, $v['view']['token']);
eq((int) row('SELECT clicks FROM ad_campaigns WHERE id = ?', [$camp])['clicks'], 1, 'click counted once per view');
$r = buy_bubbles($a, 2, 'purchase', $v['view']['token']);
eq((int) row('SELECT credits_remaining FROM ad_campaigns WHERE id = ?', [$camp])['credits_remaining'], 99, 'purchase does not bill a completed view twice');
campaign_toggle($c, $camp);
eq(row('SELECT status FROM ad_campaigns WHERE id = ?', [$camp])['status'], 'paused', 'campaign paused');
campaign_toggle($c, $camp);
campaign_add_credits($c, $camp, 10);
eq((int) row('SELECT credits_remaining FROM ad_campaigns WHERE id = ?', [$camp])['credits_remaining'], 109, 'credits added');
throws(fn () => campaign_toggle($a, $camp), "others can't touch the campaign", 'not found');
q('UPDATE ad_campaigns SET credits_remaining = 1 WHERE id = ?', [$camp]);
q('DELETE FROM ad_views WHERE user_id = ?', [$b]);
$vb = ad_start_view($b);
q('UPDATE ad_views SET started_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 12), $vb['view']['id']]);
ad_view_complete($b, $vb['view']['token']);
eq(row('SELECT status FROM ad_campaigns WHERE id = ?', [$camp])['status'], 'completed', 'campaign completes when credits run out');
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
echo "== money precision\n";
eq(to_payment_units('10.55'), u('10.55'), 'whole cents accepted');
eq(to_payment_units('10.555'), null, 'sub-cent payment amounts rejected');
eq(to_units('0,80'), u('0.80'), 'comma decimal separator accepted');
eq(to_units('abc'), null, 'garbage rejected');
eq(method_fee(['fee_fixed' => 0, 'fee_percent_bp' => 250], u('3.33')), u('0.08'), '2.5% of 3.33 rounds to the cent');
eq(money(u('1234.5')), '$1,234.50', 'money formatting');
eq(money(-u('1.6')), '−$1.60', 'negative money formatting');

finish();
