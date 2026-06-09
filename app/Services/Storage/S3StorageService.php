<?php

namespace App\Services\Storage;

use Exception;

/**
 * S3 Storage driver supporting AWS Signature Version 4.
 * Compatible with AWS S3, Cloudflare R2, DreamObjects, and other S3-compatible services.
 */
class S3StorageService implements StorageServiceInterface
{
    protected string $key;
    protected string $secret;
    protected string $region;
    protected string $bucket;
    protected string $endpoint;
    protected string $baseUrl;

    /**
     * Create a new S3StorageService instance.
     *
     * @param array $config The credential configurations (already decrypted)
     */
    public function __construct(array $config)
    {
        $this->key = $config['S3_KEY'] ?? '';
        $this->secret = $config['S3_SECRET'] ?? '';
        $this->region = $config['S3_REGION'] ?? 'us-east-1';
        $this->bucket = $config['S3_BUCKET'] ?? '';
        $this->endpoint = $config['S3_ENDPOINT'] ?? '';

        // If no endpoint is specified, default to AWS S3 virtual-host style endpoint
        if (empty($this->endpoint)) {
            $this->endpoint = "https://{$this->bucket}.s3.{$this->region}.amazonaws.com";
        }

        // Base URL for generating public URLs
        $this->baseUrl = rtrim($this->endpoint, '/');
    }

    /**
     * Store raw content in a file.
     */
    public function put(string $path, string $content): bool
    {
        return $this->upload($path, $content);
    }

    /**
     * Store a file from a local path.
     */
    public function putFile(string $path, string $localFilePath): bool
    {
        if (!file_exists($localFilePath)) {
            return false;
        }
        $content = file_get_contents($localFilePath);
        if ($content === false) {
            return false;
        }
        return $this->upload($path, $content);
    }

    /**
     * Retrieve file content.
     */
    public function get(string $path): ?string
    {
        $response = $this->request('GET', $path);
        if ($response['code'] === 200) {
            return $response['body'];
        }
        return null;
    }

    /**
     * Delete a file.
     */
    public function delete(string $path): bool
    {
        $response = $this->request('DELETE', $path);
        return $response['code'] === 204 || $response['code'] === 200;
    }

    /**
     * Get the public URL of a file.
     */
    public function url(string $path): string
    {
        // For S3, standard public URL is just the endpoint/path (if virtual-host or bucket included)
        // If path-style URL is used, we need to construct it appropriately.
        // Let's check if the endpoint has the bucket name in it or if we should append the bucket name.
        $path = ltrim($path, '/');
        $endpointHost = parse_url($this->endpoint, PHP_URL_HOST);
        
        if (str_contains($endpointHost, $this->bucket)) {
            // Virtual-host style: https://bucket.s3.region.amazonaws.com/path
            return $this->baseUrl . '/' . $path;
        } else {
            // Path style: https://endpoint/bucket/path
            return $this->baseUrl . '/' . $this->bucket . '/' . $path;
        }
    }

    /**
     * Upload helper.
     */
    protected function upload(string $path, string $content): bool
    {
        $headers = [];
        // Detect and set Content-Type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($content);
        if ($mimeType) {
            $headers['Content-Type'] = $mimeType;
        }

        $response = $this->request('PUT', $path, $content, $headers);
        return $response['code'] === 200;
    }

    /**
     * Perform signed cURL request to S3.
     */
    protected function request(string $method, string $path, string $content = '', array $headers = []): array
    {
        $path = ltrim($path, '/');
        $endpointUrl = $this->endpoint;
        $endpointHost = parse_url($endpointUrl, PHP_URL_HOST);
        
        // Determine request URI and URL based on virtual-host vs path style
        if (str_contains($endpointHost, $this->bucket)) {
            // Virtual host style
            $requestUri = '/' . $path;
            $requestUrl = $this->baseUrl . '/' . $path;
        } else {
            // Path style
            $requestUri = '/' . $this->bucket . '/' . $path;
            $requestUrl = $this->baseUrl . '/' . $this->bucket . '/' . $path;
        }

        $service = 's3';
        $region = $this->region;
        $amzDate = gmdate('Ymd\THis\Z');
        $date = substr($amzDate, 0, 8);
        $contentSha256 = hash('sha256', $content);

        // Core AWS headers
        $headers['Host'] = $endpointHost;
        $headers['x-amz-date'] = $amzDate;
        $headers['x-amz-content-sha256'] = $contentSha256;

        // Canonicalize headers
        $canonicalHeaders = [];
        foreach ($headers as $key => $value) {
            $canonicalHeaders[strtolower($key)] = trim($value);
        }
        ksort($canonicalHeaders);

        $canonicalHeaderStr = '';
        foreach ($canonicalHeaders as $key => $value) {
            $canonicalHeaderStr .= $key . ':' . $value . "\n";
        }

        $signedHeaders = implode(';', array_keys($canonicalHeaders));
        
        // Canonical Query String (empty for standard object operations)
        $canonicalQueryString = '';

        // Canonical Request
        $canonicalRequest = implode("\n", [
            $method,
            $requestUri,
            $canonicalQueryString,
            $canonicalHeaderStr,
            $signedHeaders,
            $contentSha256
        ]);

        // String to Sign
        $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest)
        ]);

        // Signature
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->secret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        // Authorization Header
        $headers['Authorization'] = "AWS4-HMAC-SHA256 Credential={$this->key}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        // Make curl request
        $ch = curl_init();
        
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "{$key}: {$value}";
        }

        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['code' => 0, 'error' => $error];
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $body = substr($response, $headerSize);

        return [
            'code' => $httpCode,
            'body' => $body
        ];
    }
}
