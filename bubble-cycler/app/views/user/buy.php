<?php
/**
 * @var array $user
 * @var array $pool
 * @var ?array $ad        ['view' => ..., 'campaign' => ?array, 'remaining' => int]
 * @var array $quote
 * @var int $quantity
 * @var string $wallet
 * @var string $nonce     one-time form token
 */
$price = setting_int('bubble_price');
$share = setting_int('pool_share');
$target = setting_int('bubble_target');
$referral = setting_int('referral_commission');
$platform = max(0, $price - $share - $referral);
$credits = setting_int('ad_credits_per_bubble');
$max = setting_int('max_bubbles_per_purchase') ?: 1000;
$seconds = max(0, setting_int('ad_seconds'));
$adRequired = setting_bool('ad_required');
$cashAllowed = setting_bool('allow_cash_purchase');
$locked = $ad !== null && $ad['remaining'] > 0;
$campaign = $ad['campaign'] ?? null;
$token = $ad['view']['token'] ?? '';
$clickUrl = $token !== '' ? url('ad.php', ['a' => 'click', 't' => $token]) : '#';
$chips = array_values(array_filter([1, 5, 10, 25, 50], static fn (int $n): bool => $n <= $max));
?>
<div class="grid grid--buy">
    <?php if ($ad !== null): ?>
        <section class="adgate card card--glow<?= $locked ? '' : ' is-done' ?>" data-ad-gate
                 data-remaining="<?= (int) $ad['remaining'] ?>" data-total="<?= $seconds ?>"
                 data-token="<?= e($token) ?>" data-complete-url="<?= e(url('ad.php', ['a' => 'complete'])) ?>">
            <header class="adgate__head">
                <div>
                    <span class="eyebrow"><span class="step-dot">1</span> Sponsored message</span>
                    <p class="adgate__lead">Watch for <?= $seconds ?> seconds to unlock your purchase.</p>
                </div>
                <div class="countdown" role="timer" aria-live="off" aria-label="Seconds remaining">
                    <svg viewBox="0 0 44 44" aria-hidden="true">
                        <circle class="countdown__track" cx="22" cy="22" r="19"/>
                        <circle class="countdown__bar" cx="22" cy="22" r="19" data-countdown-bar/>
                    </svg>
                    <span class="countdown__num" data-countdown-num><?= (int) $ad['remaining'] ?></span>
                    <span class="countdown__done"><?= icon('check') ?></span>
                </div>
            </header>

            <a class="adcreative" href="<?= e($clickUrl) ?>" target="_blank" rel="sponsored noopener">
                <?php if ($campaign !== null && !empty($campaign['image_url'])): ?>
                    <span class="adcreative__media"><img src="<?= e($campaign['image_url']) ?>" alt="" referrerpolicy="no-referrer"></span>
                <?php else: ?>
                    <span class="adcreative__media adcreative__media--placeholder">
                        <span class="adcreative__initial"><?= e(mb_strtoupper(mb_substr(url_host($campaign['url'] ?? site_name()), 0, 1))) ?></span>
                    </span>
                <?php endif; ?>
                <span class="adcreative__body">
                    <strong class="adcreative__title"><?= e($campaign['title'] ?? 'Sponsored message') ?></strong>
                    <?php if (!empty($campaign['description'])): ?><span class="adcreative__text"><?= e($campaign['description']) ?></span><?php endif; ?>
                    <?php if ($campaign !== null): ?>
                        <span class="adcreative__domain"><?= icon('globe') ?> <?= e(url_host($campaign['url'])) ?></span>
                    <?php endif; ?>
                </span>
            </a>

            <footer class="adgate__foot">
                <p class="adgate__status" data-ad-status>
                    <?php if ($locked): ?>
                        <?= icon('lock') ?> <span>Your purchase unlocks in <b data-countdown-text><?= (int) $ad['remaining'] ?>s</b>. Keep this tab open.<noscript> JavaScript is off: reload this page when the time is up.</noscript></span>
                    <?php else: ?>
                        <?= icon('unlock') ?> <span>Thanks for watching — your purchase is unlocked.</span>
                    <?php endif; ?>
                </p>
                <?php if ($campaign !== null): ?>
                    <a class="btn btn--secondary btn--sm" href="<?= e($clickUrl) ?>" target="_blank" rel="sponsored noopener"><?= e($campaign['cta_label'] ?: 'Visit site') ?> <?= icon('external') ?></a>
                <?php endif; ?>
            </footer>
        </section>
    <?php else: ?>
        <section class="adgate card is-done">
            <header class="adgate__head">
                <div>
                    <span class="eyebrow"><span class="step-dot">1</span> Sponsored message</span>
                    <p class="adgate__lead"><?= $adRequired ? 'No sponsored message is running right now.' : 'Ads are switched off for purchases.' ?></p>
                </div>
                <div class="countdown"><span class="countdown__done"><?= icon('check') ?></span></div>
            </header>
            <div class="adcreative adcreative--empty">
                <span class="adcreative__media adcreative__media--placeholder"><?= icon('megaphone') ?></span>
                <span class="adcreative__body">
                    <strong class="adcreative__title">This spot is available</strong>
                    <span class="adcreative__text">Use the ad credits included with your bubbles to show your own message here.</span>
                    <a class="adcreative__domain" href="<?= e(url('advertise.php')) ?>"><?= icon('arrow-right') ?> Create a campaign</a>
                </span>
            </div>
        </section>
    <?php endif; ?>

    <form method="post" class="card buy" data-buy-form
          data-price="<?= $price ?>" data-share="<?= $share ?>" data-credits="<?= $credits ?>"
          data-needed="<?= (int) $quote['needed'] ?>" data-symbol="<?= e(setting('currency_symbol', '$')) ?>"
          data-balance-purchase="<?= (int) $user['purchase_balance'] ?>" data-balance-cash="<?= (int) $user['cash_balance'] ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="ad_token" value="<?= e($token) ?>">
        <input type="hidden" name="nonce" value="<?= e($nonce) ?>">

        <header class="card__head">
            <div>
                <span class="eyebrow"><span class="step-dot">2</span> Your bubbles</span>
                <h2 class="card__title"><?= e(money($price)) ?> each · expires at <?= e(money($target)) ?></h2>
            </div>
        </header>

        <div class="field">
            <span class="field__label" id="qty-label">How many bubbles?</span>
            <div class="stepper">
                <button class="stepper__btn" type="button" data-step="-1" aria-label="One less"><?= icon('minus') ?></button>
                <input class="stepper__input" type="number" name="quantity" value="<?= $quantity ?>" min="1" max="<?= $max ?>" inputmode="numeric" aria-labelledby="qty-label" data-qty>
                <button class="stepper__btn" type="button" data-step="1" aria-label="One more"><?= icon('plus') ?></button>
            </div>
            <div class="chips">
                <?php foreach ($chips as $n): ?>
                    <button class="chip<?= $n === $quantity ? ' is-active' : '' ?>" type="button" data-qty-set="<?= $n ?>"><?= $n ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <fieldset class="field">
            <legend class="field__label">Pay with</legend>
            <div class="segmented">
                <label class="segmented__option">
                    <input type="radio" name="wallet" value="purchase"<?= $wallet === 'purchase' ? ' checked' : '' ?> data-wallet>
                    <span><b>Purchase balance</b><small><?= e(money($user['purchase_balance'])) ?></small></span>
                </label>
                <label class="segmented__option<?= $cashAllowed ? '' : ' is-disabled' ?>">
                    <input type="radio" name="wallet" value="cash"<?= $wallet === 'cash' ? ' checked' : '' ?><?= $cashAllowed ? '' : ' disabled' ?> data-wallet>
                    <span><b>Cash balance</b><small><?= $cashAllowed ? e(money($user['cash_balance'])) : 'Disabled' ?></small></span>
                </label>
            </div>
        </fieldset>

        <dl class="summary">
            <div><dt>Total</dt><dd class="summary__total" data-sum-total><?= e(money($price * $quantity)) ?></dd></div>
            <div><dt>Credited to the pool</dt><dd data-sum-pool><?= e(money($share * $quantity)) ?></dd></div>
            <div><dt>Ad credits included</dt><dd class="text-pink" data-sum-credits>+<?= number_format($credits * $quantity) ?></dd></div>
            <div><dt>Your first bubble</dt><dd>#<?= number_format($quote['first_id']) ?> · <?= plural($quote['ahead'], 'bubble') ?> ahead</dd></div>
            <div><dt>Expected to expire after</dt><dd data-sum-sales>~<?= plural($quote['sales'], 'more sale') ?></dd></div>
        </dl>

        <p class="buy__warning" data-buy-warning hidden><?= icon('alert') ?> <span>Not enough balance. <a href="<?= e(url('deposit.php')) ?>">Make a deposit</a>.</span></p>

        <button class="btn btn--primary btn--xl btn--block buy__submit<?= $locked ? ' is-locked' : '' ?>" type="submit" data-buy-submit<?= $locked ? ' disabled' : '' ?>>
            <span class="buy__lock"><?= icon('lock') ?></span>
            <span class="buy__go"><?= icon('sparkles') ?></span>
            <span data-buy-label>Buy <?= plural($quantity, 'bubble') ?> · <?= e(money($price * $quantity)) ?></span>
        </button>
        <p class="buy__fineprint muted">Payouts depend on future purchases and are not guaranteed.</p>
    </form>
