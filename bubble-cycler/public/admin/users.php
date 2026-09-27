<?php
require __DIR__ . '/../../app/bootstrap.php';

$admin = require_admin();
$filters = ['all' => 'All', 'active' => 'Active', 'banned' => 'Banned', 'admin' => 'Admins'];
$filter = array_key_exists(query('filter'), $filters) ? query('filter') : 'all';
$sorts = [
    'newest'  => 'u.id DESC',
    'balance' => '(u.purchase_balance + u.cash_balance) DESC',
    'bubbles' => 'u.bubbles_bought DESC',
    'earned'  => 'u.total_earned DESC',
];
$sort = array_key_exists(query('sort'), $sorts) ? query('sort') : 'newest';

$where = [];
$params = [];
if ($filter === 'active' || $filter === 'banned') {
    $where[] = 'u.status = ?';
    $params[] = $filter;
} elseif ($filter === 'admin') {
    $where[] = "u.role = 'admin'";
}
$search = query('q');
if ($search !== '') {
    $where[] = '(u.username LIKE ? OR u.email LIKE ? OR u.last_ip = ? OR u.register_ip = ?)';
    array_push($params, '%' . $search . '%', '%' . $search . '%', $search, $search);
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
if (query('export') === 'csv') {
    admin_log((int) $admin['id'], 'export.members', 'Exported members (' . $filters[$filter] . ($search !== '' ? ', search “' . $search . '”' : '') . ')');
    csv_export('members', "SELECT u.*, r.username AS referrer_name FROM users u LEFT JOIN users r ON r.id = u.referrer_id $sqlWhere ORDER BY u.id", $params, [
        'ID'                 => static fn (array $u) => $u['id'],
        'Username'           => static fn (array $u) => $u['username'],
        'Email'              => static fn (array $u) => $u['email'],
        'Role'               => static fn (array $u) => $u['role'],
        'Status'             => static fn (array $u) => $u['status'],
        'Referrer'           => static fn (array $u) => $u['referrer_name'],
        'Purchase balance'   => static fn (array $u) => csv_money($u['purchase_balance']),
        'Cash balance'       => static fn (array $u) => csv_money($u['cash_balance']),
        'Ad credits'         => static fn (array $u) => (int) $u['ad_credits'],
        'Deposited'          => static fn (array $u) => csv_money($u['total_deposited']),
        'Earned'             => static fn (array $u) => csv_money($u['total_earned']),
        'Withdrawn'          => static fn (array $u) => csv_money($u['total_withdrawn']),
        'Referral earnings'  => static fn (array $u) => csv_money($u['total_ref_earned']),
        'Bubbles bought'     => static fn (array $u) => (int) $u['bubbles_bought'],
        'Two-factor'         => static fn (array $u) => user_has_2fa($u) ? 'on' : 'off',
        'Registered (UTC)'   => static fn (array $u) => $u['created_at'],
        'Register IP'        => static fn (array $u) => $u['register_ip'],
        'Last sign-in (UTC)' => static fn (array $u) => $u['last_login_at'],
        'Last IP'            => static fn (array $u) => $u['last_ip'],
    ]);
}
$pager = paginate((int) val("SELECT COUNT(*) FROM users u $sqlWhere", $params), 25);
$users = rows(
    "SELECT u.*, r.username AS referrer_name,
            (SELECT COUNT(*) FROM bubbles b WHERE b.user_id = u.id AND b.status = 'active') AS active_bubbles
       FROM users u LEFT JOIN users r ON r.id = u.referrer_id
       $sqlWhere ORDER BY {$sorts[$sort]} LIMIT {$pager['limit']} OFFSET {$pager['offset']}",
    $params
);

render('admin/users', [
    'title'      => 'Members',
    'eyebrow'    => number_format((int) val('SELECT COUNT(*) FROM users')) . ' accounts',
    'page'       => 'admin-users',
    'admin_area' => true,
    'filters'    => $filters,
    'filter'     => $filter,
    'sort'       => $sort,
    'search'     => $search,
    'users'      => $users,
    'pager'      => $pager,
]);
