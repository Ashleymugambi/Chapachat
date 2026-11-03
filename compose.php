<?php
/**
 * Compose Message Page (compose.php)
 * Flowchart: Send Message, DFD Process 2.0
 */

$pageTitle = "Compose Message";
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/crypto.php';

require_login();

$userId = get_current_user_id();
$errors = [];
$successMsg = '';
$recipients = [];
$selectedReceiverId = (int)($_GET['to'] ?? 0);
$messageText = '';

// Fetch list of active users except current user
try {
    $db = get_db_connection();
    $stmt = $db->prepare("
        SELECT user_id, full_name, email, phone_number
        FROM users
        WHERE is_active = 1 AND user_id != :current_id
        ORDER BY full_name ASC
    ");
    $stmt->execute([':current_id' => $userId]);
    $recipients = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Recipient query error: " . $e->getMessage());
    $errors[] = "Failed to load recipient list.";
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "Invalid CSRF security token. Please try submitting again.";
    } else {
        $selectedReceiverId = (int)($_POST['receiver_id'] ?? 0);
        $messageText = trim($_POST['encrypted_text_input'] ?? '');

        // Validation Checks
        if ($selectedReceiverId <= 0) {
            $errors[] = "Please select a valid message recipient.";
        }

        if ($messageText === '') {
            $errors[] = "Message content cannot be empty.";
        } elseif (mb_strlen($messageText) > 1000) {
            $errors[] = "Message exceeds the maximum limit of 1000 characters.";
        }

        // Validate recipient exists and is active
        if (empty($errors)) {
            try {
                $checkStmt = $db->prepare("SELECT user_id FROM users WHERE user_id = :id AND is_active = 1 AND user_id != :current_id LIMIT 1");
                $checkStmt->execute([':id' => $selectedReceiverId, ':current_id' => $userId]);
                if (!$checkStmt->fetch()) {
                    $errors[] = "Selected recipient is invalid or inactive.";
                }
            } catch (Exception $e) {
                $errors[] = "Recipient validation failed.";
            }
        }

        // Process Encryption & Saving
        if (empty($errors)) {
            try {
                // 1. Encrypt message body using AES-256-CBC with APP_KEY
                $encryptedText = encrypt_text($messageText, APP_KEY);

                // 2. Insert into database
                $insertStmt = $db->prepare("
                    INSERT INTO message (sender_id, receiver_id, encrypted_text, sent_at, is_read, is_deleted)
                    VALUES (:sender_id, :receiver_id, :encrypted_text, NOW(), 0, 0)
                ");
                $insertStmt->execute([
                    ':sender_id'      => $userId,
                    ':receiver_id'    => $selectedReceiverId,
                    ':encrypted_text' => $encryptedText
                ]);

                $messageId = (int)$db->lastInsertId();

                // 3. Privacy-Aware Audit Log: Log Message ID ONLY, NEVER text!
                log_action('MESSAGE_SENT', "Message ID: {$messageId}", $userId);

                $successMsg = "Message encrypted and sent successfully!";
                $messageText = '';
                $selectedReceiverId = 0;

            } catch (Exception $e) {
                error_log("Send message error: " . $e->getMessage());
                $errors[] = "Failed to send encrypted message. Please try again.";
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow border-0 rounded-3">
                <div class="card-header card-header-primary py-3">
                    <h4 class="mb-0 text-white"><i class="bi bi-pencil-square me-2"></i>Compose Encrypted Message</h4>
                </div>
                <div class="card-body p-4">

                    <?php if (!empty($successMsg)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i><?php echo sanitize_output($successMsg); ?>
                            <div class="mt-2">
                                <a href="<?php echo BASE_URL; ?>/sent.php" class="btn btn-sm btn-success">View Sent Messages</a>
                                <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-sm btn-outline-success">Return to Dashboard</a>
                            </div>
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

                    <form action="<?php echo BASE_URL; ?>/compose.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <div class="mb-3">
                            <label for="receiver_id" class="form-label fw-medium">Select Recipient</label>
                            <select class="form-select form-select-lg" id="receiver_id" name="receiver_id" required>
                                <option value="">-- Choose active recipient --</option>
                                <?php foreach ($recipients as $recipient): ?>
                                    <option value="<?php echo $recipient['user_id']; ?>" <?php echo ($selectedReceiverId == $recipient['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitize_output($recipient['full_name']); ?> (<?php echo sanitize_output($recipient['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="encrypted_text_input" class="form-label fw-medium mb-0">Message Content</label>
                                <span class="badge bg-secondary" id="charCount">0 / 1000</span>
                            </div>
                            <textarea class="form-control" id="encrypted_text_input" name="encrypted_text_input" rows="6" maxlength="1000" required placeholder="Type your secure message here... (Max 1000 characters)"><?php echo sanitize_output($messageText); ?></textarea>
                            <small class="form-text text-muted">
                                <i class="bi bi-shield-lock text-success me-1"></i>
                                Message will be encrypted with AES-256-CBC prior to database storage.
                            </small>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold">
                                <i class="bi bi-send-fill me-1"></i> Encrypt &amp; Send Message
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
