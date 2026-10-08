<?php
/**
 * @var array $logs
 * @var array $pager
 */
?>
<section class="card card--flush">
    <?php if ($logs === []): ?>
        <?= empty_state('file', t('No admin actions yet'), t('Approvals, adjustments, pool top-ups and setting changes are recorded here.')) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(t('Date')) ?></th><th><?= e(t('Admin')) ?></th><th><?= e(t('Action')) ?></th><th><?= e(t('Details')) ?></th><th><?= e(t('IP')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="nowrap muted"><?= e(fmt_date($log['created_at'])) ?></td>
                        <td><?= e((string) ($log['username'] ?? '#' . $log['admin_id'])) ?></td>
                        <td class="nowrap"><code class="code-tag"><?= e($log['action']) ?></code></td>
                        <td><?= e(stored_text((string) $log['details'])) ?></td>
                        <td class="mono muted"><?= e((string) $log['ip']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
