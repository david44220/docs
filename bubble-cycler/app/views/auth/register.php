<div class="auth__card">
    <header class="auth__head">
        <p class="eyebrow"><?= e(t('New account')) ?></p>
        <h1><?= e(t('Create your account.')) ?></h1>
        <p class="muted"><?= e(t('Blow your first bubble in minutes.')) ?></p>
    </header>

    <?php if (!$open): ?>
        <div class="alert alert--warning"><?= icon('lock') ?><div><?= e(t('Registrations are closed at the moment. Please check back later.')) ?></div></div>
        <p class="auth__switch"><?= e(t('Already a member?')) ?> <a href="<?= e(url('login.php')) ?>"><?= e(t('Sign in')) ?></a></p>
    <?php else: ?>
        <?php if ($referrer !== null): ?>
            <div class="alert alert--info"><?= icon('gift') ?><div><?= t_html('You were invited by {name}.', ['name' => '<strong>' . e($referrer['username']) . '</strong>']) ?></div></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
        <?php endif; ?>

        <form method="post" class="form" novalidate>
            <?= csrf_field() ?>
            <label class="field">
                <span class="field__label"><?= e(t('Username')) ?></span>
                <span class="input-icon"><?= icon('user') ?><input class="input" type="text" name="username" value="<?= e($form['username']) ?>" minlength="3" maxlength="20" pattern="[A-Za-z0-9_]{3,20}" autocomplete="username" required autofocus></span>
                <span class="field__hint"><?= e(t('3–20 letters, numbers or underscores. Shown masked in public feeds.')) ?></span>
            </label>
            <label class="field">
                <span class="field__label"><?= e(t('Email')) ?></span>
                <span class="input-icon"><?= icon('mail') ?><input class="input" type="email" name="email" value="<?= e($form['email']) ?>" maxlength="190" autocomplete="email" required></span>
            </label>
            <div class="field-row">
                <label class="field">
                    <span class="field__label"><?= e(t('Password')) ?></span>
                    <span class="input-icon"><?= icon('key') ?><input class="input" type="password" name="password" minlength="8" autocomplete="new-password" required></span>
                </label>
                <label class="field">
                    <span class="field__label"><?= e(t('Confirm password')) ?></span>
                    <span class="input-icon"><?= icon('key') ?><input class="input" type="password" name="password_confirm" minlength="8" autocomplete="new-password" required></span>
                </label>
            </div>
            <label class="hp" aria-hidden="true"><?= e(t('Website')) ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            <label class="check">
                <input type="checkbox" name="terms" value="1" required>
                <span><?= t_html('I have read the {terms} and the {privacy}, and I understand that bubble payouts are not guaranteed.', [
                    'terms'   => '<a href="' . e(url('terms.php')) . '" target="_blank" rel="noopener">' . e(t('terms & risk disclosure')) . '</a>',
                    'privacy' => '<a href="' . e(url('privacy.php')) . '" target="_blank" rel="noopener">' . e(t('privacy policy')) . '</a>',
                ]) ?></span>
            </label>
            <button class="btn btn--primary btn--lg btn--block" type="submit"><?= e(t('Create account')) ?><span class="btn__glyph" aria-hidden="true">↗</span></button>
        </form>
        <p class="auth__switch"><?= e(t('Already a member?')) ?> <a href="<?= e(url('login.php')) ?>"><?= e(t('Sign in')) ?></a></p>
    <?php endif; ?>
</div>
