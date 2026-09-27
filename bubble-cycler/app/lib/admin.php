<?php
/**
 * Admin helpers: audit log, pending counters, dashboard statistics and
 * member management actions.
 */
declare(strict_types=1);

function admin_log(int $adminId, string $action, string $details = ''): void
{
    insert('admin_logs', [
        'admin_id'   => $adminId,
        'action'     => mb_substr($action, 0, 64),
        'details'    => mb_substr($details, 0, 500),
        'ip'         => PHP_SAPI === 'cli' ? null : client_ip(),
        'created_at' => now(),
    ]);
}

function admin_pending_counts(): array
{
    static $counts = null;
    return $counts ??= [
        'deposits'    => (int) val("SELECT COUNT(*) FROM deposits WHERE status = 'pending'"),
        'withdrawals' => (int) val("SELECT COUNT(*) FROM withdrawals WHERE status = 'pending'"),
        'campaigns'   => (int) val("SELECT COUNT(*) FROM ad_campaigns WHERE status = 'pending'"),
    ];
}

function admin_stats(): array
{
    $pool = pool_state();
    $users = row(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(created_at >= ?), 0) AS today,
                COALESCE(SUM(status = 'banned'), 0) AS banned,
                COALESCE(SUM(purchase_balance), 0) AS purchase,
                COALESCE(SUM(cash_balance), 0) AS cash,
                COALESCE(SUM(ad_credits), 0) AS credits
           FROM users",
        [gmdate('Y-m-d 00:00:00')]
    );
    $deposits = row(
        "SELECT COALESCE(SUM(CASE WHEN status = 'approved' THEN credit_amount END), 0) AS approved,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount END), 0) AS pending_amount,
                COALESCE(SUM(status = 'pending'), 0) AS pending
           FROM deposits"
    );
    $withdrawals = row(
        "SELECT COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS paid,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount END), 0) AS pending_amount,
                COALESCE(SUM(status = 'pending'), 0) AS pending
           FROM withdrawals"
    );
    $ads = row(
        "SELECT COALESCE(SUM(status = 'active' AND is_house = 0), 0) AS active,
                COALESCE(SUM(status = 'pending'), 0) AS pending,
                COALESCE(SUM(views), 0) AS views,
                COALESCE(SUM(clicks), 0) AS clicks,
                COALESCE(SUM(CASE WHEN is_house = 0 THEN credits_remaining END), 0) AS credits_live
           FROM ad_campaigns"
    );

    return [
        'pool'        => $pool,
        'head'        => queue_head($pool),
        'users'       => array_map('intval', $users),
        'deposits'    => array_map('intval', $deposits),
        'withdrawals' => array_map('intval', $withdrawals),
        'ads'         => array_map('intval', $ads),
        // Money the pool still has to collect to expire every active bubble.
        'queue_gap'   => max(0, (int) $pool['target_sold'] - (int) $pool['total_out'] - (int) $pool['balance']),
    ];
}

/** Bubbles bought and expired per day (UTC) for the last $days days. */
function admin_daily_series(int $days = 14): array
{
    $start = gmdate('Y-m-d', time() - ($days - 1) * 86400);
    $bought = [];
    foreach (rows('SELECT DATE(created_at) AS d, SUM(quantity) AS n FROM purchases WHERE created_at >= ? GROUP BY DATE(created_at)', [$start . ' 00:00:00']) as $r) {
        $bought[$r['d']] = (int) $r['n'];
    }
    $expired = [];
    foreach (rows("SELECT DATE(expired_at) AS d, COUNT(*) AS n FROM bubbles WHERE status = 'expired' AND expired_at >= ? GROUP BY DATE(expired_at)", [$start . ' 00:00:00']) as $r) {
        $expired[$r['d']] = (int) $r['n'];
    }
    $series = [];
    for ($i = 0; $i < $days; $i++) {
        $day = gmdate('Y-m-d', strtotime($start . ' UTC') + $i * 86400);
        $series[] = ['day' => $day, 'bought' => $bought[$day] ?? 0, 'expired' => $expired[$day] ?? 0];
    }
    return $series;
}

/** Credit or debit any wallet of a member, with a mandatory note. */
function admin_adjust_balance(int $adminId, int $userId, string $wallet, string $direction, string $amountText, string $note): void
{
    if (!isset(WALLETS[$wallet])) {
        throw new AppError('Choose a wallet.');
    }
    if ($wallet === 'ads') {
        $amount = preg_match('/^\d{1,9}$/', trim($amountText)) ? (int) $amountText : null;
    } else {
        $amount = to_payment_units($amountText);
    }
    if ($amount === null || $amount <= 0) {
        throw new AppError($wallet === 'ads' ? 'Enter a whole number of credits.' : 'Enter a valid amount.');
    }
    if (mb_strlen($note) < 3) {
        throw new AppError('Add a short note explaining the adjustment.');
    }
    $credit = $direction === 'credit';
    tx(function () use ($adminId, $userId, $wallet, $amount, $note, $credit): void {
        wallet_move($userId, $wallet, $credit ? $amount : -$amount, $credit ? 'admin_credit' : 'admin_debit', 'Adjustment: ' . $note, 'admin', $adminId);
        $shown = $wallet === 'ads' ? plural($amount, 'credit') : money($amount);
        admin_log($adminId, 'balance.' . ($credit ? 'credit' : 'debit'), sprintf('%s %s %s member #%d (%s) — %s', $credit ? 'Credited' : 'Debited', $shown, $credit ? 'to' : 'from', $userId, WALLET_LABELS[$wallet], $note));
    });
}

