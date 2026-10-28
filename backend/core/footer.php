</main>

<?php if (empty($_GET['bg_blur'])): ?>
<aside class="cookie-notice" id="cookieNotice" aria-label="Cookie information" hidden>
    <div>
        <strong>Essential cookies</strong>
        <p>ProjectSphere uses session cookies for sign-in and portal requests. No analytics or advertising cookies are configured. <a href="<?= base_url('frontend/privacy.php') ?>">Privacy policy</a></p>
    </div>
    <button class="btn btn-primary btn-sm" type="button" id="dismissCookieNotice">Got it</button>
</aside>
<?php endif; ?>

<?php if ($isHomePage): ?>
<!-- Full Footer (Home Page) -->
<footer class="footer-projectsphere">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <img src="<?= base_url('frontend/assets/images/logo.svg') ?>" alt="ProjectSphere Logo" style="height: 32px; filter: brightness(0) invert(1);">
                </div>
                <p class="small text-secondary mb-3">
                    A centralized, secure college portal designed for Diploma &amp; Degree Computer Engineering students to submit, showcase, and evaluate academic capstone projects.
                </p>
                <div class="badge bg-dark border border-secondary text-secondary p-2">
                    <i class="bi bi-shield-lock-fill me-1 text-primary"></i>Protected Source Code Policy
                </div>
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <h5>Navigation</h5>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="<?= base_url('frontend/') ?>">Home Portal</a></li>
                    <li class="mb-2"><a href="<?= base_url('frontend/browse.php') ?>">Browse Projects</a></li>
                    <li class="mb-2"><a href="<?= base_url('frontend/browse.php?sort=rank') ?>">Leaderboard</a></li>
                    <li class="mb-2"><a href="<?= base_url('frontend/student/submit-project.php') ?>">Submit Project</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 col-6">
                <h5>Portals &amp; Roles</h5>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="<?= base_url('frontend/login.php?role=student') ?>"><i class="bi bi-person me-1"></i>Student Login</a></li>
                    <li class="mb-2"><a href="<?= base_url('frontend/register.php') ?>"><i class="bi bi-person-plus me-1"></i>Student Registration</a></li>
                    <li class="mb-2"><a href="<?= base_url('frontend/admin/login.php') ?>"><i class="bi bi-shield-check me-1"></i>Faculty / Admin Portal</a></li>
                    <li class="mb-2"><a href="<?= base_url('frontend/admin/dashboard.php') ?>"><i class="bi bi-clipboard-data me-1"></i>Evaluator Dashboard</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h5>Academic Details</h5>
                <p class="small text-secondary mb-2">
                    <strong>Department:</strong> Computer Engineering<br>
                    <strong>Academic Year:</strong> 2026-2027<br>
                    <strong>Evaluation Criteria:</strong> 100 Marks System (Innovation, Functionality, UI/UX, Tech Stack &amp; Docs)
                </p>
                <div class="text-secondary small mt-2">
                    <i class="bi bi-envelope me-1"></i><a href="mailto:support@projectsphere.edu">support@projectsphere.edu</a>
                </div>
                <div class="text-secondary small mt-3">
                    <i class="bi bi-check2-circle text-success me-1"></i>PHP 8.2 &bull; MySQL &bull; Bootstrap 5
                </div>
                <div class="small mt-3">
                    <a class="d-block mb-2" href="<?= base_url('frontend/privacy.php') ?>">Privacy Policy</a>
                    <a class="d-block" href="<?= base_url('frontend/terms.php') ?>">Terms of Service</a>
                </div>
            </div>
        </div>

        <hr class="border-secondary my-4">

        <div class="row align-items-center small">
            <div class="col-md-6 text-center text-md-start text-secondary">
                &copy; <?= date('Y') ?> <strong>ProjectSphere</strong> &bull; Student Project Showcase &amp; Evaluation Portal.
            </div>
            <div class="col-md-6 text-center text-md-end text-secondary mt-2 mt-md-0">
                Diploma Computer Science Final Year Project
            </div>
        </div>
    </div>
</footer>
<?php else: ?>
<!-- Compact Footer (All Other Pages) -->
<footer class="footer-projectsphere py-3">
    <div class="container">
        <div class="row align-items-center small">
            <div class="col-md-6 text-center text-md-start text-secondary">
                &copy; <?= date('Y') ?> <strong>ProjectSphere</strong> &bull; Student Project Showcase &amp; Evaluation Portal
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                <a href="<?= base_url('frontend/privacy.php') ?>" class="text-secondary me-3">Privacy Policy</a>
                <a href="<?= base_url('frontend/terms.php') ?>" class="text-secondary me-3">Terms of Service</a>
                <a href="mailto:support@projectsphere.edu" class="text-secondary">Contact</a>
            </div>
        </div>
    </div>
</footer>
<?php endif; ?>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- ProjectSphere Custom JS -->
<?php
$jsFile = __DIR__ . '/../../frontend/assets/js/main.js';
$jsVer  = file_exists($jsFile) ? filemtime($jsFile) : time();
?>
<script src="<?= base_url('frontend/assets/js/main.js?v=' . $jsVer) ?>"></script>
</body>
</html>
