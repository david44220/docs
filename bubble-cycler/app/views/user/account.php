<?php
/**
 * @var array $user
 * @var array $errors
 * @var array $stats
 */
?>
<section class="profile card card--glow">
    <?= user_avatar($user['username'], 'xl') ?>
    <div class="profile__text">
        <h2><?= e($user['username']) ?></h2>
        <p class="muted"><?= e($user['email']) ?> · member since <?= e(fmt_date($user['created_at'], 'F j, Y')) ?></p>
    </div>
    <dl class="profile__stats">
        <div><dt>Bubbles bought</dt><dd><?= number_format((int) $user['bubbles_bought']) ?></dd></div>
        <div><dt>Expired</dt><dd><?= number_format($stats['expired']) ?></dd></div>
        <div><dt>Deposited</dt><dd><?= e(money($user['total_deposited'])) ?></dd></div>
        <div><dt>Withdrawn</dt><dd><?= e(money($user['total_withdrawn'])) ?></dd></div>
    </dl>
</section>

<section class="card security-row">
    <span class="auth__badge<?= user_has_2fa($user) ? ' auth__badge--on' : '' ?>"><?= icon('shield') ?></span>
    <div class="security-row__text">
        <h2 class="card__title">Two-factor authentication <?= user_has_2fa($user) ? status_badge('active', 'On') : status_badge('pending', 'Off') ?></h2>
        <p class="card__sub"><?= user_has_2fa($user) ? 'A code from your authenticator app is required at every sign-in.' : 'Add a second step to your sign-in with an authenticator app. Strongly recommended.' ?></p>
    </div>
    <a class="btn btn--<?= user_has_2fa($user) ? 'secondary' : 'primary' ?>" href="<?= e(url('two-factor.php')) ?>"><?= icon('shield') ?> <?= user_has_2fa($user) ? 'Manage' : 'Set up' ?></a>
</section>

<div class="grid grid--2 grid--top">
    <form method="post" class="card form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="email">
        <header class="card__head"><div><h2 class="card__title">Email address</h2><p class="card__sub">Used for password resets and account notices.</p></div></header>
        <?php if ($errors['email']): ?><div class="alert alert--danger"><?= icon('alert') ?><div><?= e($errors['email']) ?></div></div><?php endif; ?>
        <label class="field">
            <span class="field__label">New email</span>
            <span class="input-icon"><?= icon('mail') ?><input class="input" type="email" name="email" value="<?= e($user['email']) ?>" required autocomplete="email"></span>
        </label>
        <label class="field">
            <span class="field__label">Current password</span>
            <span class="input-icon"><?= icon('key') ?><input class="input" type="password" name="current_password" required autocomplete="current-password"></span>
        </label>
        <div class="form__actions"><button class="btn btn--primary" type="submit"><?= icon('check') ?> Update email</button></div>
    </form>

    <form method="post" class="card form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">
        <header class="card__head"><div><h2 class="card__title">Password</h2><p class="card__sub">Changing it signs out your other devices.</p></div></header>
        <?php if ($errors['password']): ?><div class="alert alert--danger"><?= icon('alert') ?><div><?= e($errors['password']) ?></div></div><?php endif; ?>
        <label class="field">
            <span class="field__label">Current password</span>
            <span class="input-icon"><?= icon('key') ?><input class="input" type="password" name="current_password" required autocomplete="current-password"></span>
        </label>
        <div class="field-row">
            <label class="field">
                <span class="field__label">New password</span>
                <span class="input-icon"><?= icon('lock') ?><input class="input" type="password" name="new_password" minlength="8" required autocomplete="new-password"></span>
            </label>
            <label class="field">
                <span class="field__label">Confirm</span>
                <span class="input-icon"><?= icon('lock') ?><input class="input" type="password" name="new_password_confirm" minlength="8" required autocomplete="new-password"></span>
            </label>
        </div>
        <div class="form__actions"><button class="btn btn--primary" type="submit"><?= icon('check') ?> Change password</button></div>
    </form>
</div>
