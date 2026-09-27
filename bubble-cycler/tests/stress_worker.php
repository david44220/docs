<?php
/** One concurrent client for stress_test.php (buyer or admin). */
require __DIR__ . '/bootstrap.php';

[, $role, $userId, $iterations] = $argv;
$userId = (int) $userId;
mt_srand(crc32($role . $userId . microtime()));
$stats = ['buy' => 0, 'refused' => 0, 'errors' => 0, 'popped' => 0];

for ($i = 0; $i < (int) $iterations; $i++) {
    try {
        if ($role === 'buyer') {
            $roll = mt_rand(1, 10);
            if ($roll <= 8) {
                $result = buy_bubbles($userId, mt_rand(1, 5), mt_rand(0, 3) === 0 ? 'cash' : 'purchase');
                $stats['buy']++;
                $stats['popped'] += count($result['popped']);
            } elseif ($roll === 9) {
                $method = (int) val("SELECT id FROM payment_methods WHERE type = 'withdrawal' AND status = 'active' LIMIT 1");
                $id = withdrawal_create($userId, $method, '1', 'wallet-' . $userId);
                if (mt_rand(0, 1) === 1) {
                    withdrawal_refund($id, 'cancelled', null, '', $userId);
                }
            } else {
                $view = ad_start_view($userId);
                if ($view !== null) {
                    q('UPDATE ad_views SET started_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 60), (int) $view['view']['id']]);
                    ad_view_complete($userId, $view['view']['token']);
                }
            }
        } else {
            $roll = mt_rand(1, 3);
            if ($roll === 1) {
                pool_inject($userId, u((string) mt_rand(1, 3)), 'stress');
            } elseif ($roll === 2) {
                $pending = val("SELECT id FROM withdrawals WHERE status = 'pending' ORDER BY RAND() LIMIT 1");
                if ($pending !== null) {
                    mt_rand(0, 1) === 1 ? withdrawal_mark_paid($userId, (int) $pending, 'tx') : withdrawal_refund((int) $pending, 'rejected', $userId, 'stress');
                }
            } else {
                deposit_manual($userId, (int) val("SELECT id FROM users WHERE role = 'user' ORDER BY RAND() LIMIT 1"), u((string) mt_rand(1, 5)), 'stress');
            }
        }
    } catch (AppError) {
        $stats['refused']++;
    } catch (Throwable $e) {
        $stats['errors']++;
        fwrite(STDERR, "[$role $userId] " . $e->getMessage() . "\n");
    }
}
echo json_encode($stats), "\n";
