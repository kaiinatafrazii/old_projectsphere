<?php
/**
 * ProjectSphere - Faculty Review Project View
 * Includes access to private evaluation assets (source code ZIP, documentation, repo links)
 */
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/auth.php';
require_once __DIR__ . '/../../backend/core/functions.php';

require_admin();

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($projectId <= 0) {
    header('Location: ' . base_url('frontend/admin/projects.php'));
    exit;
}

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validate_csrf()) {
        $_SESSION['flash_error'] = 'Security check failed (CSRF). Please refresh and try again.';
        header('Location: ' . base_url('frontend/admin/review-project.php?id=' . $projectId));
        exit;
    }
    $action = $_POST['action'];
    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? 'pending';
        $reason = trim($_POST['rejection_reason'] ?? '');

        if ($newStatus === 'rejected' && empty($reason)) {
            $_SESSION['flash_error'] = 'Please provide a reason or constructive feedback when rejecting a project.';
        } else {
            $up = $pdo->prepare("UPDATE projects SET status = :status, rejection_reason = :reason WHERE id = :id");
            $up->execute([
                ':status' => $newStatus,
                ':reason' => ($newStatus === 'rejected') ? $reason : null,
                ':id'     => $projectId
            ]);
            recalculate_rankings($pdo);
            $_SESSION['flash_success'] = 'Project status successfully updated to ' . ucfirst($newStatus) . '.';
        }
    }
    header('Location: ' . base_url('frontend/admin/review-project.php?id=' . $projectId));
    exit;
}

// Fetch project
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.icon AS category_icon,
           s.full_name AS student_name, s.roll_no AS student_roll, s.department, s.semester, s.phone AS student_phone,
           u.email AS student_email,
           e.innovation_score, e.functionality_score, e.ui_design_score, e.tech_usage_score, e.presentation_score, 
           e.total_score, e.feedback_text, e.is_published, e.evaluated_at,
           r.overall_rank, r.category_rank
    FROM projects p
    INNER JOIN project_categories c ON p.category_id = c.id
    INNER JOIN students s ON p.student_id = s.id
    INNER JOIN users u ON s.user_id = u.id
    LEFT JOIN evaluations e ON p.id = e.project_id
    LEFT JOIN rankings r ON p.id = r.project_id
    WHERE p.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();

if (!$project) {
    $_SESSION['flash_error'] = 'Project not found.';
    header('Location: ' . base_url('frontend/admin/projects.php'));
    exit;
}

$demoUrl = project_demo_url($project['demo_url'], $project['title']);

// Team members
$team = $pdo->prepare("SELECT * FROM project_team_members WHERE project_id = :pid");
$team->execute([':pid' => $projectId]);
$teamMembers = $team->fetchAll();

// Screenshots
$gallery = $pdo->prepare("SELECT * FROM project_images WHERE project_id = :pid");
$gallery->execute([':pid' => $projectId]);
$screenshots = $gallery->fetchAll();

$pageTitle = 'Review: ' . ($project['title'] ?? 'Project') . ' - Faculty Portal';
require_once __DIR__ . '/../../backend/core/header.php';
?>

