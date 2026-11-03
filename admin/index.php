<?php
/**
 * Administrator Dashboard (admin/index.php)
 * Protected by require_role('Administrator')
 */

$pageTitle = "Admin Dashboard";
require_once __DIR__ . '/../includes/auth.php';

require_role('Administrator');

$totalUsers = 0;
$activeUsers = 0;
$messagesToday = 0;
$securityEventsCount = 0;
$recentLogs = [];

try {
    $db = get_db_connection();

    // 1. Total Users
    $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // 2. Active Users
    $activeUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();

    // 3. Messages Sent Today
    $messagesToday = (int)$db->query("SELECT COUNT(*) FROM message WHERE DATE(sent_at) = CURDATE()")->fetchColumn();

    // 4. Recent Security Events Count (FAILED_LOGIN, ACCOUNT_LOCKED, ACCESS_DENIED, UNAUTHORIZED_ACCESS_ATTEMPT)
    $securityEventsCount = (int)$db->query("
        SELECT COUNT(*) 
        FROM audit_log 
        WHERE action IN ('FAILED_LOGIN', 'ACCOUNT_LOCKED', 'ACCESS_DENIED', 'UNAUTHORIZED_ACCESS_ATTEMPT', 'UNAUTHORIZED_DELETE_ATTEMPT', 'LOGIN_ATTEMPT_LOCKED', 'OTP_FAILED')
    ")->fetchColumn();

    // 5. Recent 5 Security Audit Events
    $stmt = $db->query("
        SELECT a.log_id, a.action, a.details, a.ip_address, a.log_time, u.full_name, u.email
        FROM audit_log a
        LEFT JOIN users u ON a.user_id = u.user_id
        ORDER BY a.log_id DESC
        LIMIT 5
    ");
    $recentLogs = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Admin dashboard query error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <!-- Header Banner -->
    <div class="p-4 mb-4 bg-white rounded-3 shadow-sm border-start border-4 border-danger">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-1 text-danger"><i class="bi bi-shield-lock-fill me-2"></i>Administrator Control Panel</h2>
                <p class="text-muted mb-0">System status, security monitoring, user RBAC management &amp; backups</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-outline-danger me-2">
                    <i class="bi bi-people-fill"></i> Users
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/backup.php" class="btn btn-danger">
                    <i class="bi bi-database-down"></i> Backups
                </a>
            </div>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card shadow-sm border-0 h-100" style="border-left-color: var(--cc-info) !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: var(--cc-info-soft); color: var(--cc-info);">
                        <i class="bi bi-people-fill fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Total Registered</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo $totalUsers; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card shadow-sm border-0 h-100" style="border-left-color: var(--cc-success) !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: var(--cc-success-soft); color: var(--cc-success);">
                        <i class="bi bi-person-check-fill fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Active Accounts</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo $activeUsers; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card shadow-sm border-0 h-100" style="border-left-color: var(--cc-accent) !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: var(--cc-accent-soft); color: var(--cc-accent);">
                        <i class="bi bi-chat-dots-fill fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Sent Today</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo $messagesToday; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card stat-card shadow-sm border-0 h-100" style="border-left-color: var(--cc-danger) !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: var(--cc-danger-soft); color: var(--cc-danger);">
                        <i class="bi bi-shield-exclamation fs-3"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Security Incidents</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo $securityEventsCount; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Audit Preview Table -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <h5 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-danger"></i>Recent System Audit Events</h5>
            <a href="<?php echo BASE_URL; ?>/admin/audit_log.php" class="btn btn-sm btn-outline-danger">Full Audit Log Viewer</a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recentLogs)): ?>
                <div class="text-center py-4 text-muted">No audit logs recorded yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-7">
                        <thead class="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i:s', strtotime($log['log_time'])); ?></td>
                                    <td>
                                        <?php if (!empty($log['full_name'])): ?>
                                            <strong><?php echo sanitize_output($log['full_name']); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">Guest / System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $action = $log['action'];
                                            $badgeClass = 'bg-secondary';
                                            if (in_array($action, ['ACCOUNT_LOCKED', 'ACCESS_DENIED', 'UNAUTHORIZED_ACCESS_ATTEMPT', 'UNAUTHORIZED_DELETE_ATTEMPT', 'LOGIN_ATTEMPT_LOCKED', 'OTP_FAILED'])) {
                                                $badgeClass = 'bg-danger';
                                            } elseif (in_array($action, ['LOGIN', 'REGISTER', 'BACKUP_CREATED'])) {
                                                $badgeClass = 'bg-success';
                                            } elseif (in_array($action, ['ROLE_CHANGED', 'PASSWORD_CHANGED'])) {
                                                $badgeClass = 'bg-warning text-dark';
                                            }
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo sanitize_output($action); ?></span>
                                    </td>
                                    <td><?php echo sanitize_output($log['details']); ?></td>
                                    <td><code><?php echo sanitize_output($log['ip_address']); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
