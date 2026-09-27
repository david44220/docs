<div class="auth__card">
    <header class="auth__head">
        <h1>Create your account</h1>
        <p class="muted">Blow your first bubble in minutes.</p>
    </header>

    <?php if (!$open): ?>
        <div class="alert alert--warning"><?= icon('lock') ?><div>Registrations are closed at the moment. Please check back later.</div></div>
        <p class="auth__switch">Already a member? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
    <?php else: ?>
        <?php if ($referrer !== null): ?>
            <div class="alert alert--info"><?= icon('gift') ?><div>You were invited by <strong><?= e($referrer['username']) ?></strong>.</div></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
        <?php endif; ?>

        <form method="post" class="form" novalidate>
            <?= csrf_field() ?>
            <label class="field">
                <span class="field__label">Username</span>
                <span class="input-icon"><?= icon('user') ?><input class="input" type="text" name="username" value="<?= e($form['username']) ?>" minlength="3" maxlength="20" pattern="[A-Za-z0-9_]{3,20}" autocomplete="username" required autofocus></span>
                <span class="field__hint">3–20 letters, numbers or underscores. Shown masked in public feeds.</span>
            </label>
            <label class="field">
                <span class="field__label">Email</span>
                <span class="input-icon"><?= icon('mail') ?><input class="input" type="email" name="email" value="<?= e($form['email']) ?>" maxlength="190" autocomplete="email" required></span>
            </label>
            <div class="field-row">
                <label class="field">
                    <span class="field__label">Password</span>
                    <span class="input-icon"><?= icon('key') ?><input class="input" type="password" name="password" minlength="8" autocomplete="new-password" required></span>
                </label>
                <label class="field">
                    <span class="field__label">Confirm password</span>
                    <span class="input-icon"><?= icon('key') ?><input class="input" type="password" name="password_confirm" minlength="8" autocomplete="new-password" required></span>
                </label>
            </div>
            <label class="check">
                <input type="checkbox" name="terms" value="1" required>
                <span>I have read the <a href="<?= e(url('terms.php')) ?>" target="_blank" rel="noopener">terms &amp; risk disclosure</a> and understand that bubble payouts are not guaranteed.</span>
            </label>
            <button class="btn btn--primary btn--lg btn--block" type="submit"><?= icon('sparkles') ?> Create account</button>
        </form>
        <p class="auth__switch">Already a member? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
    <?php endif; ?>
</div>
