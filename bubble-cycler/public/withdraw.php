<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$methods = payment_methods('withdrawal');
$selectedId = is_post() ? (int) post('method_id') : query_int('method');

$method = null;
foreach ($methods as $candidate) {
    if ((int) $candidate['id'] === $selectedId) {
        $method = $candidate;
    }
}
if ($method === null && count($methods) === 1) {
    $method = $methods[0];
}

$error = null;
$form = ['amount' => '', 'account' => ''];
if (is_post()) {
    if (post('action') === 'cancel') {
        try {
            withdrawal_refund((int) post('id'), 'cancelled', null, 'Cancelled by member', $uid);
            flash('success', t('Withdrawal cancelled — the amount is back in your cash balance.'));
        } catch (AppError $e) {
            flash('error', $e->getMessage());
        }
        redirect(url('withdraw.php'));
    }
    $form = ['amount' => post('amount'), 'account' => post('account')];
    try {
        $id = withdrawal_create($uid, (int) post('method_id'), $form['amount'], $form['account']);
        flash('success', t('Withdrawal #{id} requested. You will see it marked as paid once it is sent.', ['id' => num($id)]));
        redirect(url('withdraw.php'));
    } catch (AppError $e) {
        $error = $e->getMessage();
    }
}

// Pre-fill the account with the one used last time for this method.
if ($method !== null && $form['account'] === '') {
    $form['account'] = (string) val(
        'SELECT account FROM withdrawals WHERE user_id = ? AND method_id = ? ORDER BY id DESC LIMIT 1',
        [$uid, (int) $method['id']]
    );
}

$pager = paginate((int) val('SELECT COUNT(*) FROM withdrawals WHERE user_id = ?', [$uid]), 10);
$history = rows(
    "SELECT * FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT {$pager['limit']} OFFSET {$pager['offset']}",
    [$uid]
);

render('user/withdraw', [
    'title'   => t('Withdraw'),
    'eyebrow' => t('Cash out your earnings'),
    'page'    => 'withdraw',
    'user'    => current_user(true),
    'methods' => $methods,
    'method'  => $method,
    'form'    => $form,
    'error'   => $error,
    'history' => $history,
    'pager'   => $pager,
]);
