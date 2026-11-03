<?php
/**
 * Secure Message Deletion Handler (delete_message.php)
 * Flowchart: Delete Message, DFD Process 3.0
 * Zeroizes encrypted_text payload so content is completely unrecoverable.
 */

require_once __DIR__ . '/includes/auth.php';
require_login();

$userId = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/sent.php");
    exit();
}

$token = $_POST['csrf_token'] ?? '';
$messageId = (int)($_POST['message_id'] ?? 0);

if (!verify_csrf_token($token)) {
    die("Invalid CSRF security token.");
}

if ($messageId <= 0) {
    header("Location: " . BASE_URL . "/sent.php?error=" . urlencode("Invalid message ID specified."));
    exit();
}

try {
    $db = get_db_connection();

    // 1. Verify that the logged-in user is the sender of this message
    $checkStmt = $db->prepare("SELECT message_id, sender_id FROM message WHERE message_id = :id LIMIT 1");
    $checkStmt->execute([':id' => $messageId]);
    $msg = $checkStmt->fetch();

    if (!$msg) {
        header("Location: " . BASE_URL . "/sent.php?error=" . urlencode("Message not found."));
        exit();
    }

    if ((int)$msg['sender_id'] !== $userId) {
        // Log unauthorized delete attempt
        log_action('UNAUTHORIZED_DELETE_ATTEMPT', "User attempted to delete message ID {$messageId} owned by User ID {$msg['sender_id']}", $userId);
        
        http_response_code(403);
        include __DIR__ . '/includes/header.php';
        ?>
        <div class="container my-5 text-center">
            <div class="alert alert-danger py-4">
                <i class="bi bi-x-circle display-1 text-danger d-block mb-3"></i>
                <h3>Not Allowed</h3>
                <p>Only the original sender of a message is authorized to delete it.</p>
                <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-primary mt-3">Return to Dashboard</a>
            </div>
        </div>
        <?php
        include __DIR__ . '/includes/footer.php';
        exit();
    }

    // 2. Perform SECURE DELETION: Overwrite encrypted_text with empty string & set is_deleted=1, deleted_at=NOW()
    $deleteStmt = $db->prepare("
        UPDATE message
        SET encrypted_text = '', is_deleted = 1, deleted_at = NOW()
        WHERE message_id = :id AND sender_id = :user_id
    ");
    $deleteStmt->execute([':id' => $messageId, ':user_id' => $userId]);

    // 3. Privacy-Aware Audit Log: Log Message ID ONLY
    log_action('MESSAGE_DELETED', "Message ID: {$messageId}", $userId);

    header("Location: " . BASE_URL . "/sent.php?msg=" . urlencode("Message deleted. Original content permanently unrecoverable."));
    exit();

} catch (Exception $e) {
    error_log("Delete message error: " . $e->getMessage());
    header("Location: " . BASE_URL . "/sent.php?error=" . urlencode("Failed to delete message. Please try again."));
    exit();
}
