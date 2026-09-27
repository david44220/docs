<?php
/**
 * @var array $stats
 * @var array $series
 * @var array $purchases
 * @var array $expired
 * @var array $pending
 */
$pool = $stats['pool'];
$head = $stats['head'];
$headTarget = $head !== null ? (int) $head['target'] : setting_int('bubble_target');
$headFill = $head !== null ? min((int) $pool['balance'], $headTarget) : 0;
$fillPct = $headTarget > 0 ? $headFill / $headTarget * 100 : 0;
$users = $stats['users'];
$liabilities = $users['purchase'] + $users['cash'];
$pendingTotal = $pending['deposits'] + $pending['withdrawals'] + $pending['campaigns'];
?>
<div class="grid grid--4">
    <article class="stat">
        <span class="stat__icon stat__icon--blue"><?= icon('users') ?></span>
        <span class="stat__label">Members</span>
        <strong class="stat__value"><?= number_format($users['total']) ?></strong>
        <span class="stat__hint">+<?= number_format($users['today']) ?> today · <?= number_format($users['banned']) ?> banned</span>
    </article>
    <article class="stat">
        <span class="stat__icon stat__icon--violet"><?= icon('droplet') ?></span>
        <span class="stat__label">Pool balance</span>
        <strong class="stat__value"><?= e(money($pool['balance'])) ?></strong>
        <span class="stat__hint"><?= $head !== null ? 'Bubble #' . number_format((int) $head['id']) . ' at ' . round($fillPct) . '%' : 'Queue empty' ?></span>
    </article>
    <article class="stat">
        <span class="stat__icon stat__icon--green"><?= icon('trending') ?></span>
        <span class="stat__label">Platform revenue</span>
        <strong class="stat__value"><?= e(money($pool['site_revenue'])) ?></strong>
        <span class="stat__hint"><?= e(money($pool['referral_paid'])) ?> paid as referral commission</span>
    </article>
    <article class="stat">
        <span class="stat__icon stat__icon--pink"><?= icon('clock') ?></span>
        <span class="stat__label">Waiting for you</span>
        <strong class="stat__value"><?= number_format($pendingTotal) ?></strong>
        <span class="stat__hint"><?= $pending['deposits'] ?> deposits · <?= $pending['withdrawals'] ?> withdrawals · <?= $pending['campaigns'] ?> ads</span>
    </article>
</div>

<div class="grid grid--main">
    <section class="card"><?= partial('chart-daily', ['series' => $series]) ?></section>

    <section class="card">
        <header class="card__head"><h2 class="card__title">Needs attention</h2></header>
        <div class="todo">
            <a class="todo__item<?= $pending['deposits'] > 0 ? ' is-hot' : '' ?>" href="<?= e(url('admin/deposits.php')) ?>">
                <span class="feed__icon feed__icon--violet"><?= icon('download') ?></span>
                <span class="todo__text"><strong>Deposits to review</strong><small><?= e(money($stats['deposits']['pending_amount'])) ?> declared</small></span>
                <b class="todo__count"><?= number_format($pending['deposits']) ?></b>
            </a>
            <a class="todo__item<?= $pending['withdrawals'] > 0 ? ' is-hot' : '' ?>" href="<?= e(url('admin/withdrawals.php')) ?>">
                <span class="feed__icon feed__icon--green"><?= icon('upload') ?></span>
                <span class="todo__text"><strong>Withdrawals to pay</strong><small><?= e(money($stats['withdrawals']['pending_amount'])) ?> requested</small></span>
                <b class="todo__count"><?= number_format($pending['withdrawals']) ?></b>
            </a>
            <a class="todo__item<?= $pending['campaigns'] > 0 ? ' is-hot' : '' ?>" href="<?= e(url('admin/ads.php')) ?>">
                <span class="feed__icon feed__icon--pink"><?= icon('megaphone') ?></span>
                <span class="todo__text"><strong>Campaigns to approve</strong><small><?= number_format($stats['ads']['active']) ?> member campaigns live</small></span>
                <b class="todo__count"><?= number_format($pending['campaigns']) ?></b>
            </a>
        </div>
    </section>
