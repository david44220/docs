<?php
/**
 * @var string $tab
 * @var array $pool
 * @var ?array $head
 * @var array $bubbles
 * @var array $pager
 * @var string $userFilter
 */
$target = $head !== null ? (int) $head['target'] : setting_int('bubble_target');
$filled = $head !== null ? min((int) $pool['balance'], $target) : 0;
$fillPct = $target > 0 ? $filled / $target * 100 : 0;
$gap = max(0, (int) $pool['target_sold'] - (int) $pool['total_out'] - (int) $pool['balance']);
$share = setting_int('pool_share');
?>
<div class="grid grid--main grid--top">
    <section class="card card--glow pool-card">
        <header class="card__head">
            <div>
                <span class="eyebrow"><span class="live-dot"></span> Pool state</span>
                <h2 class="card__title"><?= $head !== null ? 'Bubble #' . number_format((int) $head['id']) . ' (' . e($head['username']) . ') is filling' : 'Queue is empty' ?></h2>
            </div>
        </header>
        <div class="pool">
            <div class="pool__stage">
                <?= bubble_html(['size' => 'lg', 'state' => $head !== null ? 'filling' : 'idle', 'fill' => $fillPct, 'label' => round($fillPct) . '%', 'sub' => money($filled) . ' / ' . money($target)]) ?>
            </div>
            <div class="pool__meta">
                <dl class="dl">
                    <div><dt>Pool balance</dt><dd><?= e(money($pool['balance'])) ?></dd></div>
                    <div><dt>Bubbles sold</dt><dd><?= number_format((int) $pool['bubbles_sold']) ?></dd></div>
                    <div><dt>Expired</dt><dd><?= number_format((int) $pool['bubbles_expired']) ?></dd></div>
                    <div><dt>In queue</dt><dd><?= number_format(queue_length($pool)) ?></dd></div>
                    <div><dt>Total into pool</dt><dd><?= e(money($pool['total_in'])) ?></dd></div>
                    <div><dt>Total paid out</dt><dd><?= e(money($pool['total_out'])) ?></dd></div>
                    <div><dt>Admin top-ups</dt><dd><?= e(money($pool['total_injected'])) ?></dd></div>
                    <div><dt>Queue gap</dt><dd class="text-pink"><?= e(money($gap)) ?></dd></div>
                </dl>
                <div class="meter meter--lg"><span class="meter__bar" style="width: <?= round($fillPct, 2) ?>%"></span></div>
                <p class="pool__note muted">Expiring every active bubble needs <?= e(money($gap)) ?> more — about <?= number_format($share > 0 ? (int) ceil($gap / $share) : 0) ?> new bubbles at <?= e(money($share)) ?> each.</p>
            </div>
        </div>
    </section>

    <form method="post" class="card form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="inject">
        <header class="card__head"><div><h2 class="card__title">Top up the pool</h2><p class="card__sub">Add platform money to the pool (promotions, launch bonus). It pays the queue in order right away.</p></div></header>
        <label class="field">
            <span class="field__label">Amount</span>
            <span class="input-group"><span class="input-group__addon"><?= e(setting('currency_symbol', '$')) ?></span><input class="input" name="amount" inputmode="decimal" placeholder="<?= e(units_to_input(max(0, $target - $filled))) ?>" required></span>
            <span class="field__hint"><?= $head !== null ? e(money(max(0, $target - $filled))) . ' expires bubble #' . number_format((int) $head['id']) . '.' : 'The queue is empty: the amount waits in the pool for the next bubble.' ?></span>
        </label>
        <label class="field"><span class="field__label">Note <small class="muted">audit log</small></span><input class="input" name="note" maxlength="200" placeholder="e.g. Weekend promotion"></label>
        <button class="btn btn--primary" type="submit" data-confirm="Add this amount to the pool? It cannot be taken back."><?= icon('droplet') ?> Add to pool</button>
    </form>
</div>

<div class="toolbar">
    <?= filter_tabs(['queue' => 'Queue (active)', 'expired' => 'Expired'], $tab, 'tab') ?>
    <form class="search" method="get">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <?= icon('search') ?><input class="input input--sm" type="search" name="user" value="<?= e($userFilter) ?>" placeholder="Exact username">
    </form>
</div>

<section class="card card--flush">
    <?php if ($bubbles === []): ?>
        <?= empty_state('bubbles', $tab === 'queue' ? 'The queue is empty' : 'No expired bubbles', $userFilter !== '' ? 'No bubbles for this member.' : '') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Bubble</th><th>Owner</th><th><?= $tab === 'queue' ? 'Position' : 'Paid' ?></th><th class="num">Price</th><th class="num">Target</th><th class="num"><?= $tab === 'queue' ? 'Pool still needs' : 'Expired' ?></th><th>Bought</th></tr></thead>
                <tbody>
                <?php foreach ($bubbles as $b): $state = bubble_state($b, $pool); ?>
                    <tr>
                        <td><strong>#<?= number_format((int) $b['id']) ?></strong></td>
                        <td><a class="cell-user" href="<?= e(url('admin/user.php', ['id' => $b['user_id']])) ?>"><?= user_avatar($b['username'], 'sm') ?><?= e($b['username']) ?></a></td>
                        <td>
                            <?php if ($state['state'] === 'filling'): ?>
                                <?= status_badge('filling', 'Filling ' . round($state['fill']) . '%') ?>
                            <?php elseif ($state['state'] === 'rising'): ?>
                                <?= number_format($state['position']) ?>
                            <?php else: ?>
                                <span class="text-green">+<?= e(money($b['earned'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= e(money($b['price'])) ?></td>
                        <td class="num"><?= e(money($b['target'])) ?></td>
                        <td class="num"><?= $tab === 'queue' ? e(money($state['needed'])) : e(fmt_date($b['expired_at'])) ?></td>
                        <td class="nowrap muted"><?= e(fmt_date($b['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
