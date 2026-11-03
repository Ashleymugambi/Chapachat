/**
 * ChapaChat Client-Side JavaScript Helpers
 */
document.addEventListener('DOMContentLoaded', function () {
    
    // Character Counter for Message Compose
    const messageInput = document.getElementById('encrypted_text_input') || document.getElementById('message_body');
    const charCounter = document.getElementById('charCount');
    
    if (messageInput && charCounter) {
        const maxLen = messageInput.getAttribute('maxlength') || 1000;
        const updateCounter = () => {
            const currentLen = messageInput.value.length;
            charCounter.textContent = `${currentLen} / ${maxLen}`;
            if (currentLen >= maxLen) {
                charCounter.classList.add('text-danger');
            } else {
                charCounter.classList.remove('text-danger');
            }
        };
        messageInput.addEventListener('input', updateCounter);
        updateCounter();
    }

    // Auto-dismiss Alerts after 6 seconds (except OTP Demo alert)
    const alertList = document.querySelectorAll('.alert:not(.alert-demo-otp)');
    alertList.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 6000);
    });

    // Password Confirmation Verification on Forms
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordInput = document.getElementById('password');
    if (confirmPasswordInput && passwordInput) {
        confirmPasswordInput.addEventListener('input', function () {
            if (passwordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity("Passwords do not match");
            } else {
                confirmPasswordInput.setCustomValidity("");
            }
        });
    }
});

/**
 * Delete Confirmation Prompt
 */
function confirmAction(message) {
    return confirm(message || 'Are you sure you want to perform this action? This cannot be undone.');
}