</div>

<div class="grid grid--main">
    <section class="card">
        <header class="card__head">
            <div>
                <h2 class="card__title">Money overview</h2>
                <p class="card__sub">Where every unit of money sits right now.</p>
            </div>
        </header>
        <dl class="ledger-grid">
            <div><dt>Deposits approved</dt><dd><?= e(money($stats['deposits']['approved'])) ?></dd></div>
            <div><dt>Withdrawals paid</dt><dd><?= e(money($stats['withdrawals']['paid'])) ?></dd></div>
            <div><dt>Member balances</dt><dd><?= e(money($liabilities)) ?><small>purchase <?= e(money($users['purchase'])) ?> · cash <?= e(money($users['cash'])) ?></small></dd></div>
            <div><dt>Pending withdrawals</dt><dd><?= e(money($stats['withdrawals']['pending_amount'])) ?></dd></div>
            <div><dt>Paid to expired bubbles</dt><dd><?= e(money($pool['total_out'])) ?></dd></div>
            <div><dt>Added to pool by admins</dt><dd><?= e(money($pool['total_injected'])) ?></dd></div>
            <div class="is-wide"><dt>Queue gap</dt><dd class="text-pink"><?= e(money($stats['queue_gap'])) ?><small>money the pool still needs to expire all <?= number_format(queue_length($pool)) ?> active bubbles — at <?= e(money(setting_int('pool_share'))) ?> per sale that is ~<?= number_format(setting_int('pool_share') > 0 ? (int) ceil($stats['queue_gap'] / setting_int('pool_share')) : 0) ?> more bubbles</small></dd></div>
        </dl>
    </section>

    <section class="card pool-mini">
        <header class="card__head">
            <h2 class="card__title">Pool</h2>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/bubbles.php')) ?>">Manage <?= icon('arrow-right') ?></a>
        </header>
        <div class="pool-mini__body">
            <?= bubble_html([
                'size'  => 'lg',
                'state' => $head !== null ? 'filling' : 'idle',
                'fill'  => $fillPct,
                'label' => $head !== null ? round($fillPct) . '%' : '—',
                'sub'   => $head !== null ? 'Bubble #' . number_format((int) $head['id']) : 'Queue empty',
            ]) ?>
            <dl class="dl">
                <div><dt>Sold</dt><dd><?= number_format((int) $pool['bubbles_sold']) ?></dd></div>
                <div><dt>Expired</dt><dd><?= number_format((int) $pool['bubbles_expired']) ?></dd></div>
                <div><dt>In queue</dt><dd><?= number_format(queue_length($pool)) ?></dd></div>
                <div><dt>Ad views</dt><dd><?= number_format($stats['ads']['views']) ?></dd></div>
            </dl>
        </div>
    </section>
</div>

<div class="grid grid--2">
    <section class="card">
        <header class="card__head"><h2 class="card__title">Latest purchases</h2></header>
        <?php if ($purchases === []): ?>
            <p class="muted">No purchases yet.</p>
        <?php else: ?>
            <div class="feed">
                <?php foreach ($purchases as $p): ?>
                    <div class="feed__item">
                        <span class="feed__icon feed__icon--blue"><?= icon('bubble') ?></span>
                        <span class="feed__body"><?= e($p['username']) ?> bought <?= plural((int) $p['quantity'], 'bubble') ?><small><?= e(time_ago($p['created_at'])) ?></small></span>
                        <span class="feed__amount"><?= e(money($p['total'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <section class="card">
        <header class="card__head"><h2 class="card__title">Latest expirations</h2></header>
        <?php if ($expired === []): ?>
            <p class="muted">No bubble has expired yet.</p>
        <?php else: ?>
            <div class="feed">
                <?php foreach ($expired as $b): ?>
                    <div class="feed__item">
                        <span class="feed__icon feed__icon--green"><?= icon('check') ?></span>
                        <span class="feed__body">Bubble #<?= number_format((int) $b['id']) ?> · <?= e($b['username']) ?><small><?= e(time_ago($b['expired_at'])) ?></small></span>
                        <span class="feed__amount text-green">+<?= e(money($b['target'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
