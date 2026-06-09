<?php

namespace App\Services\Moderation;

use Core\Settings;

/**
 * Fallback local spam filter matching content against site-configured blocklist.
 */
class LocalSpamFilter implements SpamFilterInterface
{
    protected Settings $settings;

    /**
     * Create a new LocalSpamFilter instance.
     *
     * @param Settings $settings Automatically injected Settings service
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Determine if content is spam based on word blocklist or regex filters.
     */
    public function isSpam(string $content): bool
    {
        $blocklist = $this->settings->get('spam_blocklist', '');
        if (empty(trim($blocklist))) {
            return false;
        }

        // Split by commas or newlines
        $rules = array_filter(array_map('trim', preg_split('/[,\r\n]+/', $blocklist)));

        foreach ($rules as $rule) {
            if (empty($rule)) {
                continue;
            }

            // Treat rule as regex if it begins and ends with '/'
            if (str_starts_with($rule, '/') && str_ends_with($rule, '/')) {
                try {
                    if (@preg_match($rule, $content)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    // Silently ignore malformed regex
                }
            } else {
                // Perform simple case-insensitive word matching
                if (stripos($content, $rule) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
