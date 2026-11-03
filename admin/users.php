<?php
/**
 * Manage System Users and Roles (admin/users.php)
 * Flowchart: Change a User's Role
 */

$pageTitle = "Manage Users & Roles";
require_once __DIR__ . '/../includes/auth.php';

require_role('Administrator');

$currentAdminId = get_current_user_id();
$errors = [];
$successMsg = $_GET['msg'] ?? '';
$usersList = [];

$db = get_db_connection();

// Handle Actions (Change Role / Change Activation Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);

    if (!verify_csrf_token($token)) {
        $errors[] = "Invalid CSRF security token.";
    } elseif ($targetUserId <= 0) {
        $errors[] = "Invalid target user specified.";
    } else {
        try {
            // Fetch target user details
            $stmt = $db->prepare("
                SELECT u.user_id, u.full_name, u.email, u.role_id, r.role_name, u.is_active
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.user_id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $targetUserId]);
            $targetUser = $stmt->fetch();

            if (!$targetUser) {
                $errors[] = "Target user not found.";
            } elseif ($action === 'change_role') {
                $newRoleId = (int)($_POST['new_role_id'] ?? 0);
                
                // Self-preservation check: Admin cannot remove their own admin role
                if ($targetUserId === $currentAdminId && $newRoleId != 2) {
                    $errors[] = "Action Denied: You cannot remove your own Administrator role.";
                } elseif ($newRoleId === 1 || $newRoleId === 2) {
                    $oldRoleName = $targetUser['role_name'];
                    $newRoleName = ($newRoleId === 2) ? 'Administrator' : 'User';

                    $updateRole = $db->prepare("UPDATE users SET role_id = :role_id WHERE user_id = :id");
                    $updateRole->execute([':role_id' => $newRoleId, ':id' => $targetUserId]);

                    // Log ROLE_CHANGED
                    log_action('ROLE_CHANGED', "Role changed for User ID {$targetUserId} ({$targetUser['email']}): {$oldRoleName} to {$newRoleName}", $currentAdminId);

                    $successMsg = "Successfully changed role for {$targetUser['full_name']} to {$newRoleName}.";
                } else {
                    $errors[] = "Invalid role specified.";
                }

            } elseif ($action === 'toggle_status') {
                // Self-preservation check: Admin cannot deactivate themselves
                if ($targetUserId === $currentAdminId) {
                    $errors[] = "Action Denied: You cannot deactivate your own account.";
                } else {
                    $newStatus = ((int)$targetUser['is_active'] === 1) ? 0 : 1;
                    $statusLabel = ($newStatus === 1) ? 'Activated' : 'Deactivated';

                    // When activating, reset failed attempts
                    $updateStatus = $db->prepare("UPDATE users SET is_active = :status, failed_attempts = 0 WHERE user_id = :id");
                    $updateStatus->execute([':status' => $newStatus, ':id' => $targetUserId]);

                    // Log USER_STATUS_CHANGED
                    log_action('USER_STATUS_CHANGED', "User ID {$targetUserId} ({$targetUser['email']}) set to {$statusLabel}", $currentAdminId);

                    $successMsg = "Account for {$targetUser['full_name']} has been {$statusLabel}.";
                }
            }
        } catch (Exception $e) {
            error_log("Manage users error: " . $e->getMessage());
            $errors[] = "An error occurred while updating the user record.";
        }
    }
}

// Fetch All Users
try {
    $stmt = $db->query("
        SELECT u.user_id, u.full_name, u.email, u.phone_number, u.role_id, r.role_name, u.is_active, u.failed_attempts, u.created_at
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        ORDER BY u.user_id ASC
    ");
    $usersList = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Users list fetch error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-0 text-danger"><i class="bi bi-people-fill me-2"></i>Manage Users &amp; Roles</h3>
            <p class="text-muted mb-0">Role-Based Access Control (RBAC) Administration</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/admin/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Admin Dashboard
        </a>
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

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Full Name &amp; Email</th>
                            <th>Phone Number</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Failed Logins</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersList as $u): ?>
                            <tr>
                                <td><strong>#<?php echo $u['user_id']; ?></strong></td>
                                <td>
                                    <strong><?php echo sanitize_output($u['full_name']); ?></strong>
                                    <?php if ($u['user_id'] == $currentAdminId): ?>
                                        <span class="badge bg-primary ms-1">You</span>
                                    <?php endif; ?>
                                    <br><small class="text-muted"><?php echo sanitize_output($u['email']); ?></small>
                                </td>
                                <td><code><?php echo sanitize_output($u['phone_number']); ?></code></td>
                                <td>
                                    <?php if ($u['role_name'] === 'Administrator'): ?>
                                        <span class="badge bg-danger"><i class="bi bi-shield-lock me-1"></i>Administrator</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark"><i class="bi bi-person me-1"></i>User</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$u['is_active'] === 1): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Deactivated / Locked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['failed_attempts'] > 0): ?>
                                        <span class="badge bg-warning text-dark"><?php echo $u['failed_attempts']; ?> failed</span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo date('M d, Y', strtotime($u['created_at'])); ?></small></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <!-- Role Change Form -->
                                        <form action="<?php echo BASE_URL; ?>/admin/users.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="change_role">
                                            <input type="hidden" name="target_user_id" value="<?php echo $u['user_id']; ?>">
                                            <?php if ($u['role_id'] == 1): ?>
                                                <input type="hidden" name="new_role_id" value="2">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" <?php echo ($u['user_id'] == $currentAdminId) ? 'disabled' : ''; ?> title="Promote to Admin">
                                                    Make Admin
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="new_role_id" value="1">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" <?php echo ($u['user_id'] == $currentAdminId) ? 'disabled' : ''; ?> title="Demote to User">
                                                    Make User
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Status Toggle Form -->
                                        <form action="<?php echo BASE_URL; ?>/admin/users.php" method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="target_user_id" value="<?php echo $u['user_id']; ?>">
                                            <?php if ((int)$u['is_active'] === 1): ?>
                                                <button type="submit" class="btn btn-sm btn-warning text-dark" <?php echo ($u['user_id'] == $currentAdminId) ? 'disabled' : ''; ?> title="Deactivate Account">
                                                    Deactivate
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-sm btn-success" title="Activate / Unlock Account">
                                                    Activate
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