</div>

<section class="card split-card">
    <div>
        <h2 class="card__title">Where each <?= e(money($price)) ?> goes</h2>
        <p class="card__sub">The split is identical for every bubble and every member.</p>
    </div>
    <div class="splitbar" role="img" aria-label="Pool <?= e(money($share)) ?>, referral <?= e(money($referral)) ?>, platform <?= e(money($platform)) ?>">
        <span class="splitbar__seg splitbar__seg--pool" style="flex: <?= max(1, $share) ?>"></span>
        <?php if ($referral > 0): ?><span class="splitbar__seg splitbar__seg--ref" style="flex: <?= $referral ?>"></span><?php endif; ?>
        <?php if ($platform > 0): ?><span class="splitbar__seg splitbar__seg--fee" style="flex: <?= $platform ?>"></span><?php endif; ?>
    </div>
    <ul class="legend">
        <li><i class="legend__dot legend__dot--pool"></i> Pool <b><?= e(money($share)) ?></b> <span class="muted"><?= pct($share, $price) ?></span></li>
        <?php if ($referral > 0): ?><li><i class="legend__dot legend__dot--ref"></i> Referrer <b><?= e(money($referral)) ?></b> <span class="muted"><?= pct($referral, $price) ?></span></li><?php endif; ?>
        <li><i class="legend__dot legend__dot--fee"></i> Platform <b><?= e(money($platform)) ?></b> <span class="muted"><?= pct($platform, $price) ?></span></li>
    </ul>
</section>
