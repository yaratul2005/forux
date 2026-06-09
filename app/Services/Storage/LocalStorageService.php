<?php

namespace App\Services\Storage;

/**
 * Local file storage driver.
 * Stores files in the storage/uploads/ directory (above web root)
 * and generates URLs targeting public/upload.php.
 */
class LocalStorageService implements StorageServiceInterface
{
    protected string $uploadDir;
    protected string $baseUrl;

    /**
     * Create a new LocalStorageService instance.
     *
     * @param array $config The application configuration
     */
    public function __construct(array $config)
    {
        $this->uploadDir = ROOT_PATH . '/storage/uploads';
        
        $url = $config['app']['url'] ?? 'http://localhost';
        // Strip trailing slash and index.php if present
        $url = rtrim($url, '/');
        if (str_ends_with(strtolower($url), '/index.php')) {
            $url = substr($url, 0, -10);
        }
        $this->baseUrl = rtrim($url, '/');
    }

    /**
     * Store raw content in a file.
     */
    public function put(string $path, string $content): bool
    {
        $fullPath = $this->getFullPath($path);
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($fullPath, $content) !== false;
    }

    /**
     * Store a file from a local path.
     */
    public function putFile(string $path, string $localFilePath): bool
    {
        if (!file_exists($localFilePath)) {
            return false;
        }

        $fullPath = $this->getFullPath($path);
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return copy($localFilePath, $fullPath);
    }

    /**
     * Retrieve file content.
     */
    public function get(string $path): ?string
    {
        $fullPath = $this->getFullPath($path);
        if (!file_exists($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);
        return $content === false ? null : $content;
    }

    /**
     * Delete a file.
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->getFullPath($path);
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    /**
     * Get the public URL of a file.
     */
    public function url(string $path): string
    {
        // Path needs to be safe for URL parameter
        return $this->baseUrl . '/upload.php?path=' . urlencode(ltrim($path, '/'));
    }

    /**
     * Resolve full file path and prevent directory traversal.
     */
    protected function getFullPath(string $path): string
    {
        $path = ltrim($path, '/\\');
        
        // Remove traversal elements
        $path = str_replace(['..', './', '.\\'], '', $path);
        $path = preg_replace('#[\\/]+#', DIRECTORY_SEPARATOR, $path);

        return $this->uploadDir . DIRECTORY_SEPARATOR . $path;
    }
}
