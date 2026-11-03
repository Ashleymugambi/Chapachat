<?php
/**
 * Sent Messages Page (sent.php)
 * Lists sent messages for logged in user (is_deleted = 0)
 */

$pageTitle = "Sent Messages";
require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = get_current_user_id();
$messages = [];
$totalMessages = 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$msgSuccess = $_GET['msg'] ?? '';
$msgError   = $_GET['error'] ?? '';

try {
    $db = get_db_connection();

    // Total Count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM message WHERE sender_id = :id AND is_deleted = 0");
    $countStmt->execute([':id' => $userId]);
    $totalMessages = (int)$countStmt->fetchColumn();

    $totalPages = max(1, ceil($totalMessages / $limit));

    // Fetch Sent Messages
    $stmt = $db->prepare("
        SELECT m.message_id, m.sent_at, m.is_read, u.full_name AS receiver_name, u.email AS receiver_email
        FROM message m
        JOIN users u ON m.receiver_id = u.user_id
        WHERE m.sender_id = :id AND m.is_deleted = 0
        ORDER BY m.sent_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Sent messages query error: " . $e->getMessage());
}

include __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-send-fill text-info me-2"></i>Sent Messages</h3>
            <p class="text-muted mb-0">Messages sent by you to other users</p>
        </div>
        <a href="<?php echo BASE_URL; ?>/compose.php" class="btn btn-primary fw-semibold">
            <i class="bi bi-pencil-square me-1"></i> Compose New
        </a>
    </div>

    <?php if (!empty($msgSuccess)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo sanitize_output($msgSuccess); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($msgError)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo sanitize_output($msgError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-body p-0">
            <?php if (empty($messages)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-send-exclamation display-4 d-block mb-3 text-secondary"></i>
                    <h5>No sent messages</h5>
                    <p class="mb-0">You have not sent any messages yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Recipient</th>
                                <th>Date &amp; Time Sent</th>
                                <th>Recipient Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $msg): ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-person-circle me-1 text-secondary"></i>
                                        <strong><?php echo sanitize_output($msg['receiver_name']); ?></strong>
                                        <span class="text-muted small">&lt;<?php echo sanitize_output($msg['receiver_email']); ?>&gt;</span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i:s', strtotime($msg['sent_at'])); ?></td>
                                    <td>
                                        <?php if ($msg['is_read'] == 1): ?>
                                            <span class="badge bg-success"><i class="bi bi-check2-all me-1"></i>Read</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-check me-1"></i>Delivered</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo BASE_URL; ?>/view_message.php?id=<?php echo $msg['message_id']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <!-- Secure Deletion Trigger Form -->
                                            <form action="<?php echo BASE_URL; ?>/delete_message.php" method="POST" class="d-inline" onsubmit="return confirmAction('Are you sure you want to securely delete this message? The contents will be irreversibly erased from the database.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="p-3 bg-light d-flex justify-content-between align-items-center border-top">
                        <small class="text-muted">Showing page <?php echo $page; ?> of <?php echo $totalPages; ?></small>
                        <nav aria-label="Sent pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
