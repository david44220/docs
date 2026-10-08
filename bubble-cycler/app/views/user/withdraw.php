<?php
/**
 * @var array $user
 * @var array $methods
 * @var ?array $method
 * @var array $form
 * @var ?string $error
 * @var array $history
 * @var array $pager
 */
$minimum = setting_int('min_withdrawal');
$cash = (int) $user['cash_balance'];
?>
<div class="grid grid--pay">
    <section class="stack">
        <article class="wallet wallet--green wallet--hero">
            <span class="wallet__icon"><?= icon('coins') ?></span>
            <span class="wallet__label"><?= e(t('Available to withdraw')) ?></span>
            <strong class="wallet__value"><?= e(money($cash)) ?></strong>
            <span class="wallet__hint"><?= e(t('Minimum withdrawal {amount}', ['amount' => money($minimum)])) ?> · <?= e(t('{amount} withdrawn so far', ['amount' => money($user['total_withdrawn'])])) ?></span>
        </article>

        <header class="section-title"><h2><?= e(t('Withdrawal method')) ?></h2></header>
        <?php if ($methods === []): ?>
            <div class="card"><?= empty_state('card', t('No withdrawal methods yet'), t('Withdrawal methods have not been set up yet. Please check back soon.')) ?></div>
        <?php else: ?>
            <div class="methods">
                <?php foreach ($methods as $m): $active = $method !== null && (int) $m['id'] === (int) $method['id']; ?>
                    <a class="method glass<?= $active ? ' is-active' : '' ?>" href="<?= e(url('withdraw.php', ['method' => $m['id']])) ?>">
                        <?= method_avatar($m) ?>
                        <span class="method__text">
                            <strong><?= e($m['name']) ?></strong>
                            <small><?= e($m['currency']) ?> · <?= e(t('min {amount}', ['amount' => money(max((int) $m['min_amount'], $minimum))])) ?><?= (int) $m['max_amount'] > 0 ? ' · ' . e(t('max {amount}', ['amount' => money($m['max_amount'])])) : '' ?></small>
                        </span>
                        <span class="method__fee"><?= e(method_fee_label($m)) ?></span>
                        <?= icon('chevron-right', 'method__chev') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="stack">
        <?php if ($method !== null): ?>
            <form method="post" class="card card--glow form" data-fee-calc data-fee-fixed="<?= (int) $method['fee_fixed'] ?>" data-fee-bp="<?= (int) $method['fee_percent_bp'] ?>" data-symbol="<?= e(setting('currency_symbol', '$')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="method_id" value="<?= (int) $method['id'] ?>">
                <header class="card__head">
                    <div class="row">
                        <?= method_avatar($method, 'lg') ?>
                        <div>
                            <h2 class="card__title"><?= e($method['name']) ?></h2>
                            <p class="card__sub"><?= e(t('Fee: {fee}', ['fee' => method_fee_label($method)])) ?></p>
                        </div>
                    </div>
                </header>
                <?php if ($error): ?>
                    <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
                <?php endif; ?>
                <?php if (trim((string) $method['instructions']) !== ''): ?>
                    <div class="instructions"><?= nl2br(e($method['instructions'])) ?></div>
                <?php endif; ?>
                <div class="field-row">
                    <label class="field">
                        <span class="field__label"><?= e(t('Amount')) ?></span>
                        <span class="input-group"><span class="input-group__addon"><?= e(setting('currency_symbol', '$')) ?></span>
                            <input class="input" type="text" name="amount" inputmode="decimal" value="<?= e($form['amount']) ?>" placeholder="<?= e(units_to_input(max($minimum, (int) $method['min_amount']))) ?>" required data-fee-amount>
                            <button class="input-group__btn" type="button" data-fill-amount="<?= e(units_to_input(intdiv($cash, 10000) * 10000)) ?>"><?= e(t('Max')) ?></button></span>
                    </label>
                    <div class="field">
                        <span class="field__label"><?= e(t('You will receive')) ?></span>
                        <output class="calc" data-fee-result>—</output>
                    </div>
                </div>
                <label class="field">
                    <span class="field__label"><?= e($method['account_label'] ?: t('Your account / wallet address')) ?></span>
                    <textarea class="textarea" name="account" rows="2" maxlength="500" required><?= e($form['account']) ?></textarea>
                    <span class="field__hint"><?= e(t('Double-check it: payments sent to a wrong address usually cannot be recovered.')) ?></span>
                </label>
                <button class="btn btn--primary btn--lg btn--block" type="submit"<?= $cash < max($minimum, 1) ? ' disabled' : '' ?>><?= icon('upload') ?> <?= e(t('Request withdrawal')) ?></button>
                <?php if ($cash < $minimum): ?>
                    <p class="field__hint center"><?= e(t('You need at least {amount} in your cash balance to withdraw.', ['amount' => money($minimum)])) ?></p>
                <?php endif; ?>
            </form>
        <?php elseif ($methods !== []): ?>
            <div class="card"><?= empty_state('arrow-left', t('Pick a method'), t('Choose how you want to be paid.')) ?></div>
        <?php endif; ?>
    </section>
</div>

<section class="card card--flush">
    <header class="card__head card__head--pad"><h2 class="card__title"><?= e(t('Withdrawal history')) ?></h2></header>
    <?php if ($history === []): ?>
        <p class="muted card__empty"><?= e(t('No withdrawals yet.')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th><?= e(t('Date')) ?></th><th><?= e(t('Method')) ?></th><th class="num"><?= e(t('Amount')) ?></th><th class="num"><?= e(t('Fee')) ?></th><th class="num"><?= e(t('You receive')) ?></th><th><?= e(t('Status')) ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($history as $w): ?>
                    <tr>
                        <td class="muted"><?= (int) $w['id'] ?></td>
                        <td class="nowrap"><?= e(fmt_date($w['created_at'])) ?></td>
                        <td><?= e($w['method_name']) ?><small class="cell-note mono"><?= e(str_limit($w['account'], 28)) ?></small></td>
                        <td class="num"><?= e(money($w['amount'])) ?></td>
                        <td class="num muted"><?= e(money($w['fee'])) ?></td>
                        <td class="num"><?= e(money($w['payout_amount'])) ?></td>
                        <td>
                            <?= status_badge($w['status']) ?>
                            <?php if ($w['txid']): ?><small class="cell-note mono"><?= e(t('Ref: {ref}', ['ref' => str_limit($w['txid'], 24)])) ?></small><?php endif; ?>
                            <?php if ($w['admin_note'] && $w['status'] !== 'cancelled'): ?><small class="cell-note"><?= e($w['admin_note']) ?></small><?php endif; ?>
                        </td>
                        <td class="num">
                            <?php if ($w['status'] === 'pending'): ?>
                                <form method="post" data-confirm="<?= e(t('Cancel this withdrawal and return {amount} to your cash balance?', ['amount' => money($w['amount'])])) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                                    <button class="btn btn--ghost btn--sm" type="submit"><?= e(t('Cancel')) ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
