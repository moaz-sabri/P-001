<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * ���?�?�? �?�?�?�?�? �?�?�?�?�?�?�?�? �?? SQLite �?�?�?�? MySQL.
 *
 * �?�?�?�?�? �?�?�?�?�?�?�?:
 *  - �?�?�?�? 'db.path' ��? SQLite.
 *  - �?�?�?�? 'db.host' / 'db.database' (�� env DB_* ) ��? MySQL.
 *  Database::usePath() �? SQLite �?�? ��?�? (�?�?�?�?�?�?).
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static int $txDepth = 0;
    private static ?string $pathOverride = null;
    private static string $driver = 'sqlite';

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = app_config('db', []);
        if (!is_array($config)) {
            $config = [];
        }

        $driver = strtolower((string) ($config['driver'] ?? ''));
        if ($driver === '') {
            if (self::$pathOverride !== null || isset($config['path'])) {
                $driver = 'sqlite';
            } elseif (isset($config['host']) || isset($config['database']) || isset($config['name'])) {
                $driver = 'mysql';
            } elseif (getenv('DB_HOST')) {
                $driver = 'mysql';
            } else {
                $driver = 'sqlite';
            }
        }

        self::$driver = $driver;

        if ($driver === 'mysql') {
            $host = (string) ($config['host'] ?? getenv('DB_HOST') ?: '127.0.0.1');
            $port = (int) ($config['port'] ?? getenv('DB_PORT') ?: 3306);
            $name = (string) ($config['database'] ?? $config['name'] ?? getenv('DB_DATABASE') ?: getenv('DB_NAME') ?: 'app');
            $user = (string) ($config['username'] ?? $config['user'] ?? getenv('DB_USERNAME') ?: getenv('DB_USER') ?: 'root');
            $pass = (string) ($config['password'] ?? $config['pass'] ?? getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: '');

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);

            try {
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                throw new \RuntimeException('MySQL connection failed: ' . $e->getMessage(), 0, $e);
            }

            $pdo->exec('SET NAMES utf8mb4');

            self::$pdo = $pdo;
            return $pdo;
        }

        // SQLite
        $path = self::$pathOverride ?? (string) app_config('db.path', BASE_PATH . '/storage/database/app.sqlite');
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        try {
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new \RuntimeException('SQLite connection failed: ' . $e->getMessage(), 0, $e);
        }

        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        self::$pdo = $pdo;
        return $pdo;
    }

    public static function driver(): string
    {
        if (self::$pdo === null) {
            // ensure detection
            self::pdo();
        }
        return self::$driver;
    }

    /** SQLite-only path override (tests). */
    public static function usePath(string $path): void
    {
        self::$pdo    = null;
        self::$txDepth = 0;
        self::$pathOverride = $path;
        self::$driver = 'sqlite';
    }

    public static function close(): void
    {
        self::$pdo = null;
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function today(): string
    {
        return date('Y-m-d');
    }

    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                is_null($value) => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(static fn(string $c): string => ':' . $c, $cols))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = implode(', ', array_map(static fn(string $c): string => $c . ' = :set_' . $c, array_keys($data)));
        $sql  = sprintf('UPDATE %s SET %s WHERE %s', $table, $sets, $where);
        $params = [];
        foreach ($data as $k => $v) {
            $params['set_' . $k] = $v;
        }
        return self::run($sql, array_merge($params, $whereParams))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run(sprintf('DELETE FROM %s WHERE %s', $table, $where), $params)->rowCount();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        if (self::$txDepth === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT sp' . self::$txDepth);
        }
        self::$txDepth++;

        try {
            $result = $callback();
            self::$txDepth--;
            if (self::$txDepth === 0) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT sp' . self::$txDepth);
            }
            return $result;
        } catch (\Throwable $e) {
            self::$txDepth--;
            if (self::$txDepth === 0) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } else {
                $pdo->exec('ROLLBACK TO SAVEPOINT sp' . self::$txDepth);
            }
            throw $e;
        }
    }
}
