<?php
/**
 * Public JSON feed used to refresh the live pool widgets.
 *   GET api.php?a=pool
 */
const STATELESS = true;
require __DIR__ . '/../app/bootstrap.php';

if (query('a') !== 'pool') {
    json_response(['error' => t('Unknown action')], 404);
}

$pool = pool_state();
$head = queue_head($pool);
$target = $head !== null ? (int) $head['target'] : setting_int('bubble_target');
$filled = $head !== null ? min((int) $pool['balance'], $target) : 0;

json_response([
    'balance'         => money($pool['balance']),
    'total_out'       => money($pool['total_out']),
    'bubbles_sold'    => num($pool['bubbles_sold']),
    'bubbles_expired' => num($pool['bubbles_expired']),
    'queue'           => num(queue_length($pool)),
    'head'            => $head === null ? null : [
        'id'     => (int) $head['id'],
        'label'  => '#' . num($head['id']),
        'fill'   => $target > 0 ? round($filled / $target * 100, 2) : 0,
        'filled' => money($filled),
        'target' => money($target),
    ],
    'head_label'  => $head !== null ? '#' . num($head['id']) : '—',
    'head_needed' => money($head !== null ? max(0, $target - $filled) : 0),
]);
