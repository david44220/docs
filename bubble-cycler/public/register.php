<?php
require __DIR__ . '/../app/bootstrap.php';

if (current_user() !== null) {
    redirect(url('dashboard.php'));
}
capture_referral();

$error = null;
$form = ['username' => '', 'email' => ''];
$referrerName = referral_username();
$referrer = $referrerName !== '' ? row("SELECT id, username FROM users WHERE username = ? AND status = 'active'", [$referrerName]) : null;
$open = setting_bool('registration_open');

if (is_post() && $open) {
    $form = ['username' => post('username'), 'email' => post('email')];
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
    try {
        // Honeypot: humans never see or fill the "website" field.
        if (post('website') !== '') {
            throw new AppError(t('Registration failed. Please try again.'));
        }
        if ($password !== $confirm) {
            throw new AppError(t('The two passwords do not match.'));
        }
        if (empty($_POST['terms'])) {
            throw new AppError(t('Please confirm you have read the terms and the risk disclosure.'));
        }
        rate_limit('register', client_ip(), setting_int('max_registrations_per_ip'), 86400,
            t('Too many accounts were created from your network today. Please try again tomorrow.'));
        $id = register_user($form['username'], $form['email'], $password, $referrer !== null ? (int) $referrer['id'] : null);
        login_user(row_required('SELECT * FROM users WHERE id = ?', [$id]));
        unset($_SESSION['ref']);
        flash('success', t('Welcome to {site}! Make a deposit to blow your first bubble.', ['site' => site_name()]));
        redirect(url('dashboard.php'));
    } catch (AppError $e) {
        $error = $e->getMessage();
    }
}

render('auth/register', [
    'title'    => t('Create your account'),
    'error'    => $error,
    'form'     => $form,
    'referrer' => $referrer,
    'open'     => $open,
], 'auth');
