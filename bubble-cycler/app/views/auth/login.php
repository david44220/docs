<?php
/**
 * @var ?string $error
 * @var string $login
 * @var string $next
 * @var string $step      password | code
 * @var ?array $pending   member waiting for the two-factor code
 * @var bool $canReset    email is configured
 */
?>
<div class="auth__card">
    <?php if ($step === 'code'): ?>
        <header class="auth__head">
            <span class="auth__badge"><?= icon('shield') ?></span>
            <p class="eyebrow"><?= e(t('Security')) ?></p>
            <h1><?= e(t('Two-factor check.')) ?></h1>
            <p class="muted"><?= t_html('Enter the 6-digit code from your authenticator app for {name}, or one of your recovery codes.', ['name' => '<strong>' . e($pending['username'] ?? '') . '</strong>']) ?></p>
        </header>

        <?php if ($error): ?>
            <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
        <?php endif; ?>

        <form method="post" class="form" novalidate>
            <?= csrf_field() ?>
            <label class="field">
                <span class="field__label"><?= e(t('Authentication code')) ?></span>
                <input class="input input--code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" placeholder="123 456" required autofocus>
            </label>
            <button class="btn btn--primary btn--lg btn--block" type="submit"><?= e(t('Verify and sign in')) ?><span class="btn__glyph" aria-hidden="true">↗</span></button>
        </form>
        <p class="auth__switch"><a href="<?= e(url('login.php')) ?>"><?= e(t('Use another account')) ?></a></p>
    <?php else: ?>
        <header class="auth__head">
            <p class="eyebrow"><?= e(t('Member area')) ?></p>
            <h1><?= e(t('Welcome back.')) ?></h1>
            <p class="muted"><?= e(t('Sign in to check on your bubbles.')) ?></p>
        </header>

        <?php if ($error): ?>
            <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
        <?php endif; ?>

        <form method="post" class="form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <label class="field">
                <span class="field__label"><?= e(t('Username or email')) ?></span>
                <span class="input-icon"><?= icon('user') ?><input class="input" type="text" name="login" value="<?= e($login) ?>" autocomplete="username" required autofocus></span>
            </label>
            <label class="field">
                <span class="field__label"><?= e(t('Password')) ?> <?php if ($canReset): ?><a class="field__link" href="<?= e(url('forgot.php')) ?>"><?= e(t('Forgot it?')) ?></a><?php endif; ?></span>
                <span class="input-icon"><?= icon('key') ?><input class="input" type="password" name="password" autocomplete="current-password" required></span>
            </label>
            <button class="btn btn--primary btn--lg btn--block" type="submit"><?= e(t('Sign in')) ?><span class="btn__glyph" aria-hidden="true">↗</span></button>
        </form>

        <p class="auth__switch"><?= e(t('New here?')) ?> <a href="<?= e(url('register.php')) ?>"><?= e(t('Create an account')) ?></a></p>
        <?php if (!$canReset): ?>
            <p class="auth__hint muted"><?= setting('support_email') !== ''
                ? t_html('Forgot your password? Contact support at {email} and an admin will reset it.', ['email' => '<a href="mailto:' . e(setting('support_email')) . '">' . e(setting('support_email')) . '</a>'])
                : e(t('Forgot your password? Contact support and an admin will reset it.')) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>
