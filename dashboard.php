<?php
/**
 * User Dashboard Page (dashboard.php)
 */

$pageTitle = "Dashboard";
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = get_current_user_id();
$unreadCount = 0;
$sentCount = 0;
$receivedCount = 0;
$recentMessages = [];

try {
    $db = get_db_connection();

    // 1. Unread Inbox Count
    $stmt = $db->prepare("SELECT COUNT(*) FROM message WHERE receiver_id = :id AND is_read = 0 AND is_deleted = 0");
    $stmt->execute([':id' => $userId]);
    $unreadCount = (int)$stmt->fetchColumn();

    // 2. Total Sent Count
    $stmt = $db->prepare("SELECT COUNT(*) FROM message WHERE sender_id = :id AND is_deleted = 0");
    $stmt->execute([':id' => $userId]);
    $sentCount = (int)$stmt->fetchColumn();

    // 3. Total Received Count
    $stmt = $db->prepare("SELECT COUNT(*) FROM message WHERE receiver_id = :id AND is_deleted = 0");
    $stmt->execute([':id' => $userId]);
    $receivedCount = (int)$stmt->fetchColumn();

    // 4. Fetch 5 Recent Received Messages
    $stmt = $db->prepare("
        SELECT m.message_id, m.sent_at, m.is_read, u.full_name AS sender_name, u.email AS sender_email
        FROM message m
        JOIN users u ON m.sender_id = u.user_id
        WHERE m.receiver_id = :id AND m.is_deleted = 0
        ORDER BY m.sent_at DESC
        LIMIT 5
    ");
    $stmt->execute([':id' => $userId]);
    $recentMessages = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
}

include __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
    <!-- Welcome Header -->
    <div class="p-4 mb-4 bg-white rounded-3 shadow-sm border-start border-4 border-primary">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bold mb-1">Welcome back, <?php echo sanitize_output(get_current_user_name()); ?>!</h2>
                <p class="text-muted mb-0">
                    <span class="badge bg-primary me-2"><?php echo sanitize_output(get_current_role_name()); ?></span>
                    ChapaChat Privacy-Aware Messaging Center
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="<?php echo BASE_URL; ?>/compose.php" class="btn btn-primary fw-semibold">
                    <i class="bi bi-pencil-square me-1"></i> New Message
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: var(--cc-accent-soft); color: var(--cc-accent);">
                        <i class="bi bi-envelope-exclamation-fill fs-2"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Unread Messages</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo $unreadCount; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card shadow-sm border-0 h-100" style="border-left-color: var(--cc-success) !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: var(--cc-success-soft); color: var(--cc-success);">
                        <i class="bi bi-inbox-fill fs-2"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Inbox Messages</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo $receivedCount; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card stat-card shadow-sm border-0 h-100" style="border-left-color: var(--cc-info) !important;">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle p-3 me-3" style="background: var(--cc-info-soft); color: var(--cc-info);">
                        <i class="bi bi-send-fill fs-2"></i>
                    </div>
                    <div>
                        <h6 class="text-muted mb-1">Sent Messages</h6>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo $sentCount; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Messages Table -->
    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
            <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Messages</h5>
            <a href="<?php echo BASE_URL; ?>/inbox.php" class="btn btn-sm btn-outline-primary">View All Inbox</a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recentMessages)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-chat-left-dots display-4 d-block mb-2 text-secondary"></i>
                    <p class="mb-0">You have no received messages yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sender</th>
                                <th>Date &amp; Time</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMessages as $msg): ?>
                                <tr class="<?php echo ($msg['is_read'] == 0) ? 'table-unread' : ''; ?>">
                                    <td>
                                        <i class="bi bi-person-circle me-1 text-secondary"></i>
                                        <strong><?php echo sanitize_output($msg['sender_name']); ?></strong>
                                        <span class="text-muted small">&lt;<?php echo sanitize_output($msg['sender_email']); ?>&gt;</span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($msg['sent_at'])); ?></td>
                                    <td>
                                        <?php if ($msg['is_read'] == 0): ?>
                                            <span class="badge bg-danger">Unread</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Read</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?php echo BASE_URL; ?>/view_message.php?id=<?php echo $msg['message_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i> View Message
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
