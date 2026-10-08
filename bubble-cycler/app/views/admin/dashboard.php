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
        <span class="stat__label"><?= e(t('Members')) ?></span>
        <strong class="stat__value"><?= e(num($users['total'])) ?></strong>
        <span class="stat__hint"><?= e(t('+{n} today', ['n' => num($users['today'])])) ?> · <?= e(tn('{n} banned', '{n} banned', (int) $users['banned'])) ?></span>
    </article>
    <article class="stat">
        <span class="stat__icon stat__icon--violet"><?= icon('droplet') ?></span>
        <span class="stat__label"><?= e(t('Pool balance')) ?></span>
        <strong class="stat__value"><?= e(money($pool['balance'])) ?></strong>
        <span class="stat__hint"><?= e($head !== null ? t('Bubble #{id} at {percent}', ['id' => num($head['id']), 'percent' => percent(round($fillPct))]) : t('Queue empty')) ?></span>
    </article>
    <article class="stat">
        <span class="stat__icon stat__icon--green"><?= icon('trending') ?></span>
        <span class="stat__label"><?= e(t('Platform revenue')) ?></span>
        <strong class="stat__value"><?= e(money($pool['site_revenue'])) ?></strong>
        <span class="stat__hint"><?= e(t('{amount} paid as referral commission', ['amount' => money($pool['referral_paid'])])) ?></span>
    </article>
    <article class="stat">
        <span class="stat__icon stat__icon--pink"><?= icon('clock') ?></span>
        <span class="stat__label"><?= e(t('Waiting for you')) ?></span>
        <strong class="stat__value"><?= e(num($pendingTotal)) ?></strong>
        <span class="stat__hint"><?= e(tn('{n} deposit', '{n} deposits', $pending['deposits'])) ?> · <?= e(tn('{n} withdrawal', '{n} withdrawals', $pending['withdrawals'])) ?> · <?= e(tn('{n} ad', '{n} ads', $pending['campaigns'])) ?></span>
    </article>
</div>

<div class="grid grid--main">
    <section class="card"><?= partial('chart-daily', ['series' => $series]) ?></section>

    <section class="card">
        <header class="card__head"><h2 class="card__title"><?= e(t('Needs attention')) ?></h2></header>
        <div class="todo">
            <a class="todo__item<?= $pending['deposits'] > 0 ? ' is-hot' : '' ?>" href="<?= e(url('admin/deposits.php')) ?>">
                <span class="feed__icon feed__icon--violet"><?= icon('download') ?></span>
                <span class="todo__text"><strong><?= e(t('Deposits to review')) ?></strong><small><?= e(t('{amount} declared', ['amount' => money($stats['deposits']['pending_amount'])])) ?></small></span>
                <b class="todo__count"><?= e(num($pending['deposits'])) ?></b>
            </a>
            <a class="todo__item<?= $pending['withdrawals'] > 0 ? ' is-hot' : '' ?>" href="<?= e(url('admin/withdrawals.php')) ?>">
                <span class="feed__icon feed__icon--green"><?= icon('upload') ?></span>
                <span class="todo__text"><strong><?= e(t('Withdrawals to pay')) ?></strong><small><?= e(t('{amount} requested', ['amount' => money($stats['withdrawals']['pending_amount'])])) ?></small></span>
                <b class="todo__count"><?= e(num($pending['withdrawals'])) ?></b>
            </a>
            <a class="todo__item<?= $pending['campaigns'] > 0 ? ' is-hot' : '' ?>" href="<?= e(url('admin/ads.php')) ?>">
                <span class="feed__icon feed__icon--pink"><?= icon('megaphone') ?></span>
                <span class="todo__text"><strong><?= e(t('Campaigns to approve')) ?></strong><small><?= e(tn('{n} member campaign live', '{n} member campaigns live', $stats['ads']['active'])) ?></small></span>
                <b class="todo__count"><?= e(num($pending['campaigns'])) ?></b>
            </a>
        </div>
    </section>
</div>

