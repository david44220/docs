<?php
/**
 * @var array $user
 * @var array $pool
 * @var ?array $head
 * @var array $queue
 * @var array $stats
 * @var ?array $nextMine
 * @var ?array $nextState
 * @var array $myBubbles
 * @var array $activity
 * @var array $newPops
 */
$uid = (int) $user['id'];
$headTarget = $head !== null ? (int) $head['target'] : setting_int('bubble_target');
$headFill = $head !== null ? min((int) $pool['balance'], $headTarget) : 0;
$fillPct = $headTarget > 0 ? $headFill / $headTarget * 100 : 0;
$share = setting_int('pool_share');
$headNeeded = max(0, $headTarget - $headFill);
$headSales = $share > 0 ? intdiv($headNeeded + $share - 1, $share) : 0;
?>
<?php if ($newPops['n'] > 0): ?>
    <section class="celebrate card--glow" data-celebrate>
        <div class="celebrate__bubbles" aria-hidden="true">
            <?= bubble_html(['size' => 'sm', 'state' => 'expired', 'float' => false]) ?>
        </div>
        <div>
            <h2><?= e(tn('{n} bubble expired since your last visit!', '{n} bubbles expired since your last visit!', $newPops['n'])) ?></h2>
            <p><?= t_html('{amount} has been added to your cash balance.', ['amount' => '<strong class="text-green">+' . e(money($newPops['total'])) . '</strong>']) ?></p>
        </div>
        <a class="btn btn--primary btn--sm" href="<?= e(url('bubbles.php', ['tab' => 'expired'])) ?>"><?= e(t('See them')) ?> <?= icon('arrow-right') ?></a>
    </section>
<?php endif; ?>

<?php if ((int) $user['purchase_balance'] === 0 && (int) $stats['active'] === 0 && (int) $stats['expired'] === 0): ?>
    <section class="onboard glass">
        <div class="onboard__step<?= $pendingDeposits > 0 ? ' is-done' : '' ?>"><span>1</span><div><strong><?= e(t('Make a deposit')) ?></strong><small><?= e($pendingDeposits > 0 ? t('Waiting for review') : t('Pick a method and send funds')) ?></small></div></div>
        <div class="onboard__step"><span>2</span><div><strong><?= e(t('Watch a short ad')) ?></strong><small><?= e(t('It unlocks each purchase')) ?></small></div></div>
        <div class="onboard__step"><span>3</span><div><strong><?= e(t('Buy your first bubble')) ?></strong><small><?= e(t('{price} · expires at {target}', ['price' => money(setting_int('bubble_price')), 'target' => money(setting_int('bubble_target'))])) ?></small></div></div>
        <a class="btn btn--primary" href="<?= e(url('deposit.php')) ?>"><?= icon('download') ?> <?= e(t('Deposit now')) ?></a>
    </section>
<?php endif; ?>

