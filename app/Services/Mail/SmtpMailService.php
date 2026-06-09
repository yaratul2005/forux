<?php

namespace App\Services\Mail;

use Exception;

/**
 * Socket-based SMTP Mail Delivery Service (zero external dependencies)
 */
class SmtpMailService implements MailServiceInterface
{
    protected string $host;
    protected int $port;
    protected string $username;
    protected string $password;
    protected string $encryption;
    protected string $fromEmail;
    protected string $fromName;
    protected int $timeout = 10;

    /**
     * Create a new SmtpMailService instance.
     *
     * @param array $credentials SMTP server credentials
     * @param array $config Global settings
     */
    public function __construct(array $credentials, array $config)
    {
        $this->host = $credentials['SMTP_HOST'] ?? '127.0.0.1';
        $this->port = (int)($credentials['SMTP_PORT'] ?? 587);
        $this->username = $credentials['SMTP_USER'] ?? '';
        $this->password = $credentials['SMTP_PASS'] ?? '';
        $this->encryption = strtolower($credentials['SMTP_SECURE'] ?? 'tls'); // 'tls', 'ssl', or 'none'
        
        $this->fromEmail = $config['app']['from_email'] ?? 'noreply@forux.local';
        $this->fromName = $config['app']['name'] ?? 'Forux Forum';
    }

    /**
     * Send email via SMTP protocol sockets.
     */
    public function send(string $to, string $subject, string $body, array $headers = []): bool
    {
        $remote = $this->host . ':' . $this->port;
        if ($this->encryption === 'ssl') {
            $remote = 'ssl://' . $remote;
        }

        // Open TCP stream socket connection
        $socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout);
        if (!$socket) {
            return false;
        }

        try {
            $this->readResponse($socket, 220);

            // Send greeting
            $this->sendCommand($socket, "EHLO localhost", 250);

            // Support STARTTLS upgrade if TLS is requested
            if ($this->encryption === 'tls') {
                $this->sendCommand($socket, "STARTTLS", 220);
                
                // Enable TLS crypto context
                $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                // PHP 7.2+ supports STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                
                if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
                    throw new Exception("STARTTLS negotiation failed.");
                }

                // Send EHLO again after upgrading TLS
                $this->sendCommand($socket, "EHLO localhost", 250);
            }

            // Authenticate (Auth Login)
            if (!empty($this->username) && !empty($this->password)) {
                $this->sendCommand($socket, "AUTH LOGIN", 334);
                $this->sendCommand($socket, base64_encode($this->username), 334);
                $this->sendCommand($socket, base64_encode($this->password), 235);
            }

            // Set envelopes
            $this->sendCommand($socket, "MAIL FROM:<{$this->fromEmail}>", 250);
            $this->sendCommand($socket, "RCPT TO:<{$to}>", 250);

            // Start DATA block
            $this->sendCommand($socket, "DATA", 354);

            // Build headers
            $headers['From'] = $headers['From'] ?? "\"{$this->fromName}\" <{$this->fromEmail}>";
            $headers['To'] = $headers['To'] ?? $to;
            $headers['Subject'] = $headers['Subject'] ?? $subject;
            $headers['MIME-Version'] = $headers['MIME-Version'] ?? '1.0';
            $headers['Content-Type'] = $headers['Content-Type'] ?? 'text/html; charset=utf-8';
            $headers['Date'] = date('r');

            $data = '';
            foreach ($headers as $key => $val) {
                $data .= "{$key}: {$val}\r\n";
            }
            $data .= "\r\n" . $body . "\r\n.\r\n";

            // Send data payload and close connection
            $this->sendCommand($socket, $data, 250);
            $this->sendCommand($socket, "QUIT", 221);
            
            fclose($socket);
            return true;

        } catch (\Throwable $e) {
            @fclose($socket);
            return false;
        }
    }

    /**
     * Read socket buffer and match expected HTTP/SMTP status code.
     */
    protected function readResponse($socket, int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            // SMTP status line ends when 4th character is space (e.g. "250-", "250 ")
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new Exception("SMTP protocol error: Expected {$expectedCode}, received {$code}. Details: " . trim($response));
        }

        return $response;
    }

    /**
     * Write command and read response.
     */
    protected function sendCommand($socket, string $command, int $expectedCode): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->readResponse($socket, $expectedCode);
    }
}
