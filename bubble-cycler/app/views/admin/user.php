<?php
/**
 * @var array $member
 * @var bool $isSelf
 * @var ?array $referrer
 * @var int $ipTwins      other accounts seen on the member's IP
 * @var bool $referrerIp  the referrer used the same IP
 * @var int $referrals
 * @var array $stats
 * @var array $pool
 * @var array $bubbles
 * @var array $deposits
 * @var array $withdrawals
 * @var array $ledger
 * @var array $campaigns
 */
$id = (int) $member['id'];
$symbol = setting('currency_symbol', '$');
?>
<section class="profile card card--glow">
    <?= user_avatar($member['username'], 'xl') ?>
    <div class="profile__text">
        <h2><?= e($member['username']) ?> <?= $member['role'] === 'admin' ? status_badge('admin') : '' ?> <?= status_badge($member['status']) ?> <?= user_has_2fa($member) ? status_badge('active', t('2FA on')) : '' ?></h2>
        <p class="muted"><?= e($member['email']) ?> · <?= e($member['register_ip'] ? t('joined {date} from {ip}', ['date' => fmt_date($member['created_at'], 'M j, Y'), 'ip' => $member['register_ip']]) : t('joined {date}', ['date' => fmt_date($member['created_at'], 'M j, Y')])) ?> · <?= e($member['last_login_at'] ? ($member['last_ip'] ? t('last sign-in {ago} from {ip}', ['ago' => time_ago($member['last_login_at']), 'ip' => $member['last_ip']]) : t('last sign-in {ago}', ['ago' => time_ago($member['last_login_at'])])) : t('never signed in')) ?></p>
        <p class="muted"><?= $referrer !== null ? t_html('Referred by {user}', ['user' => '<a href="' . e(url('admin/user.php', ['id' => $referrer['id']])) . '">' . e($referrer['username']) . '</a>']) : e(t('Referred by nobody')) ?> · <?= e(tn('{n} referral', '{n} referrals', $referrals)) ?></p>
        <?php if ($referrerIp || $ipTwins > 0): ?>
            <p class="flag"><?= icon('alert') ?> <span>
                <?= $referrerIp ? e(t('Same IP address as the referrer.')) . ' ' : '' ?>
                <?php if ($ipTwins > 0): ?><a href="<?= e(url('admin/users.php', ['q' => $member['register_ip'] ?? $member['last_ip']])) ?>"><?= e(tn('{n} other account on this IP', '{n} other accounts on this IP', $ipTwins)) ?></a>.<?php endif; ?>
            </span></p>
        <?php endif; ?>
    </div>
</section>

<div class="grid grid--4">
    <article class="stat"><span class="stat__icon stat__icon--violet"><?= icon('wallet') ?></span><span class="stat__label"><?= e(t('Purchase balance')) ?></span><strong class="stat__value"><?= e(money($member['purchase_balance'])) ?></strong><span class="stat__hint"><?= e(t('{amount} deposited', ['amount' => money($member['total_deposited'])])) ?></span></article>
    <article class="stat"><span class="stat__icon stat__icon--green"><?= icon('coins') ?></span><span class="stat__label"><?= e(t('Cash balance')) ?></span><strong class="stat__value"><?= e(money($member['cash_balance'])) ?></strong><span class="stat__hint"><?= e(t('{amount} withdrawn', ['amount' => money($member['total_withdrawn'])])) ?></span></article>
    <article class="stat"><span class="stat__icon stat__icon--blue"><?= icon('bubbles') ?></span><span class="stat__label"><?= e(t('Bubbles')) ?></span><strong class="stat__value"><?= e(num($stats['active'])) ?> <small class="muted"><?= e(tn('active', 'active', $stats['active'])) ?></small></strong><span class="stat__hint"><?= e(tn('{n} expired', '{n} expired', $stats['expired'])) ?> · <?= e(t('{amount} earned', ['amount' => money($member['total_earned'])])) ?></span></article>
    <article class="stat"><span class="stat__icon stat__icon--pink"><?= icon('megaphone') ?></span><span class="stat__label"><?= e(t('Ad credits')) ?></span><strong class="stat__value"><?= e(num($member['ad_credits'])) ?></strong><span class="stat__hint"><?= e(t('{amount} referral earnings', ['amount' => money($member['total_ref_earned'])])) ?></span></article>
</div>

