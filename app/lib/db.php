<?php
/**
 * MySQLi connection and small prepared-statement helpers.
 * Every query that involves user input goes through db_query(), which always
 * uses a prepared statement with bound parameters.
 */

function db(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // throw exceptions on errors
        $conn = new mysqli(
            config('db.host'),
            config('db.user'),
            config('db.password'),
            config('db.name'),
            (int) config('db.port', 3306)
        );
        $conn->set_charset('utf8mb4');
        $conn->query("SET time_zone = '+08:00'");
    }
    return $conn;
}

/**
 * Prepare, bind and execute a statement.
 * Types are inferred from the PHP values: int => i, float => d, everything else => s.
 */
function db_query(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params) {
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...array_values($params));
    }
    $stmt->execute();
    return $stmt;
}

/** All rows as associative arrays. */
function db_all(string $sql, array $params = []): array
{
    $result = db_query($sql, $params)->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/** First row or null. */
function db_one(string $sql, array $params = []): ?array
{
    $result = db_query($sql, $params)->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    return $row ?: null;
}

/** First column of the first row. */
function db_value(string $sql, array $params = [])
{
    $row = db_one($sql, $params);
    return $row ? reset($row) : null;
}

/** Run INSERT/UPDATE/DELETE; returns affected row count. */
function db_exec(string $sql, array $params = []): int
{
    return db_query($sql, $params)->affected_rows;
}

/** Run an INSERT; returns the new auto-increment id. */
function db_insert(string $sql, array $params = []): int
{
    db_query($sql, $params);
    return (int) db()->insert_id;
}

/** Run a callback inside a transaction; rolls back on any exception. */
function db_transaction(callable $fn)
{
    $conn = db();
    $conn->begin_transaction();
    try {
        $result = $fn();
        $conn->commit();
        return $result;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
