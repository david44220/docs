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
        <div class="alert alert--warning"><?= icon('alert') ?><div><strong>Before going live</strong><ul class="alert__list"><?php foreach ($warnings as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?></ul></div></div>
    <?php endif; ?>

    <section class="card card--glow settings__section">
        <header class="settings__head">
            <span class="settings__icon"><?= icon('bubbles') ?></span>
            <div><h2 class="card__title">Bubble economics</h2><p class="card__sub">Applies to bubbles bought from now on — bubbles already in the queue keep the target they were bought with.</p></div>
        </header>
        <div class="settings__grid">
            <?= $moneyField('bubble_price', 'Bubble price', 'What a member pays per bubble.', $v, $symbol) ?>
            <?= $moneyField('pool_share', 'Credited to the pool', 'Part of the price that goes into the FIFO pool.', $v, $symbol) ?>
            <?= $moneyField('bubble_target', 'Expires at', 'A bubble expires once the pool has paid it this amount.', $v, $symbol) ?>
            <?= $moneyField('referral_commission', 'Referral commission', 'Per bubble, paid from the platform share. 0 disables it.', $v, $symbol) ?>
            <?= $intField('max_bubbles_per_purchase', 'Max bubbles per purchase', '1 – 1000.', $v) ?>
            <?= $intField('max_active_bubbles', 'Max active bubbles per member', '0 = unlimited.', $v) ?>
        </div>
        <div class="econ" data-econ-summary data-symbol="<?= e($symbol) ?>">
            <div class="econ__item"><span>Platform share per bubble</span><strong data-econ-platform class="<?= $platform < 0 ? 'text-red' : '' ?>"><?= e(money($platform)) ?></strong></div>
            <div class="econ__item"><span>New sales needed per expiry</span><strong data-econ-ratio><?= $share > 0 ? rtrim(rtrim(number_format($target / $share, 2), '0'), '.') : '—' ?></strong></div>
            <div class="econ__item"><span>Return per expired bubble</span><strong data-econ-roi><?= $price > 0 ? pct($target, $price, 0) : '—' ?></strong></div>
            <div class="econ__item"><span>Bubbles in queue today</span><strong><?= number_format(queue_length($pool)) ?></strong></div>
        </div>
        <?= $switch('allow_cash_purchase', 'Allow buying with cash balance', 'Members can re-buy bubbles with their earnings instead of withdrawing.', $v) ?>
    </section>

    <section class="card settings__section">
        <header class="settings__head">
            <span class="settings__icon settings__icon--pink"><?= icon('megaphone') ?></span>
            <div><h2 class="card__title">Advertising</h2><p class="card__sub">The sponsored message shown before each purchase, and the credits members receive.</p></div>
        </header>
        <?= $switch('ad_required', 'Show a sponsored message before every purchase', 'When no campaign or house ad is active, purchases are unlocked automatically.', $v) ?>
        <div class="settings__grid">
            <?= $intField('ad_seconds', 'Ad duration', 'Enforced on the server. 0 – 120.', $v, 'sec') ?>
            <?= $intField('ad_view_ttl', 'Unlock valid for', 'How long a watched ad keeps the purchase unlocked.', $v, 'sec') ?>
            <?= $intField('ad_credits_per_bubble', 'Ad credits per bubble', '1 credit = 1 completed view.', $v, 'credits') ?>
            <?= $intField('min_campaign_credits', 'Minimum credits per campaign', '', $v, 'credits') ?>
        </div>
        <?= $switch('campaign_approval', 'Review member campaigns before they go live', 'Recommended: stops scams and unwanted content.', $v) ?>
    </section>

    <section class="card settings__section">
        <header class="settings__head">
            <span class="settings__icon settings__icon--green"><?= icon('wallet') ?></span>
            <div><h2 class="card__title">Payments</h2><p class="card__sub">Limits for manual deposits and withdrawals. Per-method limits and fees are set on each payment method.</p></div>
        </header>
        <div class="settings__grid">
            <?= $moneyField('min_withdrawal', 'Minimum withdrawal', 'Applies to every withdrawal method.', $v, $symbol) ?>
            <?= $intField('max_pending_deposits', 'Pending deposits per member', 'Stops request spam.', $v) ?>
            <?= $intField('max_pending_withdrawals', 'Pending withdrawals per member', '', $v) ?>
        </div>
    </section>

    <section class="card settings__section">
        <header class="settings__head">
            <span class="settings__icon settings__icon--blue"><?= icon('globe') ?></span>
            <div><h2 class="card__title">Site</h2><p class="card__sub">Branding, currency display and access.</p></div>
        </header>
        <div class="settings__grid">
            <label class="field"><span class="field__label">Site name</span><input class="input" name="site_name" value="<?= e($v['site_name']) ?>" maxlength="40" required></label>
            <label class="field"><span class="field__label">Support email</span><input class="input" type="email" name="support_email" value="<?= e($v['support_email']) ?>" placeholder="support@example.com"></label>
            <label class="field"><span class="field__label">Currency symbol</span><input class="input" name="currency_symbol" value="<?= e($v['currency_symbol']) ?>" maxlength="5" required></label>
            <label class="field"><span class="field__label">Currency code</span><input class="input" name="currency_code" value="<?= e($v['currency_code']) ?>" maxlength="10" required></label>
            <label class="field"><span class="field__label">Display timezone</span>
                <select class="select" name="timezone">
                    <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                        <option value="<?= e($tz) ?>"<?= $tz === $v['timezone'] ? ' selected' : '' ?>><?= e($tz) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <?= $switch('registration_open', 'Open registrations', 'Turn off to stop new sign-ups.', $v) ?>
        <?= $switch('maintenance_mode', 'Maintenance mode', 'Only admins can use the site; members see a maintenance page.', $v) ?>
    </section>

    <section class="card settings__section" id="security">
        <header class="settings__head">
            <span class="settings__icon settings__icon--green"><?= icon('shield') ?></span>
            <div><h2 class="card__title">Security</h2><p class="card__sub">Sign-in protection and sign-up limits.</p></div>
        </header>
        <div class="settings__grid">
            <?= $intField('max_registrations_per_ip', 'Sign-ups per IP address', 'Per 24 hours. 0 = no limit. Slows down multi-account abuse.', $v, 'per day') ?>
        </div>
        <?= $switch('admin_2fa_required', 'Require two-factor authentication for admins', 'Admins must set up an authenticator app before they can open the admin panel.', $v) ?>
    </section>

    <section class="card settings__section" id="email">
        <header class="settings__head">
            <span class="settings__icon settings__icon--pink"><?= icon('mail') ?></span>
            <div><h2 class="card__title">Email <?= $mailReady ? status_badge('active', 'Sending') : status_badge('pending', 'Off') ?></h2><p class="card__sub">Password resets, security notices and payment updates. Needs base_url in config.php.</p></div>
        </header>
        <div class="settings__grid">
            <label class="field"><span class="field__label">Send emails with</span>
                <select class="select" name="mail_transport">
                    <?php foreach (['off' => 'Off — no emails', 'smtp' => 'SMTP server (recommended)', 'mail' => 'PHP mail() / sendmail', 'log' => 'Write to storage/logs/mail.log (testing)'] as $key => $label): ?>
                        <option value="<?= $key ?>"<?= $key === ($v['mail_transport'] ?? 'off') ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span class="field__label">Sender address</span><input class="input" type="email" name="mail_from" value="<?= e($v['mail_from'] ?? '') ?>" placeholder="no-reply@example.com"></label>
            <label class="field"><span class="field__label">Sender name <small class="muted">optional</small></span><input class="input" name="mail_from_name" value="<?= e($v['mail_from_name'] ?? '') ?>" maxlength="60" placeholder="<?= e($v['site_name']) ?>"></label>
            <label class="field"><span class="field__label">SMTP host</span><input class="input" name="smtp_host" value="<?= e($v['smtp_host'] ?? '') ?>" placeholder="smtp.example.com" autocomplete="off"></label>
            <?= $intField('smtp_port', 'SMTP port', '587 with STARTTLS, 465 with SSL.', $v) ?>
            <label class="field"><span class="field__label">Encryption</span>
                <select class="select" name="smtp_encryption">
                    <?php foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'None (local relay only)'] as $key => $label): ?>
                        <option value="<?= $key ?>"<?= $key === ($v['smtp_encryption'] ?? 'tls') ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span class="field__label">SMTP username</span><input class="input" name="smtp_username" value="<?= e($v['smtp_username'] ?? '') ?>" autocomplete="off"></label>
            <div class="field">
                <label class="field__label" for="smtp-password">SMTP password <?php if (($v['smtp_password'] ?? '') !== ''): ?><small class="muted">saved — leave empty to keep it</small><?php endif; ?></label>
                <input class="input" id="smtp-password" type="password" name="smtp_password" value="" autocomplete="new-password" placeholder="<?= ($v['smtp_password'] ?? '') !== '' ? '••••••••' : '' ?>">
                <?php if (($v['smtp_password'] ?? '') !== ''): ?><label class="check check--sm"><input type="checkbox" name="smtp_password_clear" value="1"><span>Remove the saved password</span></label><?php endif; ?>
            </div>
        </div>
        <?= $switch('notify_members', 'Email members about their payments', 'Deposit approved or rejected, withdrawal sent or declined. Security notices are always sent.', $v) ?>
        <?= $switch('notify_admins', 'Email the support address about new requests', 'New deposits to review and withdrawals to pay.', $v) ?>
        <div class="settings__inline-action">
            <button class="btn btn--secondary" type="submit" name="action" value="test_email"><?= icon('mail') ?> Save &amp; send a test email to me</button>
        </div>
    </section>

    <section class="card settings__section">
        <header class="settings__head">
            <span class="settings__icon settings__icon--violet"><?= icon('file') ?></span>
            <div><h2 class="card__title">Legal</h2><p class="card__sub">The risk disclaimer appears in every footer; the terms replace the default terms page when filled in.</p></div>
        </header>
        <label class="field"><span class="field__label">Risk disclaimer</span><textarea class="textarea" name="disclaimer" rows="3" maxlength="2000"><?= e($v['disclaimer']) ?></textarea></label>
        <label class="field"><span class="field__label">Terms <small class="muted">plain text, blank line between paragraphs · leave empty for the default terms</small></span><textarea class="textarea" name="terms_text" rows="8" maxlength="20000"><?= e($v['terms_text']) ?></textarea></label>
        <label class="field"><span class="field__label">Privacy policy <small class="muted">plain text · leave empty for the default policy</small></span><textarea class="textarea" name="privacy_text" rows="8" maxlength="20000"><?= e($v['privacy_text'] ?? '') ?></textarea></label>
    </section>

    <div class="settings__save">
        <span class="muted">Every change is recorded in the audit log.</span>
        <button class="btn btn--primary btn--lg" type="submit"><?= icon('check') ?> Save settings</button>
    </div>
</form>
