<?php
/**
 * @var array $member
 * @var bool $isSelf
 * @var ?array $referrer
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
        <h2><?= e($member['username']) ?> <?= $member['role'] === 'admin' ? status_badge('admin', 'Admin') : '' ?> <?= status_badge($member['status']) ?></h2>
        <p class="muted"><?= e($member['email']) ?> · joined <?= e(fmt_date($member['created_at'], 'M j, Y')) ?> · last sign-in <?= e($member['last_login_at'] ? time_ago($member['last_login_at']) : 'never') ?><?= $member['last_ip'] ? ' from ' . e($member['last_ip']) : '' ?></p>
        <p class="muted">Referred by <?= $referrer !== null ? '<a href="' . e(url('admin/user.php', ['id' => $referrer['id']])) . '">' . e($referrer['username']) . '</a>' : 'nobody' ?> · <?= plural($referrals, 'referral') ?></p>
    </div>
</section>

<div class="grid grid--4">
    <article class="stat"><span class="stat__icon stat__icon--violet"><?= icon('wallet') ?></span><span class="stat__label">Purchase balance</span><strong class="stat__value"><?= e(money($member['purchase_balance'])) ?></strong><span class="stat__hint"><?= e(money($member['total_deposited'])) ?> deposited</span></article>
    <article class="stat"><span class="stat__icon stat__icon--green"><?= icon('coins') ?></span><span class="stat__label">Cash balance</span><strong class="stat__value"><?= e(money($member['cash_balance'])) ?></strong><span class="stat__hint"><?= e(money($member['total_withdrawn'])) ?> withdrawn</span></article>
    <article class="stat"><span class="stat__icon stat__icon--blue"><?= icon('bubbles') ?></span><span class="stat__label">Bubbles</span><strong class="stat__value"><?= number_format($stats['active']) ?> <small class="muted">active</small></strong><span class="stat__hint"><?= number_format($stats['expired']) ?> expired · <?= e(money($member['total_earned'])) ?> earned</span></article>
    <article class="stat"><span class="stat__icon stat__icon--pink"><?= icon('megaphone') ?></span><span class="stat__label">Ad credits</span><strong class="stat__value"><?= number_format((int) $member['ad_credits']) ?></strong><span class="stat__hint"><?= e(money($member['total_ref_earned'])) ?> referral earnings</span></article>
</div>

<div class="grid grid--3 grid--top">
    <form method="post" class="card form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="deposit">
        <input type="hidden" name="id" value="<?= $id ?>">
        <header class="card__head"><div><h2 class="card__title">Manual deposit</h2><p class="card__sub">Money received outside the site — credited to the purchase balance and recorded as an approved deposit.</p></div></header>
        <label class="field"><span class="field__label">Amount</span><span class="input-group"><span class="input-group__addon"><?= e($symbol) ?></span><input class="input" name="amount" inputmode="decimal" placeholder="25.00" required></span></label>
        <label class="field"><span class="field__label">Reference / note</span><input class="input" name="note" maxlength="190" placeholder="e.g. Cash payment, receipt #1042"></label>
        <button class="btn btn--primary" type="submit" data-confirm="Add this deposit to <?= e($member['username']) ?>’s purchase balance?"><?= icon('download') ?> Add deposit</button>
    </form>

    <form method="post" class="card form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="adjust">
        <input type="hidden" name="id" value="<?= $id ?>">
        <header class="card__head"><div><h2 class="card__title">Adjust a balance</h2><p class="card__sub">Corrections and bonuses. Every adjustment is written to the ledger and the audit log.</p></div></header>
        <div class="field-row">
            <label class="field"><span class="field__label">Wallet</span>
                <select class="select" name="wallet">
                    <option value="purchase">Purchase</option>
                    <option value="cash">Cash</option>
                    <option value="ads">Ad credits</option>
                </select>
            </label>
            <label class="field"><span class="field__label">Direction</span>
                <select class="select" name="direction">
                    <option value="credit">Credit (+)</option>
                    <option value="debit">Debit (−)</option>
                </select>
            </label>
        </div>
        <label class="field"><span class="field__label">Amount <small class="muted">money, or whole credits</small></span><input class="input" name="amount" inputmode="decimal" required></label>
        <label class="field"><span class="field__label">Reason</span><input class="input" name="note" maxlength="200" required placeholder="Shown in the member’s history"></label>
        <button class="btn btn--secondary" type="submit" data-confirm="Apply this balance adjustment?"><?= icon('sliders') ?> Apply</button>
    </form>

    <div class="card form">
        <header class="card__head"><div><h2 class="card__title">Account</h2><p class="card__sub">Access and security.</p></div></header>
        <?php if ($isSelf): ?>
            <p class="muted">This is your own account — use the Account page to change your password.</p>
        <?php else: ?>
            <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input class="input" type="text" name="password" minlength="8" placeholder="New password (8+ chars)" autocomplete="off" required>
                <button class="btn btn--secondary" type="submit"><?= icon('key') ?> Reset</button>
            </form>
            <div class="row row--wrap">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <?php if ($member['status'] === 'active'): ?>
                        <input type="hidden" name="status" value="banned">
                        <button class="btn btn--danger" type="submit" data-confirm="Ban <?= e($member['username']) ?>? They will be signed out immediately. Their bubbles stay in the queue."><?= icon('ban') ?> Ban member</button>
                    <?php else: ?>
                        <input type="hidden" name="status" value="active">
                        <button class="btn btn--success" type="submit"><?= icon('check') ?> Re-activate</button>
                    <?php endif; ?>
                </form>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="role">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="role" value="<?= $member['role'] === 'admin' ? 'user' : 'admin' ?>">
                    <button class="btn btn--ghost" type="submit" data-confirm="<?= $member['role'] === 'admin' ? 'Remove admin rights?' : 'Give this member full admin rights?' ?>"><?= icon('shield') ?> <?= $member['role'] === 'admin' ? 'Remove admin' : 'Make admin' ?></button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid--2 grid--top">
    <section class="card">
        <header class="card__head"><h2 class="card__title">Bubbles</h2><a class="btn btn--ghost btn--sm" href="<?= e(url('admin/bubbles.php', ['user' => $member['username']])) ?>">All <?= icon('arrow-right') ?></a></header>
        <?php if ($bubbles === []): ?>
            <p class="muted">No bubbles yet.</p>
        <?php else: ?>
            <div class="bubble-row">
                <?php foreach ($bubbles as $i => $b): $state = bubble_state($b, $pool); ?>
                    <div class="bubble-row__item">
                        <?= bubble_html(['size' => 'sm', 'state' => $state['state'], 'fill' => $state['fill'], 'rise' => $state['rise'], 'label' => '#' . $b['id'], 'float' => false]) ?>
                        <span><?= $state['state'] === 'expired' ? '#' . number_format((int) $b['id']) . ' · paid' : ($state['state'] === 'filling' ? 'Filling' : 'Pos. ' . number_format($state['position'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <header class="card__head"><h2 class="card__title">Ledger</h2><a class="btn btn--ghost btn--sm" href="<?= e(url('admin/transactions.php', ['user' => $member['username']])) ?>">All <?= icon('arrow-right') ?></a></header>
        <?php if ($ledger === []): ?>
            <p class="muted">No transactions yet.</p>
        <?php else: ?>
            <div class="feed">
                <?php foreach ($ledger as $tx): ?>
                    <div class="feed__item">
                        <span class="feed__body"><?= e(tx_label($tx['type'])) ?><small><?= e(str_limit($tx['description'], 56)) ?> · <?= e(time_ago($tx['created_at'])) ?></small></span>
                        <span class="feed__amount <?= (int) $tx['amount'] > 0 ? 'text-green' : 'muted' ?>"><?= e(ledger_amount($tx)) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="grid grid--3 grid--top">
    <section class="card">
        <header class="card__head"><h2 class="card__title">Deposits</h2></header>
        <?php if ($deposits === []): ?><p class="muted">None.</p><?php endif; ?>
        <div class="feed">
            <?php foreach ($deposits as $d): ?>
                <a class="feed__item" href="<?= e(url('admin/deposits.php', ['status' => 'all', 'review' => $d['id']])) ?>">
                    <span class="feed__body">#<?= (int) $d['id'] ?> · <?= e($d['method_name']) ?><small><?= e(time_ago($d['created_at'])) ?></small></span>
                    <span class="feed__amount"><?= e(money($d['amount'])) ?> <?= status_badge($d['status']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="card">
        <header class="card__head"><h2 class="card__title">Withdrawals</h2></header>
        <?php if ($withdrawals === []): ?><p class="muted">None.</p><?php endif; ?>
        <div class="feed">
            <?php foreach ($withdrawals as $w): ?>
                <a class="feed__item" href="<?= e(url('admin/withdrawals.php', ['status' => 'all', 'review' => $w['id']])) ?>">
                    <span class="feed__body">#<?= (int) $w['id'] ?> · <?= e($w['method_name']) ?><small><?= e(time_ago($w['created_at'])) ?></small></span>
                    <span class="feed__amount"><?= e(money($w['amount'])) ?> <?= status_badge($w['status']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="card">
        <header class="card__head"><h2 class="card__title">Campaigns</h2></header>
        <?php if ($campaigns === []): ?><p class="muted">None.</p><?php endif; ?>
        <div class="feed">
            <?php foreach ($campaigns as $c): ?>
                <a class="feed__item" href="<?= e(url('admin/ads.php', ['status' => 'all', 'review' => $c['id']])) ?>">
                    <span class="feed__body"><?= e(str_limit($c['title'], 28)) ?><small><?= number_format((int) $c['views']) ?> views · <?= number_format((int) $c['credits_remaining']) ?> credits left</small></span>
                    <span class="feed__amount"><?= status_badge($c['status']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</div>