function admin_set_user_status(int $adminId, int $userId, string $status): void
{
    if (!in_array($status, ['active', 'banned'], true)) {
        throw new AppError('Unknown status.');
    }
    if ($userId === $adminId) {
        throw new AppError('You cannot change the status of your own account.');
    }
    q('UPDATE users SET status = ? WHERE id = ?', [$status, $userId]);
    admin_log($adminId, 'user.' . ($status === 'banned' ? 'ban' : 'unban'), sprintf('Member #%d set to %s', $userId, $status));
}

function admin_set_user_role(int $adminId, int $userId, string $role): void
{
    if (!in_array($role, ['user', 'admin'], true)) {
        throw new AppError('Unknown role.');
    }
    if ($userId === $adminId) {
        throw new AppError('You cannot change your own role.');
    }
    q('UPDATE users SET role = ? WHERE id = ?', [$role, $userId]);
    admin_log($adminId, 'user.role', sprintf('Member #%d role set to %s', $userId, $role));
}

function admin_reset_password(int $adminId, int $userId, string $password): void
{
    validate_password($password);
    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $userId]);
    admin_log($adminId, 'user.password', sprintf('Reset the password of member #%d', $userId));
}

/** Validate & store the settings form. */
function admin_save_settings(int $adminId, array $input): void
{
    $money = ['bubble_price', 'pool_share', 'bubble_target', 'referral_commission', 'min_withdrawal'];
    $ints = [
        'max_bubbles_per_purchase' => [1, 1000],
        'max_active_bubbles'       => [0, 1000000],
        'ad_seconds'               => [0, 120],
        'ad_view_ttl'              => [60, 86400],
        'ad_credits_per_bubble'    => [0, 100000],
        'min_campaign_credits'     => [1, 100000],
        'max_pending_deposits'     => [1, 100],
        'max_pending_withdrawals'  => [1, 100],
    ];
    $bools = ['registration_open', 'maintenance_mode', 'allow_cash_purchase', 'ad_required', 'campaign_approval'];

    $values = [];
    $siteName = trim((string) ($input['site_name'] ?? ''));
    if (mb_strlen($siteName) < 2 || mb_strlen($siteName) > 40) {
        throw new AppError('Site name must be 2 to 40 characters long.');
    }
    $values['site_name'] = $siteName;
    $symbol = trim((string) ($input['currency_symbol'] ?? '$'));
    $values['currency_symbol'] = mb_substr($symbol !== '' ? $symbol : '$', 0, 5);
    $values['currency_code'] = strtoupper(mb_substr(trim((string) ($input['currency_code'] ?? 'USD')), 0, 10)) ?: 'USD';
    $timezone = trim((string) ($input['timezone'] ?? 'UTC'));
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        throw new AppError('Choose a valid timezone.');
    }
    $values['timezone'] = $timezone;
    $email = trim((string) ($input['support_email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new AppError('Support email is not valid.');
    }
    $values['support_email'] = $email;

    foreach ($money as $key) {
        $units = to_payment_units((string) ($input[$key] ?? ''));
        if ($units === null) {
            throw new AppError(sprintf('“%s” must be an amount such as 1.00 (at most 2 decimals).', str_replace('_', ' ', $key)));
        }
        $values[$key] = (string) $units;
    }
    foreach ($ints as $key => [$min, $max]) {
        $raw = trim((string) ($input[$key] ?? ''));
        if (!preg_match('/^\d{1,9}$/', $raw) || (int) $raw < $min || (int) $raw > $max) {
            throw new AppError(sprintf('“%s” must be a whole number between %d and %d.', str_replace('_', ' ', $key), $min, $max));
        }
        $values[$key] = (string) (int) $raw;
    }
    foreach ($bools as $key) {
        $values[$key] = !empty($input[$key]) ? '1' : '0';
    }

    $price = (int) $values['bubble_price'];
    $share = (int) $values['pool_share'];
    $target = (int) $values['bubble_target'];
    $referral = (int) $values['referral_commission'];
    if ($price <= 0 || $share <= 0 || $target <= 0) {
        throw new AppError('Bubble price, pool share and expiry target must be greater than zero.');
    }
    if ($share + $referral > $price) {
        throw new AppError('Pool share plus referral commission cannot exceed the bubble price.');
    }

    $values['disclaimer'] = mb_substr(trim((string) ($input['disclaimer'] ?? '')), 0, 2000);
    $values['terms_text'] = mb_substr(trim((string) ($input['terms_text'] ?? '')), 0, 20000);

    $before = settings_all();
    settings_save($values);
    $changed = [];
    foreach ($values as $key => $value) {
        if ((string) ($before[$key] ?? '') !== $value && !in_array($key, ['disclaimer', 'terms_text'], true)) {
            $changed[] = $key;
        }
    }
    admin_log($adminId, 'settings.update', $changed ? 'Changed: ' . implode(', ', $changed) : 'Saved without changes');
}
