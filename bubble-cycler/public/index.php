<?php
require __DIR__ . '/../app/bootstrap.php';

capture_referral();

$pool = pool_state();

render('public/home', [
    'title'       => site_name(),
    'pool'        => $pool,
    'head'        => queue_head($pool),
    'next'        => queue_next($pool, 7),
    'members'     => (int) val('SELECT COUNT(*) FROM users'),
    'expirations' => recent_expirations(5),
], 'public');