<div class="grid grid--3 grid--top">
    <form method="post" class="card form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="deposit">
        <input type="hidden" name="id" value="<?= $id ?>">
        <header class="card__head"><div><h2 class="card__title"><?= e(t('Manual deposit')) ?></h2><p class="card__sub"><?= e(t('Money received outside the site — credited to the purchase balance and recorded as an approved deposit.')) ?></p></div></header>
        <label class="field"><span class="field__label"><?= e(t('Amount')) ?></span><span class="input-group"><span class="input-group__addon"><?= e($symbol) ?></span><input class="input" name="amount" inputmode="decimal" placeholder="25.00" required></span></label>
        <label class="field"><span class="field__label"><?= e(t('Reference / note')) ?></span><input class="input" name="note" maxlength="190" placeholder="<?= e(t('e.g. Cash payment, receipt #1042')) ?>"></label>
        <button class="btn btn--primary" type="submit" data-confirm="<?= e(t('Add this deposit to the purchase balance of {user}?', ['user' => $member['username']])) ?>"><?= icon('download') ?> <?= e(t('Add deposit')) ?></button>
    </form>

    <form method="post" class="card form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="adjust">
        <input type="hidden" name="id" value="<?= $id ?>">
        <header class="card__head"><div><h2 class="card__title"><?= e(t('Adjust a balance')) ?></h2><p class="card__sub"><?= e(t('Corrections and bonuses. Every adjustment is written to the ledger and the audit log.')) ?></p></div></header>
        <div class="field-row">
            <label class="field"><span class="field__label"><?= e(t('Wallet')) ?></span>
                <select class="select" name="wallet">
                    <option value="purchase"><?= e(t('Purchase')) ?></option>
                    <option value="cash"><?= e(t('Cash')) ?></option>
                    <option value="ads"><?= e(t('Ad credits')) ?></option>
                </select>
            </label>
            <label class="field"><span class="field__label"><?= e(t('Direction')) ?></span>
                <select class="select" name="direction">
                    <option value="credit"><?= e(t('Credit (+)')) ?></option>
                    <option value="debit"><?= e(t('Debit (−)')) ?></option>
                </select>
            </label>
        </div>
        <label class="field"><span class="field__label"><?= e(t('Amount')) ?> <small class="muted"><?= e(t('money, or whole credits')) ?></small></span><input class="input" name="amount" inputmode="decimal" required></label>
        <label class="field"><span class="field__label"><?= e(t('Reason')) ?></span><input class="input" name="note" maxlength="200" required placeholder="<?= e(t('Shown in the member’s history')) ?>"></label>
        <button class="btn btn--secondary" type="submit" data-confirm="<?= e(t('Apply this balance adjustment?')) ?>"><?= icon('sliders') ?> <?= e(t('Apply')) ?></button>
    </form>

    <div class="card form">
        <header class="card__head"><div><h2 class="card__title"><?= e(t('Account')) ?></h2><p class="card__sub"><?= e(t('Access and security.')) ?></p></div></header>
        <?php if ($isSelf): ?>
            <p class="muted"><?= e(t('This is your own account — use the Account page to change your password.')) ?></p>
        <?php else: ?>
            <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input class="input" type="text" name="password" minlength="8" placeholder="<?= e(t('New password')) ?>" autocomplete="off" required aria-label="<?= e(t('New password (at least 8 characters)')) ?>">
                <button class="btn btn--secondary" type="submit"><?= icon('key') ?> <?= e(t('Reset')) ?></button>
            </form>
            <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="email">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input class="input" type="email" name="email" value="<?= e($member['email']) ?>" maxlength="190" required aria-label="<?= e(t('Email')) ?>">
                <button class="btn btn--secondary" type="submit"><?= icon('mail') ?> <?= e(t('Save')) ?></button>
            </form>
            <?php if (user_has_2fa($member)): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="2fa">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button class="btn btn--ghost" type="submit" data-confirm="<?= e(t('Turn off two-factor authentication for {user}? Only do this after verifying their identity.', ['user' => $member['username']])) ?>"><?= icon('shield') ?> <?= e(t('Reset two-factor')) ?></button>
                </form>
            <?php endif; ?>
            <div class="row row--wrap">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <?php if ($member['status'] === 'active'): ?>
                        <input type="hidden" name="status" value="banned">
                        <button class="btn btn--danger" type="submit" data-confirm="<?= e(t('Ban {user}? They will be signed out immediately. Their bubbles stay in the queue.', ['user' => $member['username']])) ?>"><?= icon('ban') ?> <?= e(t('Ban member')) ?></button>
                    <?php else: ?>
                        <input type="hidden" name="status" value="active">
                        <button class="btn btn--success" type="submit"><?= icon('check') ?> <?= e(t('Re-activate')) ?></button>
                    <?php endif; ?>
                </form>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="role">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="role" value="<?= $member['role'] === 'admin' ? 'user' : 'admin' ?>">
                    <button class="btn btn--ghost" type="submit" data-confirm="<?= e($member['role'] === 'admin' ? t('Remove admin rights?') : t('Give this member full admin rights?')) ?>"><?= icon('shield') ?> <?= e($member['role'] === 'admin' ? t('Remove admin') : t('Make admin')) ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid--2 grid--top">
    <section class="card">
        <header class="card__head"><h2 class="card__title"><?= e(t('Bubbles')) ?></h2><a class="btn btn--ghost btn--sm" href="<?= e(url('admin/bubbles.php', ['user' => $member['username']])) ?>"><?= e(t('All')) ?> <?= icon('arrow-right') ?></a></header>
        <?php if ($bubbles === []): ?>
            <p class="muted"><?= e(t('No bubbles yet.')) ?></p>
        <?php else: ?>
            <div class="bubble-row">
                <?php foreach ($bubbles as $i => $b): $state = bubble_state($b, $pool); ?>
                    <div class="bubble-row__item">
                        <?= bubble_html(['size' => 'sm', 'state' => $state['state'], 'fill' => $state['fill'], 'rise' => $state['rise'], 'label' => '#' . num($b['id']), 'float' => false]) ?>
                        <span><?= e($state['state'] === 'expired' ? t('#{id} · paid', ['id' => num($b['id'])]) : ($state['state'] === 'filling' ? t('Filling') : t('Pos. {n}', ['n' => num($state['position'])]))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <header class="card__head"><h2 class="card__title"><?= e(t('Ledger')) ?></h2><a class="btn btn--ghost btn--sm" href="<?= e(url('admin/transactions.php', ['user' => $member['username']])) ?>"><?= e(t('All')) ?> <?= icon('arrow-right') ?></a></header>
        <?php if ($ledger === []): ?>
            <p class="muted"><?= e(t('No transactions yet.')) ?></p>
        <?php else: ?>
            <div class="feed">
                <?php foreach ($ledger as $tx): ?>
                    <div class="feed__item">
                        <span class="feed__body"><?= e(tx_label($tx['type'])) ?><small><?= e(str_limit(stored_text($tx['description']), 56)) ?> · <?= e(time_ago($tx['created_at'])) ?></small></span>
                        <span class="feed__amount <?= (int) $tx['amount'] > 0 ? 'text-green' : 'muted' ?>"><?= e(ledger_amount($tx)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="grid grid--3 grid--top">
    <section class="card">
        <header class="card__head"><h2 class="card__title"><?= e(t('Deposits')) ?></h2></header>
        <?php if ($deposits === []): ?><p class="muted"><?= e(t('None.')) ?></p><?php endif; ?>
        <div class="feed">
            <?php foreach ($deposits as $d): ?>
                <a class="feed__item" href="<?= e(url('admin/deposits.php', ['status' => 'all', 'review' => $d['id']])) ?>">
                    <span class="feed__body">#<?= e(num($d['id'])) ?> · <?= e(t($d['method_name'])) ?><small><?= e(time_ago($d['created_at'])) ?></small></span>
                    <span class="feed__amount"><?= e(money($d['amount'])) ?> <?= status_badge($d['status']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="card">
        <header class="card__head"><h2 class="card__title"><?= e(t('Withdrawals')) ?></h2></header>
        <?php if ($withdrawals === []): ?><p class="muted"><?= e(t('None.')) ?></p><?php endif; ?>
        <div class="feed">
            <?php foreach ($withdrawals as $w): ?>
                <a class="feed__item" href="<?= e(url('admin/withdrawals.php', ['status' => 'all', 'review' => $w['id']])) ?>">
                    <span class="feed__body">#<?= e(num($w['id'])) ?> · <?= e($w['method_name']) ?><small><?= e(time_ago($w['created_at'])) ?></small></span>
                    <span class="feed__amount"><?= e(money($w['amount'])) ?> <?= status_badge($w['status']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="card">
        <header class="card__head"><h2 class="card__title"><?= e(t('Campaigns')) ?></h2></header>
        <?php if ($campaigns === []): ?><p class="muted"><?= e(t('None.')) ?></p><?php endif; ?>
        <div class="feed">
            <?php foreach ($campaigns as $c): ?>
                <a class="feed__item" href="<?= e(url('admin/ads.php', ['status' => 'all', 'review' => $c['id']])) ?>">
                    <span class="feed__body"><?= e(str_limit($c['title'], 28)) ?><small><?= e(tn('{n} view', '{n} views', (int) $c['views'])) ?> · <?= e(tn('{n} credit left', '{n} credits left', (int) $c['credits_remaining'])) ?></small></span>
                    <span class="feed__amount"><?= status_badge($c['status'], null, 'campaign') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>
