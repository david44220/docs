<?php
/**
 * @var ?string $error
 * @var ?array $member
 * @var string $token
 */
?>
<div class="auth__card">
    <header class="auth__head">
        <span class="auth__badge"><?= icon('lock') ?></span>
        <p class="eyebrow"><?= e(t('Password')) ?></p>
        <h1><?= e(t('Choose a new password.')) ?></h1>
        <?php if ($member !== null): ?><p class="muted"><?= t_html('For the account {name}.', ['name' => '<strong>' . e($member['username']) . '</strong>']) ?></p><?php endif; ?>
    </header>

    <?php if ($member === null): ?>
        <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e(t('This reset link is invalid, was already used or has expired.')) ?></div></div>
        <a class="btn btn--primary btn--lg btn--block" href="<?= e(url('forgot.php')) ?>"><?= e(t('Request a new link')) ?><span class="btn__glyph" aria-hidden="true">↗</span></a>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
        <?php endif; ?>
        <form method="post" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label class="field">
                <span class="field__label"><?= e(t('New password')) ?></span>
                <span class="input-icon"><?= icon('lock') ?><input class="input" type="password" name="password" minlength="8" autocomplete="new-password" required autofocus></span>
            </label>
            <label class="field">
                <span class="field__label"><?= e(t('Confirm the new password')) ?></span>
                <span class="input-icon"><?= icon('lock') ?><input class="input" type="password" name="password_confirm" minlength="8" autocomplete="new-password" required></span>
            </label>
            <button class="btn btn--primary btn--lg btn--block" type="submit"><?= e(t('Save the new password')) ?><span class="btn__glyph" aria-hidden="true">↗</span></button>
        </form>
    <?php endif; ?>
    <p class="auth__switch"><a href="<?= e(url('login.php')) ?>"><?= e(t('Back to sign in')) ?></a></p>
</div>
