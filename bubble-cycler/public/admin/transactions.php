<?php
require __DIR__ . '/../../app/bootstrap.php';

require_admin();
$type = array_key_exists(query('type'), TX_TYPES) ? query('type') : '';
$wallet = isset(WALLETS[query('wallet')]) ? query('wallet') : '';
$userFilter = query('user');

$where = [];
$params = [];
if ($type !== '') {
    $where[] = 't.type = ?';
    $params[] = $type;
}
if ($wallet !== '') {
    $where[] = 't.wallet = ?';
    $params[] = $wallet;
}
if ($userFilter !== '') {
    $where[] = 'u.username = ?';
    $params[] = $userFilter;
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$pager = paginate((int) val("SELECT COUNT(*) FROM transactions t JOIN users u ON u.id = t.user_id $sqlWhere", $params), 40);
$items = rows(
    "SELECT t.*, u.username FROM transactions t JOIN users u ON u.id = t.user_id $sqlWhere
      ORDER BY t.id DESC LIMIT {$pager['limit']} OFFSET {$pager['offset']}",
    $params
);

render('admin/transactions', [
    'title'      => 'Ledger',
    'eyebrow'    => 'Every balance movement',
    'page'       => 'admin-ledger',
    'admin_area' => true,
    'type'       => $type,
    'wallet'     => $wallet,
    'userFilter' => $userFilter,
    'items'      => $items,
    'pager'      => $pager,
]);
