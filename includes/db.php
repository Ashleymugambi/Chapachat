<?php
/**
 * Database Connection Helper (includes/db.php)
 * Uses PDO with prepared statements and strict error handling.
 */

require_once __DIR__ . '/../config/config.php';

function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error internally and show generic message
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please ensure MySQL is running in XAMPP and the database 'chapachat' is imported.");
        }
    }
    return $pdo;
}
