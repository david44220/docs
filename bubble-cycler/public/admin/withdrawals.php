<?php
require __DIR__ . '/../../app/bootstrap.php';

$admin = require_admin();
$aid = (int) $admin['id'];
$statuses = ['pending' => 'Pending', 'paid' => 'Paid', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'all' => 'All'];
$status = array_key_exists(query('status'), $statuses) ? query('status') : 'pending';

if (is_post()) {
    $id = (int) post('id');
    try {
        if (post('action') === 'paid') {
            withdrawal_mark_paid($aid, $id, post('txid'), post('note'));
            flash('success', sprintf('Withdrawal #%d marked as paid.', $id));
        } elseif (post('action') === 'reject') {
            if (post('note') === '') {
                throw new AppError('Give the member a reason for the rejection.');
            }
            withdrawal_refund($id, 'rejected', $aid, post('note'));
            flash('success', sprintf('Withdrawal #%d rejected and refunded.', $id));
        }
    } catch (AppError $e) {
        flash('error', $e->getMessage());
        redirect(url('admin/withdrawals.php', ['status' => $status, 'review' => $id]));
    }
    redirect(url('admin/withdrawals.php', ['status' => $status]));
}

$where = [];
$params = [];
if ($status !== 'all') {
    $where[] = 'w.status = ?';
    $params[] = $status;
}
$search = query('q');
if ($search !== '') {
    $where[] = '(u.username LIKE ? OR w.account LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$pager = paginate((int) val("SELECT COUNT(*) FROM withdrawals w JOIN users u ON u.id = w.user_id $sqlWhere", $params), 20);
$order = $status === 'pending' ? 'ASC' : 'DESC';
$withdrawals = rows(
    "SELECT w.*, u.username FROM withdrawals w JOIN users u ON u.id = w.user_id $sqlWhere
      ORDER BY w.id $order LIMIT {$pager['limit']} OFFSET {$pager['offset']}",
    $params
);

$review = null;
if (query_int('review') > 0) {
    $review = row(
        'SELECT w.*, u.username, u.email, u.cash_balance, u.total_deposited, u.total_earned, u.total_withdrawn, u.total_ref_earned, u.status AS member_status
           FROM withdrawals w JOIN users u ON u.id = w.user_id WHERE w.id = ?',
        [query_int('review')]
    );
}

$counts = [];
foreach (rows('SELECT status, COUNT(*) AS n FROM withdrawals GROUP BY status') as $r) {
    $counts[$r['status']] = (int) $r['n'];
}
$counts['all'] = array_sum($counts);

render('admin/withdrawals', [
    'title'       => 'Withdrawals',
    'eyebrow'     => 'Manual payouts',
    'page'        => 'admin-withdrawals',
    'admin_area'  => true,
    'statuses'    => $statuses,
    'status'      => $status,
    'search'      => $search,
    'withdrawals' => $withdrawals,
    'pager'       => $pager,
    'review'      => $review,
    'counts'      => $counts,
]);
