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
                <span class="eyebrow"><span class="live-dot"></span> <?= e(t('Pool state')) ?></span>
                <h2 class="card__title"><?= e($head !== null ? t('Bubble #{id} ({user}) is filling', ['id' => num($head['id']), 'user' => $head['username']]) : t('Queue is empty')) ?></h2>
            </div>
        </header>
        <div class="pool">
            <div class="pool__stage">
                <?= bubble_html(['size' => 'lg', 'state' => $head !== null ? 'filling' : 'idle', 'fill' => $fillPct, 'label' => percent(round($fillPct)), 'sub' => money($filled) . ' / ' . money($target)]) ?>
            </div>
            <div class="pool__meta">
                <dl class="dl">
                    <div><dt><?= e(t('Pool balance')) ?></dt><dd><?= e(money($pool['balance'])) ?></dd></div>
                    <div><dt><?= e(t('Bubbles sold')) ?></dt><dd><?= e(num($pool['bubbles_sold'])) ?></dd></div>
                    <div><dt><?= e(t('Expired')) ?></dt><dd><?= e(num($pool['bubbles_expired'])) ?></dd></div>
                    <div><dt><?= e(t('In queue')) ?></dt><dd><?= e(num(queue_length($pool))) ?></dd></div>
                    <div><dt><?= e(t('Total into pool')) ?></dt><dd><?= e(money($pool['total_in'])) ?></dd></div>
                    <div><dt><?= e(t('Total paid out')) ?></dt><dd><?= e(money($pool['total_out'])) ?></dd></div>
                    <div><dt><?= e(t('Admin top-ups')) ?></dt><dd><?= e(money($pool['total_injected'])) ?></dd></div>
                    <div><dt><?= e(t('Queue gap')) ?></dt><dd class="text-pink"><?= e(money($gap)) ?></dd></div>
                </dl>
                <div class="meter meter--lg"><span class="meter__bar" style="width: <?= round($fillPct, 2) ?>%"></span></div>
                <p class="pool__note muted"><?= e(tn('Expiring every active bubble needs {gap} more — about {n} new bubble at {share} each.', 'Expiring every active bubble needs {gap} more — about {n} new bubbles at {share} each.', $share > 0 ? (int) ceil($gap / $share) : 0, ['gap' => money($gap), 'share' => money($share)])) ?></p>
            </div>
        </div>
    </section>

    <form method="post" class="card form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="inject">
        <header class="card__head"><div><h2 class="card__title"><?= e(t('Top up the pool')) ?></h2><p class="card__sub"><?= e(t('Add platform money to the pool (promotions, launch bonus). It pays the queue in order right away.')) ?></p></div></header>
        <label class="field">
            <span class="field__label"><?= e(t('Amount')) ?></span>
            <span class="input-group"><span class="input-group__addon"><?= e(setting('currency_symbol', '$')) ?></span><input class="input" name="amount" inputmode="decimal" placeholder="<?= e(units_to_input(max(0, $target - $filled))) ?>" required></span>
            <span class="field__hint"><?= e($head !== null ? t('{amount} expires bubble #{id}.', ['amount' => money(max(0, $target - $filled)), 'id' => num($head['id'])]) : t('The queue is empty: the amount waits in the pool for the next bubble.')) ?></span>
        </label>
        <label class="field"><span class="field__label"><?= e(t('Note')) ?> <small class="muted"><?= e(t('audit log')) ?></small></span><input class="input" name="note" maxlength="200" placeholder="<?= e(t('e.g. Weekend promotion')) ?>"></label>
        <button class="btn btn--primary" type="submit" data-confirm="<?= e(t('Add this amount to the pool? It cannot be taken back.')) ?>"><?= icon('droplet') ?> <?= e(t('Add to pool')) ?></button>
    </form>
</div>

<div class="toolbar">
    <?= filter_tabs(['queue' => t('Queue (active)'), 'expired' => t('Expired')], $tab, 'tab') ?>
    <form class="search" method="get">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <?= icon('search') ?><input class="input input--sm" type="search" name="user" value="<?= e($userFilter) ?>" placeholder="<?= e(t('Exact username')) ?>">
    </form>
</div>

<section class="card card--flush">
    <?php if ($bubbles === []): ?>
        <?= empty_state('bubbles', $tab === 'queue' ? t('The queue is empty') : t('No expired bubbles'), $userFilter !== '' ? t('No bubbles for this member.') : '') ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(t('Bubble')) ?></th><th><?= e(t('Owner')) ?></th><th><?= e($tab === 'queue' ? t('Position') : t('Paid')) ?></th><th class="num"><?= e(t('Price')) ?></th><th class="num"><?= e(t('Target')) ?></th><th class="num"><?= e($tab === 'queue' ? t('Pool still needs') : t('Expired')) ?></th><th><?= e(t('Bought')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($bubbles as $b): $state = bubble_state($b, $pool); ?>
                    <tr>
                        <td><strong>#<?= e(num($b['id'])) ?></strong></td>
                        <td><a class="cell-user" href="<?= e(url('admin/user.php', ['id' => $b['user_id']])) ?>"><?= user_avatar($b['username'], 'sm') ?><?= e($b['username']) ?></a></td>
                        <td>
                            <?php if ($state['state'] === 'filling'): ?>
                                <?= status_badge('filling', t('Filling {percent}', ['percent' => percent(round($state['fill']))])) ?>
                            <?php elseif ($state['state'] === 'rising'): ?>
                                <?= e(num($state['position'])) ?>
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
