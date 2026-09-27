<?php
/**
 * Landing page.
 *
 * @var array $pool
 * @var ?array $head
 * @var array $next
 * @var int $members
 * @var array $expirations
 */
$price = setting_int('bubble_price');
$share = setting_int('pool_share');
$target = setting_int('bubble_target');
$credits = setting_int('ad_credits_per_bubble');
$ratio = sales_per_expiry();
$ratioText = rtrim(rtrim(number_format($ratio, 2), '0'), '.');
$headTarget = $head !== null ? (int) $head['target'] : $target;
$headFill = $head !== null ? min((int) $pool['balance'], $headTarget) : 0;
$fillPct = $headTarget > 0 ? $headFill / $headTarget * 100 : 0;
$me = current_user();
$startUrl = $me !== null ? url('buy.php') : url('register.php');
?>
<section class="hero">
    <div class="hero__copy">
        <span class="hero__eyebrow"><span class="live-dot"></span> Live cycling pool · Ad-powered</span>
        <h1 class="hero__title">Blow a bubble.<br><span class="text-iris">Let the pool lift it to <?= e(money($target)) ?>.</span></h1>
        <p class="hero__lead">
            Every <?= e(money($price)) ?> bubble sends <?= e(money($share)) ?> into one transparent, first-in-first-out pool
            and gives you <?= number_format($credits) ?> advertising credits. Bubbles fill strictly in the order they were bought
            and expire the moment the pool has paid them <?= e(money($target)) ?>.
        </p>
        <div class="hero__ctas">
            <a class="btn btn--primary btn--lg" href="<?= e($startUrl) ?>"><?= icon('sparkles') ?> Blow your first bubble</a>
            <a class="btn btn--secondary btn--lg" href="#pool"><?= icon('activity') ?> Watch the live pool</a>
        </div>
        <ul class="hero__chips">
            <li><?= icon('layers') ?> Public FIFO queue</li>
            <li><?= icon('megaphone') ?> <?= number_format($credits) ?> ad credits per bubble</li>
            <li><?= icon('shield') ?> Deposits reviewed by humans</li>
        </ul>
    </div>

    <div class="hero__art" data-pool-live="<?= e(url('api.php', ['a' => 'pool'])) ?>">
        <div class="hero__orbit">
            <?= bubble_html([
                'size'  => 'hero',
                'state' => $head !== null ? 'filling' : 'idle',
                'fill'  => $fillPct,
                'label' => $head !== null ? '#' . number_format((int) $head['id']) : 'Pool',
                'sub'   => money($headFill) . ' / ' . money($headTarget),
                'attrs' => ['data-live-head' => 'id'],
            ]) ?>
            <?php foreach (array_slice($next, 1, 5) as $i => $bubble): ?>
                <div class="hero__satellite hero__satellite--<?= $i + 1 ?>">
                    <?= bubble_html(['size' => $i < 2 ? 'sm' : 'xs', 'state' => 'rising', 'label' => '#' . $bubble['id'], 'delay' => (string) -($i * 1.3)]) ?>
                </div>
            <?php endforeach; ?>
            <?php for ($i = count(array_slice($next, 1, 5)); $i < 5; $i++): ?>
                <div class="hero__satellite hero__satellite--<?= $i + 1 ?>"><?= bubble_html(['size' => $i < 2 ? 'sm' : 'xs', 'state' => 'idle', 'delay' => (string) -($i * 1.3)]) ?></div>
            <?php endfor; ?>
        </div>
        <div class="hero__caption glass">
            <span class="muted">Filling now</span>
            <strong data-live-head-label="Bubble {label}"><?= $head !== null ? 'Bubble #' . number_format((int) $head['id']) : 'Waiting for the first bubble' ?></strong>
            <div class="meter"><span class="meter__bar" data-live-meter style="width: <?= round($fillPct, 2) ?>%"></span></div>
            <small class="muted"><?php if ($head !== null): ?><span data-live="head_needed"><?= e(money(max(0, $headTarget - $headFill))) ?></span> more to expire it<?php else: ?>Be the first in line<?php endif; ?></small>
        </div>
    </div>
</section>

<section class="statbar" aria-label="Live statistics">
    <div class="statbar__item"><span>Bubbles bought</span><strong data-live="bubbles_sold"><?= number_format((int) $pool['bubbles_sold']) ?></strong></div>
    <div class="statbar__item"><span>Expired &amp; paid</span><strong data-live="bubbles_expired"><?= number_format((int) $pool['bubbles_expired']) ?></strong></div>
    <div class="statbar__item"><span>Paid to members</span><strong data-live="total_out"><?= e(money($pool['total_out'])) ?></strong></div>
    <div class="statbar__item"><span>In the pool now</span><strong data-live="balance"><?= e(money($pool['balance'])) ?></strong></div>
    <div class="statbar__item"><span>Members</span><strong><?= number_format($members) ?></strong></div>
