<?php

namespace App\Helpers;

class AuthEncryptionHelper
{
    /**
     * Decrypt AES-256-CBC encrypted data (matching Drupal's decrypt function)
     */
    public static function decrypt($data)
    {
        if (empty($data)) {
            return '';
        }

        $encryptedString = base64_decode($data);
        $cipher = 'aes-256-cbc';
        $secret = config('services.hrms.secret', '');
        $iv = config('services.hrms.iv', '');

        if (empty($secret) || empty($iv)) {
            throw new \Exception('HRMS encryption secret or IV not configured');
        }

        $decryptedString = openssl_decrypt($encryptedString, $cipher, $secret, OPENSSL_RAW_DATA, $iv);

        return $decryptedString;
    }

    /**
     * Validate checksum using HMAC-SHA256 (matching Drupal's checksum_validation function)
     */
    public static function checksumValidation($data)
    {
        $hmacSecret = config('services.hrms.hmac_secret', '');
        
        if (empty($hmacSecret)) {
            throw new \Exception('HMAC secret not configured');
        }

        return hash_hmac('sha256', mb_convert_encoding($data, "UTF-8"), $hmacSecret);
    }

    /**
     * Generate HMAC token for SSO (matching Drupal's token generation)
     */
    public static function generateSsoToken($code, $timestamp = null)
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $encryptedCode = \App\Helpers\UrlEncryptionHelper::encryptUrl($code);
        $message = $encryptedCode . "|" . $timestamp;
        $hmacSecret = config('services.hrms.hmac_secret_me', '');
        
        if (empty($hmacSecret)) {
            throw new \Exception('HMAC secret ME not configured');
        }

        $hmac = hash_hmac("sha256", $message, $hmacSecret);
        $token = base64_encode($message . "|" . $hmac);

        return $token;
    }

    /**
     * Validate SSO token (matching Drupal's token validation)
     */
    public static function validateSsoToken($token, $maxAge = 120)
    {
        if (empty($token)) {
            return ['valid' => false, 'error' => 'No SSO token provided'];
        }

        $decoded = base64_decode($token);
        if (!$decoded || substr_count($decoded, '|') !== 2) {
            return ['valid' => false, 'error' => 'Invalid token format'];
        }

        list($code, $timestamp, $receivedHmac) = explode("|", $decoded);

        // Compute expected HMAC
        $hmacSecret = config('services.hrms.hmac_secret_me', '');
        if (empty($hmacSecret)) {
            return ['valid' => false, 'error' => 'HMAC secret not configured'];
        }

        $expectedHmac = hash_hmac("sha256", $code . "|" . $timestamp, $hmacSecret);

        if (!hash_equals($expectedHmac, $receivedHmac)) {
            return ['valid' => false, 'error' => 'Invalid token'];
        }

        // Check timestamp validity
        if (abs(time() - (int)$timestamp) > $maxAge) {
            return ['valid' => false, 'error' => 'Request Token Expired'];
        }

        // Decrypt the code
        $decryptedCode = \App\Helpers\UrlEncryptionHelper::decryptUrl($code);

        return [
            'valid' => true,
            'code' => $decryptedCode,
            'timestamp' => (int)$timestamp
        ];
    }
}

