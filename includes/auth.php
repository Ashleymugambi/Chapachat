<?php
/**
 * Authentication and RBAC Helper (includes/auth.php)
 * Handles session security, login validation, role checking, CSRF tokens, and output escaping.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';

// Configure session security parameters before session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

/**
 * Enforce session timeout (15 minutes inactivity)
 */
function check_session_timeout(): void {
    if (isset($_SESSION['user_id'])) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS)) {
            $userId = $_SESSION['user_id'];
            log_action('SESSION_TIMEOUT', 'User session expired after 15 minutes', $userId);
            
            // Destroy session
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();

            // Redirect with message
            $loginUrl = BASE_URL . '/login.php?error=' . urlencode('Your session has expired due to inactivity. Please log in again.');
            header("Location: " . $loginUrl);
            exit();
        }
        $_SESSION['last_activity'] = time();
    }
}

// Run timeout check automatically
check_session_timeout();

/**
 * Check if a user is currently logged in with MFA verified
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['role_id']) && !empty($_SESSION['mfa_verified']);
}

/**
 * Get logged-in user ID
 */
function get_current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get logged-in user full name
 */
function get_current_user_name(): string {
    return $_SESSION['full_name'] ?? 'User';
}

/**
 * Get logged-in user role name
 */
function get_current_role_name(): string {
    return $_SESSION['role_name'] ?? 'Guest';
}

/**
 * Require login for protected pages
 */
function require_login(): void {
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}

/**
 * Require a specific role (e.g., 'Administrator' or 'User')
 * 
 * @param string $requiredRole Role name expected
 */
function require_role(string $requiredRole): void {
    require_login();
    
    $currentRole = get_current_role_name();
    if (strcasecmp($currentRole, $requiredRole) !== 0) {
        $user_id = get_current_user_id();
        $requestedUri = $_SERVER['REQUEST_URI'] ?? 'Unknown page';
        
        // Log ACCESS_DENIED
        log_action('ACCESS_DENIED', "Attempted to access: " . substr($requestedUri, 0, 150), $user_id);
        
        // Return HTTP 403 Access Denied page
        http_response_code(403);
        include __DIR__ . '/header.php';
        ?>
        <div class="container my-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card border-danger shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h4 class="mb-0"><i class="bi bi-shield-slash"></i> Access Denied (HTTP 403)</h4>
                        </div>
                        <div class="card-body text-center py-5">
                            <i class="bi bi-exclamation-triangle-fill text-danger display-1 mb-3"></i>
                            <h3>Permission Required</h3>
                            <p class="lead text-muted">You do not have the required permissions to access this page.</p>
                            <p>This attempt has been logged for security auditing purposes.</p>
                            <div class="mt-4">
                                <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-primary">Return to Dashboard</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        include __DIR__ . '/footer.php';
        exit();
    }
}

/**
 * CSRF Token Generator
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output sanitizer for XSS protection
 */
function sanitize_output(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
