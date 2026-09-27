<?php
/**
 * @var array $user
 * @var bool $enabled
 * @var bool $mustKeep
 * @var ?string $secret   setup secret (only while not enabled)
 * @var ?string $uri      otpauth:// URI for the QR code
 * @var ?array $codes     recovery codes to show once
 * @var ?string $error
 */
?>
<?php if ($codes !== null): ?>
    <section class="card card--glow codes">
        <header class="card__head">
            <div>
                <span class="eyebrow"><?= icon('key') ?> Recovery codes</span>
                <h2 class="card__title">Save these codes somewhere safe</h2>
                <p class="card__sub">Each code works once, instead of an app code, if you lose your phone. They are shown only now.</p>
            </div>
        </header>
        <div class="copy">
            <div class="copy__box">
                <code class="copy__value codes__list" data-copy-source><?= e(implode("\n", $codes)) ?></code>
                <button class="btn btn--secondary btn--sm copy__btn" type="button" data-copy><?= icon('copy') ?> <span>Copy all</span></button>
            </div>
        </div>
        <?php if ($user['role'] === 'admin'): ?>
            <div class="form__actions"><a class="btn btn--primary" href="<?= e(url('admin/index.php')) ?>"><?= icon('shield') ?> Continue to the admin panel</a></div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
<?php endif; ?>

<?php if ($enabled): ?>
    <section class="profile card">
        <span class="auth__badge auth__badge--on"><?= icon('shield') ?></span>
        <div class="profile__text">
            <h2>Two-factor authentication is on <?= status_badge('active', 'Protected') ?></h2>
            <p class="muted">Enabled <?= e(fmt_date($user['totp_enabled_at'], 'M j, Y')) ?> · <?= plural(recovery_codes_left($user), 'recovery code') ?> left</p>
        </div>
    </section>

    <div class="grid grid--2 grid--top">
        <form method="post" class="card form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="recovery">
            <header class="card__head"><div><h2 class="card__title">New recovery codes</h2><p class="card__sub">Replaces all your current recovery codes.</p></div></header>
            <label class="field">
                <span class="field__label">Code from your app</span>
                <input class="input input--code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required>
            </label>
            <div class="form__actions"><button class="btn btn--secondary" type="submit"><?= icon('refresh') ?> Generate new codes</button></div>
        </form>

        <form method="post" class="card form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="disable">
            <header class="card__head"><div><h2 class="card__title">Turn off</h2><p class="card__sub"><?= $mustKeep ? 'Required for administrators by the site settings.' : 'Your account will only be protected by your password.' ?></p></div></header>
            <?php if ($mustKeep): ?>
                <p class="muted">Administrators cannot turn two-factor authentication off while it is required in Admin → Settings → Security.</p>
            <?php else: ?>
                <div class="field-row">
                    <label class="field"><span class="field__label">Password</span><input class="input" type="password" name="password" autocomplete="current-password" required></label>
                    <label class="field"><span class="field__label">Code from your app</span><input class="input input--code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required></label>
                </div>
                <div class="form__actions"><button class="btn btn--danger" type="submit" data-confirm="Turn off two-factor authentication?"><?= icon('x') ?> Turn off</button></div>
            <?php endif; ?>
        </form>
    </div>
<?php else: ?>
    <section class="card card--glow setup2fa">
        <header class="card__head">
            <div>
                <span class="eyebrow"><?= icon('shield') ?> Two-factor authentication</span>
                <h2 class="card__title">Protect your account with a second step</h2>
                <p class="card__sub">After your password you will also enter a 6-digit code from an authenticator app (Google Authenticator, Authy, 1Password, Bitwarden…).</p>
            </div>
        </header>
        <div class="setup2fa__grid">
            <div class="setup2fa__qr">
                <div class="qr" data-qr="<?= e((string) $uri) ?>" role="img" aria-label="QR code for your authenticator app">
                    <span class="qr__fallback muted">Scan unavailable — use the key below.</span>
                </div>
            </div>
            <div class="stack">
                <ol class="ticks ticks--numbered">
                    <li>Open your authenticator app and add an account.</li>
                    <li>Scan the QR code, or type this key:</li>
                </ol>
                <div class="copy">
                    <div class="copy__box">
                        <code class="copy__value copy__value--key" data-copy-source><?= e(trim(chunk_split((string) $secret, 4, ' '))) ?></code>
                        <button class="btn btn--secondary btn--sm copy__btn" type="button" data-copy><?= icon('copy') ?> <span>Copy</span></button>
                    </div>
                </div>
                <form method="post" class="form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="enable">
                    <label class="field">
                        <span class="field__label">3. Enter the 6-digit code shown in the app</span>
                        <input class="input input--code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" required>
                    </label>
                    <button class="btn btn--primary btn--lg" type="submit"><?= icon('shield') ?> Turn on two-factor authentication</button>
                </form>
            </div>
        </div>
    </section>
<?php endif; ?>
