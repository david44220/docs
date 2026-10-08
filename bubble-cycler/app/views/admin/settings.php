<?php
/**
 * @var array $v       current values (money fields also as *_text)
 * @var ?string $error
 * @var array $pool
 * @var list<string> $warnings  configuration problems to fix before going live
 * @var bool $mailReady
 */
$symbol = setting('currency_symbol', '$');
$price = to_units($v['bubble_price_text']) ?? 0;
$share = to_units($v['pool_share_text']) ?? 0;
$target = to_units($v['bubble_target_text']) ?? 0;
$referral = to_units($v['referral_commission_text']) ?? 0;
$platform = $price - $share - $referral;
$switch = static function (string $name, string $label, string $hint, array $v): string {
    return '<label class="switch switch--row"><input type="checkbox" name="' . e($name) . '" value="1"' . (($v[$name] ?? '0') === '1' ? ' checked' : '') . '>'
        . '<span class="switch__track"></span><span class="switch__text"><strong>' . e($label) . '</strong><small>' . e($hint) . '</small></span></label>';
};
$moneyField = static function (string $name, string $label, string $hint, array $v, string $symbol): string {
    return '<label class="field"><span class="field__label">' . e($label) . '</span><span class="input-group"><span class="input-group__addon">' . e($symbol) . '</span>'
        . '<input class="input" name="' . e($name) . '" value="' . e($v[$name . '_text'] ?? '') . '" inputmode="decimal" required data-econ="' . e($name) . '"></span>'
        . ($hint !== '' ? '<span class="field__hint">' . e($hint) . '</span>' : '') . '</label>';
};
$intField = static function (string $name, string $label, string $hint, array $v, string $suffix = ''): string {
    return '<label class="field"><span class="field__label">' . e($label) . '</span><span class="input-group">'
        . '<input class="input" type="number" name="' . e($name) . '" value="' . e($v[$name] ?? '') . '" min="0" required>'
        . ($suffix !== '' ? '<span class="input-group__addon">' . e($suffix) . '</span>' : '') . '</span>'
        . ($hint !== '' ? '<span class="field__hint">' . e($hint) . '</span>' : '') . '</label>';
};
?>
<form method="post" class="settings" data-settings-form>
    <?= csrf_field() ?>
    <?php if ($error): ?>
        <div class="alert alert--danger" role="alert"><?= icon('alert') ?><div><?= e($error) ?></div></div>
    <?php endif; ?>
    <?php if ($warnings !== []): ?>
        <div class="alert alert--warning"><?= icon('alert') ?><div><strong><?= e(t('Before going live')) ?></strong><ul class="alert__list"><?php foreach ($warnings as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?></ul></div></div>
    <?php endif; ?>

    <section class="card card--glow settings__section">
        <header class="settings__head">
            <span class="settings__icon"><?= icon('bubbles') ?></span>
            <div><h2 class="card__title"><?= e(t('Bubble economics')) ?></h2><p class="card__sub"><?= e(t('Applies to bubbles bought from now on — bubbles already in the queue keep the target they were bought with.')) ?></p></div>
        </header>
        <div class="settings__grid">
            <?= $moneyField('bubble_price', t('Bubble price'), t('What a member pays per bubble.'), $v, $symbol) ?>
            <?= $moneyField('pool_share', t('Credited to the pool'), t('Part of the price that goes into the FIFO pool.'), $v, $symbol) ?>
            <?= $moneyField('bubble_target', t('Expires at'), t('A bubble expires once the pool has paid it this amount.'), $v, $symbol) ?>
            <?= $moneyField('referral_commission', t('Referral commission'), t('Per bubble, paid from the platform share. 0 disables it.'), $v, $symbol) ?>
            <?= $intField('max_bubbles_per_purchase', t('Max bubbles per purchase'), t('1 – 1000.'), $v) ?>
            <?= $intField('max_active_bubbles', t('Max active bubbles per member'), t('0 = unlimited.'), $v) ?>
        </div>
        <div class="econ" data-econ-summary data-symbol="<?= e($symbol) ?>">
            <div class="econ__item"><span><?= e(t('Platform share per bubble')) ?></span><strong data-econ-platform class="<?= $platform < 0 ? 'text-red' : '' ?>"><?= e(money($platform)) ?></strong></div>
            <div class="econ__item"><span><?= e(t('New sales needed per expiry')) ?></span><strong data-econ-ratio><?= e($share > 0 ? decimal_trim($target / $share, 2) : '—') ?></strong></div>
            <div class="econ__item"><span><?= e(t('Return per expired bubble')) ?></span><strong data-econ-roi><?= e($price > 0 ? pct($target, $price, 0) : '—') ?></strong></div>
            <div class="econ__item"><span><?= e(t('Bubbles in queue today')) ?></span><strong><?= e(num(queue_length($pool))) ?></strong></div>
        </div>
        <?= $switch('allow_cash_purchase', t('Allow buying with cash balance'), t('Members can re-buy bubbles with their earnings instead of withdrawing.'), $v) ?>
    </section>

    <section class="card settings__section">
        <header class="settings__head">
            <span class="settings__icon settings__icon--pink"><?= icon('megaphone') ?></span>
            <div><h2 class="card__title"><?= e(t('Advertising')) ?></h2><p class="card__sub"><?= e(t('The sponsored message shown before each purchase, and the credits members receive.')) ?></p></div>
        </header>
        <?= $switch('ad_required', t('Show a sponsored message before every purchase'), t('When no campaign or house ad is active, purchases are unlocked automatically.'), $v) ?>
        <div class="settings__grid">
            <?= $intField('ad_seconds', t('Ad duration'), t('Enforced on the server. 0 – 120.'), $v, t('sec')) ?>
            <?= $intField('ad_view_ttl', t('Unlock valid for'), t('How long a watched ad keeps the purchase unlocked.'), $v, t('sec')) ?>
            <?= $intField('ad_credits_per_bubble', t('Ad credits per bubble'), t('1 credit = 1 completed view.'), $v, t('credits')) ?>
            <?= $intField('min_campaign_credits', t('Minimum credits per campaign'), '', $v, t('credits')) ?>
        </div>
        <?= $switch('campaign_approval', t('Review member campaigns before they go live'), t('Recommended: stops scams and unwanted content.'), $v) ?>
    </section>

    <section class="card settings__section">
        <header class="settings__head">
            <span class="settings__icon settings__icon--green"><?= icon('wallet') ?></span>
            <div><h2 class="card__title"><?= e(t('Payments')) ?></h2><p class="card__sub"><?= e(t('Limits for manual deposits and withdrawals. Per-method limits and fees are set on each payment method.')) ?></p></div>
        </header>
        <div class="settings__grid">
            <?= $moneyField('min_withdrawal', t('Minimum withdrawal'), t('Applies to every withdrawal method.'), $v, $symbol) ?>
            <?= $intField('max_pending_deposits', t('Pending deposits per member'), t('Stops request spam.'), $v) ?>
            <?= $intField('max_pending_withdrawals', t('Pending withdrawals per member'), '', $v) ?>
        </div>
    </section>

    <section class="card settings__section">
        <header class="settings__head">
            <span class="settings__icon settings__icon--blue"><?= icon('globe') ?></span>
            <div><h2 class="card__title"><?= e(t('Site')) ?></h2><p class="card__sub"><?= e(t('Branding, currency display and access.')) ?></p></div>
        </header>
        <div class="settings__grid">
            <label class="field"><span class="field__label"><?= e(t('Site name')) ?></span><input class="input" name="site_name" value="<?= e($v['site_name']) ?>" maxlength="40" required></label>
            <label class="field"><span class="field__label"><?= e(t('Support email')) ?></span><input class="input" type="email" name="support_email" value="<?= e($v['support_email']) ?>" placeholder="support@example.com"></label>
            <label class="field"><span class="field__label"><?= e(t('Currency symbol')) ?></span><input class="input" name="currency_symbol" value="<?= e($v['currency_symbol']) ?>" maxlength="5" required></label>
            <label class="field"><span class="field__label"><?= e(t('Currency code')) ?></span><input class="input" name="currency_code" value="<?= e($v['currency_code']) ?>" maxlength="10" required></label>
            <label class="field"><span class="field__label"><?= e(t('Display timezone')) ?></span>
                <select class="select" name="timezone">
                    <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                        <option value="<?= e($tz) ?>"<?= $tz === $v['timezone'] ? ' selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <?= $switch('registration_open', t('Open registrations'), t('Turn off to stop new sign-ups.'), $v) ?>
        <?= $switch('maintenance_mode', t('Maintenance mode'), t('Only admins can use the site; members see a maintenance page.'), $v) ?>
    </section>

    <section class="card settings__section" id="security">
        <header class="settings__head">
            <span class="settings__icon settings__icon--green"><?= icon('shield') ?></span>
            <div><h2 class="card__title"><?= e(t('Security')) ?></h2><p class="card__sub"><?= e(t('Sign-in protection and sign-up limits.')) ?></p></div>
        </header>
        <div class="settings__grid">
            <?= $intField('max_registrations_per_ip', t('Sign-ups per IP address'), t('Per 24 hours. 0 = no limit. Slows down multi-account abuse.'), $v, t('per day')) ?>
        </div>
        <?= $switch('admin_2fa_required', t('Require two-factor authentication for admins'), t('Admins must set up an authenticator app before they can open the admin panel.'), $v) ?>
    </section>

    <section class="card settings__section" id="email">
        <header class="settings__head">
            <span class="settings__icon settings__icon--pink"><?= icon('mail') ?></span>
            <div><h2 class="card__title"><?= e(t('Email')) ?> <?= $mailReady ? status_badge('active', t('Sending')) : status_badge('pending', t('Off')) ?></h2><p class="card__sub"><?= e(t('Password resets, security notices and payment updates. Needs base_url in config.php.')) ?></p></div>
        </header>
        <div class="settings__grid">
            <label class="field"><span class="field__label"><?= e(t('Send emails with')) ?></span>
                <select class="select" name="mail_transport">
                    <?php foreach (['off' => t('Off — no emails'), 'smtp' => t('SMTP server (recommended)'), 'mail' => t('PHP mail() / sendmail'), 'log' => t('Write to storage/logs/mail.log (testing)')] as $key => $label): ?>
                        <option value="<?= $key ?>"<?= $key === ($v['mail_transport'] ?? 'off') ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span class="field__label"><?= e(t('Sender address')) ?></span><input class="input" type="email" name="mail_from" value="<?= e($v['mail_from'] ?? '') ?>" placeholder="no-reply@example.com"></label>
            <label class="field"><span class="field__label"><?= e(t('Sender name')) ?> <small class="muted"><?= e(t('optional')) ?></small></span><input class="input" name="mail_from_name" value="<?= e($v['mail_from_name'] ?? '') ?>" maxlength="60" placeholder="<?= e($v['site_name']) ?>"></label>
            <label class="field"><span class="field__label"><?= e(t('SMTP host')) ?></span><input class="input" name="smtp_host" value="<?= e($v['smtp_host'] ?? '') ?>" placeholder="smtp.example.com" autocomplete="off"></label>
            <?= $intField('smtp_port', t('SMTP port'), t('587 with STARTTLS, 465 with SSL.'), $v) ?>
            <label class="field"><span class="field__label"><?= e(t('Encryption')) ?></span>
                <select class="select" name="smtp_encryption">
                    <?php foreach (['tls' => t('STARTTLS (port 587)'), 'ssl' => t('SSL/TLS (port 465)'), 'none' => t('None (local relay only)')] as $key => $label): ?>
                        <option value="<?= $key ?>"<?= $key === ($v['smtp_encryption'] ?? 'tls') ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span class="field__label"><?= e(t('SMTP username')) ?></span><input class="input" name="smtp_username" value="<?= e($v['smtp_username'] ?? '') ?>" autocomplete="off"></label>
            <div class="field">
                <label class="field__label" for="smtp-password"><?= e(t('SMTP password')) ?> <?php if (($v['smtp_password'] ?? '') !== ''): ?><small class="muted"><?= e(t('saved — leave empty to keep it')) ?></small><?php endif; ?></label>
                <input class="input" id="smtp-password" type="password" name="smtp_password" value="" autocomplete="new-password" placeholder="<?= ($v['smtp_password'] ?? '') !== '' ? '••••••••' : '' ?>">
                <?php if (($v['smtp_password'] ?? '') !== ''): ?><label class="check check--sm"><input type="checkbox" name="smtp_password_clear" value="1"><span><?= e(t('Remove the saved password')) ?></span></label><?php endif; ?>
            </div>
        </div>
        <?= $switch('notify_members', t('Email members about their payments'), t('Deposit approved or rejected, withdrawal sent or declined. Security notices are always sent.'), $v) ?>
        <?= $switch('notify_admins', t('Email the support address about new requests'), t('New deposits to review and withdrawals to pay.'), $v) ?>
        <div class="settings__inline-action">
            <button class="btn btn--secondary" type="submit" name="action" value="test_email"><?= icon('mail') ?> <?= e(t('Save & send a test email to me')) ?></button>
        </div>
    </section>

    <section class="card settings__section">
        <header class="settings__head">
            <span class="settings__icon settings__icon--violet"><?= icon('file') ?></span>
            <div><h2 class="card__title"><?= e(t('Legal')) ?></h2><p class="card__sub"><?= e(t('The risk disclaimer appears in every footer; the terms replace the default terms page when filled in. Each text has an English version, shown by default, and an optional French version for French-speaking visitors.')) ?></p></div>
        </header>
        <div class="settings__grid settings__grid--2">
            <label class="field"><span class="field__label"><?= e(t('Risk disclaimer')) ?> <small class="muted">English</small></span><textarea class="textarea" name="disclaimer" rows="4" maxlength="2000" lang="en"><?= e($v['disclaimer']) ?></textarea></label>
            <label class="field"><span class="field__label"><?= e(t('Risk disclaimer')) ?> <small class="muted">Français · <?= e(t('empty = automatic translation of the default text')) ?></small></span><textarea class="textarea" name="disclaimer_fr" rows="4" maxlength="2000" lang="fr"><?= e($v['disclaimer_fr'] ?? '') ?></textarea></label>
            <label class="field"><span class="field__label"><?= e(t('Terms')) ?> <small class="muted">English · <?= e(t('plain text, blank line between paragraphs · leave empty for the default terms')) ?></small></span><textarea class="textarea" name="terms_text" rows="8" maxlength="20000" lang="en"><?= e($v['terms_text']) ?></textarea></label>
            <label class="field"><span class="field__label"><?= e(t('Terms')) ?> <small class="muted">Français · <?= e(t('empty = English text above, or the default terms')) ?></small></span><textarea class="textarea" name="terms_text_fr" rows="8" maxlength="20000" lang="fr"><?= e($v['terms_text_fr'] ?? '') ?></textarea></label>
            <label class="field"><span class="field__label"><?= e(t('Privacy policy')) ?> <small class="muted">English · <?= e(t('plain text · leave empty for the default policy')) ?></small></span><textarea class="textarea" name="privacy_text" rows="8" maxlength="20000" lang="en"><?= e($v['privacy_text'] ?? '') ?></textarea></label>
            <label class="field"><span class="field__label"><?= e(t('Privacy policy')) ?> <small class="muted">Français · <?= e(t('empty = English text above, or the default policy')) ?></small></span><textarea class="textarea" name="privacy_text_fr" rows="8" maxlength="20000" lang="fr"><?= e($v['privacy_text_fr'] ?? '') ?></textarea></label>
        </div>
    </section>

    <div class="settings__save">
        <span class="muted"><?= e(t('Every change is recorded in the audit log.')) ?></span>
        <button class="btn btn--primary btn--lg" type="submit"><?= icon('check') ?> <?= e(t('Save settings')) ?></button>
    </div>
</form>
