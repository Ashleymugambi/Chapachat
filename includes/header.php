<?php
/**
 * Common Header and Navigation Bar (includes/header.php)
 * Renders Bootstrap 5 layout and role-aware navbar.
 */
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? sanitize_output($pageTitle) . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <!-- Google Fonts Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center fw-bold fs-4" href="<?php echo BASE_URL; ?>/index.php">
                <img src="<?php echo BASE_URL; ?>/assets/logo.png" alt="ChapaChat Logo" height="36" width="36">
                <span>Chapa<span style="color: #F5B731;">chat</span></span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-brand-nav navbar-nav ms-auto align-items-lg-center">
                    <?php if (is_logged_in()): ?>
                        <?php $role = get_current_role_name(); ?>
                        
                        <?php if ($role === 'Administrator'): ?>
                            <!-- Admin Navigation -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'admin') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/index.php">
                                    <i class="bi bi-speedometer2"></i> Admin Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/users.php">
                                    <i class="bi bi-people-fill"></i> Manage Users
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'audit_log.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/audit_log.php">
                                    <i class="bi bi-journal-text"></i> Audit Logs
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'backup.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/backup.php">
                                    <i class="bi bi-database-down"></i> Backups
                                </a>
                            </li>
                        <?php else: ?>
                            <!-- Regular User Navigation -->
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/dashboard.php">
                                    <i class="bi bi-house-door-fill"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'compose.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/compose.php">
                                    <i class="bi bi-pencil-square"></i> Compose
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'inbox.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/inbox.php">
                                    <i class="bi bi-inbox-fill"></i> Inbox
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'sent.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/sent.php">
                                    <i class="bi bi-send-fill"></i> Sent
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Common Profile & User Info -->
                        <li class="nav-item dropdown ms-lg-3">
                            <a class="nav-link dropdown-toggle btn btn-outline-light text-white px-3 py-1 mt-1 mt-lg-0" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i>
                                <?php echo sanitize_output(get_current_user_name()); ?>
                                <span class="badge bg-warning text-dark ms-1"><?php echo sanitize_output($role); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>/profile.php">
                                        <i class="bi bi-gear-fill me-2 text-primary"></i> Profile Settings
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>

                    <?php else: ?>
                        <!-- Guest Navigation -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'login.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/login.php">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-warning px-3 py-1 ms-lg-2 fw-semibold" href="<?php echo BASE_URL; ?>/register.php">
                                <i class="bi bi-person-plus-fill me-1"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="flex-grow-1">
