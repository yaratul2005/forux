<?php

namespace App\Services\Moderation;

/**
 * Interface for spam detection and moderation filters
 */
interface SpamFilterInterface
{
    /**
     * Determine if the given content is spam or violates safety policies.
     *
     * @param string $content The text content to analyze
     * @return bool True if content is spam/unsafe, false if clean
     */
    public function isSpam(string $content): bool;
}
