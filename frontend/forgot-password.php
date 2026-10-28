<?php
/**
 * ProjectSphere - Email Verification Code Password Reset System
 */
require_once __DIR__ . '/../backend/core/db.php';
require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Redirect if already logged in
if (is_admin()) {
    header('Location: ' . base_url('frontend/admin/dashboard.php'));
    exit;
} elseif (is_student()) {
    header('Location: ' . base_url('frontend/student/dashboard.php'));
    exit;
}

// Ensure password_resets table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS password_resets (
        user_id INT NOT NULL PRIMARY KEY,
        code_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Reset flow if user wants to start over
if (isset($_GET['new'])) {
    unset(
        $_SESSION['password_reset_email'],
        $_SESSION['password_reset_demo_code'],
        $_SESSION['password_reset_verified_user_id'],
        $_SESSION['password_reset_verified_hash'],
        $_SESSION['password_reset_verified_until']
    );
    header('Location: ' . base_url('frontend/forgot-password.php'));
    exit;
}

$error = '';
$notice = '';
$email = $_SESSION['password_reset_email'] ?? '';
$step = $email === '' ? 'request' : 'verify';
$verifiedUserId = (int)($_SESSION['password_reset_verified_user_id'] ?? 0);
$verifiedHash = $_SESSION['password_reset_verified_hash'] ?? '';
$verifiedUntil = (int)($_SESSION['password_reset_verified_until'] ?? 0);

