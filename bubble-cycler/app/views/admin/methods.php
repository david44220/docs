<?php
/**
 * @var string $type     deposit|withdrawal
 * @var array $methods
 * @var array $usage
 * @var array $form
 * @var ?string $error
 */
$isDeposit = $type === 'deposit';
$isEdit = (int) $form['id'] > 0;
$symbol = setting('currency_symbol', '$');
?>
<div class="toolbar">
    <nav class="tabs" aria-label="Method type">
        <a class="tabs__item<?= $isDeposit ? ' is-active' : '' ?>" href="<?= e(url('admin/methods.php', ['type' => 'deposit'])) ?>"><?= icon('download') ?> Deposit methods</a>
        <a class="tabs__item<?= !$isDeposit ? ' is-active' : '' ?>" href="<?= e(url('admin/methods.php', ['type' => 'withdrawal'])) ?>"><?= icon('upload') ?> Withdrawal methods</a>
    </nav>
    <div class="toolbar__end">
        <?php if ($isEdit): ?><a class="btn btn--primary btn--sm" href="<?= e(url('admin/methods.php', ['type' => $type])) ?>"><?= icon('plus') ?> New method</a><?php endif; ?>
    </div>
</div>

<div class="grid grid--main grid--top">
    <section class="stack">
        <?php if ($methods === []): ?>
            <div class="card"><?= empty_state('card', 'No ' . $type . ' methods yet', $isDeposit ? 'Add the wallets or accounts members should send money to.' : 'Add the ways you can pay members out.') ?></div>
        <?php endif; ?>
        <?php foreach ($methods as $m): $used = $usage[(int) $m['id']] ?? ['n' => 0, 'total' => 0]; ?>
            <article class="pm glass<?= (int) $m['id'] === (int) $form['id'] ? ' is-editing' : '' ?><?= $m['status'] === 'inactive' ? ' is-inactive' : '' ?>">
                <div class="pm__head">
                    <?= method_avatar($m, 'lg') ?>
                    <div class="pm__title">
                        <h3><?= e($m['name']) ?> <small class="muted"><?= e($m['currency']) ?></small></h3>
                        <p class="muted">Limits <?= e(money($m['min_amount'])) ?><?= (int) $m['max_amount'] > 0 ? ' – ' . e(money($m['max_amount'])) : '+' ?> · Fee <?= e(method_fee_label($m)) ?><?= $isDeposit && (int) $m['require_proof'] === 1 ? ' · Screenshot required' : '' ?></p>
                    </div>
                    <?= status_badge($m['status']) ?>
                </div>
                <?php if ($isDeposit): ?>
                    <div class="pm__account"><span><?= e($m['account_label'] ?: 'Send to') ?></span><code><?= e(str_limit($m['account_value'], 90)) ?></code></div>
                <?php else: ?>
                    <div class="pm__account"><span>Members fill in</span><code><?= e($m['account_label']) ?></code></div>
                <?php endif; ?>
                <div class="pm__foot">
                    <span class="muted"><?= plural($used['n'], $isDeposit ? 'deposit' : 'withdrawal') ?> · <?= e(money($used['total'])) ?></span>
                    <div class="row">
                        <a class="btn btn--ghost btn--sm" href="<?= e(url('admin/methods.php', ['type' => $type, 'edit' => $m['id']])) ?>"><?= icon('edit') ?> Edit</a>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="<?= e($type) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                            <button class="btn btn--ghost btn--sm" type="submit"><?= $m['status'] === 'active' ? icon('pause') . ' Disable' : icon('play') . ' Enable' ?></button>
                        </form>
                        <form method="post" data-confirm="Delete “<?= e($m['name']) ?>”? Existing requests keep their history.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="<?= e($type) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                            <button class="btn btn--ghost btn--sm btn--danger-text" type="submit"><?= icon('trash') ?></button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <aside class="stack sticky">
        <form method="post" class="card card--glow form" data-method-form>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
            <header class="card__head">
                <div>
                    <h2 class="card__title"><?= $isEdit ? 'Edit method' : 'Add a ' . $type . ' method' ?></h2>
                    <p class="card__sub"><?= $isDeposit ? 'Members see these details on the Deposit page and send money manually.' : 'Members choose this method and enter where they want to be paid.' ?></p>
                </div>
            </header>
            <?php if ($error): ?>
                <div class="alert alert--danger"><?= icon('alert') ?><div><?= e($error) ?></div></div>
            <?php endif; ?>

            <div class="field-row field-row--aside">
                <label class="field"><span class="field__label">Name</span><input class="input" name="name" value="<?= e($form['name']) ?>" maxlength="80" placeholder="<?= $isDeposit ? 'USDT (TRC20)' : 'PayPal' ?>" required></label>
                <label class="field"><span class="field__label">Currency</span><input class="input" name="currency" value="<?= e($form['currency']) ?>" maxlength="20" placeholder="USD"></label>
            </div>
            <div class="field-row field-row--aside">
                <label class="field"><span class="field__label">Logo URL <small class="muted">optional · https</small></span><input class="input" type="url" name="logo_url" value="<?= e($form['logo_url']) ?>" placeholder="https://…/logo.png"></label>
                <label class="field"><span class="field__label">Color</span><input class="input input--color" type="color" name="color" value="<?= e($form['color']) ?>"></label>
            </div>
            <label class="field">
                <span class="field__label"><?= $isDeposit ? 'Label for your payment details' : 'Field label shown to members' ?></span>
                <input class="input" name="account_label" value="<?= e($form['account_label']) ?>" maxlength="80" placeholder="<?= $isDeposit ? 'Our USDT TRC20 address' : 'Your PayPal email' ?>">
            </label>
            <?php if ($isDeposit): ?>
                <label class="field">
                    <span class="field__label">Your payment details <small class="muted">wallet address, IBAN, phone…</small></span>
                    <textarea class="textarea mono" name="account_value" rows="3" maxlength="500" required><?= e($form['account_value']) ?></textarea>
                </label>
            <?php endif; ?>
            <label class="field">
                <span class="field__label">Instructions <small class="muted">shown to members, one step per line</small></span>
                <textarea class="textarea" name="instructions" rows="4" maxlength="5000" placeholder="<?= $isDeposit ? 'Send only USDT on the TRC20 network…' : 'Payouts are sent within 24 hours…' ?>"><?= e($form['instructions']) ?></textarea>
            </label>
            <div class="field-row">
                <label class="field"><span class="field__label">Minimum</span><span class="input-group"><span class="input-group__addon"><?= e($symbol) ?></span><input class="input" name="min_amount" value="<?= e($form['min_amount']) ?>" inputmode="decimal"></span></label>
                <label class="field"><span class="field__label">Maximum</span><span class="input-group"><span class="input-group__addon"><?= e($symbol) ?></span><input class="input" name="max_amount" value="<?= e($form['max_amount']) ?>" inputmode="decimal" placeholder="No limit"></span></label>
            </div>
            <div class="field-row">
                <label class="field"><span class="field__label">Fixed fee</span><span class="input-group"><span class="input-group__addon"><?= e($symbol) ?></span><input class="input" name="fee_fixed" value="<?= e($form['fee_fixed']) ?>" inputmode="decimal"></span></label>
                <label class="field"><span class="field__label">Percentage fee</span><span class="input-group"><input class="input" name="fee_percent" value="<?= e($form['fee_percent']) ?>" inputmode="decimal"><span class="input-group__addon">%</span></span></label>
            </div>
            <div class="field-row field-row--aside">
                <label class="field">
                    <span class="field__label">Status</span>
                    <select class="select" name="status">
                        <option value="active"<?= $form['status'] === 'active' ? ' selected' : '' ?>>Active — visible to members</option>
                        <option value="inactive"<?= $form['status'] === 'inactive' ? ' selected' : '' ?>>Inactive — hidden</option>
                    </select>
                </label>
                <label class="field"><span class="field__label">Order</span><input class="input" type="number" name="sort_order" value="<?= e($form['sort_order']) ?>" min="-9999" max="9999"></label>
            </div>
            <?php if ($isDeposit): ?>
                <label class="switch">
                    <input type="checkbox" name="require_proof" value="1"<?= $form['require_proof'] === '1' ? ' checked' : '' ?>>
                    <span class="switch__track"></span>
                    <span>Require a payment screenshot</span>
                </label>
            <?php endif; ?>
            <div class="form__actions">
                <?php if ($isEdit): ?><a class="btn btn--ghost" href="<?= e(url('admin/methods.php', ['type' => $type])) ?>">Cancel</a><?php endif; ?>
                <button class="btn btn--primary" type="submit"><?= icon('check') ?> <?= $isEdit ? 'Save method' : 'Add method' ?></button>
            </div>
        </form>
    </aside>
</div>
