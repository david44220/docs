<?php
require __DIR__ . '/../app/bootstrap.php';

if (current_user() !== null) {
    redirect(url('account.php'));
}
if (!mail_enabled()) {
    flash('info', t('Password reset by email is not available. Please contact support.'));
    redirect(url('login.php'));
}

$error = null;
$sent = false;
$email = '';
if (is_post()) {
    $email = post('email');
    try {
        password_reset_request($email);
        $sent = true;
    } catch (AppError $e) {
        $error = $e->getMessage();
    }
}

render('auth/forgot', ['title' => t('Reset your password'), 'error' => $error, 'sent' => $sent, 'email' => $email], 'auth');
