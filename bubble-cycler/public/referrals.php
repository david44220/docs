<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];

$pager = paginate((int) val('SELECT COUNT(*) FROM users WHERE referrer_id = ?', [$uid]), 20);
$referrals = rows(
    "SELECT id, username, bubbles_bought, created_at FROM users WHERE referrer_id = ? ORDER BY id DESC LIMIT {$pager['limit']} OFFSET {$pager['offset']}",
    [$uid]
);
// Exact commission earned from each referral, from the ledger.
$earned = [];
if ($referrals !== []) {
    $ids = array_map(static fn (array $r): int => (int) $r['id'], $referrals);
    $marks = implode(',', array_fill(0, count($ids), '?'));
    foreach (rows(
        "SELECT p.user_id, SUM(t.amount) AS total FROM transactions t JOIN purchases p ON p.id = t.ref_id
          WHERE t.user_id = ? AND t.type = 'referral' AND t.ref_type = 'purchase' AND p.user_id IN ($marks) GROUP BY p.user_id",
        [$uid, ...$ids]
    ) as $row) {
        $earned[(int) $row['user_id']] = (int) $row['total'];
    }
}
$active = (int) val('SELECT COUNT(*) FROM users WHERE referrer_id = ? AND bubbles_bought > 0', [$uid]);

render('user/referrals', [
    'title'     => 'Referrals',
    'eyebrow'   => 'Invite friends',
    'page'      => 'referrals',
    'user'      => $user,
    'link'      => absolute_url('register.php', ['ref' => $user['username']]),
    'referrals' => $referrals,
    'earned'    => $earned,
    'active'    => $active,
    'pager'     => $pager,
]);
