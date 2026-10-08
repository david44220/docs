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
        <?= icon('search') ?><input class="input input--sm" type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('Member or reference')) ?>">
    </form>
    <?= csv_link('admin/deposits.php', ['status' => $status, 'q' => $search !== '' ? $search : null]) ?>
</div>

<?php if ($review !== null): ?>
    <section class="card card--glow review">
        <header class="card__head">
            <div>
                <span class="eyebrow"><?= e(t('Deposit #{id}', ['id' => num($review['id'])])) ?></span>
                <h2 class="card__title"><?= e(t('{amount} via {method}', ['amount' => money($review['amount']), 'method' => t($review['method_name'])])) ?></h2>
                <p class="card__sub"><?= e(t('Submitted {date} ({ago})', ['date' => fmt_date($review['created_at']), 'ago' => time_ago($review['created_at'])])) ?></p>
            </div>
            <div class="row">
                <?= status_badge($review['status']) ?>
                <a class="icon-btn" href="<?= e(url('admin/deposits.php', ['status' => $status])) ?>" aria-label="<?= e(t('Close')) ?>"><?= icon('x') ?></a>
            </div>
        </header>
        <div class="review__grid">
            <dl class="dl dl--rows">
                <div><dt><?= e(t('Member')) ?></dt><dd><a href="<?= e(url('admin/user.php', ['id' => $review['user_id']])) ?>"><?= e($review['username']) ?></a> <small class="muted"><?= e($review['email']) ?></small></dd></div>
                <div><dt><?= e(t('History')) ?></dt><dd><?= e(t('{approved} approved · {rejected} rejected · {amount} deposited', ['approved' => num($review['approved_count']), 'rejected' => num($review['rejected_count']), 'amount' => money($review['total_deposited'])])) ?></dd></div>
                <div><dt><?= e(t('Amount declared')) ?></dt><dd><?= e(money($review['amount'])) ?></dd></div>
                <div><dt><?= e(t('Method fee')) ?></dt><dd><?= e(money($review['fee'])) ?></dd></div>
                <div><dt><?= e(t('To credit')) ?></dt><dd class="text-green"><?= e(money($review['credit_amount'])) ?></dd></div>
                <div><dt><?= e(t('Reference')) ?></dt><dd class="mono break"><?= e($review['reference']) ?></dd></div>
                <div><dt><?= e(t('Sent from')) ?></dt><dd class="mono break"><?= $review['sender'] !== '' ? e($review['sender']) : '<span class="muted">—</span>' ?></dd></div>
                <?php if ($review['admin_note']): ?><div><dt><?= e(t('Note')) ?></dt><dd><?= e($review['admin_note']) ?></dd></div><?php endif; ?>
            </dl>
            <div class="review__proof">
                <?php if ($review['proof_file']): ?>
                    <a href="<?= e(url('admin/proof.php', ['id' => $review['id']])) ?>" target="_blank" rel="noopener">
                        <img src="<?= e(url('admin/proof.php', ['id' => $review['id']])) ?>" alt="<?= e(t('Payment screenshot for deposit #{id}', ['id' => num($review['id'])])) ?>">
                    </a>
                <?php else: ?>
                    <div class="review__noproof"><?= icon('image') ?><span><?= e(t('No screenshot attached')) ?></span></div>
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
                        <span class="field__label"><?= e(t('Amount to credit')) ?></span>
                        <span class="input-group"><span class="input-group__addon"><?= e(setting('currency_symbol', '$')) ?></span><input class="input" name="credit" inputmode="decimal" value="<?= e(units_to_input($review['credit_amount'])) ?>"></span>
                    </label>
                    <label class="field">
                        <span class="field__label"><?= e(t('Note')) ?> <small class="muted"><?= e(t('optional, visible to the member')) ?></small></span>
                        <input class="input" name="note" maxlength="255" placeholder="<?= e(t('e.g. Verified on-chain')) ?>">
                    </label>
                    <button class="btn btn--success" type="submit" data-confirm="<?= e(t('Credit this amount to the purchase balance of {user}?', ['user' => $review['username']])) ?>"><?= icon('check') ?> <?= e(t('Approve & credit')) ?></button>
                </form>
                <form method="post" class="review__form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                    <label class="field">
                        <span class="field__label"><?= e(t('Reason for rejecting')) ?></span>
                        <input class="input" name="note" maxlength="255" placeholder="<?= e(t('e.g. Payment not received')) ?>" required>
                    </label>
                    <button class="btn btn--danger" type="submit"><?= icon('x') ?> <?= e(t('Reject')) ?></button>
                </form>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="card card--flush">
    <?php if ($deposits === []): ?>
        <?= empty_state('download', $status === 'pending' ? t('All caught up') : t('No deposits'), $status === 'pending' ? t('There are no deposits waiting for review.') : t('Nothing matches this filter.')) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th><?= e(t('Member')) ?></th><th><?= e(t('Method')) ?></th><th class="num"><?= e(t('Declared')) ?></th><th class="num"><?= e(t('To credit')) ?></th><th><?= e(t('Reference')) ?></th><th><?= e(t('Proof')) ?></th><th><?= e(t('Submitted')) ?></th><th><?= e(t('Status')) ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($deposits as $d): ?>
                    <tr<?= $review !== null && (int) $review['id'] === (int) $d['id'] ? ' class="is-selected"' : '' ?>>
                        <td class="muted"><?= (int) $d['id'] ?></td>
                        <td><a class="cell-user" href="<?= e(url('admin/user.php', ['id' => $d['user_id']])) ?>"><?= user_avatar($d['username'], 'sm') ?><?= e($d['username']) ?></a></td>
                        <td><?= e(t($d['method_name'])) ?></td>
                        <td class="num"><?= e(money($d['amount'])) ?></td>
                        <td class="num"><?= e(money($d['credit_amount'])) ?></td>
                        <td class="mono truncate" title="<?= e($d['reference']) ?>"><?= e(str_limit($d['reference'], 20)) ?></td>
                        <td><?= $d['proof_file'] ? '<span class="text-green">' . icon('image') . '</span>' : '<span class="muted">—</span>' ?></td>
                        <td class="nowrap muted"><?= e(time_ago($d['created_at'])) ?></td>
                        <td><?= status_badge($d['status']) ?></td>
                        <td class="num"><a class="btn btn--<?= $d['status'] === 'pending' ? 'primary' : 'ghost' ?> btn--sm" href="<?= e(url('admin/deposits.php', ['status' => $status, 'q' => $search, 'review' => $d['id'], 'page' => $pager['page'] > 1 ? $pager['page'] : null])) ?>"><?= e($d['status'] === 'pending' ? t('Review') : t('View')) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
