<?php
/**
 * User Registration Page (register.php)
 * Flowchart: User Registration
 */

$pageTitle = "Register";
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit();
}

$errors = [];
$successMsg = '';
$formData = [
    'full_name'    => '',
    'email'        => '',
    'phone_number' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "Invalid CSRF security token. Please try submitting the form again.";
    } else {
        $formData['full_name']    = trim($_POST['full_name'] ?? '');
        $formData['email']        = trim($_POST['email'] ?? '');
        $formData['phone_number'] = trim($_POST['phone_number'] ?? '');
        $password                 = $_POST['password'] ?? '';
        $confirmPassword          = $_POST['confirm_password'] ?? '';

        // Validation Checks
        if (empty($formData['full_name'])) {
            $errors[] = "Full Name is required.";
        }

        if (empty($formData['email'])) {
            $errors[] = "Email address is required.";
        } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }

        if (empty($formData['phone_number'])) {
            $errors[] = "Phone number is required.";
        } elseif (!preg_match('/^(?:\+254|254|0)[71]\d{8}$/', $formData['phone_number'])) {
            $errors[] = "Invalid Kenyan phone number format. Examples: 0712345678, 0112345678 or +254712345678.";
        }

        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one letter and one number.";
        }

        if ($password !== $confirmPassword) {
            $errors[] = "Password and confirm password do not match.";
        }

        // Check if email already registered
        if (empty($errors)) {
            try {
                $db = get_db_connection();
                $stmt = $db->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
                $stmt->execute([':email' => $formData['email']]);
                if ($stmt->fetch()) {
                    $errors[] = "This email address is already registered.";
                }
            } catch (Exception $e) {
                error_log("Registration check error: " . $e->getMessage());
                $errors[] = "A system error occurred. Please try again later.";
            }
        }

        // Process Registration if Valid
        if (empty($errors)) {
            try {
                $db = get_db_connection();
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // User role_id = 1
                $stmt = $db->prepare("
                    INSERT INTO users (role_id, full_name, email, phone_number, password_hash, is_active, failed_attempts, created_at)
                    VALUES (1, :full_name, :email, :phone_number, :password_hash, 1, 0, NOW())
                ");
                $stmt->execute([
                    ':full_name'    => $formData['full_name'],
                    ':email'        => $formData['email'],
                    ':phone_number' => $formData['phone_number'],
                    ':password_hash'=> $passwordHash
                ]);

                $newUserId = (int)$db->lastInsertId();

                // Log REGISTER event
                log_action('REGISTER', 'New account created successfully', $newUserId);

                $successMsg = "Account created successfully! You can now proceed to log in.";
                // Clear form inputs
                $formData = ['full_name' => '', 'email' => '', 'phone_number' => ''];

            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $errors[] = "Failed to create account. Please try again.";
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
                    <h4 class="mb-0 text-white"><i class="bi bi-person-plus-fill me-2"></i>Create an Account</h4>
                    <small class="text-white-50">ChapaChat Privacy-Aware Messaging</small>
                </div>
                <div class="card-body p-4">
                    
                    <?php if (!empty($successMsg)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i><?php echo sanitize_output($successMsg); ?>
                            <div class="mt-2">
                                <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-sm btn-success">Proceed to Login</a>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please fix the following errors:</h6>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitize_output($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>/register.php" method="POST" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <div class="mb-3">
                            <label for="full_name" class="form-label fw-medium">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo sanitize_output($formData['full_name']); ?>" required placeholder="e.g. John Doe">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo sanitize_output($formData['email']); ?>" required placeholder="name@chapachat.test">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="phone_number" class="form-label fw-medium">Kenyan Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo sanitize_output($formData['phone_number']); ?>" required placeholder="0712345678 or +254712345678">
                            </div>
                            <small class="form-text text-muted">Format: 07XXXXXXXX, 01XXXXXXXX or +2547XXXXXXXX</small>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-medium">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="At least 8 chars (letters & digits)">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label fw-medium">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Re-enter your password">
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                <i class="bi bi-person-check-fill me-1"></i> Register Account
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center py-3 bg-light">
                    <span>Already have an account? </span>
                    <a href="<?php echo BASE_URL; ?>/login.php" class="fw-bold text-decoration-none">Log In Here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
