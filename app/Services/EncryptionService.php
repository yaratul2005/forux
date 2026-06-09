<?php

namespace App\Services;

use Exception;

/**
 * Service for Cryptographic Encryption and Decryption using OpenSSL
 */
class EncryptionService
{
    protected string $key;
    protected string $cipher = 'aes-256-cbc';

    /**
     * Create a new EncryptionService instance.
     *
     * @param array $config The application configuration
     * @throws Exception
     */
    public function __construct(array $config)
    {
        $key = $config['security']['encryption_key'] ?? '';
        if (strlen($key) < 16) {
            throw new Exception("Encryption key is too weak or not set in configuration.");
        }
        // Pad or truncate key to 32 bytes for AES-256
        $this->key = hash('sha256', $key, true);
    }

    /**
     * Encrypt a plaintext string.
     *
     * @param string $value
     * @return string Base64 encoded payload including random IV
     * @throws Exception
     */
    public function encrypt(string $value): string
    {
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = random_bytes($ivLength);
        
        $encrypted = openssl_encrypt($value, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new Exception("Encryption failed.");
        }

        // Package IV with the encrypted value and base64 encode
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt an encrypted payload.
     *
     * @param string $payload Base64 encoded payload package
     * @return string Plaintext value
     * @throws Exception
     */
    public function decrypt(string $payload): string
    {
        $data = base64_decode($payload);
        $ivLength = openssl_cipher_iv_length($this->cipher);
        
        if (strlen($data) < $ivLength) {
            throw new Exception("Invalid encrypted payload size.");
        }

        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new Exception("Decryption failed. Invalid key or corrupted payload.");
        }

        return $decrypted;
    }
}
