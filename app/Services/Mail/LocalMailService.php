<?php

namespace App\Services\Mail;

/**
 * Fallback local mail service writing to disk and using native mail()
 */
class LocalMailService implements MailServiceInterface
{
    protected string $queueDir;

    /**
     * Create a new LocalMailService instance.
     */
    public function __construct()
    {
        $this->queueDir = ROOT_PATH . '/storage/mail_queue';
    }

    /**
     * Send email by saving to file and executing php mail().
     */
    public function send(string $to, string $subject, string $body, array $headers = []): bool
    {
        // 1. Create mail queue directory if not exists
        if (!is_dir($this->queueDir)) {
            mkdir($this->queueDir, 0755, true);
        }

        // 2. Prepare headers
        $headerStr = '';
        if (!isset($headers['MIME-Version'])) {
            $headers['MIME-Version'] = '1.0';
        }
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'text/html; charset=utf-8';
        }
        if (!isset($headers['From'])) {
            $headers['From'] = 'noreply@forux.local';
        }

        foreach ($headers as $key => $val) {
            $headerStr .= "{$key}: {$val}\r\n";
        }

        // 3. Construct EML file payload
        $eml = "To: {$to}\r\n";
        $eml .= "Subject: {$subject}\r\n";
        $eml .= $headerStr;
        $eml .= "\r\n";
        $eml .= $body;

        // Save file
        $fileName = $this->queueDir . '/' . time() . '_' . bin2hex(random_bytes(4)) . '.eml';
        file_put_contents($fileName, $eml);

        // 4. Fallback to PHP native mail()
        try {
            // Strip Bcc or From from native header string if handled separately
            $nativeHeaders = trim($headerStr);
            return @mail($to, $subject, $body, $nativeHeaders);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
