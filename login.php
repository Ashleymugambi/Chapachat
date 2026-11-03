<?php
/**
 * User Login Page (login.php)
 * Step 1: Password Verification & Account Lockout Logic
 * Flowchart: Login with One-Time Code
 */

$pageTitle = "Login";
require_once __DIR__ . '/includes/auth.php';

// Redirect if already fully logged in
if (is_logged_in()) {
    $redirectUrl = (get_current_role_name() === 'Administrator') ? BASE_URL . '/admin/index.php' : BASE_URL . '/dashboard.php';
    header("Location: " . $redirectUrl);
    exit();
}

$error = $_GET['error'] ?? '';
$msg = $_GET['msg'] ?? '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = "Invalid CSRF security token. Please try again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Please enter both email address and password.";
        } else {
            try {
                $db = get_db_connection();
                $stmt = $db->prepare("
                    SELECT u.user_id, u.role_id, r.role_name, u.full_name, u.email, u.password_hash, u.is_active, u.failed_attempts
                    FROM users u
                    JOIN roles r ON u.role_id = r.role_id
                    WHERE u.email = :email
                    LIMIT 1
                ");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch();

                if (!$user) {
                    // General error message for security
                    $error = "Incorrect email or password.";
                } elseif ((int)$user['is_active'] === 0) {
                    $error = "Your account has been deactivated or locked due to multiple failed login attempts. Please contact an Administrator.";
                    log_action('LOGIN_ATTEMPT_LOCKED', "Failed attempt on deactivated account", (int)$user['user_id']);
                } elseif (!password_verify($password, $user['password_hash'])) {
                    // Wrong password
                    $newFailedAttempts = (int)$user['failed_attempts'] + 1;
                    $userId = (int)$user['user_id'];

                    if ($newFailedAttempts >= MAX_LOGIN_ATTEMPTS) {
                        // Deactivate account after 3 failures
                        $lockStmt = $db->prepare("UPDATE users SET failed_attempts = :attempts, is_active = 0 WHERE user_id = :id");
                        $lockStmt->execute([':attempts' => $newFailedAttempts, ':id' => $userId]);

                        log_action('ACCOUNT_LOCKED', "Account locked after {$newFailedAttempts} failed password attempts", $userId);
                        $error = "Your account has been locked due to 3 consecutive failed login attempts.";
                    } else {
                        // Increment failed attempts
                        $incStmt = $db->prepare("UPDATE users SET failed_attempts = :attempts WHERE user_id = :id");
                        $incStmt->execute([':attempts' => $newFailedAttempts, ':id' => $userId]);

                        log_action('FAILED_LOGIN', "Failed password attempt ({$newFailedAttempts}/3)", $userId);
                        $error = "Incorrect email or password.";
                    }
                } else {
                    // Step 1 Success: Generate 6-digit OTP code
                    $otpCode = (string)random_int(100000, 999999);
                    $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);

                    // Deactivate any previous unused OTP codes for this user
                    $cleanOtp = $db->prepare("UPDATE otp_code SET is_used = 1 WHERE user_id = :id AND is_used = 0");
                    $cleanOtp->execute([':id' => $user['user_id']]);

                    // Save hashed OTP with 5 minute expiration
                    $otpStmt = $db->prepare("
                        INSERT INTO otp_code (user_id, code_hash, expires_at, is_used)
                        VALUES (:user_id, :code_hash, DATE_ADD(NOW(), INTERVAL 5 MINUTE), 0)
                    ");
                    $otpStmt->execute([
                        ':user_id'   => $user['user_id'],
                        ':code_hash' => $otpHash
                    ]);

                    // Set pending session variables for OTP verification (Step 2)
                    $_SESSION['otp_pending'] = true;
                    $_SESSION['otp_user_id'] = (int)$user['user_id'];
                    $_SESSION['otp_user_name'] = $user['full_name'];
                    $_SESSION['otp_user_email'] = $user['email'];
                    $_SESSION['otp_role_id'] = (int)$user['role_id'];
                    $_SESSION['otp_role_name'] = $user['role_name'];

                    if (defined('DEMO_MODE') && DEMO_MODE) {
                        $_SESSION['demo_otp_code'] = $otpCode;
                    }

                    header("Location: " . BASE_URL . "/verify_otp.php");
                    exit();
                }

            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $error = "A system error occurred. Please try again later.";
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow border-0 rounded-3">
                <div class="card-header card-header-primary text-center py-3">
                    <h4 class="mb-0 text-white"><i class="bi bi-box-arrow-in-right me-2"></i>User Login</h4>
                    <small class="text-white-50">Step 1: Enter your credentials</small>
                </div>
                <div class="card-body p-4">

                    <?php if (!empty($msg)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i><?php echo sanitize_output($msg); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo sanitize_output($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>/login.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo sanitize_output($email); ?>" required placeholder="admin@chapachat.test">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-medium">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required placeholder="Enter password">
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                <i class="bi bi-arrow-right-circle me-1"></i> Continue to OTP Verification
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center py-3 bg-light">
                    <span>Don't have an account? </span>
                    <a href="<?php echo BASE_URL; ?>/register.php" class="fw-bold text-decoration-none">Register Here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
