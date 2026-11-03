<?php
/**
 * Common Footer (includes/footer.php)
 * Renders page footer, Bootstrap 5 JS bundle, and app JS.
 */
?>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white-50 py-3 mt-auto">
        <div class="container text-center fs-7">
            <div class="row align-items-center">
                <div class="col-md-6 text-md-start mb-2 mb-md-0">
                    <span class="fw-bold text-white"><?php echo APP_NAME; ?></span> &mdash; <?php echo APP_TAGLINE; ?>
                </div>
                <div class="col-md-6 text-md-end">
                    <small>&copy; <?php echo date('Y'); ?> ChapaChat Kenya. Secured with RBAC, OTP &amp; AES-256.</small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom Application JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>
</body>
</html>
