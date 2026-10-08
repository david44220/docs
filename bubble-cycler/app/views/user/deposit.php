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
?>
<?php if ($methods === []): ?>
    <div class="card"><?= empty_state('card', t('No deposit methods yet'), t('Deposit methods have not been set up yet. Please check back soon or contact support.')) ?></div>
<?php else: ?>
<div class="grid grid--pay">
    <section class="stack">
        <header class="section-title"><h2><?= e(t('1. Choose a method')) ?></h2></header>
        <div class="methods">
            <?php foreach ($methods as $m): $active = $method !== null && (int) $m['id'] === (int) $method['id']; ?>
                <a class="method glass<?= $active ? ' is-active' : '' ?>" href="<?= e(url('deposit.php', ['method' => $m['id']])) ?>"<?= $active ? ' aria-current="true"' : '' ?>>
                    <?= method_avatar($m) ?>
                    <span class="method__text">
                        <strong><?= e($m['name']) ?></strong>
                        <small><?= e($m['currency']) ?> · <?= e(t('min {amount}', ['amount' => money($m['min_amount'])])) ?><?= (int) $m['max_amount'] > 0 ? ' · ' . e(t('max {amount}', ['amount' => money($m['max_amount'])])) : '' ?></small>
                    </span>
                    <span class="method__fee"><?= e(method_fee_label($m)) ?></span>
                    <?= icon('chevron-right', 'method__chev') ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="note glass">
            <?= icon('shield') ?>
            <p><?= e(t('Deposits are reviewed by a human. Send the exact amount, keep your transaction reference, and never send funds to an address you did not copy from this page.')) ?></p>
        </div>
    </section>

    <section class="stack">
        <header class="section-title"><h2><?= e(t('2. Send & confirm')) ?></h2></header>
        <?php if ($method === null): ?>
            <div class="card"><?= empty_state('arrow-left', t('Pick a method'), t('Payment details and instructions appear here.')) ?></div>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" class="card card--glow form" data-fee-calc
                  data-fee-fixed="<?= (int) $method['fee_fixed'] ?>" data-fee-bp="<?= (int) $method['fee_percent_bp'] ?>" data-symbol="<?= e(setting('currency_symbol', '$')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="method_id" value="<?= (int) $method['id'] ?>">
                <header class="card__head">
                    <div class="row">
                        <?= method_avatar($method, 'lg') ?>
                        <div>
                            <h2 class="card__title"><?= e($method['name']) ?></h2>
                            <p class="card__sub"><?= e(t('Fee: {fee}', ['fee' => method_fee_label($method)])) ?> · <?= e(t('Limits: {limits}', ['limits' => money($method['min_amount']) . ((int) $method['max_amount'] > 0 ? ' – ' . money($method['max_amount']) : '+')])) ?></p>
                        </div>
                    </div>
                </header>

                <?php if ($error): ?>
                    <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
                <?php endif; ?>

                <div class="copy">
                    <span class="copy__label"><?= e($method['account_label'] ?: t('Send to')) ?></span>
                    <div class="copy__box">
                        <code class="copy__value" data-copy-source><?= e($method['account_value']) ?></code>
                        <button class="btn btn--secondary btn--sm copy__btn" type="button" data-copy><?= icon('copy') ?> <span><?= e(t('Copy')) ?></span></button>
                    </div>
                </div>

                <?php if (trim((string) $method['instructions']) !== ''): ?>
                    <div class="instructions"><?= nl2br(e($method['instructions'])) ?></div>
                <?php endif; ?>

                <div class="field-row">
                    <label class="field">
                        <span class="field__label"><?= e(t('Amount sent')) ?></span>
                        <span class="input-group"><span class="input-group__addon"><?= e(setting('currency_symbol', '$')) ?></span>
                            <input class="input" type="text" name="amount" inputmode="decimal" value="<?= e($form['amount']) ?>" placeholder="<?= e(units_to_input(max((int) $method['min_amount'], MONEY_SCALE))) ?>" required data-fee-amount></span>
                    </label>
                    <div class="field">
                        <span class="field__label"><?= e(t('You will be credited')) ?></span>
                        <output class="calc" data-fee-result>—</output>
                    </div>
                </div>
                <label class="field">
                    <span class="field__label"><?= e(t('Transaction ID / reference')) ?></span>
                    <input class="input" type="text" name="reference" maxlength="190" value="<?= e($form['reference']) ?>" placeholder="<?= e(t('e.g. transaction hash or payment reference')) ?>" required>
                </label>
                <label class="field">
                    <span class="field__label"><?= e(t('Sent from')) ?> <small class="muted"><?= e(t('optional')) ?></small></span>
                    <input class="input" type="text" name="sender" maxlength="190" value="<?= e($form['sender']) ?>" placeholder="<?= e(t('Your wallet address, account name or phone number')) ?>">
                </label>
                <label class="field">
                    <span class="field__label"><?= e(t('Payment screenshot')) ?> <?= (int) $method['require_proof'] === 1 ? '<small class="text-pink">' . e(t('required')) . '</small>' : '<small class="muted">' . e(t('optional')) . '</small>' ?></span>
                    <span class="file">
                        <input type="file" name="proof" accept="image/png,image/jpeg,image/webp,image/gif"<?= (int) $method['require_proof'] === 1 ? ' required' : '' ?> data-file-input>
                        <span class="file__ui"><?= icon('image') ?> <span data-file-name data-default="<?= e(t('Choose an image (max {size})', ['size' => proof_max_label()])) ?>"><?= e(t('Choose an image (max {size})', ['size' => proof_max_label()])) ?></span></span>
                    </span>
                </label>
                <button class="btn btn--primary btn--lg btn--block" type="submit"><?= icon('check') ?> <?= e(t('I have sent the payment')) ?></button>
            </form>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>

<section class="card card--flush">
    <header class="card__head card__head--pad">
        <h2 class="card__title"><?= e(t('Deposit history')) ?></h2>
    </header>
    <?php if ($history === []): ?>
        <p class="muted card__empty"><?= e(t('No deposits yet.')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>#</th><th><?= e(t('Date')) ?></th><th><?= e(t('Method')) ?></th><th class="num"><?= e(t('Amount')) ?></th><th class="num"><?= e(t('Fee')) ?></th><th class="num"><?= e(t('Credited')) ?></th><th><?= e(t('Reference')) ?></th><th><?= e(t('Status')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($history as $d): ?>
                    <tr>
                        <td class="muted"><?= (int) $d['id'] ?></td>
                        <td class="nowrap"><?= e(fmt_date($d['created_at'])) ?></td>
                        <td><?= e(t($d['method_name'])) ?></td>
                        <td class="num"><?= e(money($d['amount'])) ?></td>
                        <td class="num muted"><?= e(money($d['fee'])) ?></td>
                        <td class="num"><?= $d['status'] === 'rejected' ? '<span class="muted">—</span>' : e(money($d['credit_amount'])) ?></td>
                        <td class="mono truncate" title="<?= e($d['reference']) ?>"><?= e(str_limit($d['reference'], 24)) ?></td>
                        <td>
                            <?= status_badge($d['status']) ?>
                            <?php if ($d['admin_note']): ?><small class="cell-note"><?= e($d['admin_note']) ?></small><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($pager) ?>
    <?php endif; ?>
</section>