</section>

<section class="section" id="how">
    <header class="section__head">
        <span class="eyebrow">How it works</span>
        <h2>Four steps. One honest queue.</h2>
        <p>No hidden multipliers and no secret tiers: every bubble follows the same rules, in the same line.</p>
    </header>
    <ol class="steps">
        <li class="step glass">
            <span class="step__num">01</span>
            <span class="step__icon"><?= icon('download') ?></span>
            <h3>Fund your account</h3>
            <p>Deposit with one of the manual methods listed in your dashboard. An admin checks the payment and credits your purchase balance.</p>
        </li>
        <li class="step glass">
            <span class="step__num">02</span>
            <span class="step__icon"><?= icon('eye') ?></span>
            <h3>Watch a short ad</h3>
            <p>A sponsored message from another member plays for <?= (int) setting_int('ad_seconds') ?> seconds before every purchase. That is how advertisers reach real, active members.</p>
        </li>
        <li class="step glass">
            <span class="step__num">03</span>
            <span class="step__icon"><?= icon('bubble') ?></span>
            <h3>Buy bubbles</h3>
            <p><?= e(money($price)) ?> each. <?= e(money($share)) ?> goes to the pool and you instantly receive <?= number_format($credits) ?> ad credits to promote your own link.</p>
        </li>
        <li class="step glass">
            <span class="step__num">04</span>
            <span class="step__icon"><?= icon('burst') ?></span>
            <h3>Rise, fill, expire</h3>
            <p>Your bubble rises to the front of the queue. When the pool has paid it <?= e(money($target)) ?> it expires and the full amount lands in your cash balance.</p>
        </li>
    </ol>
</section>

