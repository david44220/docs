<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$pool = pool_state();

// Bubbles that expired since the last visit → celebration banner.
$since = $user['pops_seen_at'] ?? $user['created_at'];
$newPops = row_required(
    "SELECT COUNT(*) AS n, COALESCE(SUM(earned), 0) AS total FROM bubbles WHERE user_id = ? AND status = 'expired' AND expired_at > ?",
    [$uid, $since]
);
if ((int) $newPops['n'] > 0) {
    q('UPDATE users SET pops_seen_at = ? WHERE id = ?', [now(), $uid]);
}

$nextMine = row("SELECT * FROM bubbles WHERE user_id = ? AND status = 'active' ORDER BY id ASC LIMIT 1", [$uid]);

render('user/dashboard', [
    'title'      => 'Dashboard',
    'eyebrow'    => 'Hi ' . $user['username'],
    'page'       => 'dashboard',
    'user'       => $user,
    'pool'       => $pool,
    'head'       => queue_head($pool),
    'queue'      => queue_next($pool, 7),
    'stats'      => member_bubble_stats($uid),
    'nextMine'   => $nextMine,
    'nextState'  => $nextMine !== null ? bubble_state($nextMine, $pool) : null,
    'myBubbles'  => rows("SELECT * FROM bubbles WHERE user_id = ? AND status = 'active' ORDER BY id ASC LIMIT 8", [$uid]),
    'activity'   => rows('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 7', [$uid]),
    'newPops'    => ['n' => (int) $newPops['n'], 'total' => (int) $newPops['total']],
    'referrals'  => (int) val('SELECT COUNT(*) FROM users WHERE referrer_id = ?', [$uid]),
    'pendingDeposits' => (int) val("SELECT COUNT(*) FROM deposits WHERE user_id = ? AND status = 'pending'", [$uid]),
]);