<div class="grid grid--main">
    <section class="card">
        <header class="card__head">
            <div>
                <h2 class="card__title"><?= e(t('Money overview')) ?></h2>
                <p class="card__sub"><?= e(t('Where every unit of money sits right now.')) ?></p>
            </div>
        </header>
        <dl class="ledger-grid">
            <div><dt><?= e(t('Deposits approved')) ?></dt><dd><?= e(money($stats['deposits']['approved'])) ?></dd></div>
            <div><dt><?= e(t('Withdrawals paid')) ?></dt><dd><?= e(money($stats['withdrawals']['paid'])) ?></dd></div>
            <div><dt><?= e(t('Member balances')) ?></dt><dd><?= e(money($liabilities)) ?><small><?= e(t('purchase {purchase} · cash {cash}', ['purchase' => money($users['purchase']), 'cash' => money($users['cash'])])) ?></small></dd></div>
            <div><dt><?= e(t('Pending withdrawals')) ?></dt><dd><?= e(money($stats['withdrawals']['pending_amount'])) ?></dd></div>
            <div><dt><?= e(t('Paid to expired bubbles')) ?></dt><dd><?= e(money($pool['total_out'])) ?></dd></div>
            <div><dt><?= e(t('Added to pool by admins')) ?></dt><dd><?= e(money($pool['total_injected'])) ?></dd></div>
            <div class="is-wide"><dt><?= e(t('Queue gap')) ?></dt><dd class="text-pink"><?= e(money($stats['queue_gap'])) ?><small><?= e(tn('money the pool still needs to expire all {n} active bubble — at {share} per sale that is ~{more} more bubbles', 'money the pool still needs to expire all {n} active bubbles — at {share} per sale that is ~{more} more bubbles', queue_length($pool), ['share' => money(setting_int('pool_share')), 'more' => num(setting_int('pool_share') > 0 ? (int) ceil($stats['queue_gap'] / setting_int('pool_share')) : 0)])) ?></small></dd></div>
        </dl>
    </section>

    <section class="card pool-mini">
        <header class="card__head">
            <h2 class="card__title"><?= e(t('Pool')) ?></h2>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/bubbles.php')) ?>"><?= e(t('Manage')) ?> <?= icon('arrow-right') ?></a>
        </header>
        <div class="pool-mini__body">
            <?= bubble_html([
                'size'  => 'lg',
                'state' => $head !== null ? 'filling' : 'idle',
                'fill'  => $fillPct,
                'label' => $head !== null ? percent(round($fillPct)) : '—',
                'sub'   => $head !== null ? t('Bubble #{id}', ['id' => num($head['id'])]) : t('Queue empty'),
            ]) ?>
            <dl class="dl">
                <div><dt><?= e(t('Sold')) ?></dt><dd><?= e(num($pool['bubbles_sold'])) ?></dd></div>
                <div><dt><?= e(t('Expired')) ?></dt><dd><?= e(num($pool['bubbles_expired'])) ?></dd></div>
                <div><dt><?= e(t('In queue')) ?></dt><dd><?= e(num(queue_length($pool))) ?></dd></div>
                <div><dt><?= e(t('Ad views')) ?></dt><dd><?= e(num($stats['ads']['views'])) ?></dd></div>
            </dl>
        </div>
    </section>
</div>

<div class="grid grid--2">
    <section class="card">
        <header class="card__head"><h2 class="card__title"><?= e(t('Latest purchases')) ?></h2></header>
        <?php if ($purchases === []): ?>
            <p class="muted"><?= e(t('No purchases yet.')) ?></p>
        <?php else: ?>
            <div class="feed">
                <?php foreach ($purchases as $p): ?>
                    <div class="feed__item">
                        <span class="feed__icon feed__icon--blue"><?= icon('bubble') ?></span>
                        <span class="feed__body"><?= e(tn('{user} bought {n} bubble', '{user} bought {n} bubbles', (int) $p['quantity'], ['user' => $p['username']])) ?><small><?= e(time_ago($p['created_at'])) ?></small></span>
                        <span class="feed__amount"><?= e(money($p['total'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <section class="card">
        <header class="card__head"><h2 class="card__title"><?= e(t('Latest expirations')) ?></h2></header>
        <?php if ($expired === []): ?>
            <p class="muted"><?= e(t('No bubble has expired yet.')) ?></p>
        <?php else: ?>
            <div class="feed">
                <?php foreach ($expired as $b): ?>
                    <div class="feed__item">
                        <span class="feed__icon feed__icon--green"><?= icon('check') ?></span>
                        <span class="feed__body"><?= e(t('Bubble #{id}', ['id' => num($b['id'])])) ?> · <?= e($b['username']) ?><small><?= e(time_ago($b['expired_at'])) ?></small></span>
                        <span class="feed__amount text-green">+<?= e(money($b['target'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
