<?php
/**
 * View Message Page (view_message.php)
 * Dynamic decryption on demand for authorized sender or receiver ONLY.
 */

$pageTitle = "View Message";
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/crypto.php';

require_login();

$userId = get_current_user_id();
$messageId = (int)($_GET['id'] ?? 0);
$error = '';
$message = null;
$decryptedText = '';

if ($messageId <= 0) {
    $error = "Invalid message ID specified.";
} else {
    try {
        $db = get_db_connection();

        // Query enforcing RBAC: User MUST be sender OR receiver, and message NOT deleted
        $stmt = $db->prepare("
            SELECT m.message_id, m.sender_id, m.receiver_id, m.encrypted_text, m.sent_at, m.is_read, m.is_deleted,
                   u_sender.full_name AS sender_name, u_sender.email AS sender_email,
                   u_receiver.full_name AS receiver_name, u_receiver.email AS receiver_email
            FROM message m
            JOIN users u_sender ON m.sender_id = u_sender.user_id
            JOIN users u_receiver ON m.receiver_id = u_receiver.user_id
            WHERE m.message_id = :id 
              AND (m.sender_id = :sender_id OR m.receiver_id = :receiver_id)
              AND m.is_deleted = 0
            LIMIT 1
        ");
        // Native prepares (ATTR_EMULATE_PREPARES = false) forbid reusing a named placeholder
        $stmt->execute([':id' => $messageId, ':sender_id' => $userId, ':receiver_id' => $userId]);
        $message = $stmt->fetch();

        if (!$message) {
            $error = "Message not found or you do not have authorization to view it.";
            log_action('UNAUTHORIZED_ACCESS_ATTEMPT', "Attempted unauthorized view of message ID: {$messageId}", $userId);
        } else {
            // If viewer is recipient and message is unread, mark as read
            if ((int)$message['receiver_id'] === $userId && (int)$message['is_read'] === 0) {
                $markRead = $db->prepare("UPDATE message SET is_read = 1 WHERE message_id = :id");
                $markRead->execute([':id' => $messageId]);
                $message['is_read'] = 1;
            }

            // Decrypt message body dynamically using AES-256-CBC
            $decryptedText = decrypt_text($message['encrypted_text'], APP_KEY);
        }

    } catch (Exception $e) {
        error_log("View message error: " . $e->getMessage());
        $error = "A system error occurred while retrieving the message.";
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <?php if (!empty($error)): ?>
                <div class="card border-danger shadow-sm my-4">
                    <div class="card-header bg-danger text-white fw-bold">
                        <i class="bi bi-shield-lock-fill me-2"></i>Access Restriction
                    </div>
                    <div class="card-body text-center py-5">
                        <i class="bi bi-eye-slash-fill text-danger display-1 mb-3"></i>
                        <h4><?php echo sanitize_output($error); ?></h4>
                        <p class="text-muted">You can only access messages where you are explicitly the sender or receiver.</p>
                        <div class="mt-4">
                            <a href="<?php echo BASE_URL; ?>/inbox.php" class="btn btn-primary me-2">Go to Inbox</a>
                            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>

                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h4 class="mb-0 fw-bold text-primary">
                            <i class="bi bi-envelope-open-fill me-2"></i>Encrypted Message
                        </h4>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($message['sender_id'] == $userId): ?>
                                <span class="badge bg-info text-dark">Sent Message</span>
                                <!-- Secure Deletion Button -->
                                <form action="<?php echo BASE_URL; ?>/delete_message.php" method="POST" class="d-inline" onsubmit="return confirmAction('Are you sure you want to securely delete this message? The stored ciphertext will be permanently zeroized.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="message_id" value="<?php echo $message['message_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-success">Received Message</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-body p-4">
                        <!-- Message Metadata Header -->
                        <div class="row bg-light p-3 rounded mb-4 border">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <small class="text-muted d-block">From:</small>
                                <strong><?php echo sanitize_output($message['sender_name']); ?></strong> 
                                <span class="text-muted small">&lt;<?php echo sanitize_output($message['sender_email']); ?>&gt;</span>
                            </div>
                            <div class="col-md-6 text-md-end mb-2 mb-md-0">
                                <small class="text-muted d-block">To:</small>
                                <strong><?php echo sanitize_output($message['receiver_name']); ?></strong> 
                                <span class="text-muted small">&lt;<?php echo sanitize_output($message['receiver_email']); ?>&gt;</span>
                            </div>
                            <div class="col-12 mt-2 pt-2 border-top">
                                <small class="text-muted"><i class="bi bi-clock me-1"></i>Sent At: <?php echo date('F j, Y \a\t g:i A', strtotime($message['sent_at'])); ?></small>
                            </div>
                        </div>

                        <!-- Decrypted Content -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-success">
                                <i class="bi bi-unlock-fill me-1"></i>Decrypted Message Payload:
                            </label>
                            <div class="p-3 bg-white border rounded shadow-sm fs-5 text-break" style="white-space: pre-wrap; min-height: 120px;"><?php echo sanitize_output($decryptedText); ?></div>
                        </div>

                        <!-- Technical Security Inspection Accordion -->
                        <div class="accordion" id="securityAccordion">
                            <div class="accordion-item border">
                                <h2 class="accordion-header" id="headingCrypto">
                                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCrypto" aria-expanded="false" aria-controls="collapseCrypto">
                                        <i class="bi bi-code-square me-2 text-primary"></i>Inspect Raw Database Encrypted Ciphertext (AES-256-CBC)
                                    </button>
                                </h2>
                                <div id="collapseCrypto" class="accordion-collapse collapse" aria-labelledby="headingCrypto" data-bs-parent="#securityAccordion">
                                    <div class="accordion-body bg-dark text-light rounded-bottom">
                                        <small class="text-info d-block mb-1">Stored Base64 Package (16-byte random IV + Ciphertext):</small>
                                        <div class="code-cipher"><?php echo sanitize_output($message['encrypted_text']); ?></div>
                                        <small class="text-muted mt-2 d-block">This raw ciphertext is all that is stored in MySQL. System administrators and audit logs have no access to the unencrypted plaintext.</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="card-footer bg-light py-3 d-flex justify-content-between">
                        <a href="<?php echo ($message['sender_id'] == $userId) ? BASE_URL . '/sent.php' : BASE_URL . '/inbox.php'; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to <?php echo ($message['sender_id'] == $userId) ? 'Sent' : 'Inbox'; ?>
                        </a>
                        <?php if ($message['receiver_id'] == $userId): ?>
                            <a href="<?php echo BASE_URL; ?>/compose.php?to=<?php echo $message['sender_id']; ?>" class="btn btn-primary">
                                <i class="bi bi-reply-fill me-1"></i> Reply to Sender
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