<section class="section" id="pool">
    <div class="split">
        <div class="split__copy">
            <span class="eyebrow">Live pool</span>
            <h2>The queue is public.<br>Watch it move.</h2>
            <p>
                The pool always pays the oldest bubble first. With the current settings each bubble needs
                <strong><?= e($ratioText) ?> new bubbles</strong> to be bought after it reaches the front
                (<?= e(money($target)) ?> ÷ <?= e(money($share)) ?>). When purchases slow down, the queue slows down too.
            </p>
            <dl class="dl dl--inline">
                <div><dt>Bubbles in queue</dt><dd data-live="queue"><?= number_format(queue_length($pool)) ?></dd></div>
                <div><dt>Next to expire</dt><dd data-live="head_label"><?= $head !== null ? '#' . number_format((int) $head['id']) : '—' ?></dd></div>
                <div><dt>Still needed</dt><dd data-live="head_needed"><?= e(money($head !== null ? max(0, $headTarget - $headFill) : 0)) ?></dd></div>
            </dl>
        </div>
        <div class="split__panel glass card--glow">
            <div class="queue queue--landing">
                <?php if ($next === []): ?>
                    <?= empty_state('bubbles', 'The queue is empty', 'The first bubble bought will sit at the front of the line.', $startUrl, 'Blow the first bubble') ?>
                <?php else: ?>
                    <?php foreach ($next as $i => $bubble): ?>
                        <div class="queue__item<?= $i === 0 ? ' is-head' : '' ?>">
                            <?= bubble_html([
                                'size'  => $i === 0 ? 'md' : 'sm',
                                'state' => $i === 0 ? 'filling' : 'rising',
                                'fill'  => $i === 0 ? $fillPct : 0,
                                'label' => '#' . $bubble['id'],
                                'delay' => (string) -($i * 0.8),
                            ]) ?>
                            <span class="queue__label"><?= $i === 0 ? 'Filling' : 'Position ' . ($i + 1) ?><small><?= e(mask_name($bubble['username'])) ?></small></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if ($expirations !== []): ?>
                <div class="feed feed--compact">
                    <h3 class="feed__title">Recently expired</h3>
                    <?php foreach ($expirations as $bubble): ?>
                        <div class="feed__item">
                            <span class="feed__icon feed__icon--green"><?= icon('check') ?></span>
                            <span class="feed__body">Bubble #<?= number_format((int) $bubble['id']) ?> <small><?= e(mask_name($bubble['username'])) ?> · <?= e(time_ago($bubble['expired_at'])) ?></small></span>
                            <span class="feed__amount text-green">+<?= e(money($bubble['target'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section" id="advertise">
    <div class="split split--reverse">
        <div class="split__copy">
            <span class="eyebrow">Advertising built in</span>
            <h2>Every bubble is also<br><span class="text-iris">real advertising.</span></h2>
            <p>
                Each bubble you buy includes <?= number_format($credits) ?> ad credits. One credit buys one completed view by a member
                who is about to make a purchase — the moment their attention is highest.
            </p>
            <ul class="ticks">
                <li><?= icon('check') ?> Banner or text ads with your own headline and link</li>
                <li><?= icon('check') ?> Views are only billed when the <?= (int) setting_int('ad_seconds') ?>-second countdown completes</li>
                <li><?= icon('check') ?> Live views, clicks and click-through rate for every campaign</li>
                <li><?= icon('check') ?> Pause, top up or delete anytime — unused credits come back</li>
            </ul>
        </div>
        <div class="split__panel">
            <div class="adcreative adcreative--demo glass card--glow">
                <div class="adcreative__media adcreative__media--placeholder"><?= icon('megaphone') ?></div>
                <div class="adcreative__body">
                    <span class="eyebrow"><?= icon('sparkles') ?> Sponsored</span>
                    <h3>Your headline, in front of every buyer</h3>
                    <p>Show your brand, shop or channel to members right before they buy their next bubble.</p>
                    <span class="adcreative__domain"><?= icon('globe') ?> yoursite.com</span>
                </div>
                <div class="countdown countdown--static" aria-hidden="true">
                    <svg viewBox="0 0 44 44"><circle class="countdown__track" cx="22" cy="22" r="19"/><circle class="countdown__bar" cx="22" cy="22" r="19" style="stroke-dashoffset: 40"/></svg>
                    <span class="countdown__num"><?= (int) setting_int('ad_seconds') ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" id="faq">
    <header class="section__head">
        <span class="eyebrow">FAQ</span>
        <h2>Straight answers.</h2>
    </header>
    <div class="faq">
        <details class="faq__item glass" open>
            <summary>How does the pool decide which bubble expires?<?= icon('chevron-down') ?></summary>
            <p>Strictly first in, first out. <?= e(money($share)) ?> of every bubble bought goes into the pool, and the pool only ever pays the oldest active bubble. As soon as that bubble has received <?= e(money($target)) ?> it expires, the full amount is credited to its owner's cash balance and the next bubble moves to the front.</p>
        </details>
        <details class="faq__item glass">
            <summary>Is my payout guaranteed?<?= icon('chevron-down') ?></summary>
            <p>No. Bubbles are paid only from new bubble purchases entering the pool. If purchases slow down or stop, bubbles wait longer and may never expire. Only spend money you can afford to lose.</p>
        </details>
        <details class="faq__item glass">
            <summary>Where does the rest of the <?= e(money($price)) ?> go?<?= icon('chevron-down') ?></summary>
            <p><?= e(money($share)) ?> goes to the pool. <?php if (setting_int('referral_commission') > 0): ?><?= e(money(setting_int('referral_commission'))) ?> is paid to the member who referred the buyer, if any. <?php endif; ?>The remainder is the platform fee that runs the site, the ad network and payment processing.</p>
        </details>
        <details class="faq__item glass">
            <summary>What are ad credits for?<?= icon('chevron-down') ?></summary>
            <p>Each bubble includes <?= number_format($credits) ?> ad credits. Spend them on campaigns: every completed view of your ad costs one credit. Unused credits stay in your account and are refunded if you delete a campaign.</p>
        </details>
        <details class="faq__item glass">
            <summary>How do deposits and withdrawals work?<?= icon('chevron-down') ?></summary>
            <p>Both are handled manually. Choose a method, follow its instructions and submit your transaction reference; an admin reviews it and credits your purchase balance. Cash balances can be withdrawn with any active withdrawal method<?= setting_int('min_withdrawal') > 0 ? ' from ' . e(money(setting_int('min_withdrawal'))) : '' ?>.</p>
        </details>
        <details class="faq__item glass">
            <summary>Can I re-buy with my earnings?<?= icon('chevron-down') ?></summary>
            <p><?= setting_bool('allow_cash_purchase') ? 'Yes. When you buy bubbles you can choose to pay from your purchase balance or your cash balance.' : 'Bubbles are bought with your purchase balance, funded by deposits.' ?></p>
        </details>
    </div>
</section>

<section class="cta-band glass card--glow">
    <div>
        <h2>Ready to blow your first bubble?</h2>
        <p class="muted">It takes a minute to create an account. Read the risks first — then have fun watching the pool move.</p>
    </div>
    <a class="btn btn--primary btn--lg" href="<?= e($startUrl) ?>"><?= icon('sparkles') ?> <?= $me !== null ? 'Buy bubbles' : 'Create my account' ?></a>
</section>
