<?php

namespace App\Services\Mail;

/**
 * SendGrid Web API Mail Delivery Service
 */
class SendGridMailService implements MailServiceInterface
{
    protected string $apiKey;
    protected string $fromEmail;
    protected string $fromName;

    /**
     * Create a new SendGridMailService instance.
     *
     * @param array $credentials
     * @param array $config
     */
    public function __construct(array $credentials, array $config)
    {
        $this->apiKey = $credentials['SENDGRID_API_KEY'] ?? '';
        $this->fromEmail = $config['app']['from_email'] ?? 'noreply@forux.local';
        $this->fromName = $config['app']['name'] ?? 'Forux Forum';
    }

    /**
     * Send email via SendGrid Web API.
     */
    public function send(string $to, string $subject, string $body, array $headers = []): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        $url = 'https://api.sendgrid.com/v3/mail/send';

        $payload = [
            'personalizations' => [
                [
                    'to' => [
                        ['email' => $to]
                    ]
                ]
            ],
            'from' => [
                'email' => $this->fromEmail,
                'name' => $this->fromName
            ],
            'subject' => $subject,
            'content' => [
                [
                    'type' => 'text/html',
                    'value' => $body
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status >= 200 && $status < 300;
    }
}
