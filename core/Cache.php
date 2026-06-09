<?php

namespace Core;

/**
 * Lightweight Flat-File Partial Caching Subsystem
 */
class Cache
{
    protected static string $cacheDir = ROOT_PATH . '/storage/cache/partials';

    /**
     * Resolve unique absolute path for a cache key.
     *
     * @param string $key
     * @return string
     */
    protected static function getPath(string $key): string
    {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        return self::$cacheDir . '/cache_' . md5($key) . '.cache';
    }

    /**
     * Get value from cache. Returns null if expired or not found.
     *
     * @param string $key
     * @param int $ttl Default time-to-live in seconds (3600 = 1 hour)
     * @return string|null
     */
    public static function get(string $key, int $ttl = 3600): ?string
    {
        $path = self::getPath($key);
        if (!file_exists($path)) {
            return null;
        }

        try {
            $raw = file_get_contents($path);
            if ($raw === false) {
                return null;
            }

            $data = unserialize($raw);
            if (!is_array($data)) {
                return null;
            }

            // Check expiration
            if (time() > $data['expires_at']) {
                self::delete($key);
                return null;
            }

            return $data['content'];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Write value to cache.
     *
     * @param string $key
     * @param string $content
     * @param int $ttl Time-to-live in seconds
     * @return bool
     */
    public static function set(string $key, string $content, int $ttl = 3600): bool
    {
        $path = self::getPath($key);
        $data = serialize([
            'expires_at' => time() + $ttl,
            'content' => $content
        ]);
        return file_put_contents($path, $data) !== false;
    }

    /**
     * Delete a cache key.
     *
     * @param string $key
     * @return bool
     */
    public static function delete(string $key): bool
    {
        $path = self::getPath($key);
        if (file_exists($path)) {
            return @unlink($path);
        }
        return false;
    }

    /**
     * Retrieve cache value, or execute callback, store result, and return it.
     *
     * @param string $key
     * @param int $ttl Time-to-live in seconds
     * @param callable $callback
     * @return string
     */
    public static function remember(string $key, int $ttl, callable $callback): string
    {
        $value = self::get($key, $ttl);
        if ($value !== null) {
            return $value;
        }

        $value = (string)$callback();
        self::set($key, $value, $ttl);
        return $value;
    }
}
