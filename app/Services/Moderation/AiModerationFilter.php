<?php

namespace App\Services\Moderation;

/**
 * AI-assisted moderation and spam filter calling Gemini or OpenAI safety APIs.
 */
class AiModerationFilter implements SpamFilterInterface
{
    protected array $credentials;

    /**
     * Create a new AiModerationFilter instance.
     *
     * @param array $credentials Decrypted credentials from credentials vault
     */
    public function __construct(array $credentials)
    {
        $this->credentials = $credentials;
    }

    /**
     * Analyze content using remote AI moderation APIs.
     */
    public function isSpam(string $content): bool
    {
        $geminiKey = $this->credentials['GEMINI_API_KEY'] ?? '';
        $openaiKey = $this->credentials['OPENAI_API_KEY'] ?? '';

        if (!empty($geminiKey)) {
            return $this->callGemini($content, $geminiKey);
        }

        if (!empty($openaiKey)) {
            return $this->callOpenAi($content, $openaiKey);
        }

        // Default fallback if no keys configured
        return false;
    }

    /**
     * Moderation via Google Gemini API
     */
    protected function callGemini(string $content, string $apiKey): bool
    {
        // Using gemini-1.5-flash as the fast, lightweight model suitable for classification tasks
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . urlencode($apiKey);

        $prompt = "Analyze the following user-submitted forum post content. Determine if it is spam, advertising, link-stuffing, malicious, or abusive. Answer with a single word: either 'SPAM' or 'CLEAN'.\n\nContent:\n" . $content;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.0,
                'maxOutputTokens' => 5
            ]
        ];

        $response = $this->postJson($url, $payload);
        if ($response['code'] !== 200) {
            return false; // Fail open to avoid blocking user posts if API is down
        }

        $data = json_decode($response['body'], true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        return stripos(trim($text), 'SPAM') !== false;
    }

    /**
     * Moderation via OpenAI Moderation API
     */
    protected function callOpenAi(string $content, string $apiKey): bool
    {
        $url = "https://api.openai.com/v1/moderations";
        $payload = [
            'input' => $content
        ];

        $headers = [
            "Authorization: Bearer {$apiKey}"
        ];

        $response = $this->postJson($url, $payload, $headers);
        if ($response['code'] !== 200) {
            return false; // Fail open
        }

        $data = json_decode($response['body'], true);
        return (bool)($data['results'][0]['flagged'] ?? false);
    }

    /**
     * Make cURL POST request with JSON payload.
     */
    protected function postJson(string $url, array $payload, array $headers = []): array
    {
        $ch = curl_init();
        $headers[] = 'Content-Type: application/json';

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8); // Moderate timeout

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['code' => 0, 'error' => $error];
        }

        curl_close($ch);
        return [
            'code' => $httpCode,
            'body' => $response
        ];
    }
}