<div class="grid grid--main">
    <section class="card card--glow pool-card" data-pool-live="<?= e(url('api.php', ['a' => 'pool'])) ?>">
        <header class="card__head">
            <div>
                <span class="eyebrow"><span class="live-dot"></span> <?= e(t('Live pool')) ?></span>
                <h2 class="card__title" data-live-head-label="<?= e(t('Bubble {label} is filling')) ?>"><?= e($head !== null ? t('Bubble {label} is filling', ['label' => '#' . num($head['id'])]) : t('The queue is empty')) ?></h2>
            </div>
            <a class="btn btn--secondary btn--sm" href="<?= e(url('buy.php')) ?>"><?= icon('plus') ?> <?= e(t('Add bubbles')) ?></a>
        </header>

        <div class="pool">
            <div class="pool__stage">
                <?= bubble_html([
                    'size'  => 'lg',
                    'state' => $head !== null ? 'filling' : 'idle',
                    'fill'  => $fillPct,
                    'label' => $head !== null ? percent(round($fillPct)) : '—',
                    'sub'   => money($headFill) . ' / ' . money($headTarget),
                    'mine'  => $head !== null && (int) $head['user_id'] === $uid,
                    'attrs' => ['data-live-head' => 'percent'],
                ]) ?>
            </div>
            <div class="pool__meta">
                <dl class="dl">
                    <div><dt><?= e(t('In the pool')) ?></dt><dd data-live="balance"><?= e(money($pool['balance'])) ?></dd></div>
                    <div><dt><?= e(t('Needed to expire')) ?></dt><dd data-live="head_needed"><?= e(money($headNeeded)) ?></dd></div>
                    <div><dt><?= e(t('Bubbles in queue')) ?></dt><dd data-live="queue"><?= e(num(queue_length($pool))) ?></dd></div>
                    <div><dt><?= e(t('Expired so far')) ?></dt><dd data-live="bubbles_expired"><?= e(num($pool['bubbles_expired'])) ?></dd></div>
                </dl>
                <div class="meter meter--lg"><span class="meter__bar" data-live-meter style="width: <?= round($fillPct, 2) ?>%"></span></div>
                <p class="pool__note muted">
                    <?php if ($head === null): ?>
                        <?= e(t('The next bubble bought goes straight to the front of the line.')) ?>
                    <?php elseif ((int) $head['user_id'] === $uid): ?>
                        <?= icon('sparkles') ?> <strong class="text-iris"><?= e(t('This one is yours!')) ?></strong> <?= e(tn('About {n} more bubble sale to go.', 'About {n} more bubble sales to go.', $headSales)) ?>
                    <?php else: ?>
                        <?= e(tn('About {n} more bubble sale will expire it.', 'About {n} more bubble sales will expire it.', $headSales)) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (count($queue) > 1): ?>
            <div class="queue queue--strip" aria-label="<?= e(t('Next in line')) ?>">
                <?php foreach (array_slice($queue, 1) as $i => $bubble): $mine = (int) $bubble['user_id'] === $uid; ?>
                    <div class="queue__item<?= $mine ? ' is-mine' : '' ?>">
                        <?= bubble_html(['size' => 'xs', 'state' => 'rising', 'mine' => $mine, 'label' => num($i + 2), 'delay' => (string) -($i * 0.7)]) ?>
                        <span class="queue__label">#<?= e(num($bubble['id'])) ?><small><?= e($mine ? t('You') : mask_name($bubble['username'])) ?></small></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <aside class="stack">
        <article class="wallet wallet--violet">
            <span class="wallet__icon"><?= icon('wallet') ?></span>
            <span class="wallet__label"><?= e(t('Purchase balance')) ?></span>
            <strong class="wallet__value"><?= e(money($user['purchase_balance'])) ?></strong>
            <span class="wallet__hint"><?= e(t('Deposits · used to buy bubbles')) ?></span>
            <a class="btn btn--secondary btn--sm" href="<?= e(url('deposit.php')) ?>"><?= icon('download') ?> <?= e(t('Deposit')) ?></a>
        </article>
        <article class="wallet wallet--green">
            <span class="wallet__icon"><?= icon('coins') ?></span>
            <span class="wallet__label"><?= e(t('Cash balance')) ?></span>
            <strong class="wallet__value"><?= e(money($user['cash_balance'])) ?></strong>
            <span class="wallet__hint"><?= e(t('Bubble payouts & referrals · withdrawable')) ?></span>
            <a class="btn btn--secondary btn--sm" href="<?= e(url('withdraw.php')) ?>"><?= icon('upload') ?> <?= e(t('Withdraw')) ?></a>
        </article>
        <article class="wallet wallet--pink">
            <span class="wallet__icon"><?= icon('megaphone') ?></span>
            <span class="wallet__label"><?= e(t('Ad credits')) ?></span>
            <strong class="wallet__value"><?= e(num($user['ad_credits'])) ?></strong>
            <span class="wallet__hint"><?= e(t('1 credit = 1 completed ad view')) ?></span>
            <a class="btn btn--secondary btn--sm" href="<?= e(url('advertise.php')) ?>"><?= icon('megaphone') ?> <?= e(t('Advertise')) ?></a>
        </article>
    </aside>
</div>

