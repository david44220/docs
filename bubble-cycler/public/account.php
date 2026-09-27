<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$uid = (int) $user['id'];
$errors = ['email' => null, 'password' => null];

if (is_post()) {
    $action = post('action');
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    try {
        if (!password_verify($current, $user['password_hash'])) {
            throw new AppError('Your current password is incorrect.');
        }
        if ($action === 'email') {
            $email = mb_strtolower(post('email'));
            validate_email($email);
            if (val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $uid]) !== null) {
                throw new AppError('Another account already uses this email.');
            }
            q('UPDATE users SET email = ? WHERE id = ?', [$email, $uid]);
            flash('success', 'Email updated.');
        } elseif ($action === 'password') {
            $new = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
            $confirm = is_string($_POST['new_password_confirm'] ?? null) ? $_POST['new_password_confirm'] : '';
            validate_password($new);
            if ($new !== $confirm) {
                throw new AppError('The new passwords do not match.');
            }
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            // Keep this session, sign out every other one.
            login_user(row('SELECT * FROM users WHERE id = ?', [$uid]));
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
