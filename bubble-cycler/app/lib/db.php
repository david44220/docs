<?php
/**
 * Thin PDO layer: one shared connection, prepared statements everywhere,
 * and a transaction helper that retries on deadlocks.
 */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if (!$pdo instanceof PDO) {
        $pdo = db_connect((array) config('db', []));
    }
    return $pdo;
}

function db_connect(array $c): PDO
{
    $host = (string) ($c['host'] ?? '127.0.0.1');
    $name = (string) ($c['name'] ?? '');
    $dsn = str_starts_with($host, '/')
        ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $host, $name)
        : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int) ($c['port'] ?? 3306), $name);

    $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

/** Prepare + execute with correctly typed parameters. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $position = 0;
    foreach ($params as $key => $value) {
        $name = is_int($key) ? ++$position : $key;
        if (is_bool($value)) {
            $value = (int) $value;
        }
        $type = match (true) {
            is_int($value) => PDO::PARAM_INT,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
        $stmt->bindValue($name, $value, $type);
    }
    $stmt->execute();
    return $stmt;
}

function row(string $sql, array $params = []): ?array
{
    $result = q($sql, $params)->fetch();
    return $result === false ? null : $result;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = []): mixed
{
    $result = q($sql, $params)->fetchColumn();
    return $result === false ? null : $result;
}

/** Insert a row; table and column names always come from code, never from input. */
function insert(string $table, array $data): int
{
    $columns = implode(', ', array_map(static fn ($c) => '`' . $c . '`', array_keys($data)));
    $marks = implode(', ', array_fill(0, count($data), '?'));
    q("INSERT INTO `$table` ($columns) VALUES ($marks)", array_values($data));
    return (int) db()->lastInsertId();
}

function update_row(string $table, int $id, array $data): void
{
    $set = implode(', ', array_map(static fn ($c) => '`' . $c . '` = ?', array_keys($data)));
    q("UPDATE `$table` SET $set WHERE id = ?", [...array_values($data), $id]);
}

/**
 * Run $fn inside a transaction. Deadlocks and lock wait timeouts are retried
 * a few times; any other exception rolls back and propagates.
 */
function tx(callable $fn, int $attempts = 3): mixed
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    for ($try = 1; ; $try++) {
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $code = $e instanceof PDOException ? (int) ($e->errorInfo[1] ?? 0) : 0;
            if (in_array($code, [1213, 1205], true) && $try < $attempts) {
                usleep(random_int(20000, 150000));
                continue;
            }
            throw $e;
        }
    }
}

function is_duplicate_key(Throwable $e): bool
{
    return $e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062;
}
