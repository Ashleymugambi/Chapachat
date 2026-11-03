<?php
/**
 * Encryption / Decryption Helper (includes/crypto.php)
 * Implements AES-256-CBC with a unique random IV for every message.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Encrypt plaintext using AES-256-CBC
 * 
 * @param string $plaintext Message content to encrypt
 * @param string $key Hex string or raw key
 * @return string Base64 encoded string containing (IV + Ciphertext)
 */
function encrypt_text(string $plaintext, string $key = APP_KEY): string {
    if ($plaintext === '') {
        return '';
    }
    
    // Ensure 32-byte key for AES-256
    $rawKey = (ctype_xdigit($key) && strlen($key) === 64) ? hex2bin($key) : hash('sha256', $key, true);
    
    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = random_bytes($ivLength);
    
    $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $rawKey, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        throw new Exception("Encryption failed");
    }
    
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt base64 package (IV + Ciphertext) using AES-256-CBC
 * 
 * @param string $encrypted_package Base64 string containing (IV + Ciphertext)
 * @param string $key Hex string or raw key
 * @return string Decrypted plaintext or empty string if deleted/invalid
 */
function decrypt_text(string $encrypted_package, string $key = APP_KEY): string {
    if ($encrypted_package === '' || $encrypted_package === null) {
        return '[Message Content Deleted]';
    }
    
    $data = base64_decode($encrypted_package, true);
    if ($data === false) {
        return '[Decryption Error: Invalid Package]';
    }
    
    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    if (strlen($data) <= $ivLength) {
        return '[Decryption Error: Data Corrupted]';
    }
    
    $iv = substr($data, 0, $ivLength);
    $ciphertext = substr($data, $ivLength);
    
    $rawKey = (ctype_xdigit($key) && strlen($key) === 64) ? hex2bin($key) : hash('sha256', $key, true);
    
    $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $rawKey, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        return '[Decryption Error: Bad Key or Data]';
    }
    
    return $decrypted;
}
