<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Internal cache with automatic TTL.
 *
 * Prefers APCu when available, falls back to filesystem under storage/cache.
 * Disabled in debug mode so tests and development always read fresh data.
 */
final class Cache
{
    private const DIR = 'storage/cache';

    public static function enabled(): bool
    {
        return !(bool) app_config('debug', false);
    }

    private static function dir(): string
    {
        return BASE_PATH . '/' . self::DIR;
    }

    private static function ready(): bool
    {
        if (!self::enabled()) {
            return false;
        }
        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        return true;
    }

    private static function useApcu(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled()
            && function_exists('apcu_fetch') && function_exists('apcu_store');
    }

    private static function normalizeKey(string $key): string
    {
        $key = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $key) ?? $key;
        return $key === '' ? 'default' : substr($key, 0, 120);
    }

    public static function get(string $key): mixed
    {
        if (!self::ready()) {
            return null;
        }
        if (self::useApcu()) {
            $value = apcu_fetch($key, $success);
            return $success ? $value : null;
        }
        $file = self::dir() . '/' . self::normalizeKey($key) . '.cache';
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = @unserialize($raw);
        if (!is_array($data) || !array_key_exists('expires', $data) || !array_key_exists('value', $data)) {
            return null;
        }
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        return $data['value'];
    }

    public static function set(string $key, mixed $value, int $ttl = 60): void
    {
        if (!self::ready()) {
            return;
        }
        $ttl = max(0, $ttl);
        $expires = $ttl > 0 ? time() + $ttl : 0;
        if (self::useApcu()) {
            apcu_store($key, $value, $ttl);
            return;
        }
        $file = self::dir() . '/' . self::normalizeKey($key) . '.cache';
        $payload = serialize(['expires' => $expires, 'value' => $value]);
        @file_put_contents($file, $payload, LOCK_EX);
    }

    public static function delete(string $key): void
    {
        if (!self::ready()) {
            return;
        }
        if (self::useApcu()) {
            apcu_delete($key);
            return;
        }
        $file = self::dir() . '/' . self::normalizeKey($key) . '.cache';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    /** Remove all cache files / entries. */
    public static function flush(): void
    {
        if (self::useApcu()) {
            apcu_clear_cache();
        }
        $dir = self::dir();
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }
}
