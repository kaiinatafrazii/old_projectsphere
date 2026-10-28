<?php
/**
 * ProjectSphere - Student Sidebar Navigation
 * Location: backend/core/student-navbar.php
 */
$currentScript = basename($_SERVER['PHP_SELF']);
$displayName = $student['full_name'] ?? $_SESSION['full_name'] ?? 'Student';
$displayBranch = $student['department'] ?? $_SESSION['department'] ?? 'Engineering Scholar';
$initialLetter = strtoupper(substr(trim($displayName), 0, 1));
?>
<div class="dashboard-sidebar shadow-sm">
    <!-- Student Profile Header Card -->
    <div class="sidebar-user-card mb-4 pb-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <div class="sidebar-avatar-wrapper position-relative">
                <div class="sidebar-avatar-gradient">
                    <span><?= htmlspecialchars($initialLetter) ?></span>
                </div>
                <span class="sidebar-status-dot" title="Active Session"></span>
            </div>
            <div class="overflow-hidden">
                <h6 class="mb-0 fw-bold text-dark text-truncate" title="<?= htmlspecialchars($displayName) ?>">
                    <?= htmlspecialchars($displayName) ?>
                </h6>
                <div class="d-flex align-items-center gap-1 mt-1">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill" style="font-size: 0.7rem; font-weight: 600;">
                        Student Portal
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Menu Links -->
    <ul class="sidebar-menu">
        <li>
            <a href="<?= base_url('frontend/student/dashboard.php') ?>" 
               onclick="if(typeof switchDashboardTab === 'function'){ switchDashboardTab('overview'); return false; }"
               class="sidebar-link <?= ($currentScript == 'dashboard.php' && (!isset($_GET['tab']) || $_GET['tab'] !== 'explore')) ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('frontend/student/submit-project.php') ?>" class="sidebar-link <?= ($currentScript == 'submit-project.php') ? 'active' : '' ?>">
                <i class="bi bi-cloud-arrow-up"></i>
                <span>Submit Project</span>
                <span class="badge bg-primary text-white rounded-pill ms-auto" style="font-size: 0.65rem;">New</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('frontend/student/my-projects.php') ?>" class="sidebar-link <?= in_array($currentScript, ['my-projects.php', 'edit-project.php']) ? 'active' : '' ?>">
                <i class="bi bi-folder2-open"></i>
                <span>My Projects</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('frontend/student/my-evaluations.php') ?>" class="sidebar-link <?= ($currentScript == 'my-evaluations.php') ? 'active' : '' ?>">
                <i class="bi bi-award"></i>
                <span>Evaluations &amp; Marks</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('frontend/student/dashboard.php?tab=explore') ?>" 
               onclick="if(typeof switchDashboardTab === 'function'){ switchDashboardTab('explore'); return false; }"
               class="sidebar-link <?= ($currentScript == 'dashboard.php' && isset($_GET['tab']) && $_GET['tab'] === 'explore') ? 'active' : '' ?>">
                <i class="bi bi-compass"></i>
                <span>Browse Projects</span>
                <span class="badge bg-light text-primary border border-primary-subtle rounded-pill ms-auto" style="font-size: 0.68rem; font-weight: 600;">Explore</span>
            </a>
        </li>
        <li>
            <a href="<?= base_url('frontend/student/profile.php') ?>" class="sidebar-link <?= ($currentScript == 'profile.php') ? 'active' : '' ?>">
                <i class="bi bi-person-gear"></i>
                <span>My Profile</span>
            </a>
        </li>

        <li class="mt-4 pt-3 border-top">
            <a href="<?= base_url('frontend/logout.php') ?>" class="sidebar-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>

    <!-- Mini Academic Term Card -->
    <div class="sidebar-term-card mt-4 p-3 rounded-3">
        <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi bi-mortarboard-fill text-primary"></i>
            <span class="small fw-bold text-dark" style="font-size: 0.78rem;">Capstone Term 2026-27</span>
        </div>
        <div class="small text-muted" style="font-size: 0.72rem; line-height: 1.3;">
            Diploma Evaluation Portal &bull; Department of Computer Engineering
        </div>
        <div class="d-flex align-items-center gap-1 mt-2 text-success" style="font-size: 0.7rem; font-weight: 600;">
            <span class="spinner-grow spinner-grow-sm text-success" style="width: 7px; height: 7px;" role="status"></span>
            Submissions Open
        </div>
    </div>
</div>
