<?php
require __DIR__ . '/../app/bootstrap.php';

if (current_user() !== null) {
    redirect(url('dashboard.php'));
}

$error = null;
$login = '';
$next = post('next', query('next'));
$step = query('step') === 'code' ? 'code' : 'password';
$pending = $step === 'code' ? pending_two_factor() : null;
if ($step === 'code' && $pending === null) {
    flash('info', t('Please sign in again.'));
    redirect(url('login.php'));
}

$landing = static fn (array $user, string $next): string => safe_next(
    $next,
    url($user['role'] === 'admin' && $next === '' ? 'admin/index.php' : 'dashboard.php')
);

if (is_post() && $step === 'password') {
    $login = post('login');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    try {
        $user = check_credentials($login, $password);
        if (user_has_2fa($user)) {
            begin_two_factor($user, $next);
            redirect(url('login.php', ['step' => 'code']));
        }
        login_user($user);
        flash('success', t('Welcome back, {user}!', ['user' => $user['username']]));
        redirect($landing($user, $next));
    } catch (AppError $e) {
        $error = $e->getMessage();
    }
}

if (is_post() && $pending !== null) {
    try {
        $next = complete_two_factor(post('code'));
        flash('success', t('Welcome back, {user}!', ['user' => $pending['username']]));
        redirect($landing($pending, $next));
    } catch (AppError $e) {
        $error = $e->getMessage();
        if (pending_two_factor() === null) {
            flash('error', $error);
            redirect(url('login.php'));
        }
    }
}

render('auth/login', [
    'title'   => $step === 'code' ? t('Two-factor authentication') : t('Sign in'),
    'error'   => $error,
    'login'   => $login,
    'next'    => $next,
    'step'    => $step,
    'pending' => $pending,
    'canReset' => mail_enabled(),
], 'auth');
