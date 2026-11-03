<?php
/**
 * User Profile Management Page (profile.php)
 * Updates user profile details & changes password.
 */

$pageTitle = "Profile Settings";
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = get_current_user_id();
$errors = [];
$successMsg = '';
$user = null;

try {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT user_id, full_name, email, phone_number, created_at FROM users WHERE user_id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    error_log("Profile load error: " . $e->getMessage());
    $errors[] = "Failed to load user profile.";
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($token)) {
        $errors[] = "Invalid CSRF security token.";
    } elseif ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phoneNumber = trim($_POST['phone_number'] ?? '');

        if (empty($fullName)) {
            $errors[] = "Full Name is required.";
        }

        if (empty($phoneNumber)) {
            $errors[] = "Phone number is required.";
        } elseif (!preg_match('/^(?:\+254|254|0)[71]\d{8}$/', $phoneNumber)) {
            $errors[] = "Invalid Kenyan phone number format.";
        }

        if (empty($errors)) {
            try {
                $updateStmt = $db->prepare("UPDATE users SET full_name = :name, phone_number = :phone WHERE user_id = :id");
                $updateStmt->execute([
                    ':name'  => $fullName,
                    ':phone' => $phoneNumber,
                    ':id'    => $userId
                ]);

                // Update session state
                $_SESSION['full_name'] = $fullName;
                $user['full_name'] = $fullName;
                $user['phone_number'] = $phoneNumber;

                // Log PROFILE_UPDATED (Never log values)
                log_action('PROFILE_UPDATED', 'User updated contact details', $userId);

                $successMsg = "Profile information updated successfully.";
            } catch (Exception $e) {
                error_log("Update profile error: " . $e->getMessage());
                $errors[] = "Failed to update profile.";
            }
        }

    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errors[] = "All password fields are required.";
        } else {
            // Verify current password from database
            $pwStmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = :id LIMIT 1");
            $pwStmt->execute([':id' => $userId]);
            $currentHash = $pwStmt->fetchColumn();

            if (!password_verify($currentPassword, $currentHash)) {
                $errors[] = "Current password is incorrect.";
            } elseif (strlen($newPassword) < 8) {
                $errors[] = "New password must be at least 8 characters long.";
            } elseif (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                $errors[] = "New password must contain at least one letter and one number.";
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = "New password and confirmation do not match.";
            } else {
                try {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updatePw = $db->prepare("UPDATE users SET password_hash = :hash WHERE user_id = :id");
                    $updatePw->execute([':hash' => $newHash, ':id' => $userId]);

                    // Log PASSWORD_CHANGED (Never log password)
                    log_action('PASSWORD_CHANGED', 'User successfully changed account password', $userId);

                    $successMsg = "Password changed successfully.";
                } catch (Exception $e) {
                    error_log("Change password error: " . $e->getMessage());
                    $errors[] = "Failed to update password.";
                }
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="d-flex align-items-center mb-4">
                <i class="bi bi-person-gear text-primary display-5 me-3"></i>
                <div>
                    <h3 class="fw-bold mb-0">Profile Settings</h3>
                    <p class="text-muted mb-0">Manage your personal information and security credentials</p>
                </div>
            </div>

            <?php if (!empty($successMsg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo sanitize_output($successMsg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo sanitize_output($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Profile Info Card -->
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-card-heading text-primary me-2"></i>Contact Details</h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?php echo BASE_URL; ?>/profile.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="mb-3">
                            <label class="form-label text-muted">Email Address (Read Only)</label>
                            <input type="text" class="form-control bg-light" value="<?php echo sanitize_output($user['email']); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label fw-medium">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo sanitize_output($user['full_name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="phone_number" class="form-label fw-medium">Kenyan Phone Number</label>
                            <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo sanitize_output($user['phone_number']); ?>" required>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Save Profile Details
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Password Change Card -->
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-key-fill text-warning me-2"></i>Change Password</h5>
                </div>
                <div class="card-body p-4">
                    <form action="<?php echo BASE_URL; ?>/profile.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label for="current_password" class="form-label fw-medium">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label fw-medium">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required placeholder="At least 8 characters (letters & digits)">
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label fw-medium">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-warning text-dark fw-semibold">
                                <i class="bi bi-shield-lock me-1"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
