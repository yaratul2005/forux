<?php

namespace App\Services\Storage;

/**
 * Interface for file storage drivers
 */
interface StorageServiceInterface
{
    /**
     * Store raw content in a file.
     *
     * @param string $path Target path inside storage
     * @param string $content Raw content to write
     * @return bool True on success, false on failure
     */
    public function put(string $path, string $content): bool;

    /**
     * Store a file from a local path.
     *
     * @param string $path Target path inside storage
     * @param string $localFilePath Absolute path to local file
     * @return bool True on success, false on failure
     */
    public function putFile(string $path, string $localFilePath): bool;

    /**
     * Retrieve file content.
     *
     * @param string $path Target path inside storage
     * @return string|null File content, or null if not found
     */
    public function get(string $path): ?string;

    /**
     * Delete a file.
     *
     * @param string $path Target path inside storage
     * @return bool True on success, false on failure
     */
    public function delete(string $path): bool;

    /**
     * Get the public URL of a file.
     *
     * @param string $path Target path inside storage
     * @return string Public URL
     */
    public function url(string $path): string;
}
