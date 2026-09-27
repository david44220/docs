<?php
/**
 * @var array $statuses
 * @var string $status
 * @var string $search
 * @var array $deposits
 * @var array $pager
 * @var ?array $review
 * @var array $counts
 */
?>
<div class="toolbar">
    <?= filter_tabs($statuses, $status, 'status', $counts) ?>
    <form class="search" method="get">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <?= icon('search') ?><input class="input input--sm" type="search" name="q" value="<?= e($search) ?>" placeholder="Member or reference">
    </form>
    <?= csv_link('admin/deposits.php', ['status' => $status, 'q' => $search !== '' ? $search : null]) ?>
</div>

<?php if ($review !== null): ?>
    <section class="card card--glow review">
        <header class="card__head">
            <div>
                <span class="eyebrow">Deposit #<?= (int) $review['id'] ?></span>
                <h2 class="card__title"><?= e(money($review['amount'])) ?> via <?= e($review['method_name']) ?></h2>
                <p class="card__sub">Submitted <?= e(fmt_date($review['created_at'])) ?> (<?= e(time_ago($review['created_at'])) ?>)</p>
            </div>
            <div class="row">
                <?= status_badge($review['status']) ?>
                <a class="icon-btn" href="<?= e(url('admin/deposits.php', ['status' => $status])) ?>" aria-label="Close"><?= icon('x') ?></a>
            </div>
        </header>
        <div class="review__grid">
            <dl class="dl dl--rows">
                <div><dt>Member</dt><dd><a href="<?= e(url('admin/user.php', ['id' => $review['user_id']])) ?>"><?= e($review['username']) ?></a> <small class="muted"><?= e($review['email']) ?></small></dd></div>
                <div><dt>History</dt><dd><?= number_format((int) $review['approved_count']) ?> approved · <?= number_format((int) $review['rejected_count']) ?> rejected · <?= e(money($review['total_deposited'])) ?> deposited</dd></div>
                <div><dt>Amount declared</dt><dd><?= e(money($review['amount'])) ?></dd></div>
                <div><dt>Method fee</dt><dd><?= e(money($review['fee'])) ?></dd></div>
                <div><dt>To credit</dt><dd class="text-green"><?= e(money($review['credit_amount'])) ?></dd></div>
                <div><dt>Reference</dt><dd class="mono break"><?= e($review['reference']) ?></dd></div>
                <div><dt>Sent from</dt><dd class="mono break"><?= $review['sender'] !== '' ? e($review['sender']) : '<span class="muted">—</span>' ?></dd></div>
                <?php if ($review['admin_note']): ?><div><dt>Note</dt><dd><?= e($review['admin_note']) ?></dd></div><?php endif; ?>
            </dl>
            <div class="review__proof">
                <?php if ($review['proof_file']): ?>
                    <a href="<?= e(url('admin/proof.php', ['id' => $review['id']])) ?>" target="_blank" rel="noopener">
                        <img src="<?= e(url('admin/proof.php', ['id' => $review['id']])) ?>" alt="Payment screenshot for deposit #<?= (int) $review['id'] ?>">
                    </a>
                <?php else: ?>
                    <div class="review__noproof"><?= icon('image') ?><span>No screenshot attached</span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($review['status'] === 'pending'): ?>
            <div class="review__actions">
                <form method="post" class="review__form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                    <label class="field">
                        <span class="field__label">Amount to credit</span>
                        <span class="input-group"><span class="input-group__addon"><?= e(setting('currency_symbol', '$')) ?></span><input class="input" name="credit" inputmode="decimal" value="<?= e(units_to_input($review['credit_amount'])) ?>"></span>
                    </label>
                    <label class="field">
                        <span class="field__label">Note <small class="muted">optional, visible to the member</small></span>
                        <input class="input" name="note" maxlength="255" placeholder="e.g. Verified on-chain">
                    </label>
                    <button class="btn btn--success" type="submit" data-confirm="Credit this amount to <?= e($review['username']) ?>'s purchase balance?"><?= icon('check') ?> Approve &amp; credit</button>
                </form>
                <form method="post" class="review__form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                    <label class="field">
                        <span class="field__label">Reason for rejecting</span>
                        <input class="input" name="note" maxlength="255" placeholder="e.g. Payment not received" required>
                    </label>
                    <button class="btn btn--danger" type="submit"><?= icon('x') ?> Reject</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="card card--flush">
    <?php if ($deposits === []): ?>
        <?= empty_state('download', $status === 'pending' ? 'All caught up' : 'No deposits', $status === 'pending' ? 'There are no deposits waiting for review.' : 'Nothing matches this filter.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>Member</th><th>Method</th><th class="num">Declared</th><th class="num">To credit</th><th>Reference</th><th>Proof</th><th>Submitted</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($deposits as $d): ?>
                    <tr<?= $review !== null && (int) $review['id'] === (int) $d['id'] ? ' class="is-selected"' : '' ?>>
                        <td class="muted"><?= (int) $d['id'] ?></td>
                        <td><a class="cell-user" href="<?= e(url('admin/user.php', ['id' => $d['user_id']])) ?>"><?= user_avatar($d['username'], 'sm') ?><?= e($d['username']) ?></a></td>
                        <td><?= e($d['method_name']) ?></td>
                        <td class="num"><?= e(money($d['amount'])) ?></td>
                        <td class="num"><?= e(money($d['credit_amount'])) ?></td>
                        <td class="mono truncate" title="<?= e($d['reference']) ?>"><?= e(str_limit($d['reference'], 20)) ?></td>
                        <td><?= $d['proof_file'] ? '<span class="text-green">' . icon('image') . '</span>' : '<span class="muted">—</span>' ?></td>
                        <td class="nowrap muted"><?= e(time_ago($d['created_at'])) ?></td>
                        <td><?= status_badge($d['status']) ?></td>
                        <td class="num"><a class="btn btn--<?= $d['status'] === 'pending' ? 'primary' : 'ghost' ?> btn--sm" href="<?= e(url('admin/deposits.php', ['status' => $status, 'q' => $search, 'review' => $d['id'], 'page' => $pager['page'] > 1 ? $pager['page'] : null])) ?>"><?= $d['status'] === 'pending' ? 'Review' : 'View' ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
