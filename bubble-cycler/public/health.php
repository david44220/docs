<?php
/**
 * Uptime check for monitoring services: 200 when PHP and the database answer,
 * 503 otherwise. Reveals nothing beyond that.
 */
const STATELESS = true;
require __DIR__ . '/../app/bootstrap.php';

try {
    $ok = (int) val('SELECT 1') === 1 && row('SELECT id FROM pool WHERE id = 1') !== null;
} catch (Throwable $e) {
    log_error($e);
    $ok = false;
}
json_response(['ok' => $ok, 'time' => gmdate('c')], $ok ? 200 : 503);
