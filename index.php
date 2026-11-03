<?php
/**
 * Main Index Page (index.php)
 * Redirects logged-in users to appropriate dashboard or guests to login.
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    if (get_current_role_name() === 'Administrator') {
        header("Location: " . BASE_URL . "/admin/index.php");
    } else {
        header("Location: " . BASE_URL . "/dashboard.php");
    }
} else {
    header("Location: " . BASE_URL . "/login.php");
}
exit();
