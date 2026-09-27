<?php
/**
 * The bubble cycler.
 *
 *  - A bubble costs `bubble_price` ($1.00). `pool_share` ($0.80) of it is
 *    credited to the pool, the buyer's referrer receives
 *    `referral_commission`, and the rest is the platform share.
 *  - Bubbles queue up in purchase order (FIFO). The pool always pays the
 *    bubble at the head of the queue: as soon as the pool holds the bubble's
 *    `target` ($1.60), the bubble expires, the owner's cash balance is
 *    credited with the full target and the next bubble becomes the head.
 *  - With the defaults, every two bubbles sold expire one bubble.
 *
 * Bubble ids are dense queue numbers assigned under the pool row lock, so the
 * head is always `bubbles_expired + 1` and queue maths stays O(1).
 */
declare(strict_types=1);

function pool_state(): array
{
    return row('SELECT * FROM pool WHERE id = 1') ?? [
        'id' => 1, 'balance' => 0, 'total_in' => 0, 'total_injected' => 0, 'total_out' => 0,
        'site_revenue' => 0, 'referral_paid' => 0, 'bubbles_sold' => 0, 'bubbles_expired' => 0,
        'target_sold' => 0, 'updated_at' => null,
    ];
}

function queue_length(array $pool): int
{
    return (int) $pool['bubbles_sold'] - (int) $pool['bubbles_expired'];
}

/** Bubble currently being filled by the pool (null when the queue is empty). */
function queue_head(array $pool): ?array
{
    if (queue_length($pool) <= 0) {
        return null;
    }
    return row(
        'SELECT b.*, u.username FROM bubbles b JOIN users u ON u.id = b.user_id WHERE b.id = ?',
        [(int) $pool['bubbles_expired'] + 1]
    );
}

/** The next bubbles in line, head first. */
function queue_next(array $pool, int $limit = 8): array
{
    return rows(
        'SELECT b.id, b.user_id, b.target, b.cum_target, u.username
           FROM bubbles b JOIN users u ON u.id = b.user_id
          WHERE b.id > ? ORDER BY b.id ASC LIMIT ' . max(1, $limit),
        [(int) $pool['bubbles_expired']]
    );
}

/**
 * Buy $quantity bubbles for a member.
 *
 * @return array{purchase_id:int, quantity:int, total:int, first:int, last:int, credits:int, popped:array}
 */
