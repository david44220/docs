<div class="auth__card">
    <header class="auth__head">
        <h1>Welcome back</h1>
        <p class="muted">Sign in to check on your bubbles.</p>
    </header>

    <?php if ($error): ?>
        <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
    <?php endif; ?>

    <form method="post" class="form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="field">
            <span class="field__label">Username or email</span>
            <span class="input-icon"><?= icon('user') ?><input class="input" type="text" name="login" value="<?= e($login) ?>" autocomplete="username" required autofocus></span>
        </label>
        <label class="field">
            <span class="field__label">Password</span>
            <span class="input-icon"><?= icon('key') ?><input class="input" type="password" name="password" autocomplete="current-password" required></span>
        </label>
        <button class="btn btn--primary btn--lg btn--block" type="submit"><?= icon('arrow-right') ?> Sign in</button>
    </form>

    <p class="auth__switch">New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
    <p class="auth__hint muted">Forgot your password? Contact support<?= setting('support_email') !== '' ? ' at <a href="mailto:' . e(setting('support_email')) . '">' . e(setting('support_email')) . '</a>' : '' ?> and an admin will reset it.</p>
</div>
