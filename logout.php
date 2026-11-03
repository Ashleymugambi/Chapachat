<?php
/**
 * User Logout Script (logout.php)
 * Logs event, clears session cookie, and destroys session.
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $userId = get_current_user_id();
    log_action('LOGOUT', 'User logged out', $userId);
}

// Clear session state
$_SESSION = array();

// If session cookie is set, invalidate it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

header("Location: " . BASE_URL . "/login.php?msg=" . urlencode("You have been logged out successfully."));
exit();