function buy_bubbles(int $userId, int $quantity, string $wallet, string $adToken = ''): array
{
    $price = setting_int('bubble_price');
    $share = setting_int('pool_share');
    $target = setting_int('bubble_target');
    $referralCut = setting_int('referral_commission');
    $creditsEach = setting_int('ad_credits_per_bubble');
    $maxPerPurchase = setting_int('max_bubbles_per_purchase');
    $maxActive = setting_int('max_active_bubbles');

    if ($price <= 0 || $share <= 0 || $target <= 0) {
        throw new AppError('Bubble sales are not configured yet. Please try again later.');
    }
    if ($quantity < 1) {
        throw new AppError('Choose at least one bubble.');
    }
    if ($maxPerPurchase > 0 && $quantity > $maxPerPurchase) {
        throw new AppError(sprintf('You can buy up to %d bubbles at once.', $maxPerPurchase));
    }
    if (!in_array($wallet, ['purchase', 'cash'], true)) {
        throw new AppError('Choose a balance to pay with.');
    }
    if ($wallet === 'cash' && !setting_bool('allow_cash_purchase')) {
        throw new AppError('Buying with your cash balance is currently disabled.');
    }

    return tx(function () use ($userId, $quantity, $wallet, $adToken, $price, $share, $target, $referralCut, $creditsEach, $maxActive): array {
        // Lock order everywhere: pool → member rows → ad campaign.
        $pool = row('SELECT * FROM pool WHERE id = 1 FOR UPDATE');
        if ($pool === null) {
            throw new RuntimeException('Pool row is missing — re-run the installer.');
        }
        $user = row('SELECT * FROM users WHERE id = ? FOR UPDATE', [$userId]);
        if ($user === null || $user['status'] !== 'active') {
            throw new AppError('Your account cannot buy bubbles right now.');
        }

        if ($maxActive > 0) {
            $active = (int) val("SELECT COUNT(*) FROM bubbles WHERE user_id = ? AND status = 'active'", [$userId]);
            if ($active + $quantity > $maxActive) {
                throw new AppError(sprintf(
                    'Members can hold up to %d active bubbles. You have %d.',
                    $maxActive,
                    $active
                ));
            }
        }

        $view = setting_bool('ad_required') ? ad_view_for_purchase($userId, $adToken) : null;

        $total = $price * $quantity;
        $column = WALLETS[$wallet];
        if ((int) $user[$column] < $total) {
            throw new AppError(sprintf(
                'Your %s is %s — you need %s for %s.',
                strtolower(WALLET_LABELS[$wallet]),
                money($user[$column]),
                money($total),
                plural($quantity, 'bubble')
            ));
        }

        $now = now();
        $first = (int) $pool['bubbles_sold'] + 1;
        $last = (int) $pool['bubbles_sold'] + $quantity;
        $poolIn = $share * $quantity;
        $credits = max(0, $creditsEach) * $quantity;

        $purchaseId = insert('purchases', [
            'user_id'      => $userId,
            'quantity'     => $quantity,
            'unit_price'   => $price,
            'total'        => $total,
            'pool_amount'  => $poolIn,
            'wallet'       => $wallet,
            'ad_credits'   => $credits,
            'ad_view_id'   => $view['id'] ?? null,
            'first_bubble' => $first,
            'last_bubble'  => $last,
            'created_at'   => $now,
        ]);

        $label = $quantity === 1 ? 'Bubble #' . number_format($first) : sprintf('Bubbles #%s–#%s', number_format($first), number_format($last));
        wallet_move($userId, $wallet, -$total, 'bubble_purchase', $label . ' bought', 'purchase', $purchaseId);

        // New bubbles join the back of the queue.
        $ahead = queue_length($pool);
        $cumulative = (int) $pool['target_sold'];
        $marks = [];
        $params = [];
        for ($i = 0; $i < $quantity; $i++) {
            $cumulative += $target;
            $marks[] = '(?, ?, ?, ?, ?, ?, ?, ?)';
            array_push($params, $first + $i, $userId, $purchaseId, $price, $target, $cumulative, $ahead + $i, $now);
        }
        q('INSERT INTO bubbles (id, user_id, purchase_id, price, target, cum_target, ahead_at_buy, created_at) VALUES '
            . implode(', ', $marks), $params);

        q('UPDATE users SET bubbles_bought = bubbles_bought + ? WHERE id = ?', [$quantity, $userId]);
        if ($credits > 0) {
            wallet_move($userId, 'ads', $credits, 'ad_credits', 'Advertising credits included with ' . plural($quantity, 'bubble'), 'purchase', $purchaseId);
        }

        // Referral commission, paid out of the platform share.
        $referralPaid = 0;
        $referrerId = (int) ($user['referrer_id'] ?? 0);
        if ($referralCut > 0 && $referrerId > 0 && $referrerId !== $userId) {
            if (val('SELECT status FROM users WHERE id = ?', [$referrerId]) === 'active') {
                $referralPaid = min($referralCut * $quantity, max(0, $total - $poolIn));
                if ($referralPaid > 0) {
                    wallet_move($referrerId, 'cash', $referralPaid, 'referral', sprintf(
                        '%s bought %s',
                        $user['username'],
                        plural($quantity, 'bubble')
                    ), 'purchase', $purchaseId);
                    q('UPDATE users SET total_ref_earned = total_ref_earned + ? WHERE id = ?', [$referralPaid, $referrerId]);
                }
            }
        }

        $platform = max(0, $total - $poolIn - $referralPaid);
        q('UPDATE pool SET balance = balance + ?, total_in = total_in + ?, site_revenue = site_revenue + ?,
                referral_paid = referral_paid + ?, bubbles_sold = bubbles_sold + ?, target_sold = target_sold + ?,
                updated_at = ? WHERE id = 1', [
            $poolIn, $poolIn, $platform, $referralPaid, $quantity, $target * $quantity, $now,
        ]);

        $popped = pool_process();

        if ($view !== null) {
            ad_view_consume($view, $purchaseId);
        }

        return [
            'purchase_id' => $purchaseId,
            'quantity'    => $quantity,
            'total'       => $total,
            'first'       => $first,
            'last'        => $last,
            'credits'     => $credits,
            'popped'      => $popped,
        ];
    });
}

/**
 * Pay the head of the queue while the pool can afford it. Must run inside a
 * transaction that already holds the pool row lock.
 *
 * @return list<array{id:int, user_id:int, target:int}>
 */
