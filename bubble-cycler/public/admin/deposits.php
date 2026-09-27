<?php
require __DIR__ . '/../../app/bootstrap.php';

$admin = require_admin();
$aid = (int) $admin['id'];
$statuses = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'];
$status = array_key_exists(query('status'), $statuses) ? query('status') : 'pending';

if (is_post()) {
    $id = (int) post('id');
    try {
        if (post('action') === 'approve') {
            $creditText = post('credit');
            $credit = $creditText === '' ? null : to_payment_units($creditText);
            if ($creditText !== '' && $credit === null) {
                throw new AppError('Enter a valid amount to credit.');
            }
            deposit_approve($aid, $id, $credit, post('note'));
            flash('success', sprintf('Deposit #%d approved and credited.', $id));
        } elseif (post('action') === 'reject') {
            deposit_reject($aid, $id, post('note'));
            flash('success', sprintf('Deposit #%d rejected.', $id));
        }
    } catch (AppError $e) {
        flash('error', $e->getMessage());
        redirect(url('admin/deposits.php', ['status' => $status, 'review' => $id]));
    }
    redirect(url('admin/deposits.php', ['status' => $status]));
}

$where = [];
$params = [];
if ($status !== 'all') {
    $where[] = 'd.status = ?';
    $params[] = $status;
}
$search = query('q');
if ($search !== '') {
    $where[] = '(u.username LIKE ? OR d.reference LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$pager = paginate((int) val("SELECT COUNT(*) FROM deposits d JOIN users u ON u.id = d.user_id $sqlWhere", $params), 20);
$order = $status === 'pending' ? 'ASC' : 'DESC';
$deposits = rows(
    "SELECT d.*, u.username FROM deposits d JOIN users u ON u.id = d.user_id $sqlWhere
      ORDER BY d.id $order LIMIT {$pager['limit']} OFFSET {$pager['offset']}",
    $params
);

$review = null;
if (query_int('review') > 0) {
    $review = row(
        'SELECT d.*, u.username, u.email, u.total_deposited, u.created_at AS member_since,
                (SELECT COUNT(*) FROM deposits x WHERE x.user_id = d.user_id AND x.status = ?) AS approved_count,
                (SELECT COUNT(*) FROM deposits x WHERE x.user_id = d.user_id AND x.status = ?) AS rejected_count
           FROM deposits d JOIN users u ON u.id = d.user_id WHERE d.id = ?',
        ['approved', 'rejected', query_int('review')]
    );
}

$counts = [];
foreach (rows('SELECT status, COUNT(*) AS n FROM deposits GROUP BY status') as $r) {
    $counts[$r['status']] = (int) $r['n'];
}
$counts['all'] = array_sum($counts);

render('admin/deposits', [
    'title'      => 'Deposits',
    'eyebrow'    => 'Manual deposit review',
    'page'       => 'admin-deposits',
    'admin_area' => true,
    'statuses'   => $statuses,
    'status'     => $status,
    'search'     => $search,
    'deposits'   => $deposits,
    'pager'      => $pager,
    'review'     => $review,
    'counts'     => $counts,
]);
