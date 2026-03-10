<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Audit: Password encrypted on frontend (RSA), decrypted on backend before verification.
 */
class RsaEncryptionService
{
    private ?string $privateKey = null;
    private ?string $publicKey = null;
    private string $privateKeyPath;
    private string $publicKeyPath;

    public function __construct()
    {
        $this->privateKeyPath = storage_path('app/rsa_private.pem');
        $this->publicKeyPath = storage_path('app/rsa_public.pem');
    }

    public function getPublicKey(): string
    {
        $this->ensureKeysExist();
        return $this->publicKey ?? File::get($this->publicKeyPath);
    }

    public function decrypt(string $base64Encrypted): string
    {
        $this->ensureKeysExist();
        $privateKey = $this->privateKey ?? File::get($this->privateKeyPath);
        $encrypted = base64_decode($base64Encrypted, true);
        if ($encrypted === false) {
            throw new \InvalidArgumentException('Invalid base64 encrypted data');
        }
        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new \RuntimeException('Invalid private key');
        }
        $decrypted = '';
        $result = openssl_private_decrypt(
            $encrypted,
            $decrypted,
            $key,
            OPENSSL_PKCS1_OAEP_PADDING
        );
        openssl_pkey_free($key);
        if (!$result || $decrypted === '') {
            throw new \InvalidArgumentException('Decryption failed');
        }
        return $decrypted;
    }

    private function ensureKeysExist(): void
    {
        if (File::exists($this->publicKeyPath) && File::exists($this->privateKeyPath)) {
            return;
        }
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $key = openssl_pkey_new($config);
        if ($key === false) {
            throw new \RuntimeException('Failed to generate RSA keys');
        }
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        $publicKey = $details['key'] ?? null;
        openssl_pkey_free($key);
        if (!$privateKey || !$publicKey) {
            throw new \RuntimeException('Failed to export RSA keys');
        }
        File::put($this->privateKeyPath, $privateKey);
        File::put($this->publicKeyPath, $publicKey);
    }
}