function pool_process(): array
{
    if (!db()->inTransaction()) {
        throw new LogicException('pool_process() must run inside tx().');
    }
    $popped = [];
    while (true) {
        $pool = row('SELECT balance, bubbles_sold, bubbles_expired FROM pool WHERE id = 1 FOR UPDATE');
        $headId = (int) $pool['bubbles_expired'] + 1;
        if ($headId > (int) $pool['bubbles_sold']) {
            break;
        }
        $head = row('SELECT id, user_id, target FROM bubbles WHERE id = ? FOR UPDATE', [$headId]);
        if ($head === null || (int) $pool['balance'] < (int) $head['target']) {
            break;
        }

        $target = (int) $head['target'];
        $owner = (int) $head['user_id'];
        $now = now();
        q("UPDATE bubbles SET status = 'expired', earned = ?, expired_at = ? WHERE id = ?", [$target, $now, $headId]);
        q('UPDATE pool SET balance = balance - ?, total_out = total_out + ?, bubbles_expired = bubbles_expired + 1,
                updated_at = ? WHERE id = 1', [$target, $target, $now]);
        wallet_move($owner, 'cash', $target, 'bubble_payout', sprintf('Bubble #%s expired at %s', number_format($headId), money($target)), 'bubble', $headId);
        q('UPDATE users SET total_earned = total_earned + ? WHERE id = ?', [$target, $owner]);

        $popped[] = ['id' => $headId, 'user_id' => $owner, 'target' => $target];
    }
    return $popped;
}

/** Admin top-up of the pool; expires every bubble it can afford right away. */
function pool_inject(int $adminId, int $amount, string $note = ''): array
{
    if ($amount <= 0) {
        throw new AppError('Enter a positive amount to add to the pool.');
    }
    return tx(function () use ($adminId, $amount, $note): array {
        row('SELECT id FROM pool WHERE id = 1 FOR UPDATE');
        q('UPDATE pool SET balance = balance + ?, total_in = total_in + ?, total_injected = total_injected + ?,
                updated_at = ? WHERE id = 1', [$amount, $amount, $amount, now()]);
        $popped = pool_process();
        admin_log($adminId, 'pool.inject', trim(sprintf(
            'Added %s to the pool, %s expired. %s',
            money($amount),
            plural(count($popped), 'bubble'),
            $note
        )));
        return $popped;
    });
}

/**
 * Where a bubble stands: rising through the queue, filling at the head, or
 * expired. "needed" is the money the pool still has to receive before this
 * bubble expires; "sales" converts it into new bubble purchases.
 */
function bubble_state(array $bubble, array $pool): array
{
    $id = (int) $bubble['id'];
    $target = (int) $bubble['target'];
    if ($bubble['status'] === 'expired') {
        return [
            'state' => 'expired', 'position' => 0, 'ahead' => 0, 'fill' => 100.0, 'rise' => 100.0,
            'filled' => $target, 'needed' => 0, 'sales' => 0,
        ];
    }

    $ahead = max(0, $id - ((int) $pool['bubbles_expired'] + 1));
    $filled = $ahead === 0 ? min((int) $pool['balance'], $target) : 0;
    $aheadAtBuy = (int) $bubble['ahead_at_buy'];
    $rise = $aheadAtBuy > 0 ? (1 - $ahead / $aheadAtBuy) * 100 : 100.0;
    $needed = max(0, (int) $bubble['cum_target'] - (int) $pool['total_out'] - (int) $pool['balance']);
    $share = setting_int('pool_share');

    return [
        'state'    => $ahead === 0 ? 'filling' : 'rising',
        'position' => $ahead + 1,
        'ahead'    => $ahead,
        'fill'     => $target > 0 ? $filled / $target * 100 : 0.0,
        'rise'     => max(0.0, min(100.0, $rise)),
        'filled'   => $filled,
        'needed'   => $needed,
        'sales'    => $share > 0 ? intdiv($needed + $share - 1, $share) : 0,
    ];
}

/** What happens if someone buys $quantity bubbles right now (buy page preview). */
function queue_quote(array $pool, int $quantity = 1): array
{
    $share = setting_int('pool_share');
    $target = setting_int('bubble_target');
    $balanceAfter = (int) $pool['balance'] + $share * $quantity;
    $needed = max(0, (int) $pool['target_sold'] + $target - (int) $pool['total_out'] - $balanceAfter);
    return [
        'first_id' => (int) $pool['bubbles_sold'] + 1,
        'ahead'    => queue_length($pool),
        'needed'   => $needed,
        'sales'    => $share > 0 ? intdiv($needed + $share - 1, $share) : 0,
    ];
}

/** Totals for one member's bubbles. */
function member_bubble_stats(int $userId): array
{
    $stats = row(
        "SELECT COALESCE(SUM(status = 'active'), 0) AS active,
                COALESCE(SUM(status = 'expired'), 0) AS expired,
                COALESCE(SUM(CASE WHEN status = 'active' THEN target ELSE 0 END), 0) AS active_value,
                COALESCE(SUM(earned), 0) AS earned
           FROM bubbles WHERE user_id = ?",
        [$userId]
    );
    return array_map('intval', $stats ?? ['active' => 0, 'expired' => 0, 'active_value' => 0, 'earned' => 0]);
}

/** Recent expirations for public feeds (usernames masked). */
function recent_expirations(int $limit = 8): array
{
    return rows(
        "SELECT b.id, b.target, b.expired_at, u.username
           FROM bubbles b JOIN users u ON u.id = b.user_id
          WHERE b.status = 'expired' ORDER BY b.id DESC LIMIT " . max(1, $limit)
    );
}

function recent_purchases(int $limit = 8): array
{
    return rows(
        'SELECT p.id, p.quantity, p.total, p.created_at, u.username
           FROM purchases p JOIN users u ON u.id = p.user_id
          ORDER BY p.id DESC LIMIT ' . max(1, $limit)
    );
}
