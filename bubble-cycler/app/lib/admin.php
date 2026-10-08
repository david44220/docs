<?php
/**
 * Admin helpers: audit log, pending counters, dashboard statistics and
 * member management actions.
 */
declare(strict_types=1);

/** The label of a setting, as shown on Admin → Settings (for validation messages). */
function setting_label(string $key): string
{
    return match ($key) {
        'bubble_price'             => t('Bubble price'),
        'pool_share'               => t('Credited to the pool'),
        'bubble_target'            => t('Expires at'),
        'referral_commission'      => t('Referral commission'),
        'min_withdrawal'           => t('Minimum withdrawal'),
        'max_bubbles_per_purchase' => t('Max bubbles per purchase'),
        'max_active_bubbles'       => t('Max active bubbles per member'),
        'ad_seconds'               => t('Ad duration'),
        'ad_view_ttl'              => t('Unlock valid for'),
        'ad_credits_per_bubble'    => t('Ad credits per bubble'),
        'min_campaign_credits'     => t('Minimum credits per campaign'),
        'max_pending_deposits'     => t('Pending deposits per member'),
        'max_pending_withdrawals'  => t('Pending withdrawals per member'),
        'max_registrations_per_ip' => t('Sign-ups per IP address'),
        'smtp_port'                => t('SMTP port'),
        default                    => $key,
    };
}

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
    $users = row_required(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(created_at >= ?), 0) AS today,
                COALESCE(SUM(status = 'banned'), 0) AS banned,
                COALESCE(SUM(purchase_balance), 0) AS purchase,
                COALESCE(SUM(cash_balance), 0) AS cash,
                COALESCE(SUM(ad_credits), 0) AS credits
           FROM users",
        [gmdate('Y-m-d 00:00:00')]
    );
    $deposits = row_required(
        "SELECT COALESCE(SUM(CASE WHEN status = 'approved' THEN credit_amount END), 0) AS approved,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount END), 0) AS pending_amount,
                COALESCE(SUM(status = 'pending'), 0) AS pending
           FROM deposits"
    );
    $withdrawals = row_required(
        "SELECT COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS paid,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN amount END), 0) AS pending_amount,
                COALESCE(SUM(status = 'pending'), 0) AS pending
           FROM withdrawals"
    );
    $ads = row_required(
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
    // expired_at is only set on expired bubbles: its index alone answers this.
    foreach (rows('SELECT DATE(expired_at) AS d, COUNT(*) AS n FROM bubbles WHERE expired_at >= ? GROUP BY DATE(expired_at)', [$start . ' 00:00:00']) as $r) {
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
        throw new AppError(t('Choose a wallet.'));
    }
    if ($wallet === 'ads') {
        $amount = preg_match('/^\d{1,9}$/', trim($amountText)) ? (int) $amountText : null;
    } else {
        $amount = to_payment_units($amountText);
    }
    if ($amount === null || $amount <= 0) {
        throw new AppError($wallet === 'ads' ? t('Enter a whole number of credits.') : t('Enter a valid amount.'));
    }
    if (mb_strlen($note) < 3) {
        throw new AppError(t('Add a short note explaining the adjustment.'));
    }
    $credit = $direction === 'credit';
    tx(function () use ($adminId, $userId, $wallet, $amount, $note, $credit): void {
        wallet_move($userId, $wallet, $credit ? $amount : -$amount, $credit ? 'admin_credit' : 'admin_debit', 'Adjustment: ' . $note, 'admin', $adminId);
        $shown = $wallet === 'ads' ? stored_count($amount, '{n} credit', '{n} credits') : stored_money($amount);
        admin_log($adminId, 'balance.' . ($credit ? 'credit' : 'debit'), sprintf('%s %s %s member #%d (%s) — %s', $credit ? 'Credited' : 'Debited', $shown, $credit ? 'to' : 'from', $userId, WALLET_LABELS[$wallet], $note));
    });
}

