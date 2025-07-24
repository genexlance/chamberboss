<?php
/**
 * Security utilities for Chamber Boss
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CB_Security {
    
    /**
     * Encrypt data using WordPress salts
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        $key = defined('AUTH_KEY') ? AUTH_KEY : '';
        if (empty($key)) {
            // Fallback if AUTH_KEY is not defined
            $key = 'fallback_key_for_encryption';
        }
        
        $iv = substr(wp_hash($key), 0, 16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($encrypted . '::' . $iv);
    }
    
    /**
     * Decrypt data using WordPress salts
     */
    public static function decrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        $key = defined('AUTH_KEY') ? AUTH_KEY : '';
        if (empty($key)) {
            // Fallback if AUTH_KEY is not defined
            $key = 'fallback_key_for_encryption';
        }
        
        $data = base64_decode($data);
        $parts = explode('::', $data);
        
        if (count($parts) !== 2) {
            return false;
        }
        
        $encrypted = $parts[0];
        $iv = $parts[1];
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        
        return $decrypted;
    }
    
    /**
     * Securely store an option
     */
    public static function update_secure_option($option, $value) {
        $encrypted_value = self::encrypt($value);
        return update_option($option, $encrypted_value);
    }
    
    /**
     * Securely retrieve an option
     */
    public static function get_secure_option($option, $default = false) {
        $encrypted_value = get_option($option, $default);
        if ($encrypted_value === $default) {
            return $default;
        }
        
        $decrypted_value = self::decrypt($encrypted_value);
        return $decrypted_value !== false ? $decrypted_value : $default;
    }
}