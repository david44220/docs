<?php
/**
 * @var array $logs
 * @var array $pager
 */
?>
<section class="card card--flush">
    <?php if ($logs === []): ?>
        <?= empty_state('file', 'No admin actions yet', 'Approvals, adjustments, pool top-ups and setting changes are recorded here.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Date</th><th>Admin</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="nowrap muted"><?= e(fmt_date($log['created_at'])) ?></td>
                        <td><?= e((string) ($log['username'] ?? '#' . $log['admin_id'])) ?></td>
                        <td class="nowrap"><code class="code-tag"><?= e($log['action']) ?></code></td>
                        <td><?= e($log['details']) ?></td>
                        <td class="mono muted"><?= e((string) $log['ip']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
