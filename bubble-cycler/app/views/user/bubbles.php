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
    <?= filter_tabs(['active' => 'Rising & filling', 'expired' => 'Expired'], $tab, 'tab', ['active' => $stats['active'], 'expired' => $stats['expired']]) ?>
    <div class="toolbar__end">
        <span class="muted">Potential payout <strong class="text-iris"><?= e(money($stats['active_value'])) ?></strong></span>
        <a class="btn btn--primary btn--sm" href="<?= e(url('buy.php')) ?>"><?= icon('plus') ?> Buy more</a>
    </div>
</div>

<?php if ($bubbles === []): ?>
    <div class="card">
        <?php if ($tab === 'active'): ?>
            <?= empty_state('bubbles', 'No bubbles rising', 'Buy a bubble to join the queue. It will climb towards the front and fill up as new bubbles are bought.', url('buy.php'), 'Buy bubbles') ?>
        <?php else: ?>
            <?= empty_state('burst', 'No expired bubbles yet', 'When one of your bubbles reaches the front and the pool pays it in full, it shows up here.') ?>
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
                    'label' => $state['state'] === 'expired' ? null : ($state['state'] === 'filling' ? round($state['fill']) . '%' : (string) number_format($state['position'])),
                    'sub'   => $state['state'] === 'rising' ? 'in line' : ($state['state'] === 'filling' ? 'filled' : null),
                    'delay' => (string) -($i % 7),
                ]) ?>
                <div class="bcard__body">
                    <div class="bcard__row">
                        <strong>Bubble #<?= number_format($id) ?></strong>
                        <?= match ($state['state']) {
                            'filling' => status_badge('filling', 'Filling'),
                            'rising'  => status_badge('rising', 'Rising'),
                            default   => status_badge('expired', 'Expired'),
                        } ?>
                    </div>
                    <div class="progress" title="<?= round($progress) ?>%"><span class="progress__bar" style="width: <?= round($progress, 2) ?>%"></span></div>
                    <dl class="bcard__meta">
                        <?php if ($state['state'] === 'expired'): ?>
                            <div><dt>Paid</dt><dd class="text-green">+<?= e(money($bubble['earned'])) ?></dd></div>
                            <div><dt>Expired</dt><dd><?= e(time_ago($bubble['expired_at'])) ?></dd></div>
                        <?php elseif ($state['state'] === 'filling'): ?>
                            <div><dt>Filled</dt><dd><?= e(money($state['filled'])) ?> / <?= e(money($bubble['target'])) ?></dd></div>
                            <div><dt>To go</dt><dd>~<?= plural($state['sales'], 'sale') ?></dd></div>
                        <?php else: ?>
                            <div><dt>Ahead</dt><dd><?= number_format($state['ahead']) ?></dd></div>
                            <div><dt>To go</dt><dd>~<?= plural($state['sales'], 'sale') ?></dd></div>
                        <?php endif; ?>
                    </dl>
                    <small class="muted">Bought <?= e(time_ago($bubble['created_at'])) ?> · expires at <?= e(money($bubble['target'])) ?></small>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?= pagination_links($pager) ?>
<?php endif; ?>

<p class="note muted"><?= icon('info') ?> “To go” estimates how many new bubbles must be bought before this one expires. It is not a promise: the queue only moves when new bubbles are bought.</p>
