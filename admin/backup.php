<?php
/**
 * Database Backup and Recovery Module (admin/backup.php)
 * Backup & Restore database schema and data.
 * Protected by require_role('Administrator')
 */

$pageTitle = "Backup & Recovery";
require_once __DIR__ . '/../includes/auth.php';

require_role('Administrator');

$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}
if (!file_exists($backupDir . '/.htaccess')) {
    file_put_contents($backupDir . '/.htaccess', "# Deny access to all backup files\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n");
}

$errors = [];
$successMsg = $_GET['msg'] ?? '';
$adminId = get_current_user_id();

// Helper to generate pure PHP SQL export
function generate_sql_dump(PDO $db): string {
    $tables = ['roles', 'users', 'otp_code', 'message', 'audit_log'];
    $dump = "-- ChapaChat Database Backup Dump\n";
    $dump .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
    $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // Table structure
        $createStmt = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $dump .= $createStmt[1] . ";\n\n";

        // Table data
        $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $escapedCols = array_map(function ($c) { return "`" . $c . "`"; }, $cols);
                $escapedVals = array_map(function ($v) use ($db) {
                    return ($v === null) ? 'NULL' : $db->quote($v);
                }, array_values($row));

                $dump .= "INSERT INTO `{$table}` (" . implode(', ', $escapedCols) . ") VALUES (" . implode(', ', $escapedVals) . ");\n";
            }
            $dump .= "\n";
        }
    }

    $dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $dump;
}

// Handle Download Request
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $filePath = $backupDir . '/' . $file;

    if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $file) && file_exists($filePath)) {
        if (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit();
    } else {
        $errors[] = "Invalid or missing backup file for download.";
    }
}

// Handle Form Posts (Create / Restore / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!verify_csrf_token($token)) {
        $errors[] = "Invalid CSRF security token.";
    } elseif ($action === 'create_backup') {
        try {
            $db = get_db_connection();
            $dumpContent = generate_sql_dump($db);

            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filePath = $backupDir . '/' . $filename;

            if (file_put_contents($filePath, $dumpContent) !== false) {
                // Log BACKUP_CREATED
                log_action('BACKUP_CREATED', "Database backup created: {$filename}", $adminId);

                $successMsg = "Database backup file '{$filename}' created successfully.";
            } else {
                $errors[] = "Failed to write backup file to disk.";
            }
        } catch (Exception $e) {
            error_log("Backup creation error: " . $e->getMessage());
            $errors[] = "Failed to generate database backup.";
        }

    } elseif ($action === 'restore_backup') {
        $file = basename($_POST['backup_file'] ?? '');
        $filePath = $backupDir . '/' . $file;

        if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $file) || !file_exists($filePath)) {
            $errors[] = "Invalid backup file selected for restore.";
        } else {
            try {
                $db = get_db_connection();
                $sqlContent = file_get_contents($filePath);

                // Execute SQL script
                $db->exec($sqlContent);

                // Log BACKUP_RESTORED
                log_action('BACKUP_RESTORED', "Database restored from file: {$file}", $adminId);

                $successMsg = "Database successfully restored from '{$file}'. Deleted messages remain securely erased.";
            } catch (Exception $e) {
                error_log("Backup restore error: " . $e->getMessage());
                $errors[] = "Failed to restore database from backup file: " . $e->getMessage();
            }
        }
    }
}

// List Existing Backups
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/backup_*.sql');
    if (!empty($files)) {
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        foreach ($files as $f) {
            $backupFiles[] = [
                'name'    => basename($f),
                'size'    => round(filesize($f) / 1024, 2) . ' KB',
                'created' => date('Y-m-d H:i:s', filemtime($f))
            ];
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="fw-bold mb-0 text-danger"><i class="bi bi-database-down me-2"></i>Database Backup &amp; Recovery</h3>
            <p class="text-muted mb-0">Create and restore system database snapshots</p>
        </div>
        <div>
            <form action="<?php echo BASE_URL; ?>/admin/backup.php" method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="create_backup">
                <button type="submit" class="btn btn-danger fw-semibold">
                    <i class="bi bi-plus-circle-fill me-1"></i> Create New Backup
                </button>
            </form>
            <a href="<?php echo BASE_URL; ?>/admin/index.php" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
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

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-archive-fill text-primary me-2"></i>Available Database Backups</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($backupFiles)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-database-exclamation display-4 d-block mb-3 text-secondary"></i>
                    <h5>No database backup files found</h5>
                    <p class="mb-0">Click 'Create New Backup' above to generate a database snapshot.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Backup File Name</th>
                                <th>File Size</th>
                                <th>Creation Date &amp; Time</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backupFiles as $file): ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-file-earmark-code text-danger me-2"></i>
                                        <strong><?php echo sanitize_output($file['name']); ?></strong>
                                    </td>
                                    <td><code><?php echo $file['size']; ?></code></td>
                                    <td><?php echo $file['created']; ?></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo BASE_URL; ?>/admin/backup.php?download=<?php echo urlencode($file['name']); ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-download"></i> Download
                                            </a>
                                            <!-- Restore Form -->
                                            <form action="<?php echo BASE_URL; ?>/admin/backup.php" method="POST" class="d-inline" onsubmit="return confirmAction('WARNING: Restoring database from snapshot \'<?php echo $file['name']; ?>\' will overwrite current state. Proceed?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="restore_backup">
                                                <input type="hidden" name="backup_file" value="<?php echo sanitize_output($file['name']); ?>">
                                                <button type="submit" class="btn btn-warning text-dark">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Restore
                                                </button>
                                            </form>
                                        </div>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
