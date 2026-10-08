<?php
require __DIR__ . '/../app/bootstrap.php';

// Signing out only happens through the POST form (CSRF-protected).
if (is_post()) {
    logout_user();
    start_session();
    flash('success', t('You are signed out. See you soon!'));
    redirect(url('login.php'));
}
redirect(url(current_user() !== null ? 'dashboard.php' : 'index.php'));
