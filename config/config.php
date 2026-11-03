<?php
/**
 * ChapaChat - Privacy-Aware Messaging System
 * Active Configuration File (config.php)
 */

// Database Settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'chapachat');

// Application Encryption Key (32 bytes / 64 hex characters for AES-256-CBC)
define('APP_KEY', '750abc767f647bd25c350ca6c59081b86173b2d8701395048b94ea71a99416d6');

// OTP & Security Settings
define('OTP_EXPIRY_SECONDS', 300); // 5 minutes
define('SESSION_TIMEOUT_SECONDS', 900); // 15 minutes
define('MAX_LOGIN_ATTEMPTS', 3);

// Environment & Feature Flags
define('DEMO_MODE', true); // Displays OTP in alert on verify page for local testing

// Application Details
define('APP_NAME', 'ChapaChat');
define('APP_TAGLINE', 'Privacy-Aware Messaging System');
define('BASE_URL', '/chapachat');
