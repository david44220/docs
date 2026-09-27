<?php
require __DIR__ . '/../../app/bootstrap.php';

$admin = require_admin();
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
    // Filter on the member id so the (user_id, id) index is used.
    $where[] = 't.user_id = ?';
    $params[] = (int) val('SELECT id FROM users WHERE username = ?', [$userFilter]);
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
if (query('export') === 'csv') {
    admin_log((int) $admin['id'], 'export.ledger', 'Exported the ledger' . ($where !== [] ? ' (filtered)' : ''));
    csv_export('ledger', "SELECT t.*, u.username FROM transactions t STRAIGHT_JOIN users u ON u.id = t.user_id $sqlWhere ORDER BY t.id", $params, [
        'ID'            => static fn (array $t) => $t['id'],
        'Date (UTC)'    => static fn (array $t) => $t['created_at'],
        'Member'        => static fn (array $t) => $t['username'],
        'Wallet'        => static fn (array $t) => $t['wallet'],
        'Type'          => static fn (array $t) => $t['type'],
        'Amount'        => static fn (array $t) => $t['wallet'] === 'ads' ? (int) $t['amount'] : csv_money($t['amount']),
        'Balance after' => static fn (array $t) => $t['wallet'] === 'ads' ? (int) $t['balance_after'] : csv_money($t['balance_after']),
        'Description'   => static fn (array $t) => $t['description'],
        'Reference'     => static fn (array $t) => $t['ref_type'] !== null ? $t['ref_type'] . ' #' . $t['ref_id'] : '',
    ]);
}
$pager = paginate((int) val("SELECT COUNT(*) FROM transactions t $sqlWhere", $params), 40);
// Page through ids only (index scan), then fetch the 40 rows and their members.
$items = rows(
    "SELECT t.*, u.username
       FROM (SELECT t.id FROM transactions t $sqlWhere ORDER BY t.id DESC LIMIT {$pager['limit']} OFFSET {$pager['offset']}) page
       JOIN transactions t ON t.id = page.id
       JOIN users u ON u.id = t.user_id
      ORDER BY t.id DESC",
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
