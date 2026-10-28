<?php
/**
 * ProjectSphere - User Login (Student & Faculty)
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

$error = '';
$selectedRole = $_GET['role'] ?? 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailOrUsername = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $rateLimitKey = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'local');

    if (!validate_csrf()) {
        $error = 'Security check failed (CSRF token mismatch). Please refresh and try again.';
    } elseif (is_rate_limited($rateLimitKey, 5, 300)) {
        $error = 'Too many failed login attempts. Please wait 5 minutes before trying again.';
    } elseif (empty($emailOrUsername) || empty($password)) {
        $error = 'Please enter both your username/email and password.';
    } else {
        // Query user by email or username (case-insensitive & trimmed)
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   s.id AS student_id, s.full_name AS student_name, s.roll_no,
                   a.id AS admin_id, a.full_name AS admin_name
            FROM users u
            LEFT JOIN students s ON u.id = s.user_id
            LEFT JOIN admins a ON u.id = a.user_id
            WHERE LOWER(TRIM(u.email)) = LOWER(:query1) OR LOWER(TRIM(u.username)) = LOWER(:query2)
            LIMIT 1
        ");
        $stmt->execute([
            ':query1' => $emailOrUsername,
            ':query2' => $emailOrUsername
        ]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            clear_rate_limit($rateLimitKey);
            if ($user['status'] !== 'active') {
                $error = 'Your account has been deactivated. Please contact the department administrator.';
            } else {
                // Regenerate session for security
                session_regenerate_id(true);

                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email']    = $user['email'];
                $_SESSION['role']     = $user['role'];

                if ($user['role'] === 'admin') {
                    $_SESSION['profile_id'] = $user['admin_id'];
                    $_SESSION['full_name']  = $user['admin_name'] ?? 'Faculty Evaluator';
                    $_SESSION['flash_success'] = 'Welcome back, ' . $_SESSION['full_name'] . '!';
                    header('Location: ' . base_url('frontend/admin/dashboard.php'));
                    exit;
                } else {
                    // Fallback if student profile row was missing
                    if (empty($user['student_id'])) {
                        $sCheck = $pdo->prepare("SELECT id, full_name, roll_no FROM students WHERE user_id = :uid LIMIT 1");
                        $sCheck->execute([':uid' => $user['id']]);
                        $sRow = $sCheck->fetch();
                        if ($sRow) {
                            $user['student_id'] = $sRow['id'];
                            $user['student_name'] = $sRow['full_name'];
                            $user['roll_no'] = $sRow['roll_no'];
                        } else {
                            $sIns = $pdo->prepare("INSERT INTO students (user_id, roll_no, full_name, department, semester) VALUES (:uid, :roll, :name, 'Computer Engineering', '6th Semester')");
                            $sIns->execute([
                                ':uid'  => $user['id'],
                                ':roll' => 'ROLL-' . $user['id'],
                                ':name' => ucfirst($user['username'])
                            ]);
                            $user['student_id'] = $pdo->lastInsertId();
                            $user['student_name'] = ucfirst($user['username']);
                            $user['roll_no'] = 'ROLL-' . $user['id'];
                        }
                    }

                    $_SESSION['profile_id'] = $user['student_id'];
                    $_SESSION['full_name']  = $user['student_name'] ?? 'Student';
                    $_SESSION['roll_no']    = $user['roll_no'] ?? '';
                    $_SESSION['flash_success'] = 'Welcome back, ' . $_SESSION['full_name'] . '!';
                    header('Location: ' . base_url('frontend/student/dashboard.php'));
                    exit;
                }
            }
        } else {
            record_rate_limit_attempt($rateLimitKey);
            $error = 'Invalid email/username or password. Please verify and try again.';
        }
    }
}

$pageTitle = 'Sign In - ProjectSphere Academic Portal';
$pageDescription = 'Sign in to ProjectSphere with your student enrollment credentials or departmental faculty account.';
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
            <a class="auth-home-link glass-back-link" href="<?= base_url() ?>">
                <i class="bi bi-arrow-left"></i> <span>Back to Catalog</span>
            </a>

            <div class="card auth-card glass-auth-card">
                <div class="text-center mb-4">
                    <a href="<?= base_url() ?>" class="d-inline-block mb-3">
                        <img src="<?= base_url('frontend/assets/images/logo.svg') ?>" alt="ProjectSphere Logo" style="height: 42px;">
                    </a>
                    <div>
                        <span class="auth-header-badge glass-header-badge" id="roleBadge">
                            <i class="bi bi-person-fill"></i> Student Portal
                        </span>
                    </div>
                    <h2 class="h4 fw-bold text-dark mb-1">Welcome Back</h2>
                    <p class="text-secondary small mb-0">Sign in to track evaluations and showcase your capstone</p>
                </div>

                <!-- Role Switcher Tabs -->
                <div class="auth-role-tabs glass-role-tabs" role="tablist">
                    <button class="nav-link <?= ($selectedRole === 'student') ? 'active' : '' ?>" id="studentTabBtn" type="button" role="tab" onclick="setRole('student')">
                        <i class="bi bi-mortarboard-fill"></i> Student
                    </button>
                    <button class="nav-link <?= ($selectedRole === 'admin') ? 'active' : '' ?>" id="adminTabBtn" type="button" role="tab" onclick="setRole('admin')">
                        <i class="bi bi-shield-lock-fill"></i> Faculty / Admin
                    </button>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show small d-flex align-items-center shadow-sm" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-6 flex-shrink-0"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($selectedRole) ?>">

                    <div class="mb-3">
                        <label for="username_or_email" class="form-label fw-semibold text-secondary small" id="usernameLabel">
                            <?= ($selectedRole === 'admin') ? 'Faculty Email or Username' : 'Student Email or Username' ?>
                        </label>
                        <div class="input-group auth-input-group glass-input-group">
                            <span class="input-group-text"><i class="bi bi-person" id="usernameIcon"></i></span>
                            <input type="text" class="form-control" id="username_or_email" name="username_or_email" 
                                   placeholder="<?= ($selectedRole === 'admin') ? 'admin@projectsphere.edu' : 'rahul@college.edu or rahul_sharma' ?>" 
                                   value="<?= htmlspecialchars($_POST['username_or_email'] ?? '') ?>" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="password" class="form-label fw-semibold text-secondary small mb-0">Password</label>
                            <a href="<?= base_url('frontend/forgot-password.php') ?>" class="small text-decoration-none">Forgot password?</a>
                        </div>
                        <div class="input-group auth-input-group glass-input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                            <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="password" aria-label="Toggle password visibility">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary auth-submit-btn glass-submit-btn" id="submitBtn">
                            <span>Sign In</span> <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </form>

                <!-- Quick Demo Credentials Box for Testing Convenience -->
                <div class="demo-credentials-box glass-demo-box">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold"><i class="bi bi-key-fill text-warning me-1"></i>Quick Test Logins:</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button type="button" class="btn btn-outline-primary btn-copy-cred" onclick="fillCredentials('student', 'rahul@college.edu', 'student123')">
                            <i class="bi bi-person me-1"></i>Fill Student
                        </button>
                        <button type="button" class="btn btn-outline-dark btn-copy-cred" onclick="fillCredentials('admin', 'admin@projectsphere.edu', 'admin123')">
                            <i class="bi bi-shield-check me-1"></i>Fill Faculty
                        </button>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top text-center small text-secondary">
                    <span id="registerPrompt">Don't have a student account?</span>
                    <a href="<?= base_url('frontend/register.php') ?>" class="text-primary fw-semibold ms-1" id="registerLink">Register here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function setRole(role) {
    document.getElementById('roleInput').value = role;
    const sBtn = document.getElementById('studentTabBtn');
    const aBtn = document.getElementById('adminTabBtn');
    const uLabel = document.getElementById('usernameLabel');
    const uInput = document.getElementById('username_or_email');
    const uIcon = document.getElementById('usernameIcon');
    const rPrompt = document.getElementById('registerPrompt');
    const rLink = document.getElementById('registerLink');
    const badge = document.getElementById('roleBadge');
    const submitBtn = document.getElementById('submitBtn');

    if (role === 'admin') {
        sBtn.classList.remove('active');
        aBtn.classList.add('active');
        badge.className = 'auth-header-badge bg-dark text-white';
        badge.innerHTML = '<i class="bi bi-shield-check"></i> Faculty / Admin Portal';
        uLabel.textContent = 'Faculty Email or Username';
        uInput.placeholder = 'admin@projectsphere.edu';
        uIcon.className = 'bi bi-person-badge';
        submitBtn.className = 'btn btn-dark btn-lg auth-submit-btn';
        rPrompt.textContent = 'Need faculty access?';
        rLink.textContent = 'Contact Department HOD';
        rLink.href = 'mailto:hod.cs@college.edu';
    } else {
        aBtn.classList.remove('active');
        sBtn.classList.add('active');
        badge.className = 'auth-header-badge bg-primary-subtle text-primary';
        badge.innerHTML = '<i class="bi bi-mortarboard-fill"></i> Student Portal';
        uLabel.textContent = 'Student Email or Username';
        uInput.placeholder = 'rahul@college.edu or rahul_sharma';
        uIcon.className = 'bi bi-person';
        submitBtn.className = 'btn btn-primary btn-lg auth-submit-btn';
        rPrompt.textContent = "Don't have a student account?";
        rLink.textContent = 'Register here';
        rLink.href = '<?= base_url("frontend/register.php") ?>';
    }
}

function fillCredentials(role, username, password) {
    setRole(role);
    document.getElementById('username_or_email').value = username;
    document.getElementById('password').value = password;
}
</script>

<?php require_once __DIR__ . '/../backend/core/footer.php'; ?>
