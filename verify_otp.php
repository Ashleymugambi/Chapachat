<?php
/**
 * OTP Verification Page (verify_otp.php)
 * Step 2 & 3: Multi-Factor Authentication Verification
 * Flowchart: Login with One-Time Code
 */

$pageTitle = "Verify OTP";
require_once __DIR__ . '/includes/auth.php';

// If already fully logged in, redirect to dashboard
if (is_logged_in()) {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit();
}

// Ensure user completed Step 1 (email + password)
if (empty($_SESSION['otp_pending']) || empty($_SESSION['otp_user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$error = '';
$userId = (int)$_SESSION['otp_user_id'];
$userEmail = $_SESSION['otp_user_email'];
$userName = $_SESSION['otp_user_name'];
$roleId = (int)$_SESSION['otp_role_id'];
$roleName = $_SESSION['otp_role_name'];
$demoCode = $_SESSION['demo_otp_code'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = "Invalid CSRF security token. Please try submitting the form again.";
    } else {
        $inputOtp = trim($_POST['otp_code'] ?? '');

        if (empty($inputOtp)) {
            $error = "Please enter the 6-digit OTP code.";
        } else {
            try {
                $db = get_db_connection();
                
                // Fetch the latest active OTP code for this user
                $stmt = $db->prepare("
                    SELECT otp_id, code_hash, expires_at, (expires_at < NOW()) AS is_expired
                    FROM otp_code
                    WHERE user_id = :user_id AND is_used = 0
                    ORDER BY otp_id DESC
                    LIMIT 1
                ");
                $stmt->execute([':user_id' => $userId]);
                $otpRecord = $stmt->fetch();

                if (!$otpRecord) {
                    $error = "No active verification code found. Please log in again.";
                    log_action('OTP_FAILED', "Attempted verification with no active OTP record", $userId);
                } elseif ((int)$otpRecord['is_expired'] === 1) {
                    $error = "The verification code has expired (valid for 5 minutes). Please log in again to generate a new code.";
                    log_action('OTP_EXPIRED', "Attempted verification with expired OTP token", $userId);
                } elseif (!password_verify($inputOtp, $otpRecord['code_hash'])) {
                    $error = "Incorrect verification code. Please check and try again.";
                    log_action('OTP_FAILED', "Invalid OTP code entered", $userId);
                } else {
                    // Successful OTP Verification
                    
                    // 1. Mark OTP as used
                    $markStmt = $db->prepare("UPDATE otp_code SET is_used = 1 WHERE otp_id = :otp_id");
                    $markStmt->execute([':otp_id' => $otpRecord['otp_id']]);

                    // 2. Reset failed attempts counter
                    $resetStmt = $db->prepare("UPDATE users SET failed_attempts = 0 WHERE user_id = :user_id");
                    $resetStmt->execute([':user_id' => $userId]);

                    // 3. Regenerate session ID for session fixation protection
                    session_regenerate_id(true);

                    // 4. Set active user session
                    $_SESSION['user_id']      = $userId;
                    $_SESSION['full_name']    = $userName;
                    $_SESSION['email']        = $userEmail;
                    $_SESSION['role_id']      = $roleId;
                    $_SESSION['role_name']    = $roleName;
                    $_SESSION['mfa_verified'] = true;
                    $_SESSION['last_activity'] = time();

                    // Clear temporary OTP pending data
                    unset($_SESSION['otp_pending'], $_SESSION['otp_user_id'], $_SESSION['otp_user_name'],
                          $_SESSION['otp_user_email'], $_SESSION['otp_role_id'], $_SESSION['otp_role_name'],
                          $_SESSION['demo_otp_code']);

                    // 5. Log LOGIN event
                    log_action('LOGIN', "Successful multi-factor authentication (OTP verified)", $userId);

                    // 6. Redirect to dashboard according to role
                    if ($roleName === 'Administrator') {
                        header("Location: " . BASE_URL . "/admin/index.php");
                    } else {
                        header("Location: " . BASE_URL . "/dashboard.php");
                    }
                    exit();
                }

            } catch (Exception $e) {
                error_log("OTP Verification Error: " . $e->getMessage());
                $error = "A system error occurred. Please try again.";
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">

            <?php if (defined('DEMO_MODE') && DEMO_MODE && !empty($demoCode)): ?>
                <div class="alert alert-info alert-demo-otp shadow-sm border-info rounded-3 mb-4">
                    <div class="d-flex align-items-center mb-2">
                        <i class="bi bi-phone-vibrate-fill fs-3 me-2 text-info"></i>
                        <h5 class="alert-heading mb-0 fw-bold">XAMPP Demo SMS Gateway</h5>
                    </div>
                    <p class="mb-2 fs-6">In a production environment, your one-time passcode is sent via SMS/Email to your registered phone or email address.</p>
                    <div class="bg-white p-2 rounded text-center border">
                        <span class="text-muted small d-block mb-1">Your Demo OTP Code:</span>
                        <span class="display-6 fw-bold letter-spacing-2 text-primary"><?php echo sanitize_output($demoCode); ?></span>
                    </div>
                    <small class="form-text text-muted d-block text-center mt-2">(Expires in 5 minutes)</small>
                </div>
            <?php endif; ?>

            <div class="card shadow border-0 rounded-3">
                <div class="card-header card-header-primary text-center py-3">
                    <h4 class="mb-0 text-white"><i class="bi bi-shield-check me-2"></i>MFA Verification</h4>
                    <small class="text-white-50">Step 2: Enter One-Time Password</small>
                </div>
                <div class="card-body p-4">

                    <p class="text-muted text-center mb-4">
                        We have generated a 6-digit authentication code for account 
                        <strong class="text-dark"><?php echo sanitize_output($userEmail); ?></strong>.
                    </p>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo sanitize_output($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>/verify_otp.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <div class="mb-4">
                            <label for="otp_code" class="form-label fw-medium text-center d-block">6-Digit Code</label>
                            <input type="text" class="form-control form-control-lg text-center fw-bold fs-3 letter-spacing-3" id="otp_code" name="otp_code" required maxlength="6" pattern="[0-9]{6}" autocomplete="off" placeholder="000000" autofocus>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                <i class="bi bi-check-circle-fill me-1"></i> Verify Code & Log In
                            </button>
                            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Cancel & Return to Login
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