function admin_set_user_status(int $adminId, int $userId, string $status): void
{
    if (!in_array($status, ['active', 'banned'], true)) {
        throw new AppError(t('Unknown status.'));
    }
    if ($userId === $adminId) {
        throw new AppError(t('You cannot change the status of your own account.'));
    }
    q('UPDATE users SET status = ? WHERE id = ?', [$status, $userId]);
    admin_log($adminId, 'user.' . ($status === 'banned' ? 'ban' : 'unban'), sprintf('Member #%d set to %s', $userId, $status));
}

function admin_set_user_role(int $adminId, int $userId, string $role): void
{
    if (!in_array($role, ['user', 'admin'], true)) {
        throw new AppError(t('Unknown role.'));
    }
    if ($userId === $adminId) {
        throw new AppError(t('You cannot change your own role.'));
    }
    q('UPDATE users SET role = ? WHERE id = ?', [$role, $userId]);
    admin_log($adminId, 'user.role', sprintf('Member #%d role set to %s', $userId, $role));
}

function admin_reset_password(int $adminId, int $userId, string $password): void
{
    validate_password($password, (string) val('SELECT username FROM users WHERE id = ?', [$userId]));
    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $userId]);
    admin_log($adminId, 'user.password', sprintf('Reset the password of member #%d', $userId));
    notify_member($userId, static fn (): array => [
        'subject' => t('Your password was reset'),
        'title'   => t('Your password was reset by support'),
        'lines'   => [
            t('An administrator set a new password on your {site} account. Your other devices were signed out.', ['site' => site_name()]),
            t('If you did not ask for this, contact support immediately.'),
        ],
    ], security: true);
}

/** For members who lost both their phone and their recovery codes. */
function admin_disable_2fa(int $adminId, int $userId): void
{
    user_disable_2fa($userId);
    admin_log($adminId, 'user.2fa_reset', sprintf('Turned off two-factor authentication of member #%d', $userId));
    notify_member($userId, static fn (): array => [
        'subject' => t('Two-factor authentication was reset'),
        'title'   => t('Two-factor authentication was reset by support'),
        'lines'   => [
            t('An administrator turned off two-factor authentication on your {site} account. You can set it up again from your account page.', ['site' => site_name()]),
            t('If you did not ask for this, contact support immediately.'),
        ],
    ], security: true);
}