<div class="grid grid--4">
    <article class="stat">
        <span class="stat__icon stat__icon--blue"><?= icon('bubbles') ?></span>
        <span class="stat__label"><?= e(t('Active bubbles')) ?></span>
        <strong class="stat__value"><?= e(num($stats['active'])) ?></strong>
        <span class="stat__hint"><?= e(t('Worth {amount} when expired', ['amount' => money($stats['active_value'])])) ?></span>
    </article>
    <article class="stat">
        <span class="stat__icon stat__icon--violet"><?= icon('burst') ?></span>
        <span class="stat__label"><?= e(t('Expired bubbles')) ?></span>
        <strong class="stat__value"><?= e(num($stats['expired'])) ?></strong>
        <span class="stat__hint"><?= e(t('{n} bought in total', ['n' => num($user['bubbles_bought'])])) ?></span>
    </article>
    <article class="stat">
        <span class="stat__icon stat__icon--green"><?= icon('trending') ?></span>
        <span class="stat__label"><?= e(t('Total earned')) ?></span>
        <strong class="stat__value"><?= e(money((int) $user['total_earned'] + (int) $user['total_ref_earned'])) ?></strong>
        <span class="stat__hint"><?= e(tn('{amount} from {n} referral', '{amount} from {n} referrals', $referrals, ['amount' => money($user['total_ref_earned'])])) ?></span>
    </article>
    <article class="stat">
        <span class="stat__icon stat__icon--pink"><?= icon('hourglass') ?></span>
        <span class="stat__label"><?= e(t('Your next expiry')) ?></span>
        <?php if ($nextState !== null): ?>
            <strong class="stat__value">#<?= e(num($nextMine['id'])) ?></strong>
            <span class="stat__hint"><?= e($nextState['state'] === 'filling'
                ? t('Filling now · {pct}', ['pct' => percent(round($nextState['fill']))])
                : tn('Position {position} · ~{n} sale away', 'Position {position} · ~{n} sales away', $nextState['sales'], ['position' => num($nextState['position'])])) ?></span>
        <?php else: ?>
            <strong class="stat__value">—</strong>
            <span class="stat__hint"><?= e(t('No active bubbles yet')) ?></span>
        <?php endif; ?>
    </article>
</div>

<div class="grid grid--main">
    <section class="card">
        <header class="card__head">
            <div>
                <h2 class="card__title"><?= e(t('Your rising bubbles')) ?></h2>
                <p class="card__sub"><?= e(t('Oldest first — the ring shows how far each one has climbed.')) ?></p>
            </div>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('bubbles.php')) ?>"><?= e(t('View all')) ?> <?= icon('arrow-right') ?></a>
        </header>
        <?php if ($myBubbles === []): ?>
            <?= empty_state('bubble', t('No active bubbles'), t('Buy a bubble to join the queue — it starts rising right away.'), url('buy.php'), t('Buy bubbles')) ?>
        <?php else: ?>
            <div class="bubble-row">
                <?php foreach ($myBubbles as $i => $bubble): $state = bubble_state($bubble, $pool); ?>
                    <a class="bubble-row__item" href="<?= e(url('bubbles.php')) ?>">
                        <?= bubble_html([
                            'size'  => 'sm',
                            'state' => $state['state'],
                            'fill'  => $state['fill'],
                            'rise'  => $state['rise'],
                            'label' => '#' . num($bubble['id']),
                            'delay' => (string) -($i * 0.6),
                        ]) ?>
                        <span><?= e($state['state'] === 'filling' ? t('Filling · {pct}', ['pct' => percent(round($state['fill']))]) : t('Pos. {n}', ['n' => num($state['position'])])) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <header class="card__head">
            <h2 class="card__title"><?= e(t('Recent activity')) ?></h2>
            <a class="btn btn--ghost btn--sm" href="<?= e(url('transactions.php')) ?>"><?= e(t('History')) ?> <?= icon('arrow-right') ?></a>
        </header>
        <?php if ($activity === []): ?>
            <p class="muted"><?= e(t('Nothing yet — your deposits, bubbles and payouts will show up here.')) ?></p>
        <?php else: ?>
            <div class="feed">
                <?php foreach ($activity as $tx): $positive = (int) $tx['amount'] > 0; ?>
                    <div class="feed__item">
                        <span class="feed__icon feed__icon--<?= $tx['wallet'] === 'ads' ? 'pink' : ($positive ? 'green' : 'violet') ?>"><?= icon(match ($tx['type']) {
                            'bubble_payout' => 'burst', 'bubble_purchase' => 'bubble', 'deposit' => 'download',
                            'withdrawal' => 'upload', 'referral' => 'users', 'ad_credits', 'campaign_fund', 'campaign_refund' => 'megaphone',
                            default => 'activity',
                        }) ?></span>
                        <span class="feed__body"><?= e(tx_label($tx['type'])) ?><small><?= e(str_limit(stored_text($tx['description']), 48)) ?> · <?= e(time_ago($tx['created_at'])) ?></small></span>
                        <span class="feed__amount <?= $positive ? 'text-green' : 'muted' ?>"><?= e(ledger_amount($tx)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
