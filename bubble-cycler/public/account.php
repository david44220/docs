<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$errors = ['email' => null, 'password' => null];

if (is_post()) {
    $action = post('action');
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    try {
        rate_limit('password-check', (string) $uid, 10, 900, 'Too many attempts. Please wait 15 minutes and try again.');
        if (!password_verify($current, $user['password_hash'])) {
            throw new AppError('Your current password is incorrect.');
        }
        if ($action === 'email') {
            $email = mb_strtolower(post('email'));
            validate_email($email);
            if (val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $uid]) !== null) {
                throw new AppError('Another account already uses this email.');
            }
            if ($email !== mb_strtolower($user['email'])) {
                q('UPDATE users SET email = ? WHERE id = ?', [$email, $uid]);
                // Tell the previous address, in case the account was taken over.
                notify_email($user['email'], 'Your email address was changed', 'Your email address was changed', [
                    'Hi ' . $user['username'] . ', the email address of your ' . site_name() . ' account was changed to ' . $email . '.',
                    'If you did not do this, contact support immediately.',
                ]);
            }
            flash('success', 'Email updated.');
        } elseif ($action === 'password') {
            $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
            $confirm = is_string($_POST['new_password_confirm'] ?? null) ? $_POST['new_password_confirm'] : '';
            validate_password($new, $user['username']);
            if ($new !== $confirm) {
                throw new AppError('The new passwords do not match.');
            }
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            // Keep this session, sign out every other one.
            login_user(row_required('SELECT * FROM users WHERE id = ?', [$uid]));
            notify_member($uid, 'Your password was changed', 'Your password was changed', [
                'The password of your ' . site_name() . ' account was changed from your account settings.',
                'If this was not you, reset your password and contact support immediately.',
            ], security: true);
            flash('success', 'Password changed. Other devices have been signed out.');
        }
        redirect(url('account.php'));
    } catch (AppError $e) {
        $errors[$action === 'password' ? 'password' : 'email'] = $e->getMessage();
    }
}

render('user/account', [
    'title'   => 'Account',
    'eyebrow' => 'Profile & security',
    'page'    => 'account',
    'user'    => $user,
    'errors'  => $errors,
    'stats'   => member_bubble_stats($uid),
]);