function admin_set_email(int $adminId, int $userId, string $email): void
{
    $email = mb_strtolower(trim($email));
    validate_email($email);
    if (val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $userId]) !== null) {
        throw new AppError(t('Another account already uses this email.'));
    }
    $old = (string) val('SELECT email FROM users WHERE id = ?', [$userId]);
    q('UPDATE users SET email = ? WHERE id = ?', [$email, $userId]);
    admin_log($adminId, 'user.email', sprintf('Changed the email of member #%d from %s to %s', $userId, $old, $email));
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
        'max_registrations_per_ip' => [0, 1000],
        'smtp_port'                => [1, 65535],
    ];
    $bools = ['registration_open', 'maintenance_mode', 'allow_cash_purchase', 'ad_required', 'campaign_approval',
        'admin_2fa_required', 'notify_members', 'notify_admins'];

    $values = [];
    $siteName = trim((string) ($input['site_name'] ?? ''));
    if (mb_strlen($siteName) < 2 || mb_strlen($siteName) > 40) {
        throw new AppError(t('Site name must be 2 to 40 characters long.'));
    }
    $values['site_name'] = $siteName;
    $symbol = trim((string) ($input['currency_symbol'] ?? '$'));
    $values['currency_symbol'] = mb_substr($symbol !== '' ? $symbol : '$', 0, 5);
    $values['currency_code'] = strtoupper(mb_substr(trim((string) ($input['currency_code'] ?? 'USD')), 0, 10)) ?: 'USD';
    $timezone = trim((string) ($input['timezone'] ?? 'UTC'));
    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        throw new AppError(t('Choose a valid timezone.'));
    }
    $values['timezone'] = $timezone;
    $email = trim((string) ($input['support_email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new AppError(t('Support email is not valid.'));
    }
    $values['support_email'] = $email;

    foreach ($money as $key) {
        $units = to_payment_units((string) ($input[$key] ?? ''));
        if ($units === null) {
            throw new AppError(t('“{field}” must be an amount such as 1.00 (at most 2 decimals).', ['field' => setting_label($key)]));
        }
        $values[$key] = (string) $units;
    }
    foreach ($ints as $key => [$min, $max]) {
        $raw = trim((string) ($input[$key] ?? ''));
        if (!preg_match('/^\d{1,9}$/', $raw) || (int) $raw < $min || (int) $raw > $max) {
            throw new AppError(t('“{field}” must be a whole number between {min} and {max}.', ['field' => setting_label($key), 'min' => num($min), 'max' => num($max)]));
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
        throw new AppError(t('Bubble price, pool share and expiry target must be greater than zero.'));
    }
    if ($share + $referral > $price) {
        throw new AppError(t('Pool share plus referral commission cannot exceed the bubble price.'));
    }

    $values['disclaimer'] = mb_substr(trim((string) ($input['disclaimer'] ?? '')), 0, 2000);
    $values['terms_text'] = mb_substr(trim((string) ($input['terms_text'] ?? '')), 0, 20000);
    $values['privacy_text'] = mb_substr(trim((string) ($input['privacy_text'] ?? '')), 0, 20000);
    $values['disclaimer_fr'] = mb_substr(trim((string) ($input['disclaimer_fr'] ?? '')), 0, 2000);
    $values['terms_text_fr'] = mb_substr(trim((string) ($input['terms_text_fr'] ?? '')), 0, 20000);
    $values['privacy_text_fr'] = mb_substr(trim((string) ($input['privacy_text_fr'] ?? '')), 0, 20000);

    // Email
    $transport = (string) ($input['mail_transport'] ?? 'off');
    if (!in_array($transport, ['off', 'smtp', 'mail', 'log'], true)) {
        throw new AppError(t('Choose how emails are sent.'));
    }
    $values['mail_transport'] = $transport;
    $from = trim((string) ($input['mail_from'] ?? ''));
    if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new AppError(t('The sender address is not a valid email.'));
    }
    if ($transport !== 'off' && $from === '') {
        throw new AppError(t('Enter the sender address emails are sent from.'));
    }
    $values['mail_from'] = $from;
    $values['mail_from_name'] = mb_substr(mail_header_value((string) ($input['mail_from_name'] ?? '')), 0, 60);
    $smtpHost = trim((string) ($input['smtp_host'] ?? ''));
    if ($smtpHost !== '' && !preg_match('/^[A-Za-z0-9.-]{1,253}$/', $smtpHost)) {
        throw new AppError(t('The SMTP host must be a host name such as smtp.example.com.'));
    }
    if ($transport === 'smtp' && $smtpHost === '') {
        throw new AppError(t('Enter the SMTP host.'));
    }
    $values['smtp_host'] = $smtpHost;
    $encryption = (string) ($input['smtp_encryption'] ?? 'tls');
    $values['smtp_encryption'] = in_array($encryption, ['tls', 'ssl', 'none'], true) ? $encryption : 'tls';
    $values['smtp_username'] = mb_substr(trim((string) ($input['smtp_username'] ?? '')), 0, 190);
    // Write-only: an empty field keeps the saved password.
    $smtpPassword = (string) ($input['smtp_password'] ?? '');
    if (!empty($input['smtp_password_clear'])) {
        $values['smtp_password'] = '';
    } elseif ($smtpPassword !== '') {
        $values['smtp_password'] = seal_secret($smtpPassword);
    }

    $before = settings_all();
    settings_save($values);
    $changed = [];
    foreach ($values as $key => $value) {
        if ((string) ($before[$key] ?? '') !== $value && !in_array($key, ['disclaimer', 'terms_text', 'privacy_text', 'disclaimer_fr', 'terms_text_fr', 'privacy_text_fr'], true)) {
            $changed[] = $key;
        }
    }
    admin_log($adminId, 'settings.update', $changed ? 'Changed: ' . implode(', ', $changed) : 'Saved without changes');
}
