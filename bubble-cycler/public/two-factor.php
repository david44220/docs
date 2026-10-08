<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login(); // not require_admin(): admins are sent here to set it up
$uid = (int) $user['id'];
$enabled = user_has_2fa($user);
$mustKeep = $user['role'] === 'admin' && setting_bool('admin_2fa_required');
$error = null;

if (is_post()) {
    $action = post('action');
    try {
        if ($action === 'enable' && !$enabled) {
            $secret = (string) ($_SESSION['2fa_setup'] ?? '');
            if ($secret === '') {
                throw new AppError(t('The setup expired. Please scan the new code and try again.'));
            }
            rate_limit('2fa-setup', (string) $uid, 10, 900, t('Too many attempts. Please wait 15 minutes.'));
            $step = totp_verify($secret, post('code'));
            if ($step === null) {
                throw new AppError(t('That code is not valid. Make sure the time on your phone is correct and try again.'));
            }
            [$codes, $hashes] = recovery_codes_generate();
            user_enable_2fa($uid, $secret, $hashes, $step);
            unset($_SESSION['2fa_setup']);
            $_SESSION['2fa_codes'] = $codes;
            notify_member($uid, static fn (): array => [
                'subject' => t('Two-factor authentication is on'),
                'title'   => t('Two-factor authentication is on'),
                'lines'   => [
                    t('Signing in to your {site} account now requires a code from your authenticator app.', ['site' => site_name()]),
                    t('If you did not do this, contact support immediately.'),
                ],
            ], security: true);
            flash('success', t('Two-factor authentication is on. Save your recovery codes now.'));
            redirect(url('two-factor.php', ['codes' => 1]));
        }

        if ($action === 'recovery' && $enabled) {
            rate_limit('2fa-manage', (string) $uid, 10, 900, t('Too many attempts. Please wait 15 minutes.'));
            if (!user_verify_2fa($uid, post('code'))) {
                throw new AppError(t('That code is not valid.'));
            }
            [$codes, $hashes] = recovery_codes_generate();
            user_set_recovery_codes($uid, $hashes);
            $_SESSION['2fa_codes'] = $codes;
            flash('success', t('New recovery codes generated. The old ones no longer work.'));
            redirect(url('two-factor.php', ['codes' => 1]));
        }

        if ($action === 'disable' && $enabled) {
            if ($mustKeep) {
                throw new AppError(t('Administrators must keep two-factor authentication on (Admin → Settings → Security).'));
            }
            rate_limit('2fa-manage', (string) $uid, 10, 900, t('Too many attempts. Please wait 15 minutes.'));
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            if (!password_verify($password, $user['password_hash'])) {
                throw new AppError(t('Your password is incorrect.'));
            }
            if (!user_verify_2fa($uid, post('code'))) {
                throw new AppError(t('That code is not valid.'));
            }
            user_disable_2fa($uid);
            notify_member($uid, static fn (): array => [
                'subject' => t('Two-factor authentication is off'),
                'title'   => t('Two-factor authentication was turned off'),
                'lines'   => [
                    t('Your {site} account no longer asks for a code when you sign in.', ['site' => site_name()]),
                    t('If you did not do this, change your password and contact support immediately.'),
                ],
            ], security: true);
            flash('success', t('Two-factor authentication is off.'));
            redirect(url('two-factor.php'));
        }
    } catch (AppError $e) {
        $error = $e->getMessage();
    }
}

if (!$enabled && empty($_SESSION['2fa_setup'])) {
    $_SESSION['2fa_setup'] = totp_new_secret();
}
$codes = null;
if (query('codes') !== '' && isset($_SESSION['2fa_codes']) && is_array($_SESSION['2fa_codes'])) {
    $codes = $_SESSION['2fa_codes'];
    unset($_SESSION['2fa_codes']);
}
$secret = $enabled ? null : (string) $_SESSION['2fa_setup'];

render('user/two-factor', [
    'title'     => t('Two-factor authentication'),
    'eyebrow'   => t('Account security'),
    'page'      => 'account',
    'user'      => current_user(true),
    'enabled'   => $enabled,
    'mustKeep'  => $mustKeep,
    'secret'    => $secret,
    'uri'       => $secret !== null ? totp_uri($secret, $user['username']) : null,
    'codes'     => $codes,
    'error'     => $error,
    'scripts'   => ['js/vendor/qrcode.js'],
]);
