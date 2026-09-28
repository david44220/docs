<?php
/**
 * @var ?string $error
 * @var bool $sent
 * @var string $email
 */
?>
<div class="auth__card">
    <header class="auth__head">
        <span class="auth__badge"><?= icon('key') ?></span>
        <p class="eyebrow">Password</p>
        <h1>Reset your password.</h1>
        <p class="muted">Enter the email address of your account and we will send you a link to choose a new password.</p>
    </header>

    <?php if ($sent): ?>
        <div class="alert alert--info" role="status"><?= icon('mail') ?><div>If an account uses <strong><?= e($email) ?></strong>, a reset link is on its way. It expires in one hour — check your spam folder too.</div></div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
        <?php endif; ?>
        <form method="post" class="form" novalidate>
            <?= csrf_field() ?>
            <label class="field">
                <span class="field__label">Email</span>
                <span class="input-icon"><?= icon('mail') ?><input class="input" type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus></span>
            </label>
            <button class="btn btn--primary btn--lg btn--block" type="submit">Send the reset link<span class="btn__glyph" aria-hidden="true">↗</span></button>
        </form>
    <?php endif; ?>
    <p class="auth__switch"><a href="<?= e(url('login.php')) ?>">Back to sign in</a></p>
</div>
