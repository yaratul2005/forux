<?php

namespace Core;

/**
 * Site Settings Caching and Management Service
 */
class Settings
{
    protected Container $container;
    protected array $settings = [];
    protected string $cacheFile;

    /**
     * Create a new Settings instance.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->cacheFile = ROOT_PATH . '/storage/cache/settings.php';
        $this->load();
    }

    /**
     * Load settings from file cache or database.
     */
    public function load(): void
    {
        if (file_exists($this->cacheFile)) {
            try {
                $this->settings = require $this->cacheFile;
                if (!is_array($this->settings)) {
                    $this->settings = [];
                    $this->refreshCache();
                }
            } catch (\Throwable $e) {
                $this->settings = [];
                $this->refreshCache();
            }
        } else {
            $this->refreshCache();
        }
    }

    /**
     * Query settings from database and write them to the cache file.
     */
    public function refreshCache(): void
    {
        if (!$this->container->has(\PDO::class)) {
            return;
        }

        try {
            $pdo = $this->container->get(\PDO::class);
            
            // Check if settings table exists
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'settings'");
            if ($tableCheck->rowCount() === 0) {
                return;
            }

            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            $this->settings = $settings;

            // Ensure cache directory exists
            $dir = dirname($this->cacheFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Write to cache file safely as standard PHP array export
            $content = "<?php\n\n// Generated at " . date('Y-m-d H:i:s') . "\nreturn " . var_export($settings, true) . ";\n";
            file_put_contents($this->cacheFile, $content);
        } catch (\Throwable $e) {
            // Fail silently at runtime if database is inaccessible
        }
    }

    /**
     * Get a setting value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set a setting value, persisting it to DB and regenerating cache.
     *
     * @param string $key
     * @param mixed $value
     * @return bool True on success, false on failure
     */
    public function set(string $key, $value): bool
    {
        $valueStr = is_array($value) || is_object($value) ? json_encode($value) : (string)$value;

        if (!$this->container->has(\PDO::class)) {
            // If DB is not available, just set in-memory
            $this->settings[$key] = $valueStr;
            return false;
        }

        try {
            $pdo = $this->container->get(\PDO::class);
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([$key, $valueStr, $valueStr]);

            $this->settings[$key] = $valueStr;
            $this->refreshCache();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Clear the cache file.
     */
    public function clearCache(): void
    {
        if (file_exists($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
        $this->settings = [];
    }
}