<div class="container-xl py-4">
    <div class="row">
        <div class="col-lg-3 col-md-4 mb-4">
            <?php require_once __DIR__ . '/../../backend/core/admin-navbar.php'; ?>
        </div>

        <div class="col-lg-9 col-md-8">
            <!-- Top Action Header -->
            <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 16px;">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <?= get_status_badge($project['status']) ?>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($project['category_name']) ?></span>
                            <?php if (!empty($project['overall_rank'])): ?>
                                <?= get_rank_badge((int)$project['overall_rank']) ?>
                            <?php endif; ?>
                        </div>
                        <h2 class="h3 fw-bold mb-1"><?= htmlspecialchars($project['title']) ?></h2>
                        <div class="text-muted small">
                            Submitted by <strong><?= htmlspecialchars($project['student_name']) ?></strong> (<?= htmlspecialchars($project['student_roll']) ?>) &bull; <?= date('d M Y, h:i A', strtotime($project['submitted_at'])) ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3 mt-md-0">
                        <a href="<?= base_url('frontend/admin/evaluate.php?id=' . $project['id']) ?>" class="btn btn-success shadow-sm">
                            <i class="bi bi-pencil-square me-1"></i>Award / Edit Marks
                        </a>
                        <a href="<?= base_url('frontend/project-details.php?id=' . $project['id']) ?>" class="btn btn-outline-secondary" target="_blank" title="View Public Student Showcase">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Student View
                        </a>
                    </div>
                </div>

                <!-- Status Update Bar -->
                <div class="p-3 bg-light rounded-3 border">
                    <form method="POST" action="<?= base_url('frontend/admin/review-project.php?id=' . $projectId) ?>" class="row g-2 align-items-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_status">
                        <div class="col-auto">
                            <label class="col-form-label fw-bold small">Change Status:</label>
                        </div>
                        <div class="col-md-3 col-6">
                            <select name="status" class="form-select form-select-sm" id="statusSelect" onchange="toggleRejectionBox()">
                                <option value="pending" <?= ($project['status'] === 'pending') ? 'selected' : '' ?>>Pending Review</option>
                                <option value="approved" <?= ($project['status'] === 'approved') ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= ($project['status'] === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-5 col-12" id="rejectionBox" style="display: <?= ($project['status'] === 'rejected') ? 'block' : 'none' ?>;">
                            <input type="text" name="rejection_reason" class="form-control form-control-sm" placeholder="Reason for rejection (sent to student)" value="<?= htmlspecialchars($project['rejection_reason'] ?? '') ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Two-Column Layout -->
            <div class="row g-4">
                <!-- Main Details -->
                <div class="col-lg-8">
                    <!-- Thumbnail & Quick Overview -->
                    <div class="content-card mb-4">
                        <img src="<?= base_url($project['thumbnail_image']) ?>" class="img-fluid rounded-3 mb-3 w-100" style="max-height: 350px; object-fit: cover;" alt="Thumbnail">
                        <h5 class="fw-bold mb-2">Short Summary</h5>
                        <p class="text-secondary"><?= htmlspecialchars($project['short_description']) ?></p>
                        
                        <div class="mt-3">
                            <h6 class="fw-bold small text-muted text-uppercase mb-2">Technologies Used:</h6>
                            <div>
                                <?php 
                                $techs = array_map('trim', explode(',', $project['technologies']));
                                foreach ($techs as $t): 
                                ?>
                                    <span class="badge bg-light text-dark border p-2 me-1 mb-1"><?= htmlspecialchars($t) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Problem Statement -->
                    <div class="content-card">
                        <h5 class="fw-bold mb-3 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Problem Statement</h5>
                        <p class="text-secondary" style="white-space: pre-line;"><?= htmlspecialchars($project['problem_statement']) ?></p>
                    </div>

                    <!-- Objectives -->
                    <div class="content-card">
                        <h5 class="fw-bold mb-3 text-primary"><i class="bi bi-bullseye me-2"></i>Objectives</h5>
                        <p class="text-secondary" style="white-space: pre-line;"><?= htmlspecialchars($project['objectives']) ?></p>
                    </div>

                    <!-- Features -->
                    <div class="content-card">
                        <h5 class="fw-bold mb-3 text-success"><i class="bi bi-check2-circle me-2"></i>Features & Modules</h5>
                        <p class="text-secondary" style="white-space: pre-line;"><?= htmlspecialchars($project['features']) ?></p>
                    </div>

                    <!-- Gallery Screenshots -->
                    <?php if (!empty($screenshots)): ?>
                        <div class="content-card">
                            <h5 class="fw-bold mb-3"><i class="bi bi-images me-2"></i>Uploaded Screenshots</h5>
                            <div class="row g-2">
                                <?php foreach ($screenshots as $sc): ?>
                                    <div class="col-4">
                                        <a href="<?= base_url($sc['image_path']) ?>" target="_blank">
                                            <img src="<?= base_url($sc['image_path']) ?>" class="img-thumbnail w-100" style="height: 120px; object-fit: cover;" alt="Screenshot">
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Details: Private Code, Documents & Evaluation Status -->
                <div class="col-lg-4">
                    <!-- Private Source Code Submission (EXCLUSIVE FACULTY ACCESS) -->
                    <div class="content-card border-warning mb-4" style="background-color: #fffdf5;">
                        <h6 class="fw-bold text-dark mb-2">
                            <i class="bi bi-shield-lock-fill text-warning me-1"></i>Private Code Submission
                        </h6>
                        <p class="small text-muted mb-3">
                            This source code is strictly confidential for teacher verification and cannot be accessed by students.
                        </p>

                        <?php if (!empty($project['private_source_code_file'])): ?>
                            <a href="<?= base_url('frontend/admin/download-source.php?id=' . (int)$project['id']) ?>" class="btn btn-warning btn-sm w-100 mb-2 fw-semibold">
                                <i class="bi bi-file-earmark-zip-fill me-1"></i>Download Source Code (.ZIP)
                            </a>
                        <?php else: ?>
                            <div class="badge bg-light text-muted border p-2 w-100 mb-2">No ZIP Archive Uploaded</div>
                        <?php endif; ?>

                        <?php if (!empty($project['private_source_repo'])): ?>
                            <a href="<?= htmlspecialchars($project['private_source_repo']) ?>" target="_blank" class="btn btn-dark btn-sm w-100 text-start">
                                <i class="bi bi-github me-1"></i>Open Private Repository &rarr;
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Documentation & Demo Link -->
                    <div class="content-card mb-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Project Assets</h6>
                        <div class="d-grid gap-2">
                            <?php if (!empty($project['documentation_file'])): ?>
                                <a href="<?= base_url($project['documentation_file']) ?>" target="_blank" class="btn btn-outline-danger btn-sm text-start py-2">
                                    <i class="bi bi-file-earmark-pdf-fill me-2"></i>View Project PDF Synopsis
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">No documentation file attached.</span>
                            <?php endif; ?>

                            <?php if ($demoUrl !== null): ?>
                                <a href="<?= htmlspecialchars($demoUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm text-start py-2">
                                    <i class="bi bi-play-circle-fill me-2"></i><?= htmlspecialchars(project_demo_link_label($demoUrl, 'Open Demo Video / URL')) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Current Evaluation Status Card -->
                    <div class="content-card mb-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-award me-2 text-success"></i>Evaluation Summary</h6>
                        <?php if (!empty($project['total_score'])): ?>
                            <div class="text-center p-3 bg-light rounded-3 mb-3">
                                <div class="small text-muted">Total Marks Awarded</div>
                                <div class="h2 fw-bold text-success mb-1"><?= format_score($project['total_score']) ?>/100</div>
                                <?php if (!empty($project['overall_rank'])): ?>
                                    <div class="mt-1"><?= get_rank_badge((int)$project['overall_rank']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="small mb-3">
                                <strong>Feedback Remarks:</strong>
                                <p class="text-muted fst-italic mb-0">"<?= htmlspecialchars($project['feedback_text'] ?? '') ?>"</p>
                            </div>
                            <a href="<?= base_url('frontend/admin/evaluate.php?id=' . $project['id']) ?>" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-pencil me-1"></i>Modify Evaluation Marks
                            </a>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <i class="bi bi-clipboard-x fs-2 text-muted mb-2 d-block"></i>
                                <p class="small text-muted mb-3">This project has not received evaluation marks yet.</p>
                                <a href="<?= base_url('frontend/admin/evaluate.php?id=' . $project['id']) ?>" class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-pencil-square me-1"></i>Evaluate Now
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Student & Team Information -->
                    <div class="content-card">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-people me-2 text-primary"></i>Student & Team Details</h6>
                        <div class="small mb-3">
                            <div><strong>Lead Student:</strong> <?= htmlspecialchars($project['student_name']) ?></div>
                            <div><strong>Roll Number:</strong> <?= htmlspecialchars($project['student_roll']) ?></div>
                            <div><strong>Email:</strong> <?= htmlspecialchars($project['student_email']) ?></div>
                            <?php if (!empty($project['student_phone'])): ?>
                                <div><strong>Phone:</strong> <?= htmlspecialchars($project['student_phone']) ?></div>
                            <?php endif; ?>
                            <div><strong>Department:</strong> <?= htmlspecialchars($project['department']) ?></div>
                            <div><strong>Semester:</strong> <?= htmlspecialchars($project['semester']) ?></div>
                        </div>

                        <?php if (!empty($teamMembers)): ?>
                            <h6 class="fw-bold small text-muted text-uppercase mb-2">Additional Team Members:</h6>
                            <ul class="list-group list-group-flush border rounded-3 small">
                                <?php foreach ($teamMembers as $tm): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                        <div>
                                            <strong><?= htmlspecialchars($tm['member_name']) ?></strong>
                                            <div class="text-muted small"><?= htmlspecialchars($tm['roll_no']) ?></div>
                                        </div>
                                        <span class="badge bg-light text-dark border"><?= htmlspecialchars($tm['role']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRejectionBox() {
    const status = document.getElementById('statusSelect').value;
    const box = document.getElementById('rejectionBox');
    box.style.display = (status === 'rejected') ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/../../backend/core/footer.php'; ?>
