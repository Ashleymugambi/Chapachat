<?php
/**
 * ChapaChat - Privacy-Aware Messaging System
 * Configuration Template (config.sample.php)
 */

// Database Settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'chapachat');

// Application Encryption Key (Must be 64 hex characters / 32 bytes for AES-256)
define('APP_KEY', 'CHANGE_THIS_TO_A_SECURE_64_CHARACTER_HEX_KEY');

// OTP & Security Settings
define('OTP_EXPIRY_SECONDS', 300); // 5 minutes
define('SESSION_TIMEOUT_SECONDS', 900); // 15 minutes
define('MAX_LOGIN_ATTEMPTS', 3);

// Environment & Feature Flags
define('DEMO_MODE', true); // Displays OTP in alert on verify page for testing

// Application Details
define('APP_NAME', 'ChapaChat');
define('APP_TAGLINE', 'Privacy-Aware Messaging System');
define('BASE_URL', 'http://localhost/chapachat');
