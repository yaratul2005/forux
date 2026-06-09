<?php

/**
 * Global Helper Functions
 */

if (!function_exists('__')) {
    function __(string $key, array $replacements = []): string
    {
        return \Core\Language::translate($key, $replacements);
    }
}
