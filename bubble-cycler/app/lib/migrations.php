<?php
/**
 * Database versioning. Fresh installs get the latest schema.sql; existing
 * installs are upgraded automatically, once, on the next request.
 */
declare(strict_types=1);

const DB_VERSION = 2;

function db_version(): int
{
    return (int) (settings_all()['db_version'] ?? 1);
}

function migrations_pending(): bool
{
    return db_version() < DB_VERSION;
}

function migrate(): void
{
    if ((int) val("SELECT GET_LOCK('bubblecycle_migrate', 30)") !== 1) {
        throw new RuntimeException('Could not acquire the database migration lock.');
    }
    try {
        settings_all(true);
        $version = db_version();
        $steps = [2 => 'migration_2'];
        foreach ($steps as $target => $step) {
            if ($version < $target) {
                $step();
                settings_save(['db_version' => (string) $target]);
                $version = $target;
            }
        }
    } finally {
        val("SELECT RELEASE_LOCK('bubblecycle_migrate')");
    }
}

function column_exists(string $table, string $column): bool
{
    return (int) val(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    ) > 0;
}

function index_exists(string $table, string $index): bool
{
    return (int) val(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
        [$table, $index]
    ) > 0;
}

/** v1 → v2: two-factor authentication, registration IP, rate limits, password resets. */
function migration_2(): void
{
    require_once APP_DIR . '/lib/installer.php';
    foreach (install_schema_statements() as $statement) {
        if (str_starts_with($statement, 'CREATE TABLE IF NOT EXISTS')) {
            db()->exec($statement); // creates the new tables, leaves existing ones alone
        }
    }
    $columns = [
        'register_ip'     => 'VARCHAR(45) NULL AFTER last_ip',
        'totp_secret'     => 'VARCHAR(255) NULL AFTER register_ip',
        'totp_recovery'   => 'TEXT NULL AFTER totp_secret',
        'totp_last_step'  => 'BIGINT NULL AFTER totp_recovery',
        'totp_enabled_at' => 'DATETIME NULL AFTER totp_last_step',
    ];
    foreach ($columns as $column => $definition) {
        if (!column_exists('users', $column)) {
            db()->exec("ALTER TABLE users ADD COLUMN `$column` $definition");
        }
    }
    if (!index_exists('users', 'idx_users_register_ip')) {
        db()->exec('ALTER TABLE users ADD KEY idx_users_register_ip (register_ip)');
    }
}
