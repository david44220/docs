<?php
/**
 * Installer: checks requirements, creates the tables, seeds the defaults and
 * the first admin account, then writes app/config.php and a lock file.
 */
declare(strict_types=1);

function install_requirements(): array
{
    return [
        'PHP 8.1 or newer'            => version_compare(PHP_VERSION, '8.1.0', '>='),
        'PDO MySQL extension'         => extension_loaded('pdo_mysql'),
        'mbstring extension'          => extension_loaded('mbstring'),
        'fileinfo extension'          => extension_loaded('fileinfo'),
        'app/ folder writable'        => is_writable(dirname(config_file())) || is_writable(config_file()),
        'storage/ folder writable'    => is_writable(STORAGE_DIR),
        'storage/uploads writable'    => is_dir(STORAGE_DIR . '/uploads') ? is_writable(STORAGE_DIR . '/uploads') : is_writable(STORAGE_DIR),
    ];
}

/** Split schema.sql into statements (the file has no string literals, only DDL and "--" comments). */
function install_schema_statements(): array
{
    $sql = (string) file_get_contents(APP_ROOT . '/database/schema.sql');
    $sql = preg_replace('/--[^\n]*/', '', $sql) ?? '';
    return array_values(array_filter(array_map('trim', explode(';', $sql)), static fn ($s) => $s !== ''));
}

/**
 * Full installation: database + app/config.php + storage/installed.lock.
 *
 * @param array $db    host, port, name, user, pass
 * @param array $site  site_name, base_url
 * @param array $admin username, email, password
 * @return bool true when the admin account was created (false: one already existed)
 */
function install_run(array $db, array $site, array $admin): bool
{
    $created = install_database($db, $site, $admin);
    install_write_config($db, rtrim(trim((string) ($site['base_url'] ?? '')), '/'));
    if (@file_put_contents(STORAGE_DIR . '/installed.lock', 'Installed ' . now() . " UTC\n") === false) {
        throw new AppError('Could not write storage/installed.lock — make storage/ writable and try again.');
    }
    return $created;
}

/**
 * Create the tables, default settings, pool row, first admin and starter
 * content. Safe to run again on an existing database.
 */
