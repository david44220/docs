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
    try {
        admin_save_settings((int) $admin['id'], $input);
        flash('success', 'Settings saved.');
        redirect(url('admin/settings.php'));
    } catch (AppError $e) {
        $error = $e->getMessage();
        $values = $input;
        // Money fields come back as typed text; keep them readable in the form.
        foreach (['bubble_price', 'pool_share', 'bubble_target', 'referral_commission', 'min_withdrawal'] as $key) {
            $values[$key . '_text'] = $input[$key];
        }
    }
}

foreach (['bubble_price', 'pool_share', 'bubble_target', 'referral_commission', 'min_withdrawal'] as $key) {
    $values[$key . '_text'] ??= units_to_input((int) $values[$key]);
}

render('admin/settings', [
    'title'      => 'Settings',
    'eyebrow'    => 'Economics, ads & site',
    'page'       => 'admin-settings',
    'admin_area' => true,
    'v'          => $values,
    'error'      => $error,
    'pool'       => pool_state(),
]);
