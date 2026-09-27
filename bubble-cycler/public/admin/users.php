<?php
require __DIR__ . '/../../app/bootstrap.php';

require_admin();
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
    $where[] = '(u.username LIKE ? OR u.email LIKE ? OR u.last_ip = ?)';
    array_push($params, '%' . $search . '%', '%' . $search . '%', $search);
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
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
