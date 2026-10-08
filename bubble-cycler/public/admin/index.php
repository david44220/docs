<?php
require __DIR__ . '/../../app/bootstrap.php';

$admin = require_admin();

render('admin/dashboard', [
    'title'      => t('Overview'),
    'eyebrow'    => t('Admin panel'),
    'page'       => 'admin',
    'admin_area' => true,
    'stats'      => admin_stats(),
    'series'     => admin_daily_series(14),
    'purchases'  => recent_purchases(6),
    'expired'    => recent_expirations(6),
    'pending'    => admin_pending_counts(),
]);
