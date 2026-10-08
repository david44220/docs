<?php
require __DIR__ . '/../../app/bootstrap.php';

$admin = require_admin();
$error = null;
$values = settings_all();

if (is_post()) {
    $input = [];
    foreach (array_keys(SETTING_DEFAULTS) as $key) {
        $input[$key] = post($key);
    }
    // Passwords are taken as typed (no trimming) and never echoed back.
    $input['smtp_password'] = is_string($_POST['smtp_password'] ?? null) ? $_POST['smtp_password'] : '';
    $input['smtp_password_clear'] = post('smtp_password_clear');
    try {
        admin_save_settings((int) $admin['id'], $input);
        if (post('action') === 'test_email') {
            try {
                send_mail($admin['email'], t('Test email from {site}', ['site' => site_name()]), t('It works!') . "\n\n" . t('This test message confirms that {site} can send emails.', ['site' => site_name()]));
                flash('success', t('Settings saved. A test email was sent to {email}.', ['email' => $admin['email']]));
            } catch (RuntimeException $e) {
                flash('error', t('Settings saved, but the test email failed: {error}', ['error' => $e->getMessage()]));
            }
            redirect(url('admin/settings.php') . '#email');
        }
        flash('success', t('Settings saved.'));
        redirect(url('admin/settings.php'));
    } catch (AppError $e) {
        $error = $e->getMessage();
        $values = array_merge($values, $input, ['smtp_password' => $values['smtp_password']]);
        // Money fields come back as typed text; keep them readable in the form.
        foreach (['bubble_price', 'pool_share', 'bubble_target', 'referral_commission', 'min_withdrawal'] as $key) {
            $values[$key . '_text'] = post($key);
        }
    }
}

foreach (['bubble_price', 'pool_share', 'bubble_target', 'referral_commission', 'min_withdrawal'] as $key) {
    $values[$key . '_text'] ??= units_to_input((int) $values[$key]);
}

// Configuration problems worth fixing before going live.
$warnings = [];
if (app_key() === null) {
    $warnings[] = t('config.php has no valid app_key: two-factor secrets and the SMTP password are stored unencrypted. Add {code}.', ['code' => "'app_key' => '" . base64_encode(random_bytes(32)) . "'"]);
}
if (mail_base_url() === '') {
    $warnings[] = t('config.php has no base_url (e.g. https://example.com): emails and password resets stay disabled until it is set.');
}
if (!is_https()) {
    $warnings[] = t('This page was not loaded over HTTPS. Use HTTPS in production and set {code} in config.php.', ['code' => "'force_https' => true"]);
}
if (config('debug')) {
    $warnings[] = t('Debug mode is on in config.php: error details are shown to visitors. Set {code} in production.', ['code' => "'debug' => false"]);
}

render('admin/settings', [
    'title'      => t('Settings'),
    'eyebrow'    => t('Economics, ads & site'),
    'page'       => 'admin-settings',
    'admin_area' => true,
    'v'          => $values,
    'error'      => $error,
    'pool'       => pool_state(),
    'warnings'   => $warnings,
    'mailReady'  => mail_enabled(),
]);
