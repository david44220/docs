<?php
require __DIR__ . '/../../app/bootstrap.php';

require_admin();
$pager = paginate((int) val('SELECT COUNT(*) FROM admin_logs'), 40);
$logs = rows(
    "SELECT l.*, u.username FROM admin_logs l LEFT JOIN users u ON u.id = l.admin_id
      ORDER BY l.id DESC LIMIT {$pager['limit']} OFFSET {$pager['offset']}"
);

render('admin/logs', [
    'title'      => 'Audit log',
    'eyebrow'    => 'Every admin action',
    'page'       => 'admin-logs',
    'admin_area' => true,
    'logs'       => $logs,
    'pager'      => $pager,
]);
