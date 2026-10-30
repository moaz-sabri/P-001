<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Database migrations for SQLite and MySQL.
 */
final class Migrations
{
    public static function isInstalled(): bool
    {
        try {
            if (Database::driver() === 'mysql') {
                $row = Database::one("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name='app_meta'");
                return $row !== null;
            }
            $row = Database::one("SELECT name FROM sqlite_master WHERE type='table' AND name='app_meta'");
            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function migrate(): void
    {
        if (Database::driver() === 'mysql') {
            $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS app_meta (
                `key`         VARCHAR(191) PRIMARY KEY,
                `value`       TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS settings (
                `key`         VARCHAR(191) PRIMARY KEY,
                `value`       TEXT NOT NULL,
                updated_at    VARCHAR(64) NOT NULL DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS categories (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(255) NOT NULL,
                description TEXT NOT NULL DEFAULT (''),
                image_path  VARCHAR(500) NOT NULL DEFAULT '',
                sort_order  INT NOT NULL DEFAULT 0,
                is_active   TINYINT NOT NULL DEFAULT 1,
                created_at  VARCHAR(64) NOT NULL DEFAULT '',
                updated_at  VARCHAR(64) NOT NULL DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS products (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                category_id   INT NULL,
                name          VARCHAR(255) NOT NULL,
                description   TEXT NOT NULL DEFAULT (''),
                price_minor   BIGINT NOT NULL DEFAULT 0,
                image_path    VARCHAR(500) NOT NULL DEFAULT '',
                extra_json    TEXT NOT NULL,
                is_available  TINYINT NOT NULL DEFAULT 1,
                is_featured   TINYINT NOT NULL DEFAULT 0,
                sort_order    INT NOT NULL DEFAULT 0,
                created_at    VARCHAR(64) NOT NULL DEFAULT '',
                updated_at    VARCHAR(64) NOT NULL DEFAULT '',
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS locations (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                code        VARCHAR(64) NOT NULL UNIQUE,
                name        VARCHAR(255) NOT NULL,
                type        VARCHAR(64) NOT NULL DEFAULT '',
                note        TEXT NOT NULL DEFAULT (''),
                is_active   TINYINT NOT NULL DEFAULT 1,
                sort_order  INT NOT NULL DEFAULT 0,
                created_at  VARCHAR(64) NOT NULL DEFAULT '',
                updated_at  VARCHAR(64) NOT NULL DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS orders (
                id                 INT AUTO_INCREMENT PRIMARY KEY,
                public_token       VARCHAR(64) NOT NULL UNIQUE,
                number             INT NOT NULL,
                served_on          VARCHAR(32) NOT NULL,
                order_type         VARCHAR(64) NOT NULL DEFAULT 'dine_in',
                customer_name      VARCHAR(255) NOT NULL DEFAULT '',
                customer_phone     VARCHAR(64) NOT NULL DEFAULT '',
                location_id        INT NULL,
                location_label     VARCHAR(255) NOT NULL DEFAULT '',
                status             VARCHAR(64) NOT NULL DEFAULT 'new',
                fields_json        TEXT NOT NULL DEFAULT ('{}'),
                notes              TEXT NOT NULL DEFAULT (''),
                total_minor        BIGINT NOT NULL DEFAULT 0,
                items_count        INT NOT NULL DEFAULT 0,
                idempotency_key    VARCHAR(128) NULL UNIQUE,
                cancel_reason      TEXT NOT NULL DEFAULT (''),
                created_at         VARCHAR(64) NOT NULL DEFAULT '',
                updated_at         VARCHAR(64) NOT NULL DEFAULT '',
                FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS order_items (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                order_id         INT NOT NULL,
                product_id       INT NULL,
                name             VARCHAR(255) NOT NULL,
                unit_price_minor BIGINT NOT NULL DEFAULT 0,
                quantity         INT NOT NULL DEFAULT 1,
                line_total_minor BIGINT NOT NULL DEFAULT 0,
                extra_json       TEXT NOT NULL DEFAULT ('{}'),
                created_at       VARCHAR(64) NOT NULL DEFAULT '',
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS order_events (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                order_id    INT NOT NULL,
                from_status VARCHAR(64) NOT NULL DEFAULT '',
                to_status   VARCHAR(64) NOT NULL,
                actor       VARCHAR(64) NOT NULL DEFAULT 'system',
                note        TEXT NOT NULL DEFAULT (''),
                created_at  VARCHAR(64) NOT NULL DEFAULT '',
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS daily_counters (
                day   VARCHAR(32) PRIMARY KEY,
                value INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            CREATE TABLE IF NOT EXISTS login_attempts (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                username   VARCHAR(128) NOT NULL,
                ip         VARCHAR(64) NOT NULL,
                success    TINYINT NOT NULL DEFAULT 0,
                created_at VARCHAR(64) NOT NULL DEFAULT ''
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

            SQL;
        } else {
            $sql = <<<'SQL'
            CREATE TABLE IF NOT EXISTS app_meta (
                key         TEXT PRIMARY KEY,
                value       TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS settings (
                key         TEXT PRIMARY KEY,
                value       TEXT NOT NULL DEFAULT (''),
                updated_at  TEXT NOT NULL DEFAULT ('')
            );

            CREATE TABLE IF NOT EXISTS categories (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT (''),
                image_path  TEXT NOT NULL DEFAULT (''),
                sort_order  INTEGER NOT NULL DEFAULT 0,
                is_active   INTEGER NOT NULL DEFAULT 1,
                created_at  TEXT NOT NULL DEFAULT (''),
                updated_at  TEXT NOT NULL DEFAULT ('')
            );

            CREATE TABLE IF NOT EXISTS products (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id   INTEGER NULL REFERENCES categories(id) ON DELETE SET NULL,
                name          TEXT NOT NULL,
                description   TEXT NOT NULL DEFAULT (''),
                price_minor   INTEGER NOT NULL DEFAULT 0,
                image_path    TEXT NOT NULL DEFAULT (''),
                extra_json    TEXT NOT NULL DEFAULT ('{}'),
                is_available  INTEGER NOT NULL DEFAULT 1,
                is_featured   INTEGER NOT NULL DEFAULT 0,
                sort_order    INTEGER NOT NULL DEFAULT 0,
                created_at    TEXT NOT NULL DEFAULT (''),
                updated_at    TEXT NOT NULL DEFAULT ('')
            );

            CREATE TABLE IF NOT EXISTS locations (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                code        TEXT NOT NULL UNIQUE,
                name        TEXT NOT NULL,
                type        TEXT NOT NULL DEFAULT (''),
                note        TEXT NOT NULL DEFAULT (''),
                is_active   INTEGER NOT NULL DEFAULT 1,
                sort_order  INTEGER NOT NULL DEFAULT 0,
                created_at  TEXT NOT NULL DEFAULT (''),
                updated_at  TEXT NOT NULL DEFAULT ('')
            );

            CREATE TABLE IF NOT EXISTS orders (
                id                 INTEGER PRIMARY KEY AUTOINCREMENT,
                public_token       TEXT NOT NULL UNIQUE,
                number             INTEGER NOT NULL,
                served_on          TEXT NOT NULL,
                order_type         TEXT NOT NULL DEFAULT 'dine_in',
                customer_name      TEXT NOT NULL DEFAULT (''),
                customer_phone     TEXT NOT NULL DEFAULT (''),
                location_id        INTEGER NULL REFERENCES locations(id) ON DELETE SET NULL,
                location_label     TEXT NOT NULL DEFAULT (''),
                status             TEXT NOT NULL DEFAULT 'new',
                fields_json        TEXT NOT NULL DEFAULT ('{}'),
                notes              TEXT NOT NULL DEFAULT (''),
                total_minor        INTEGER NOT NULL DEFAULT 0,
                items_count        INTEGER NOT NULL DEFAULT 0,
                idempotency_key    TEXT NULL UNIQUE,
                cancel_reason      TEXT NOT NULL DEFAULT (''),
                created_at         TEXT NOT NULL DEFAULT (''),
                updated_at         TEXT NOT NULL DEFAULT ('')
            );

            CREATE TABLE IF NOT EXISTS order_items (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id         INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
                product_id       INTEGER NULL REFERENCES products(id) ON DELETE SET NULL,
                name             TEXT NOT NULL,
                unit_price_minor INTEGER NOT NULL DEFAULT 0,
                quantity         INTEGER NOT NULL DEFAULT 1,
                line_total_minor INTEGER NOT NULL DEFAULT 0,
                extra_json       TEXT NOT NULL DEFAULT ('{}'),
                created_at       TEXT NOT NULL DEFAULT ('')
            );

            CREATE TABLE IF NOT EXISTS order_events (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id    INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
                from_status TEXT NOT NULL DEFAULT (''),
                to_status   TEXT NOT NULL,
                actor       TEXT NOT NULL DEFAULT 'system',
                note        TEXT NOT NULL DEFAULT (''),
                created_at  TEXT NOT NULL DEFAULT ('')
            );

            CREATE TABLE IF NOT EXISTS daily_counters (
                day   TEXT PRIMARY KEY,
                value INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS login_attempts (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                username   TEXT NOT NULL,
                ip         TEXT NOT NULL,
                success    INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT ('')
            );

            CREATE INDEX IF NOT EXISTS idx_products_category  ON products(category_id, sort_order);
            CREATE INDEX IF NOT EXISTS idx_products_available ON products(is_available);
            CREATE INDEX IF NOT EXISTS idx_orders_status      ON orders(status, created_at DESC);
            CREATE INDEX IF NOT EXISTS idx_orders_location    ON orders(location_id, status);
            CREATE INDEX IF NOT EXISTS idx_orders_token       ON orders(public_token);
            CREATE INDEX IF NOT EXISTS idx_orders_served_on   ON orders(served_on, number);
            CREATE INDEX IF NOT EXISTS idx_orders_created     ON orders(created_at DESC);
            CREATE INDEX IF NOT EXISTS idx_items_order        ON order_items(order_id);
            CREATE INDEX IF NOT EXISTS idx_events_order       ON order_events(order_id, id);
            CREATE INDEX IF NOT EXISTS idx_login_ip           ON login_attempts(ip, created_at DESC);
            SQL;
        }

        Database::pdo()->exec($sql);
        if (Database::driver() === 'mysql') {
            $indexes = [
                'CREATE INDEX idx_products_category  ON products(category_id, sort_order)',
                'CREATE INDEX idx_products_available ON products(is_available)',
                'CREATE INDEX idx_orders_status      ON orders(status, created_at)',
                'CREATE INDEX idx_orders_location    ON orders(location_id, status)',
                'CREATE INDEX idx_orders_token       ON orders(public_token)',
                'CREATE INDEX idx_orders_served_on   ON orders(served_on, `number`)',
                'CREATE INDEX idx_orders_created     ON orders(created_at)',
                'CREATE INDEX idx_items_order        ON order_items(order_id)',
                'CREATE INDEX idx_events_order       ON order_events(order_id, id)',
                'CREATE INDEX idx_login_ip           ON login_attempts(ip, created_at)',
            ];
            foreach ($indexes as $idxSql) {
                try {
                    Database::pdo()->exec($idxSql);
                } catch (\Throwable) {
                    // ignore duplicate index
                }
            }
        }

        $version = '1';
        if (Database::driver() === 'mysql') {
            Database::run(
                "INSERT INTO app_meta (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                ['schema_version', $version]
            );
        } else {
            Database::run(
                'INSERT INTO app_meta (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value',
                ['schema_version', $version]
            );
        }

        self::ensureColumns();
        self::validateFieldDefinitions();
        self::backfillExtraFields();
        self::seedSettings();
    }

    private static function ensureColumns(): void
    {
        $required = [
            'categories' => [
                'description' => "TEXT NOT NULL DEFAULT ('')",
                'image_path'  => "TEXT NOT NULL DEFAULT ('')",
            ],
            'products' => [
                'is_featured' => 'INTEGER NOT NULL DEFAULT 0',
                'sort_order'  => 'INTEGER NOT NULL DEFAULT 0',
                'extra_json'  => "TEXT NOT NULL DEFAULT ('{}')",
            ],
            'locations' => [
                'type'      => "TEXT NOT NULL DEFAULT ('')",
                'note'      => "TEXT NOT NULL DEFAULT ('')",
                'sort_order' => 'INTEGER NOT NULL DEFAULT 0',
            ],
            'orders' => [
                'customer_phone' => "TEXT NOT NULL DEFAULT ('')",
                'fields_json'    => "TEXT NOT NULL DEFAULT ('{}')",
                'cancel_reason'  => "TEXT NOT NULL DEFAULT ('')",
                'idempotency_key' => 'TEXT NULL',
                'items_count'    => 'INTEGER NOT NULL DEFAULT 0',
            ],
            'order_items' => [
                'extra_json' => "TEXT NOT NULL DEFAULT ('{}')",
                'created_at' => "TEXT NOT NULL DEFAULT ('')",
            ],
            'order_events' => [
                'note' => "TEXT NOT NULL DEFAULT ('')",
            ],
        ];

        foreach ($required as $table => $columns) {
            $existing = [];
            if (Database::driver() === 'mysql') {
                try {
                    foreach (Database::all('SHOW COLUMNS FROM ' . $table) as $column) {
                        $existing[$column['Field']] = true;
                    }
                } catch (\Throwable) {
                    $existing = [];
                }
            } else {
                foreach (Database::all('PRAGMA table_info(' . $table . ')') as $column) {
                    $existing[$column['name']] = true;
                }
            }
            if ($existing === []) {
                continue;
            }
            foreach ($columns as $name => $definition) {
                if (!isset($existing[$name])) {
                    Database::pdo()->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $definition);
                }
            }
        }
    }

    private static function validateFieldDefinitions(): void
    {
        $contract = \App\Config\TemplateContract::class;

        try {
            $contract::assertFieldTypes(\App\Config\ProjectConfig::productExtraFields(), 'PRODUCT_EXTRA_FIELDS');
            $contract::assertFieldTypes(\App\Config\ProjectConfig::productDetailFields(), 'PRODUCT_DETAIL_FIELDS');

            $where = 'ORDER_TYPES';
            foreach (\App\Config\ProjectConfig::orderTypes() as $key => $def) {
                $contract::assertFieldTypes((array) ($def['fields'] ?? []), $where . '[' . $key . ']');
            }
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException(
                'Field definition error: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private static function backfillExtraFields(): void
    {
        $fields = \App\Config\ProjectConfig::productExtraFields();
        if ($fields === []) {
            return;
        }

        $rows = Database::all('SELECT id, extra_json FROM products');
        $updated = 0;

        foreach ($rows as $row) {
            $extra = json_decode((string) $row['extra_json'], true);
            if (!is_array($extra)) {
                $extra = [];
            }

            $missing = false;
            foreach ($fields as $field) {
                $key = (string) ($field['key'] ?? '');
                if ($key === '' || array_key_exists($key, $extra)) {
                    continue;
                }
                $extra[$key] = \App\Config\TemplateContract::fieldDefault($field);
                $missing      = true;
            }

            if (!$missing) {
                continue;
            }

            $extra = array_filter($extra, static fn(mixed $v): bool => $v !== '' && $v !== null);

            Database::update(
                'products',
                ['extra_json' => json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'],
                'id = :id',
                ['id' => (int) $row['id']]
            );
            $updated++;
        }

        if ($updated > 0) {
            Logger::info('Backfilled extra fields for ' . $updated . ' product(s)');
        }
    }

    public static function seedSettings(): void
    {
        foreach (\App\Config\ProjectConfig::defaultRuntimeSettings() as $key => $value) {
            if (Database::driver() === 'mysql') {
                Database::run(
                    'INSERT INTO settings (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value` = `value`',
                    ['k' => $key, 'v' => (string) $value]
                );
            } else {
                Database::run(
                    'INSERT INTO settings (key, value) VALUES (:k, :v) ON CONFLICT(key) DO NOTHING',
                    ['k' => $key, 'v' => (string) $value]
                );
            }
        }
    }

    public static function reset(): void
    {
        $preset = '';
        $blueprint = '';
        try {
            $preset    = (string) \App\Core\RuntimeSettings::get('business_preset', '');
            $blueprint = (string) \App\Core\RuntimeSettings::get('template_blueprint', '');
        } catch (\Throwable) {
            $preset = '';
            $blueprint = '';
        }

        $tables = ['order_events', 'order_items', 'orders', 'products', 'categories', 'locations', 'settings', 'daily_counters', 'login_attempts'];

        if (Database::driver() === 'mysql') {
            Database::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($tables as $t) {
                Database::pdo()->exec('DROP TABLE IF EXISTS ' . $t);
            }
            Database::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
        } else {
            Database::pdo()->exec('PRAGMA foreign_keys = OFF');
            foreach ($tables as $t) {
                Database::pdo()->exec('DROP TABLE IF EXISTS ' . $t);
            }
            Database::pdo()->exec('PRAGMA foreign_keys = ON');
        }

        self::migrate();

        if ($preset !== '' && (\App\Config\TemplatePatterns::exists($preset) || \App\Config\BusinessPresets::exists($preset))) {
            Database::update('settings', ['value' => $preset], '`key` = :key', ['key' => 'business_preset']);
        }
        if ($blueprint !== '') {
            Database::update('settings', ['value' => $blueprint], '`key` = :key', ['key' => 'template_blueprint']);
        }
    }
}
