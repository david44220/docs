<?php
/**
 * @var array $user
 * @var string $link
 * @var array $referrals
 * @var array $earned    member id => commission earned
 * @var int $active
 * @var array $pager
 */
$commission = setting_int('referral_commission');
?>
<section class="hub card card--glow">
    <div class="hub__intro">
        <span class="eyebrow"><?= icon('gift') ?> <?= e(t('Referral program')) ?></span>
        <?php if ($commission > 0): ?>
            <h2><?= e(t('Earn {amount} for every bubble your referrals buy.', ['amount' => money($commission)])) ?></h2>
            <p class="muted"><?= e(t('Commissions are paid instantly to your cash balance, out of the platform share — they never reduce the pool.')) ?></p>
        <?php else: ?>
            <h2><?= e(t('Share {site} with friends.', ['site' => site_name()])) ?></h2>
            <p class="muted"><?= e(t('Referral commissions are currently switched off, but your referrals are still tracked.')) ?></p>
        <?php endif; ?>
        <div class="copy copy--inline">
            <div class="copy__box">
                <code class="copy__value" data-copy-source><?= e($link) ?></code>
                <button class="btn btn--primary btn--sm copy__btn" type="button" data-copy><?= icon('copy') ?> <span><?= e(t('Copy link')) ?></span></button>
            </div>
        </div>
    </div>
    <dl class="hub__stats">
        <div><dt><?= e(t('Referrals')) ?></dt><dd><?= e(num($pager['total'])) ?></dd></div>
        <div><dt><?= e(t('Active buyers')) ?></dt><dd><?= e(num($active)) ?></dd></div>
        <div><dt><?= e(t('Commission earned')) ?></dt><dd class="text-green"><?= e(money($user['total_ref_earned'])) ?></dd></div>
    </dl>
</section>

<section class="card card--flush">
    <header class="card__head card__head--pad"><h2 class="card__title"><?= e(t('Your referrals')) ?></h2></header>
    <?php if ($referrals === []): ?>
        <?= empty_state('users', t('No referrals yet'), t('Share your link — anyone who signs up through it is linked to your account.')) ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(t('Member')) ?></th><th><?= e(t('Joined')) ?></th><th class="num"><?= e(t('Bubbles bought')) ?></th><th class="num"><?= e(t('Your commission')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($referrals as $r): ?>
                    <tr>
                        <td><span class="cell-user"><?= user_avatar($r['username'], 'sm') ?><?= e($r['username']) ?></span></td>
                        <td class="muted"><?= e(fmt_date($r['created_at'], 'M j, Y')) ?></td>
                        <td class="num"><?= e(num($r['bubbles_bought'])) ?></td>
                        <td class="num text-green"><?= e(money($earned[(int) $r['id']] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
