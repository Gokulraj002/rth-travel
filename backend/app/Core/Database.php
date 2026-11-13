<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper. Always use parameter binding — never interpolate user input into SQL.
 *   Database::fetchAll('SELECT * FROM packages WHERE status = ?', ['published']);
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $port = Env::get('DB_PORT', '3306');
            $name = Env::get('DB_NAME', '');
            $socket = Env::get('DB_SOCKET', '');
            $dsn = $socket !== ''
                ? "mysql:unix_socket={$socket};dbname={$name};charset=utf8mb4"
                : "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            self::$pdo = new PDO($dsn, Env::get('DB_USER', ''), Env::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            self::$pdo->exec("SET time_zone = '+05:30'");
        }
        return self::$pdo;
    }

    /** @param array<int|string,mixed> $params */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @param array<int|string,mixed> $params @return array<string,mixed>|null */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<int|string,mixed> $params @return list<array<string,mixed>> */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** @param array<int|string,mixed> $params */
    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /**
     * Insert a row. Column names come from code (never from user input).
     * @param array<string,mixed> $data
     */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn ($c) => "`{$c}`", $cols)),
            implode(', ', array_fill(0, count($cols), '?')),
        );
        self::query($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int|string,mixed> $whereParams
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(static fn ($c) => "`{$c}` = ?", array_keys($data)));
        return self::query("UPDATE `{$table}` SET {$set} WHERE {$where}", [...array_values($data), ...$whereParams])->rowCount();
    }

    /**
     * Run $fn inside a transaction; rolls back and rethrows on failure.
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
