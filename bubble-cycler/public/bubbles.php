<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$tab = query('tab') === 'expired' ? 'expired' : 'active';
$pool = pool_state();
$stats = member_bubble_stats($uid);

$p = paginate($tab === 'active' ? $stats['active'] : $stats['expired'], 24);
$order = $tab === 'active' ? 'ASC' : 'DESC';
$bubbles = rows(
    "SELECT * FROM bubbles WHERE user_id = ? AND status = ? ORDER BY id $order LIMIT {$p['limit']} OFFSET {$p['offset']}",
    [$uid, $tab]
);

$new = query_int('new');
$highlight = $new > 0 ? row('SELECT first_bubble, last_bubble FROM purchases WHERE id = ? AND user_id = ?', [$new, $uid]) : null;

render('user/bubbles', [
    'title'     => t('My bubbles'),
    'eyebrow'   => tn('{n} rising', '{n} rising', $stats['active']) . ' · ' . tn('{n} expired', '{n} expired', $stats['expired']),
    'page'      => 'bubbles',
    'tab'       => $tab,
    'pool'      => $pool,
    'stats'     => $stats,
    'bubbles'   => $bubbles,
    'pager'     => $p,
    'highlight' => $highlight,
]);
