<?php
/**
 * Privacy-Aware Audit Logging Helper (includes/audit.php)
 * Logs system actions, user events, and security flags without storing
 * any private conversation contents, passwords, or OTP tokens.
 */

require_once __DIR__ . '/db.php';

/**
 * Log an event to the AUDIT_LOG table
 * 
 * @param string $action Event identifier (e.g., LOGIN, REGISTER, MESSAGE_SENT)
 * @param string $details Brief metadata (e.g., Message ID: 5), NEVER plaintext message/password
 * @param int|null $user_id User ID performing the action (defaults to session user if logged in)
 * @return bool True if logged successfully
 */
function log_action(string $action, string $details = '', ?int $user_id = null): bool {
    try {
        $db = get_db_connection();
        
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = (int)$_SESSION['user_id'];
        }
        
        // Retrieve IP Address safely
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        $ipAddress = substr(trim($ipAddress), 0, 45);

        // Sanitize & enforce field constraints
        $action = substr(trim($action), 0, 50);
        $details = substr(trim($details), 0, 255);

        $stmt = $db->prepare("
            INSERT INTO audit_log (user_id, action, details, ip_address, log_time)
            VALUES (:user_id, :action, :details, :ip_address, NOW())
        ");

        return $stmt->execute([
            ':user_id'    => $user_id,
            ':action'     => $action,
            ':details'    => $details,
            ':ip_address' => $ipAddress
        ]);
    } catch (Exception $e) {
        error_log("Audit Log Failure: " . $e->getMessage());
        return false;
    }
}
