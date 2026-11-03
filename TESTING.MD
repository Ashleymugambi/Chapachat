ChapaChat – User Acceptance Testing (UAT) Manual
This document provides step-by-step test cases to verify that ChapaChat – Privacy-Aware Messaging System complies with all functional, security, and privacy requirements outlined in the project proposal.

________________________________________
Test Suite 1: Account Lockout after 3 Failed Login Attempts
Test Case ID	TC-01
Objective	Verify that entering an incorrect password 3 consecutive times deactivates the user account and logs an ACCOUNT_LOCKED audit event.
Prerequisites	User account john@chapachat.test exists in database (failed_attempts = 0, is_active = 1).
Test Steps	1. Navigate to http://localhost/chapachat/login.php
2. Enter email john@chapachat.test and wrong password wrongpass1. Click Continue.

3. Repeat step 2 two more times with wrongpass2 and wrongpass3.
Expected Result	- On 1st & 2nd attempt: Display error "Incorrect email or password."
- On 3rd attempt: Display error "Your account has been locked due to 3 consecutive failed login attempts."
- In MySQL users table: is_active is set to 0 and failed_attempts is 3.
- In audit_log table: Action ACCOUNT_LOCKED is logged for User ID 2.

- Subsequent login attempts for john@chapachat.test display account deactivated message.
Pass / Fail	PASS

________________________________________
Test Suite 2: Multi-Factor Authentication (OTP Expiry & Validation)
Test Case ID	TC-02
Objective	Verify that a 6-digit OTP code is generated upon successful password authentication, and an expired or incorrect code is rejected.
Prerequisites	User account jane@chapachat.test is active.
Test Steps	1. Log in with jane@chapachat.test / User@1234. Redirected to verify_otp.php.
2. Note the generated OTP displayed in the DEMO MODE banner (e.g., 123456).
3. Enter an incorrect code 000000 and click Verify.

4. Now enter the correct OTP code and click Verify.
Expected Result	- Step 3: Displays error "Incorrect verification code. Please check and try again." Log OTP_FAILED created.

- Step 4: Successfully logs in, marks OTP as used (is_used = 1 in otp_code table), logs LOGIN in audit log, and opens the user dashboard.
Pass / Fail	PASS

________________________________________
Test Suite 3: Unauthorized Message Viewing Prevention
Test Case ID	TC-03
Objective	Verify that a user cannot read another user's private message by manually modifying the URL parameter view_message.php?id=X.
Prerequisites	Message ID 1 exists between User 1 (Admin) and User 2 (John). User 3 (Jane) is logged in.
Test Steps	1. Log in as jane@chapachat.test (User ID 3).

2. Type http://localhost/chapachat/view_message.php?id=1 directly in browser address bar.
Expected Result	- Access is denied. Page renders error card: "Message not found or you do not have authorization to view it."
- Decrypted payload is NOT displayed.

- In audit_log table: Action UNAUTHORIZED_ACCESS_ATTEMPT is logged for User ID 3.
Pass / Fail	PASS

________________________________________
Test Suite 4: Role-Based Access Control (RBAC) Enforcement
Test Case ID	TC-04
Objective	Verify that non-administrator users are blocked from accessing /admin pages and an HTTP 403 Access Denied page is returned and logged.
Prerequisites	Logged in as standard user john@chapachat.test.
Test Steps	1. Navigate to http://localhost/chapachat/admin/index.php or http://localhost/chapachat/admin/users.php.
Expected Result	- System returns HTTP 403 response code.
- Displays red Access Denied page banner.
- Navbar does NOT display Admin menu links.

- In audit_log table: Action ACCESS_DENIED is logged with details of requested admin URL.
Pass / Fail	PASS

________________________________________
Test Suite 5: Secure Message Deletion & Zeroization
Test Case ID	TC-05
Objective	Verify that deleting a sent message overwrites encrypted_text with an empty string in MySQL so that content is permanently unrecoverable.
Prerequisites	Logged in as john@chapachat.test. Compose and send a message "Secret Meeting at 10 AM" to jane@chapachat.test.
Test Steps	1. Go to Sent Messages (sent.php).
2. Click Delete next to the sent message and confirm prompt.
3. Check Inbox as jane@chapachat.test.

4. Inspect database table message in phpMyAdmin.
Expected Result	- Message is removed from Sent list for John and Inbox list for Jane.
- In MySQL message table: encrypted_text is "" (empty string), is_deleted = 1, and deleted_at has timestamp.
- In audit_log table: Action MESSAGE_DELETED is logged with Message ID only.

- Content cannot be recovered by sender, recipient, or administrator.
Pass / Fail	PASS

________________________________________
Test Suite 6: Privacy-Aware Audit Log Inspection
Test Case ID	TC-06
Objective	Verify that the audit log records user actions and timestamps but NEVER contains plaintext passwords, OTPs, or message body content.
Prerequisites	System has registered users, logged in, sent messages, and performed admin actions. Logged in as admin@chapachat.test.
Test Steps	1. Navigate to Audit Logs (admin/audit_log.php).
2. Review log entries for actions REGISTER, LOGIN, MESSAGE_SENT, PROFILE_UPDATED, PASSWORD_CHANGED, MESSAGE_DELETED.

3. Run SQL query: SELECT * FROM audit_log WHERE details LIKE '%Secret%' OR details LIKE '%User@1234%';
Expected Result	- Log table displays action names, timestamps, user IDs, details (e.g., Message ID: 4), and IP addresses.
- No message body text, raw passwords, or OTP codes appear in the details column.

- SQL query returns 0 rows. Privacy constraint is fully satisfied.
Pass / Fail	PASS

