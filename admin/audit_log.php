<?php
/**
 * System Audit Log Viewer (admin/audit_log.php)
 * Privacy-Aware Audit Log: Displays actions, timestamps, users & IPs.
 * NEVER displays message body content.
 */

$pageTitle = "Audit Logs";
require_once __DIR__ . '/../includes/auth.php';

require_role('Administrator');

$db = get_db_connection();

$filterAction = trim($_GET['action_filter'] ?? '');
$filterDate   = trim($_GET['date_filter'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = 20;
$offset       = ($page - 1) * $limit;

$whereClauses = [];
$params       = [];

if (!empty($filterAction)) {
    $whereClauses[] = "a.action = :action";
    $params[':action'] = $filterAction;
}

if (!empty($filterDate)) {
    $whereClauses[] = "DATE(a.log_time) = :log_date";
    $params[':log_date'] = $filterDate;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$totalLogs = 0;
$logs = [];
$distinctActions = [];

try {
    // Distinct actions for dropdown filter
    $actionStmt = $db->query("SELECT DISTINCT action FROM audit_log ORDER BY action ASC");
    $distinctActions = $actionStmt->fetchAll(PDO::FETCH_COLUMN);

    // Total Count for Pagination
    $countSql = "SELECT COUNT(*) FROM audit_log a {$whereSql}";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalLogs = (int)$countStmt->fetchColumn();

    $totalPages = max(1, ceil($totalLogs / $limit));

    // Fetch Paginated Logs (Newest First)
    $logSql = "
        SELECT a.log_id, a.user_id, a.action, a.details, a.ip_address, a.log_time,
               u.full_name, u.email
        FROM audit_log a
        LEFT JOIN users u ON a.user_id = u.user_id
        {$whereSql}
        ORDER BY a.log_id DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $db->prepare($logSql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Audit log query error: " . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-0 text-danger"><i class="bi bi-journal-text me-2"></i>Privacy-Aware Audit Logs</h3>
            <p class="text-muted mb-0">System events, security audits &amp; activity logs (20 per page, newest first)</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/admin/index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Admin Dashboard
        </a>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 rounded-3 mb-4">
        <div class="card-body p-3 bg-light">
            <form action="<?php echo BASE_URL; ?>/admin/audit_log.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label for="action_filter" class="form-label mb-1 fw-medium small">Filter by Action</label>
                    <select name="action_filter" id="action_filter" class="form-select form-select-sm">
                        <option value="">-- All Security Actions --</option>
                        <?php foreach ($distinctActions as $act): ?>
                            <option value="<?php echo sanitize_output($act); ?>" <?php echo ($filterAction === $act) ? 'selected' : ''; ?>>
                                <?php echo sanitize_output($act); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="date_filter" class="form-label mb-1 fw-medium small">Filter by Date</label>
                    <input type="date" name="date_filter" id="date_filter" class="form-control form-control-sm" value="<?php echo sanitize_output($filterDate); ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2 mt-4">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-filter"></i> Apply Filters
                    </button>
                    <a href="<?php echo BASE_URL; ?>/admin/audit_log.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Log Table -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-x display-4 d-block mb-3 text-secondary"></i>
                    <h5>No audit log entries match your filter.</h5>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 fs-7">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Log Timestamp</th>
                                <th>User Account</th>
                                <th>Action Event</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><strong>#<?php echo $log['log_id']; ?></strong></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['log_time'])); ?></td>
                                    <td>
                                        <?php if (!empty($log['full_name'])): ?>
                                            <strong><?php echo sanitize_output($log['full_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo sanitize_output($log['email']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Guest / System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $act = $log['action'];
                                            $badgeClass = 'bg-secondary';
                                            if (in_array($act, ['ACCOUNT_LOCKED', 'ACCESS_DENIED', 'UNAUTHORIZED_ACCESS_ATTEMPT', 'UNAUTHORIZED_DELETE_ATTEMPT'])) {
                                                $badgeClass = 'bg-danger';
                                            } elseif (in_array($act, ['LOGIN', 'REGISTER', 'BACKUP_CREATED'])) {
                                                $badgeClass = 'bg-success';
                                            } elseif (in_array($act, ['ROLE_CHANGED', 'USER_STATUS_CHANGED', 'PASSWORD_CHANGED'])) {
                                                $badgeClass = 'bg-warning text-dark';
                                            } elseif (in_array($act, ['MESSAGE_SENT', 'MESSAGE_DELETED'])) {
                                                $badgeClass = 'bg-info text-dark';
                                            }
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>"><?php echo sanitize_output($act); ?></span>
                                    </td>
                                    <td><code><?php echo sanitize_output($log['details']); ?></code></td>
                                    <td><code><?php echo sanitize_output($log['ip_address']); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <?php if ($totalPages > 1): ?>
                    <div class="p-3 bg-light d-flex justify-content-between align-items-center border-top">
                        <small class="text-muted">Total Logs: <?php echo $totalLogs; ?> | Page <?php echo $page; ?> of <?php echo $totalPages; ?></small>
                        <nav aria-label="Audit log pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&action_filter=<?php echo urlencode($filterAction); ?>&date_filter=<?php echo urlencode($filterDate); ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&action_filter=<?php echo urlencode($filterAction); ?>&date_filter=<?php echo urlencode($filterDate); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&action_filter=<?php echo urlencode($filterAction); ?>&date_filter=<?php echo urlencode($filterDate); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
