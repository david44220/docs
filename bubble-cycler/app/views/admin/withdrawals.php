<?php
/**
 * @var array $statuses
 * @var string $status
 * @var string $search
 * @var array $withdrawals
 * @var array $pager
 * @var ?array $review
 * @var array $counts
 */
?>
<div class="toolbar">
    <?= filter_tabs($statuses, $status, 'status', $counts) ?>
    <form class="search" method="get">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <?= icon('search') ?><input class="input input--sm" type="search" name="q" value="<?= e($search) ?>" placeholder="Member or account">
    </form>
    <?= csv_link('admin/withdrawals.php', ['status' => $status, 'q' => $search !== '' ? $search : null]) ?>
</div>

<?php if ($review !== null): ?>
    <section class="card card--glow review">
        <header class="card__head">
            <div>
                <span class="eyebrow">Withdrawal #<?= (int) $review['id'] ?></span>
                <h2 class="card__title">Send <?= e(money($review['payout_amount'])) ?> via <?= e($review['method_name']) ?></h2>
                <p class="card__sub">Requested <?= e(fmt_date($review['created_at'])) ?> (<?= e(time_ago($review['created_at'])) ?>)</p>
            </div>
            <div class="row">
                <?= status_badge($review['status']) ?>
                <a class="icon-btn" href="<?= e(url('admin/withdrawals.php', ['status' => $status])) ?>" aria-label="Close"><?= icon('x') ?></a>
            </div>
        </header>
        <div class="review__grid">
            <dl class="dl dl--rows">
                <div><dt>Member</dt><dd><a href="<?= e(url('admin/user.php', ['id' => $review['user_id']])) ?>"><?= e($review['username']) ?></a> <small class="muted"><?= e($review['email']) ?></small> <?= $review['member_status'] === 'banned' ? status_badge('banned') : '' ?></dd></div>
                <div><dt>Requested</dt><dd><?= e(money($review['amount'])) ?></dd></div>
                <div><dt>Fee</dt><dd><?= e(money($review['fee'])) ?></dd></div>
                <div><dt>Amount to send</dt><dd class="text-green"><?= e(money($review['payout_amount'])) ?></dd></div>
                <div><dt>Pay to</dt><dd>
                    <div class="copy__box copy__box--sm"><code class="copy__value" data-copy-source><?= e($review['account']) ?></code><button class="btn btn--secondary btn--sm copy__btn" type="button" data-copy><?= icon('copy') ?> <span>Copy</span></button></div>
                </dd></div>
                <?php if ($review['txid']): ?><div><dt>Payment ref.</dt><dd class="mono break"><?= e($review['txid']) ?></dd></div><?php endif; ?>
                <?php if ($review['admin_note']): ?><div><dt>Note</dt><dd><?= e($review['admin_note']) ?></dd></div><?php endif; ?>
            </dl>
            <dl class="dl dl--rows review__side">
                <div><dt>Deposited</dt><dd><?= e(money($review['total_deposited'])) ?></dd></div>
                <div><dt>Bubble payouts</dt><dd><?= e(money($review['total_earned'])) ?></dd></div>
                <div><dt>Referral commissions</dt><dd><?= e(money($review['total_ref_earned'])) ?></dd></div>
                <div><dt>Withdrawn so far</dt><dd><?= e(money($review['total_withdrawn'])) ?></dd></div>
                <div><dt>Cash balance now</dt><dd><?= e(money($review['cash_balance'])) ?></dd></div>
            </dl>
        </div>
        <?php if ($review['status'] === 'pending'): ?>
            <div class="review__actions">
                <form method="post" class="review__form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="paid">
                    <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                    <label class="field">
                        <span class="field__label">Payment reference <small class="muted">tx hash, transfer ID…</small></span>
                        <input class="input" name="txid" maxlength="190">
                    </label>
                    <label class="field">
                        <span class="field__label">Note <small class="muted">optional</small></span>
                        <input class="input" name="note" maxlength="255">
                    </label>
                    <button class="btn btn--success" type="submit" data-confirm="Confirm you have sent <?= e(money($review['payout_amount'])) ?> to this member?"><?= icon('check') ?> Mark as paid</button>
                </form>
                <form method="post" class="review__form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                    <label class="field">
                        <span class="field__label">Reason <small class="muted">refunds <?= e(money($review['amount'])) ?> to cash balance</small></span>
                        <input class="input" name="note" maxlength="255" placeholder="e.g. Invalid wallet address" required>
                    </label>
                    <button class="btn btn--danger" type="submit"><?= icon('x') ?> Reject &amp; refund</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="card card--flush">
    <?php if ($withdrawals === []): ?>
        <?= empty_state('upload', $status === 'pending' ? 'All caught up' : 'No withdrawals', $status === 'pending' ? 'No withdrawal is waiting to be paid.' : 'Nothing matches this filter.') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th>Member</th><th>Method</th><th class="num">Amount</th><th class="num">To send</th><th>Account</th><th>Requested</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($withdrawals as $w): ?>
                    <tr<?= $review !== null && (int) $review['id'] === (int) $w['id'] ? ' class="is-selected"' : '' ?>>
                        <td class="muted"><?= (int) $w['id'] ?></td>
                        <td><a class="cell-user" href="<?= e(url('admin/user.php', ['id' => $w['user_id']])) ?>"><?= user_avatar($w['username'], 'sm') ?><?= e($w['username']) ?></a></td>
                        <td><?= e($w['method_name']) ?></td>
                        <td class="num"><?= e(money($w['amount'])) ?></td>
                        <td class="num"><?= e(money($w['payout_amount'])) ?></td>
                        <td class="mono truncate" title="<?= e($w['account']) ?>"><?= e(str_limit($w['account'], 22)) ?></td>
                        <td class="nowrap muted"><?= e(time_ago($w['created_at'])) ?></td>
                        <td><?= status_badge($w['status']) ?></td>
                        <td class="num"><a class="btn btn--<?= $w['status'] === 'pending' ? 'primary' : 'ghost' ?> btn--sm" href="<?= e(url('admin/withdrawals.php', ['status' => $status, 'q' => $search, 'review' => $w['id'], 'page' => $pager['page'] > 1 ? $pager['page'] : null])) ?>"><?= $w['status'] === 'pending' ? 'Process' : 'View' ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
