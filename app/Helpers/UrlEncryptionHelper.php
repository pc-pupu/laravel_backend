<?php

namespace App\Helpers;

class UrlEncryptionHelper
{
    private static $key = "MAL_979805";

    /**
     * Encrypt URL parameter (matching Drupal's encrypt_url function)
     */
    public static function encryptUrl($string)
    {
        if (empty($string)) {
            return '';
        }

        $key = self::$key;
        $result = '';
        
        for ($i = 0; $i < strlen($string); $i++) {
            $char = substr($string, $i, 1);
            $keychar = substr($key, ($i % strlen($key)) - 1, 1);
            $char = chr(ord($char) + ord($keychar));
            $result .= $char;
        }

        return urlencode(base64_encode($result));
    }

    /**
     * Decrypt URL parameter (matching Drupal's decrypt_url function)
     */
    public static function decryptUrl($string)
    {
        if (empty($string)) {
            return '';
        }

        $key = self::$key;
        $result = '';
        $string = base64_decode(urldecode($string));

        for ($i = 0; $i < strlen($string); $i++) {
            $char = substr($string, $i, 1);
            $keychar = substr($key, ($i % strlen($key)) - 1, 1);
            $char = chr(ord($char) - ord($keychar));
            $result .= $char;
        }

        return $result;
    }
}

