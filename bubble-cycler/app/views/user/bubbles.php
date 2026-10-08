<?php
/**
 * @var string $tab
 * @var array $pool
 * @var array $stats
 * @var array $bubbles
 * @var array $pager
 * @var ?array $highlight
 */
?>
<div class="toolbar">
    <?= filter_tabs(['active' => t('Rising & filling'), 'expired' => t('Expired')], $tab, 'tab', ['active' => $stats['active'], 'expired' => $stats['expired']]) ?>
    <div class="toolbar__end">
        <span class="muted"><?= t_html('Potential payout {amount}', ['amount' => '<strong class="text-iris">' . e(money($stats['active_value'])) . '</strong>']) ?></span>
        <a class="btn btn--primary btn--sm" href="<?= e(url('buy.php')) ?>"><?= icon('plus') ?> <?= e(t('Buy more')) ?></a>
    </div>
</div>

<?php if ($bubbles === []): ?>
    <div class="card">
        <?php if ($tab === 'active'): ?>
            <?= empty_state('bubbles', t('No bubbles rising'), t('Buy a bubble to join the queue. It will climb towards the front and fill up as new bubbles are bought.'), url('buy.php'), t('Buy bubbles')) ?>
        <?php else: ?>
            <?= empty_state('burst', t('No expired bubbles yet'), t('When one of your bubbles reaches the front and the pool pays it in full, it shows up here.')) ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="bubble-grid">
        <?php foreach ($bubbles as $i => $bubble):
            $state = bubble_state($bubble, $pool);
            $id = (int) $bubble['id'];
            $isNew = $highlight !== null && $id >= (int) $highlight['first_bubble'] && $id <= (int) $highlight['last_bubble'];
            $progress = $state['state'] === 'rising' ? $state['rise'] : $state['fill'];
        ?>
            <article class="bcard is-<?= e($state['state']) ?><?= $isNew ? ' is-new' : '' ?>">
                <?= bubble_html([
                    'size'  => 'md',
                    'state' => $state['state'],
                    'fill'  => $state['fill'],
                    'rise'  => $state['rise'],
                    'label' => $state['state'] === 'expired' ? null : ($state['state'] === 'filling' ? percent(round($state['fill'])) : num($state['position'])),
                    'sub'   => $state['state'] === 'rising' ? t('in line') : ($state['state'] === 'filling' ? t('filled') : null),
                    'delay' => (string) -($i % 7),
                ]) ?>
                <div class="bcard__body">
                    <div class="bcard__row">
                        <strong><?= e(t('Bubble #{id}', ['id' => num($id)])) ?></strong>
                        <?= status_badge(in_array($state['state'], ['filling', 'rising'], true) ? $state['state'] : 'expired', null, 'bubble') ?>
                    </div>
                    <div class="progress" title="<?= e(percent(round($progress))) ?>"><span class="progress__bar" style="width: <?= round($progress, 2) ?>%"></span></div>
                    <dl class="bcard__meta">
                        <?php if ($state['state'] === 'expired'): ?>
                            <div><dt><?= e(t('Paid')) ?></dt><dd class="text-green">+<?= e(money($bubble['earned'])) ?></dd></div>
                            <div><dt><?= e(tc('bubble', 'Expired')) ?></dt><dd><?= e(time_ago($bubble['expired_at'])) ?></dd></div>
                        <?php elseif ($state['state'] === 'filling'): ?>
                            <div><dt><?= e(t('Filled')) ?></dt><dd><?= e(money($state['filled'])) ?> / <?= e(money($bubble['target'])) ?></dd></div>
                            <div><dt><?= e(t('To go')) ?></dt><dd>~<?= e(tn('{n} sale', '{n} sales', $state['sales'])) ?></dd></div>
                        <?php else: ?>
                            <div><dt><?= e(t('Ahead')) ?></dt><dd><?= e(num($state['ahead'])) ?></dd></div>
                            <div><dt><?= e(t('To go')) ?></dt><dd>~<?= e(tn('{n} sale', '{n} sales', $state['sales'])) ?></dd></div>
                        <?php endif; ?>
                    </dl>
                    <small class="muted"><?= e(t('Bought {ago} · expires at {amount}', ['ago' => time_ago($bubble['created_at']), 'amount' => money($bubble['target'])])) ?></small>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?= pagination_links($pager) ?>
<?php endif; ?>

<p class="note muted"><?= icon('info') ?> <?= e(t('“To go” estimates how many new bubbles must be bought before this one expires. It is not a promise: the queue only moves when new bubbles are bought.')) ?></p>
