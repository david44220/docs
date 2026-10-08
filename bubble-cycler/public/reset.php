<?php
require __DIR__ . '/../app/bootstrap.php';

$token = is_post() ? post('token') : query('token');
$member = password_reset_user($token);
$error = null;

if (is_post() && $member !== null) {
    try {
        $user = password_reset_complete(
            $token,
            is_string($_POST['password'] ?? null) ? $_POST['password'] : '',
            is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : ''
        );
        notify_member((int) $user['id'], static fn (): array => [
            'subject' => t('Your password was changed'),
            'title'   => t('Your password was changed'),
            'lines'   => [
                t('The password of your {site} account was just reset.', ['site' => site_name()]),
                t('If this was not you, contact support immediately.'),
            ],
        ], security: true);
        if (current_user() !== null) {
            logout_user();
            start_session();
        }
        flash('success', t('Password updated. You can sign in with your new password.'));
        redirect(url('login.php'));
    } catch (AppError $e) {
        $error = $e->getMessage();
        $member = password_reset_user($token);
    }
}

render('auth/reset', ['title' => t('Choose a new password'), 'error' => $error, 'member' => $member, 'token' => $token], 'auth');