function install_database(array $db, array $site, array $admin): bool
{
    validate_username($admin['username']);
    validate_email($admin['email']);
    validate_password($admin['password']);
    $siteName = trim((string) ($site['site_name'] ?? '')) ?: 'BubbleCycle';
    $baseUrl = rtrim(trim((string) ($site['base_url'] ?? '')), '/');
    if ($baseUrl !== '' && !valid_http_url($baseUrl)) {
        throw new AppError('The site URL must start with http:// or https://');
    }

    try {
        $pdo = db_connect($db);
    } catch (PDOException $e) {
        throw new AppError('Could not connect to the database: ' . $e->getMessage());
    }

    foreach (install_schema_statements() as $statement) {
        $pdo->exec($statement);
    }

    $hasAdmin = (int) install_value($pdo, 'SELECT COUNT(*) FROM users WHERE role = ?', ['admin']) > 0;

    $now = now();
    $settings = $pdo->prepare('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)');
    foreach (SETTING_DEFAULTS as $key => $value) {
        $settings->execute([$key, $key === 'site_name' ? $siteName : $value]);
    }
    $pdo->prepare('UPDATE settings SET `value` = ? WHERE `key` = ?')->execute([$siteName, 'site_name']);
    $settings->execute(['db_version', (string) DB_VERSION]);
    $pdo->exec('INSERT IGNORE INTO pool (id, updated_at) VALUES (1, ' . $pdo->quote($now) . ')');

    if (!$hasAdmin) {
        $insert = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, pops_seen_at, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        try {
            $insert->execute([$admin['username'], mb_strtolower($admin['email']), password_hash($admin['password'], PASSWORD_DEFAULT), 'admin', $now, $now]);
        } catch (PDOException $e) {
            if (is_duplicate_key($e)) {
                throw new AppError('A member with this username or email already exists in the database.');
            }
            throw $e;
        }
    }

    // Starter content: one house ad and inactive example payment methods.
    $houseAds = (int) install_value($pdo, 'SELECT COUNT(*) FROM ad_campaigns WHERE is_house = 1');
    if ($houseAds === 0) {
        $advertiseUrl = match (true) {
            $baseUrl !== ''     => $baseUrl . '/advertise.php',
            PHP_SAPI === 'cli'  => 'https://example.com/advertise.php',
            default             => absolute_url('advertise.php'),
        };
        $pdo->prepare('INSERT INTO ad_campaigns (user_id, is_house, title, description, url, cta_label, status, created_at, updated_at)
                       VALUES (NULL, 1, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                'Your ad could be here',
                'Every bubble you buy includes advertising credits. Launch a campaign and reach members right before they buy.',
                $advertiseUrl,
                'Start advertising',
                'active',
                $now,
                $now,
            ]);
    }
    $methods = (int) install_value($pdo, 'SELECT COUNT(*) FROM payment_methods');
    if ($methods === 0) {
        $method = $pdo->prepare('INSERT INTO payment_methods (type, name, currency, color, account_label, account_value, instructions,
                                 min_amount, max_amount, fee_fixed, fee_percent_bp, require_proof, status, sort_order, created_at, updated_at)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?, ?, ?)');
        $method->execute(['deposit', 'USDT (TRC20)', 'USDT', '#26a17b', 'Our USDT TRC20 address', 'Replace with your wallet address',
            "Send USDT on the TRON (TRC20) network only.\nPaste the transaction hash below once the transfer is confirmed.", 5 * MONEY_SCALE, 1, 'inactive', 1, $now, $now]);
        $method->execute(['deposit', 'Bank transfer', 'USD', '#3b82f6', 'Bank details', "Account name: …\nIBAN: …\nBIC/SWIFT: …",
            "Use your username as the transfer reference.\nTransfers are credited once they reach our account.", 10 * MONEY_SCALE, 0, 'inactive', 2, $now, $now]);
        $method->execute(['withdrawal', 'USDT (TRC20)', 'USDT', '#26a17b', 'Your USDT TRC20 address', '',
            'Double-check your address: blockchain transfers cannot be reversed.', 2 * MONEY_SCALE, 0, 'inactive', 1, $now, $now]);
    }

    return !$hasAdmin;
}

function install_value(PDO $pdo, string $sql, array $params = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

function install_config_source(array $db, string $baseUrl): string
{
    $config = [
        'db' => [
            'host' => (string) $db['host'],
            'port' => (int) ($db['port'] ?? 3306),
            'name' => (string) $db['name'],
            'user' => (string) $db['user'],
            'pass' => (string) $db['pass'],
        ],
        // Full public URL, e.g. https://example.com — required for emails (reset links).
        'base_url'    => $baseUrl,
        // Encrypts two-factor secrets and the SMTP password. Keep it secret, never change it.
        'app_key'     => base64_encode(random_bytes(32)),
        // Show error details. Never enable on a live site.
        'debug'       => false,
        // Redirect every http:// request to https:// (on when the site was installed over HTTPS).
        'force_https' => str_starts_with($baseUrl, 'https://'),
        // Send Strict-Transport-Security on HTTPS responses.
        'hsts'        => true,
        // Send the Content-Security-Policy header (disable only if you add third-party scripts).
        'csp'         => true,
        // Sign members out after this many seconds of inactivity.
        'session_idle' => 7200,
        // Behind Cloudflare or a reverse proxy: e.g. 'HTTP_CF_CONNECTING_IP' / true.
        'ip_header'   => '',
        'trust_proxy' => false,
    ];
    return "<?php\n// Generated by the installer on " . gmdate('Y-m-d H:i') . " UTC.\nreturn " . var_export($config, true) . ";\n";
}

function install_write_config(array $db, string $baseUrl): void
{
    $file = config_file();
    if (@file_put_contents($file, install_config_source($db, $baseUrl), LOCK_EX) === false) {
        throw new AppError('Could not write app/config.php. Make the app/ folder writable, or create the file by hand from app/config.sample.php.');
    }
    @chmod($file, 0640);
}
