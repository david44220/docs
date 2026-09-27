<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$wallet = query('wallet');
if (!isset(WALLETS[$wallet])) {
    $wallet = '';
}

$where = 'user_id = ?';
$params = [$uid];
if ($wallet !== '') {
    $where .= ' AND wallet = ?';
    $params[] = $wallet;
}
$pager = paginate((int) val("SELECT COUNT(*) FROM transactions WHERE $where", $params), 25);
$items = rows("SELECT * FROM transactions WHERE $where ORDER BY id DESC LIMIT {$pager['limit']} OFFSET {$pager['offset']}", $params);

render('user/transactions', [
    'title'   => 'History',
    'eyebrow' => 'Every movement on your account',
    'page'    => 'transactions',
    'wallet'  => $wallet,
    'items'   => $items,
    'pager'   => $pager,
]);