// Check if verification permission is currently granted
if ($verifiedUserId > 0 && $verifiedUntil > time() && $email !== '') {
    $verifiedStmt = $pdo->prepare("
        SELECT u.email, pr.code_hash, pr.expires_at, pr.attempts
        FROM users u
        INNER JOIN password_resets pr ON pr.user_id = u.id
        WHERE u.id = :user_id
        LIMIT 1
    ");
    $verifiedStmt->execute([':user_id' => $verifiedUserId]);
    $verifiedReset = $verifiedStmt->fetch();

    if (
        $verifiedReset
        && strtolower(trim($verifiedReset['email'])) === $email
        && hash_equals($verifiedHash, $verifiedReset['code_hash'])
        && strtotime($verifiedReset['expires_at']) > time()
        && (int)$verifiedReset['attempts'] < 5
    ) {
        $step = 'password'; // Permission to change password GRANTED
    } else {
        unset(
            $_SESSION['password_reset_verified_user_id'],
            $_SESSION['password_reset_verified_hash'],
            $_SESSION['password_reset_verified_until']
        );
        $step = $email === '' ? 'request' : 'verify';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Security check failed. Please refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'request_code') {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $rateLimitKey = 'password_reset_' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local');
        unset(
            $_SESSION['password_reset_verified_user_id'],
            $_SESSION['password_reset_verified_hash'],
            $_SESSION['password_reset_verified_until'],
            $_SESSION['password_reset_demo_code']
        );

        if (is_rate_limited($rateLimitKey, 5, 900)) {
            $error = 'Too many requests. Please wait 15 minutes before trying again.';
            $step = 'request';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
            $step = 'request';
        } else {
            $userStmt = $pdo->prepare('SELECT id, username, email FROM users WHERE LOWER(TRIM(email)) = :email LIMIT 1');
            $userStmt->execute([':email' => $email]);
            $user = $userStmt->fetch();

            if (!$user) {
                // If account doesn't exist, provide clear error message
                $error = 'No account found with this email address. Please verify your email or register.';
                $step = 'request';
            } else {
                // Generate a cryptographically secure 6-digit code
                $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $codeHash = password_hash($code, PASSWORD_DEFAULT);

                // Save code with 10-minute expiry
                $saveStmt = $pdo->prepare("
                    INSERT INTO password_resets (user_id, code_hash, expires_at, attempts, created_at)
                    VALUES (:user_id, :code_hash, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, NOW())
                    ON DUPLICATE KEY UPDATE
                        code_hash = VALUES(code_hash),
                        expires_at = VALUES(expires_at),
                        attempts = 0,
                        created_at = NOW()
                ");
                $saveStmt->execute([
                    ':user_id' => $user['id'],
                    ':code_hash' => $codeHash,
                ]);

                // Email content
                $emailBody = "Hello,\n\n"
                           . "You requested a password reset for your ProjectSphere account.\n\n"
                           . "Your 6-digit verification code is:\n\n"
                           . "       {$code}\n\n"
                           . "This code is valid for 10 minutes.\n"
                           . "If you did not request this reset, you can safely ignore this email.\n\n"
                           . "Regards,\nProjectSphere Academic Portal";

                send_project_email($email, 'Your ProjectSphere Password Reset Code', $emailBody);

                record_rate_limit_attempt($rateLimitKey);
                $_SESSION['password_reset_email'] = $email;
                $step = 'verify';
                $notice = "A 6-digit verification code has been sent to your registered email. Please check your inbox or spam folder.";
            }
        }
    } elseif (($_POST['action'] ?? '') === 'verify_code') {
        $email = $_SESSION['password_reset_email'] ?? '';
        $step = $email === '' ? 'request' : 'verify';
        $code = trim($_POST['code'] ?? '');

        if ($email === '') {
            $error = 'Please enter your account email first.';
            $step = 'request';
        } elseif (!preg_match('/^\d{6}$/', $code)) {
            $error = 'Please enter a valid 6-digit numeric verification code.';
        } else {
            $resetStmt = $pdo->prepare("
                SELECT u.id, pr.code_hash, pr.expires_at, pr.attempts
                FROM users u
                INNER JOIN password_resets pr ON pr.user_id = u.id
                WHERE LOWER(TRIM(u.email)) = :email
                LIMIT 1
            ");
            $resetStmt->execute([':email' => $email]);
            $reset = $resetStmt->fetch();

            if (!$reset) {
                $error = 'No active reset request found. Please request a new code.';
                $step = 'request';
            } elseif (strtotime($reset['expires_at']) <= time()) {
                $error = 'Verification code has expired. Please request a new code.';
                $step = 'request';
            } elseif ((int)$reset['attempts'] >= 5) {
                $error = 'Too many failed attempts. Code locked for security. Please request a new code.';
                $step = 'request';
            } elseif (!password_verify($code, $reset['code_hash'])) {
                // Incorrect code: permission NOT granted!
                $attemptStmt = $pdo->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE user_id = :user_id');
                $attemptStmt->execute([':user_id' => $reset['id']]);
                $remaining = 5 - ((int)$reset['attempts'] + 1);
                $error = "Galat verification code! ($remaining attempts remaining). Please check the code sent to your email.";
                $step = 'verify';
            } else {
                // SUCCESS! Code is verified -> Permission to change password is now GRANTED!
                $_SESSION['password_reset_verified_user_id'] = (int)$reset['id'];
                $_SESSION['password_reset_verified_hash'] = $reset['code_hash'];
                $_SESSION['password_reset_verified_until'] = time() + 600; // 10 minutes permission
                unset($_SESSION['password_reset_demo_code']);
                
                header('Location: ' . base_url('frontend/forgot-password.php'));
                exit;
            }
        }
    } elseif (($_POST['action'] ?? '') === 'change_password') {
        $email = $_SESSION['password_reset_email'] ?? '';
        $verifiedUserId = (int)($_SESSION['password_reset_verified_user_id'] ?? 0);
        $verifiedHash = $_SESSION['password_reset_verified_hash'] ?? '';
        $verifiedUntil = (int)($_SESSION['password_reset_verified_until'] ?? 0);
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Strict verification: Password can ONLY be changed if verified
        if ($email === '' || $verifiedUserId <= 0 || $verifiedUntil <= time()) {
            $error = 'Permission denied. You must verify the email code first.';
            $step = $email === '' ? 'request' : 'verify';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
            $step = 'password';
        } elseif ($password !== $confirmPassword) {
            $error = 'New password and confirm password do not match.';
            $step = 'password';
        } else {
            $resetStmt = $pdo->prepare("
                SELECT code_hash, expires_at, attempts
                FROM password_resets
                WHERE user_id = :user_id
                LIMIT 1
            ");
            $resetStmt->execute([':user_id' => $verifiedUserId]);
            $reset = $resetStmt->fetch();

            if (
                !$reset
                || !hash_equals($verifiedHash, $reset['code_hash'])
                || strtotime($reset['expires_at']) <= time()
                || (int)$reset['attempts'] >= 5
            ) {
                unset(
                    $_SESSION['password_reset_verified_user_id'],
                    $_SESSION['password_reset_verified_hash'],
                    $_SESSION['password_reset_verified_until']
                );
                $error = 'Verification session has expired. Please request a new code.';
                $step = 'request';
            } else {
                // Update user password and clear reset token
                $pdo->beginTransaction();
                try {
                    $updateStmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :user_id');
                    $updateStmt->execute([
                        ':password' => password_hash($password, PASSWORD_DEFAULT),
                        ':user_id' => $verifiedUserId,
                    ]);
                    $deleteStmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
                    $deleteStmt->execute([':user_id' => $verifiedUserId]);
                    $pdo->commit();
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }

                unset(
                    $_SESSION['password_reset_email'],
                    $_SESSION['password_reset_demo_code'],
                    $_SESSION['password_reset_verified_user_id'],
                    $_SESSION['password_reset_verified_hash'],
                    $_SESSION['password_reset_verified_until']
                );
                clear_rate_limit('password_reset_' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local'));
                $_SESSION['flash_success'] = 'Password successfully reset! Please sign in with your new password.';
                header('Location: ' . base_url('frontend/login.php'));
                exit;
            }
        }
    }
}

$pageTitle = 'Forgot Password - ProjectSphere Academic Portal';
$pageDescription = 'Reset your password securely via email verification code.';
$hideMainNavigation = true;
require_once __DIR__ . '/../backend/core/header.php';
?>

<div class="auth-page-wrapper glass-auth-wrapper">
    <!-- Blurred Home Page Background -->
    <div class="auth-home-bg-layer" aria-hidden="true">
        <iframe src="<?= base_url('frontend/index.php?bg_blur=1') ?>" class="auth-home-bg-frame" tabindex="-1" title="Background Home Page"></iframe>
        <div class="auth-home-bg-overlay"></div>
    </div>

    <div class="container d-flex flex-column align-items-center">
        <div class="auth-container-glass">
            <a class="auth-home-link glass-back-link" href="<?= base_url('frontend/login.php') ?>">
                <i class="bi bi-arrow-left"></i> <span>Back to Sign In</span>
            </a>

            <div class="card auth-card glass-auth-card">
                <div class="text-center mb-4">
                    <a href="<?= base_url() ?>" class="d-inline-block mb-3">
                        <img src="<?= base_url('frontend/assets/images/logo.svg') ?>" alt="ProjectSphere Logo" style="height: 42px;">
                    </a>
                    <div>
                        <span class="auth-header-badge glass-header-badge">
                            <i class="bi bi-shield-lock-fill"></i> Security Portal
                        </span>
                    </div>

                    <?php if ($step === 'request'): ?>
                        <h2 class="h4 fw-bold text-dark mb-1">Forgot Password?</h2>
                        <p class="text-secondary small mb-0">Enter your registered email to receive a 6-digit verification code.</p>
                    <?php elseif ($step === 'verify'): ?>
                        <h2 class="h4 fw-bold text-dark mb-1">Verify Email Code</h2>
                        <p class="text-secondary small mb-0">Enter the 6-digit verification code sent to your registered email.</p>
                    <?php else: ?>
                        <h2 class="h4 fw-bold text-dark mb-1">Set New Password</h2>
                        <p class="text-secondary small mb-0">Code verified successfully. You may now create a new password.</p>
                    <?php endif; ?>
                </div>

                <!-- Step Flow Indicator -->
                <div class="d-flex align-items-center justify-content-between mb-4 px-2">
                    <div class="text-center flex-fill">
                        <div class="badge rounded-circle <?= ($step === 'request') ? 'bg-primary text-white' : 'bg-success text-white' ?> p-2 mb-1" style="width: 28px; height: 28px;">
                            <i class="bi <?= ($step === 'request') ? 'bi-envelope' : 'bi-check' ?>"></i>
                        </div>
                        <div class="small fw-semibold <?= ($step === 'request') ? 'text-primary' : 'text-secondary' ?>" style="font-size: 0.72rem;">1. Email</div>
                    </div>
                    <div class="flex-fill border-top border-2 <?= ($step !== 'request') ? 'border-primary' : 'border-secondary-subtle' ?> mx-1" style="height: 0;"></div>
                    <div class="text-center flex-fill">
                        <div class="badge rounded-circle <?= ($step === 'verify') ? 'bg-primary text-white' : (($step === 'password') ? 'bg-success text-white' : 'bg-light text-secondary border') ?> p-2 mb-1" style="width: 28px; height: 28px;">
                            <i class="bi <?= ($step === 'password') ? 'bi-check' : 'bi-key' ?>"></i>
                        </div>
                        <div class="small fw-semibold <?= ($step === 'verify') ? 'text-primary' : 'text-secondary' ?>" style="font-size: 0.72rem;">2. Verify Code</div>
                    </div>
                    <div class="flex-fill border-top border-2 <?= ($step === 'password') ? 'border-primary' : 'border-secondary-subtle' ?> mx-1" style="height: 0;"></div>
                    <div class="text-center flex-fill">
                        <div class="badge rounded-circle <?= ($step === 'password') ? 'bg-primary text-white' : 'bg-light text-secondary border' ?> p-2 mb-1" style="width: 28px; height: 28px;">
                            <i class="bi bi-lock"></i>
                        </div>
                        <div class="small fw-semibold <?= ($step === 'password') ? 'text-primary' : 'text-secondary' ?>" style="font-size: 0.72rem;">3. New Password</div>
                    </div>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger alert-dismissible fade show small d-flex align-items-center shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-6 flex-shrink-0"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($notice !== ''): ?>
                    <div class="alert alert-info alert-dismissible fade show small d-flex align-items-center shadow-sm" role="status">
                        <i class="bi bi-info-circle-fill me-2 fs-6 flex-shrink-0"></i>
                        <div><?= htmlspecialchars($notice) ?></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($step === 'request'): ?>
                    <!-- STEP 1: Enter Email -->
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="request_code">

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold text-secondary small">Registered Account Email</label>
                            <div class="input-group auth-input-group glass-input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="Enter your registered email address" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" autocomplete="email" required autofocus>
                            </div>
                            <div class="form-text small text-muted">We will send a 6-digit verification code to this address.</div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary auth-submit-btn glass-submit-btn">
                                <span>Send Verification Code</span> <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </form>

                <?php elseif ($step === 'verify'): ?>
                    <!-- STEP 2: Verify 6-digit Code (Password Change Permission is LOCKED until code is verified) -->
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="verify_code">



                        <div class="mb-3 text-center">
                            <label for="code" class="form-label fw-semibold text-secondary small">Enter 6-Digit Code</label>
                            <div class="input-group auth-input-group glass-input-group">
                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                <input type="text" class="form-control text-center fs-4 fw-bold letter-spacing-lg" 
                                       id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" 
                                       placeholder="------" style="letter-spacing: 0.35em;" autocomplete="one-time-code" required autofocus>
                            </div>
                            <div class="form-text small text-muted mt-2">Code expires in 10 minutes. Check your mail or spam folder.</div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary auth-submit-btn glass-submit-btn">
                                <span>Verify Code</span> <i class="bi bi-shield-check"></i>
                            </button>
                        </div>

                        <div class="text-center mt-3 pt-2">
                            <a href="<?= base_url('frontend/forgot-password.php?new=1') ?>" class="text-muted small text-decoration-none">
                                <i class="bi bi-arrow-repeat me-1"></i>Request a new code or change email
                            </a>
                        </div>
                    </form>

                <?php else: ?>
                    <!-- STEP 3: Permission Granted! User entered valid code, now set new password -->
                    <div class="alert alert-success d-flex align-items-center small py-2 px-3 mb-3 border-success-subtle shadow-sm">
                        <i class="bi bi-check-circle-fill text-success fs-5 me-2"></i>
                        <div><strong>Code Verified!</strong> You have permission to change your password.</div>
                    </div>

                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold text-secondary small">New Password</label>
                            <div class="input-group auth-input-group glass-input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter at least 6 characters" minlength="6" autocomplete="new-password" required autofocus>
                                <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="password" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label fw-semibold text-secondary small">Confirm New Password</label>
                            <div class="input-group auth-input-group glass-input-group">
                                <span class="input-group-text"><i class="bi bi-shield-lock-fill"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Re-enter your new password" minlength="6" autocomplete="new-password" required>
                                <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="confirm_password" aria-label="Toggle confirm password visibility">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-success auth-submit-btn glass-submit-btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;">
                                <span>Save New Password & Sign In</span> <i class="bi bi-check-lg"></i>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="mt-4 pt-3 border-top text-center small text-secondary">
                    <span>Remember your password?</span>
                    <a href="<?= base_url('frontend/login.php') ?>" class="text-primary fw-semibold ms-1">Sign in here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password toggle functionality
document.querySelectorAll('.password-toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const targetId = btn.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'bi bi-eye';
        }
    });
});
</script>

<?php require_once __DIR__ . '/../backend/core/footer.php'; ?>
