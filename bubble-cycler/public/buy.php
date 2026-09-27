<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];

if (is_post()) {
    $quantity = (int) post('quantity', '1');
    $wallet = post('wallet', 'purchase');
    if (!form_nonce_consume('buy', post('nonce'))) {
        flash('info', 'This purchase form was already submitted. Check your bubbles below before buying again.');
        redirect(url('bubbles.php'));
    }
    try {
        $result = buy_bubbles($uid, $quantity, $wallet, post('ad_token'));
        $label = $result['quantity'] === 1
            ? 'Bubble #' . number_format($result['first'])
            : sprintf('Bubbles #%s–#%s', number_format($result['first']), number_format($result['last']));
        flash('success', sprintf('%s joined the queue. +%s ad credits added to your account.', $label, number_format($result['credits'])));

        $popped = $result['popped'];
        $mine = array_values(array_filter($popped, static fn (array $b): bool => $b['user_id'] === $uid));
        if ($mine !== []) {
            flash('pop', sprintf(
                '%s of yours just expired — +%s added to your cash balance!',
                plural(count($mine), 'bubble'),
                money(array_sum(array_column($mine, 'target')))
            ));
        } elseif ($popped !== []) {
            flash('info', sprintf('Your purchase filled the pool: %s at the front of the queue expired.', plural(count($popped), 'bubble')));
        }
        redirect(url('bubbles.php', ['new' => $result['purchase_id']]));
    } catch (AppError $e) {
        flash('error', $e->getMessage());
        redirect(url('buy.php', ['qty' => $quantity > 0 ? $quantity : null, 'wallet' => $wallet]));
    }
}

$pool = pool_state();
$max = setting_int('max_bubbles_per_purchase');

render('user/buy', [
    'title'    => 'Buy bubbles',
    'eyebrow'  => 'Two quick steps',
    'page'     => 'buy',
    'user'     => $user,
    'pool'     => $pool,
    'ad'       => setting_bool('ad_required') ? ad_start_view($uid) : null,
    'quote'    => queue_quote($pool, 1),
    'quantity' => max(1, min($max > 0 ? $max : 1000, query_int('qty', 1))),
    'wallet'   => query('wallet') === 'cash' && setting_bool('allow_cash_purchase') ? 'cash' : 'purchase',
    'nonce'    => form_nonce('buy'),
]);
