<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$methods = payment_methods('deposit');
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
$form = ['amount' => '', 'reference' => '', 'sender' => ''];
if (is_post()) {
    $form = ['amount' => post('amount'), 'reference' => post('reference'), 'sender' => post('sender')];
    try {
        $id = deposit_create($uid, (int) post('method_id'), $form['amount'], $form['reference'], $form['sender'], $_FILES['proof'] ?? null);
        flash('success', t('Deposit #{id} submitted. Your purchase balance is credited as soon as an admin verifies the payment.', ['id' => num($id)]));
        redirect(url('deposit.php'));
    } catch (AppError $e) {
        $error = $e->getMessage();
    }
}

$pager = paginate((int) val('SELECT COUNT(*) FROM deposits WHERE user_id = ?', [$uid]), 10);
$history = rows(
    "SELECT * FROM deposits WHERE user_id = ? ORDER BY id DESC LIMIT {$pager['limit']} OFFSET {$pager['offset']}",
    [$uid]
);

render('user/deposit', [
    'title'   => t('Deposit'),
    'eyebrow' => t('Fund your purchase balance'),
    'page'    => 'deposit',
    'user'    => $user,
    'methods' => $methods,
    'method'  => $method,
    'form'    => $form,
    'error'   => $error,
    'history' => $history,
    'pager'   => $pager,
]);
