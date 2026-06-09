<?php

namespace App\Services\Mail;

/**
 * Interface for mail delivery drivers
 */
interface MailServiceInterface
{
    /**
     * Send an email.
     *
     * @param string $to Recipient email address
     * @param string $subject Email subject line
     * @param string $body Email content (plain text or HTML)
     * @param array $headers Additional headers
     * @return bool True on success, false on failure
     */
    public function send(string $to, string $subject, string $body, array $headers = []): bool;
}
