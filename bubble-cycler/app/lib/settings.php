<?php
/**
 * Site settings, editable from Admin → Settings. Values live in the
 * `settings` table; the defaults below fill any gap.
 */
declare(strict_types=1);

const SETTING_DEFAULTS = [
    // General
    'site_name'          => 'Bubble Cycler',
    'currency_symbol'    => '$',
    'currency_code'      => 'USD',
    'timezone'           => 'UTC',
    'support_email'      => '',
    'registration_open'  => '1',
    'maintenance_mode'   => '0',

    // Bubble economics (money in micro-units)
    'bubble_price'        => '1000000', // $1.00 paid per bubble
    'pool_share'          => '800000',  // $0.80 of it credited to the pool
    'bubble_target'       => '1600000', // bubble expires once the pool has paid it $1.60
    'referral_commission' => '50000',   // $0.05 per bubble bought by a referral (from the platform share)
    'max_bubbles_per_purchase' => '25',
    'max_active_bubbles'  => '0',       // per member, 0 = unlimited
    'allow_cash_purchase' => '1',       // members may re-buy with their cash balance

    // Advertising
    'ad_required'           => '1',    // a sponsored message plays before every purchase
    'ad_seconds'            => '10',
    'ad_view_ttl'           => '1800', // an unlocked purchase stays valid this many seconds
    'ad_credits_per_bubble' => '50',   // 1 credit = 1 guaranteed ad view
    'min_campaign_credits'  => '10',
    'campaign_approval'     => '1',    // member campaigns wait for admin approval

    // Payments
    'min_withdrawal'          => '2000000',
    'max_pending_deposits'    => '5',
    'max_pending_withdrawals' => '3',

    // Security
    'admin_2fa_required'       => '1', // admins must set up two-factor authentication
    'max_registrations_per_ip' => '5', // per 24 hours, 0 = unlimited

    // Email (smtp_password is stored encrypted)
    'mail_transport'  => 'off',        // off | smtp | mail | log
    'mail_from'       => '',
    'mail_from_name'  => '',
    'smtp_host'       => '',
    'smtp_port'       => '587',
    'smtp_encryption' => 'tls',        // tls (STARTTLS) | ssl | none
    'smtp_username'   => '',
    'smtp_password'   => '',
    'notify_members'  => '1',          // deposit / withdrawal status emails
    'notify_admins'   => '1',          // new requests, sent to the support email

    // Legal
    'disclaimer' => 'Bubble payouts are funded only by new bubble purchases entering the pool. '
        . 'They are not guaranteed, bubbles may wait in the queue for a long time, and you can lose the money you spend. '
        . 'Only use money you can afford to lose.',
    'terms_text'   => '',
    'privacy_text' => '',
];

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    $cache = SETTING_DEFAULTS;
    // No database configured for this request yet (e.g. while the installer runs).
    if (!is_array(config('db'))) {
        return $cache;
    }
    try {
        foreach (rows('SELECT `key`, `value` FROM settings') as $setting) {
            $cache[$setting['key']] = (string) $setting['value'];
        }
    } catch (Throwable $e) {
        log_error($e);
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings_all();
    return array_key_exists($key, $all) ? (string) $all[$key] : $default;
}

function setting_int(string $key): int
{
    return (int) setting($key, '0');
}

function setting_bool(string $key): bool
{
    return setting($key, '0') === '1';
}

function settings_save(array $values): void
{
    tx(static function () use ($values): void {
        foreach ($values as $key => $value) {
            q(
                'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?',
                [(string) $key, (string) $value, (string) $value]
            );
        }
    });
    settings_all(true);
}

function site_name(): string
{
    return setting('site_name', 'Bubble Cycler');
}

/** Platform share of one bubble after the pool and the referral commission. */
function platform_share(): int
{
    return setting_int('bubble_price') - setting_int('pool_share') - setting_int('referral_commission');
}

/** Number of new bubble sales needed to fully pay one bubble (1.60 / 0.80 = 2). */
function sales_per_expiry(): float
{
    $share = setting_int('pool_share');
    return $share > 0 ? setting_int('bubble_target') / $share : 0.0;
}
