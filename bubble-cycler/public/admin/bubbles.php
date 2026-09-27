<?php
require __DIR__ . '/../../app/bootstrap.php';

$admin = require_admin();
$tab = query('tab') === 'expired' ? 'expired' : 'queue';

if (is_post() && post('action') === 'inject') {
    try {
        $amount = to_payment_units(post('amount'));
        if ($amount === null || $amount <= 0) {
            throw new AppError('Enter a positive amount, e.g. 10 or 1.60.');
        }
        $popped = pool_inject((int) $admin['id'], $amount, post('note'));
        flash('success', sprintf('%s added to the pool — %s expired.', money($amount), plural(count($popped), 'bubble')));
    } catch (AppError $e) {
        flash('error', $e->getMessage());
    }
    redirect(url('admin/bubbles.php'));
}

$pool = pool_state();
$where = ['b.status = ?'];
$params = [$tab === 'queue' ? 'active' : 'expired'];
$userFilter = query('user');
if ($userFilter !== '') {
    $where[] = 'b.user_id = ?';
    $params[] = (int) val('SELECT id FROM users WHERE username = ?', [$userFilter]);
}
$sqlWhere = 'WHERE ' . implode(' AND ', $where);
$pager = paginate((int) val("SELECT COUNT(*) FROM bubbles b $sqlWhere", $params), 30);
$order = $tab === 'queue' ? 'ASC' : 'DESC';
// Page through ids on the (status, id) index, then fetch the rows and their members.
$bubbles = rows(
    "SELECT b.*, u.username
       FROM (SELECT b.id FROM bubbles b $sqlWhere ORDER BY b.id $order LIMIT {$pager['limit']} OFFSET {$pager['offset']}) page
       JOIN bubbles b ON b.id = page.id
       JOIN users u ON u.id = b.user_id
      ORDER BY b.id $order",
    $params
);

render('admin/bubbles', [
    'title'      => 'Pool & queue',
    'eyebrow'    => 'FIFO cycler',
    'page'       => 'admin-pool',
    'admin_area' => true,
    'tab'        => $tab,
    'pool'       => $pool,
    'head'       => queue_head($pool),
    'bubbles'    => $bubbles,
    'pager'      => $pager,
    'userFilter' => $userFilter,
]);
