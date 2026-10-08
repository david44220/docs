<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];

if (is_post()) {
    $quantity = (int) post('quantity', '1');
    $wallet = post('wallet', 'purchase');
    if (!form_nonce_consume('buy', post('nonce'))) {
        flash('info', t('This purchase form was already submitted. Check your bubbles below before buying again.'));
        redirect(url('bubbles.php'));
    }
    try {
        $result = buy_bubbles($uid, $quantity, $wallet, post('ad_token'));
        $label = $result['quantity'] === 1
            ? t('Bubble #{id}', ['id' => num($result['first'])])
            : t('Bubbles #{first}–#{last}', ['first' => num($result['first']), 'last' => num($result['last'])]);
        flash('success', tn('{label} joined the queue. +{n} ad credit added to your account.', '{label} joined the queue. +{n} ad credits added to your account.', (int) $result['credits'], ['label' => $label]));

        $popped = $result['popped'];
        $mine = array_values(array_filter($popped, static fn (array $b): bool => $b['user_id'] === $uid));
        if ($mine !== []) {
            flash('pop', tn(
                '{n} bubble of yours just expired — +{amount} added to your cash balance!',
                '{n} bubbles of yours just expired — +{amount} added to your cash balance!',
                count($mine),
                ['amount' => money(array_sum(array_column($mine, 'target')))]
            ));
        } elseif ($popped !== []) {
            flash('info', tn('Your purchase filled the pool: {n} bubble at the front of the queue expired.', 'Your purchase filled the pool: {n} bubbles at the front of the queue expired.', count($popped)));
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
    'title'    => t('Buy bubbles'),
    'eyebrow'  => t('Two quick steps'),
    'page'     => 'buy',
    'user'     => $user,
    'pool'     => $pool,
    'ad'       => setting_bool('ad_required') ? ad_start_view($uid) : null,
    'quote'    => queue_quote($pool, 1),
    'quantity' => max(1, min($max > 0 ? $max : 1000, query_int('qty', 1))),
    'wallet'   => query('wallet') === 'cash' && setting_bool('allow_cash_purchase') ? 'cash' : 'purchase',
    'nonce'    => form_nonce('buy'),
]);
