<?php
/** Terms & risk disclosure. Custom text from Admin → Settings replaces the default terms. */
$custom = setting_text('terms_text');
$site = site_name();
?>
<section class="section doc">
    <aside class="doc__aside">
        <div class="alert alert--warning">
            <?= icon('alert') ?>
            <div>
                <strong class="alert__title"><?= e(t('Risk disclosure')) ?></strong>
                <p><?= e(setting_text('disclaimer')) ?></p>
            </div>
        </div>
    </aside>

    <article class="prose">
        <?php if ($custom !== ''): ?>
            <?php foreach (preg_split('/\R{2,}/', $custom) as $paragraph): ?>
                <p><?= nl2br(e(trim($paragraph))) ?></p>
            <?php endforeach; ?>
        <?php else: ?>
            <h2><?= e(t('What {site} is', ['site' => $site])) ?></h2>
            <p><?= e(t('{site} is a bubble cycler game with an advertising network. Members buy bubbles for {price} each. {share} of every bubble is credited to a shared pool and each bubble receives advertising credits.', ['site' => $site, 'price' => money(setting_int('bubble_price')), 'share' => money(setting_int('pool_share'))])) ?></p>

            <h2><?= e(t('How payouts work')) ?></h2>
            <p><?= e(t('Bubbles wait in a single first-in-first-out queue. The pool pays the oldest active bubble; once it has received {target} it expires and that amount is credited to its owner’s cash balance. Payouts come exclusively from new bubble purchases (and any amount the operator adds to the pool). There is no guarantee that any bubble will expire, nor any promise about when.', ['target' => money(setting_int('bubble_target'))])) ?></p>

            <h2><?= e(t('Deposits & withdrawals')) ?></h2>
            <p><?= e(t('Deposits and withdrawals are processed manually by the operator using the methods listed in the member area. Fees and limits are shown on each method before you confirm. The operator may reject deposits that cannot be verified and withdrawals whose details are invalid; rejected withdrawals are refunded to your cash balance.')) ?></p>

            <h2><?= e(t('Advertising')) ?></h2>
            <p><?= e(t('Ads must not promote illegal content, malware, adult content, scams or other cycler and investment programs. The operator may reject or remove any campaign. One ad credit pays for one completed view.')) ?></p>

            <h2><?= e(t('Accounts')) ?></h2>
            <p><?= e(t('One account per person. Automated purchases, multiple accounts and abuse of the referral program may lead to suspension. You are responsible for keeping your password safe.')) ?></p>

            <h2><?= e(t('Your responsibility')) ?></h2>
            <p><?= e(t('Participation may be restricted or regulated where you live. You are responsible for complying with your local laws and for any taxes. Never spend money you cannot afford to lose.')) ?></p>
        <?php endif; ?>
    </article>
</section>
