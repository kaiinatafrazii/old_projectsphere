<?php
/**
 * ProjectSphere - Dedicated Faculty & Admin Login Portal
 */
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/auth.php';
require_once __DIR__ . '/../../backend/core/functions.php';

// Redirect if already logged in as admin
if (is_admin()) {
    header('Location: ' . base_url('frontend/admin/dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailOrUsername = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rateLimitKey = 'admin_login_' . ($_SERVER['REMOTE_ADDR'] ?? 'local');

    if (!validate_csrf()) {
        $error = 'Security validation failed (CSRF token mismatch). Please refresh and try again.';
    } elseif (is_rate_limited($rateLimitKey, 5, 300)) {
        $error = 'Too many failed login attempts. Please wait 5 minutes before trying again.';
    } elseif (empty($emailOrUsername) || empty($password)) {
        $error = 'Please enter faculty credentials.';
    } else {
        $stmt = $pdo->prepare("
            SELECT u.*, a.id AS admin_id, a.full_name AS admin_name, a.designation
            FROM users u
            INNER JOIN admins a ON u.id = a.user_id
            WHERE (LOWER(TRIM(u.email)) = LOWER(:q1) OR LOWER(TRIM(u.username)) = LOWER(:q2)) AND u.role = 'admin' AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([':q1' => $emailOrUsername, ':q2' => $emailOrUsername]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            clear_rate_limit($rateLimitKey);
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = 'admin';
            $_SESSION['profile_id'] = $user['admin_id'];
            $_SESSION['full_name']  = $user['admin_name'] ?? 'Faculty Evaluator';
            $_SESSION['flash_success'] = 'Welcome to Faculty Evaluation Portal, ' . $_SESSION['full_name'];
            header('Location: ' . base_url('frontend/admin/dashboard.php'));
            exit;
        } else {
            record_rate_limit_attempt($rateLimitKey);
            $error = 'Invalid faculty credentials. Access restricted to authorized department evaluators.';
        }
    }
}

$pageTitle = 'Faculty & Evaluator Portal - ProjectSphere';
$pageDescription = 'Sign in to the ProjectSphere Faculty & Evaluator Portal to review student capstone projects and award academic rubric marks.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/svg+xml" href="<?= base_url('frontend/assets/images/projectsphere-mark.svg') ?>">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= base_url('frontend/assets/css/style.css') ?>">
</head>
<body class="bg-light">

<div class="auth-page-wrapper glass-auth-wrapper">
    <!-- Blurred Home Page Background -->
    <div class="auth-home-bg-layer" aria-hidden="true">
        <iframe src="<?= base_url('frontend/index.php?bg_blur=1') ?>" class="auth-home-bg-frame" tabindex="-1" title="Background Home Page"></iframe>
        <div class="auth-home-bg-overlay"></div>
    </div>

    <div class="container d-flex flex-column align-items-center">
        <div class="auth-container-glass">
            <a class="auth-home-link glass-back-link" href="<?= base_url() ?>">
                <i class="bi bi-arrow-left"></i> <span>Back to Catalog</span>
            </a>

            <div class="card auth-card glass-auth-card">
                <div class="text-center mb-4">
                    <a href="<?= base_url() ?>" class="d-inline-block mb-3">
                        <img src="<?= base_url('frontend/assets/images/logo.svg') ?>" alt="ProjectSphere Logo" style="height: 42px;">
                    </a>
                    <div>
                        <span class="auth-header-badge bg-dark text-white">
                            <i class="bi bi-shield-lock-fill"></i> Faculty & Staff Only
                        </span>
                    </div>
                    <h2 class="h4 fw-bold text-dark mb-1">Evaluator Portal</h2>
                    <p class="text-secondary small mb-0">Review capstones & award academic marks</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show small d-flex align-items-center shadow-sm" role="alert">
                        <i class="bi bi-exclamation-octagon-fill me-2 fs-6 flex-shrink-0"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="adminLoginForm">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary small" for="adminUsername">Faculty Username or Email</label>
                        <div class="input-group auth-input-group glass-input-group">
                            <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                            <input type="text" id="adminUsername" name="username_or_email" class="form-control" placeholder="admin@projectsphere.edu" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold text-secondary small mb-0" for="adminPassword">Faculty Password</label>
                            <a href="<?= base_url('frontend/forgot-password.php') ?>" class="small text-decoration-none">Forgot password?</a>
                        </div>
                        <div class="input-group auth-input-group glass-input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="adminPassword" class="form-control" placeholder="••••••••" required>
                            <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="adminPassword" aria-label="Toggle password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-dark btn-lg auth-submit-btn">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Access Admin Panel
                        </button>
                    </div>
                </form>

                <div class="mt-4 pt-3 border-top text-center small text-secondary">
                    <a href="<?= base_url('frontend/login.php?role=student') ?>" class="text-decoration-none fw-semibold">
                        <i class="bi bi-mortarboard me-1"></i>Switch to Student Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('frontend/assets/js/main.js') ?>"></script>
</body>
</html>
