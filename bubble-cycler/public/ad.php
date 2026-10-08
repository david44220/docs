<?php
/**
 * Ad endpoints:
 *   POST ad.php?a=complete   (AJAX, CSRF header) — the countdown finished
 *   GET  ad.php?a=click&t=…  — count the click and go to the advertiser
 */
require __DIR__ . '/../app/bootstrap.php';

$action = query('a');
$user = current_user();

if ($action === 'complete') {
    if (!is_post()) {
        json_response(['ok' => false, 'error' => t('Method not allowed')], 405);
    }
    if ($user === null) {
        json_response(['ok' => false, 'error' => t('Please sign in again.')], 401);
    }
    $token = post('token');
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        json_response(['ok' => false, 'error' => t('Invalid ad session.')], 422);
    }
    $ok = ad_view_complete((int) $user['id'], $token);
    json_response(['ok' => $ok], $ok ? 200 : 409);
}

if ($action === 'click') {
    $token = query('t');
    if ($user === null || !preg_match('/^[a-f0-9]{32}$/', $token)) {
        redirect(url('buy.php'));
    }
    $target = ad_click((int) $user['id'], $token);
    if ($target === null || !valid_http_url($target)) {
        redirect(url('buy.php'));
    }
    header('Referrer-Policy: no-referrer');
    redirect($target);
}

abort(404);
