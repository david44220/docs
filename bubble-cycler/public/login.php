<?php
require __DIR__ . '/../app/bootstrap.php';

if (current_user() !== null) {
    redirect(url('dashboard.php'));
}

$error = null;
$login = '';
$next = post('next', query('next'));

if (is_post()) {
    $login = post('login');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    try {
        $user = attempt_login($login, $password);
        flash('success', 'Welcome back, ' . $user['username'] . '!');
        redirect(safe_next($next, url($user['role'] === 'admin' && $next === '' ? 'admin/index.php' : 'dashboard.php')));
    } catch (AppError $e) {
        $error = $e->getMessage();
    }
}

render('auth/login', [
    'title' => 'Sign in',
    'error' => $error,
    'login' => $login,
    'next'  => $next,
], 'auth');
