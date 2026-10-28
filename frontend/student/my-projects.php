<?php
/**
 * ProjectSphere - My Submitted Projects List
 */
require_once __DIR__ . '/../../backend/core/db.php';
require_once __DIR__ . '/../../backend/core/auth.php';
require_once __DIR__ . '/../../backend/core/functions.php';

require_student();

$studentId = $_SESSION['profile_id'];

// Handle Project Deletion (Only if status is Pending)
if (isset($_POST['delete_project_id'])) {
    require_csrf();
    $delId = (int)$_POST['delete_project_id'];
    $delCheck = $pdo->prepare("SELECT id, status FROM projects WHERE id = :id AND student_id = :sid");
    $delCheck->execute([':id' => $delId, ':sid' => $studentId]);
    $projToDel = $delCheck->fetch();

    if ($projToDel) {
        if ($projToDel['status'] === 'pending') {
            $pdo->prepare("DELETE FROM projects WHERE id = :id")->execute([':id' => $delId]);
            recalculate_rankings($pdo);
            $_SESSION['flash_success'] = 'Project draft deleted successfully.';
        } else {
            $_SESSION['flash_error'] = 'Evaluated or approved projects cannot be deleted.';
        }
    }
    header('Location: ' . base_url('frontend/student/my-projects.php'));
    exit;
}

// Fetch all projects submitted by this student
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name,
           e.innovation_score, e.functionality_score, e.ui_design_score, 
           e.tech_usage_score, e.presentation_score, e.total_score, 
           e.feedback_text, e.is_published, e.evaluated_at,
           r.overall_rank, r.category_rank,
           a.full_name AS evaluator_name, a.designation AS evaluator_designation
    FROM projects p
    INNER JOIN project_categories c ON p.category_id = c.id
    LEFT JOIN evaluations e ON p.id = e.project_id
    LEFT JOIN rankings r ON p.id = r.project_id
    LEFT JOIN admins a ON e.admin_id = a.id
    WHERE p.student_id = :sid
    ORDER BY p.submitted_at DESC
");
$stmt->execute([':sid' => $studentId]);
$projects = $stmt->fetchAll();

$pageTitle = 'My Projects - ProjectSphere';
require_once __DIR__ . '/../../backend/core/header.php';
?>

<div class="container-xl py-4">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-lg-3 col-md-4 mb-4">
            <?php require_once __DIR__ . '/../../backend/core/student-navbar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9 col-md-8">
            <div class="card border-0 shadow-sm p-4 mb-4" style="border-radius: 16px;">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 border-bottom pb-3">
                    <div>
                        <h2 class="h4 fw-bold mb-1"><i class="bi bi-folder2-open me-2 text-primary"></i>My Submitted Projects</h2>
                        <p class="text-secondary small mb-0">Track review statuses, teacher marks, rankings, and faculty evaluations.</p>
                    </div>
                    <a href="<?= base_url('frontend/student/submit-project.php') ?>" class="btn btn-primary btn-sm shadow-sm mt-2 mt-md-0">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i>Submit Another Project
                    </a>
                </div>

                <?php if (empty($projects)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-folder-plus fs-1 text-muted d-block mb-3"></i>
                        <h5 class="fw-bold">No projects submitted yet</h5>
                        <p class="text-secondary small mb-3">You have not submitted any academic capstones for evaluation.</p>
                        <a href="<?= base_url('frontend/student/submit-project.php') ?>" class="btn btn-primary btn-sm">Submit New Project</a>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($projects as $p): ?>
                            <div class="col-12">
                                <div class="card border shadow-sm p-3 p-md-4" style="border-radius: 14px;">
                                    <div class="row g-3 align-items-center">
                                        <!-- Thumbnail -->
                                        <div class="col-md-3 text-center">
                                            <img src="<?= base_url($p['thumbnail_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" class="img-fluid rounded-3 w-100" style="height: 140px; object-fit: cover;">
                                        </div>

                                        <!-- Details -->
                                        <div class="col-md-6">
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                                <span class="category-pill small"><?= htmlspecialchars($p['category_name']) ?></span>
                                                <?= get_status_badge($p['status']) ?>
                                                <?php if ($p['status'] === 'approved' && !empty($p['overall_rank'])): ?>
                                                    <?= get_rank_badge((int)$p['overall_rank']) ?>
                                                <?php endif; ?>
                                            </div>

                                            <h4 class="h5 fw-bold mb-1">
                                                <a href="<?= base_url('frontend/project-details.php?id=' . $p['id']) ?>" class="text-dark text-decoration-none">
                                                    <?= htmlspecialchars($p['title']) ?>
                                                </a>
                                            </h4>
                                            <p class="text-secondary small mb-2"><?= htmlspecialchars($p['short_description']) ?></p>

                                            <div class="text-muted small">
                                                <i class="bi bi-calendar3 me-1"></i> Submitted: <?= date('d M Y, h:i A', strtotime($p['submitted_at'])) ?>
                                                <?php $demoUrl = project_demo_url($p['demo_url'], $p['title']); ?>
                                                <?php if ($demoUrl !== null): ?>
                                                    &bull; <a href="<?= htmlspecialchars($demoUrl) ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none"><i class="bi bi-play-circle me-1"></i><?= htmlspecialchars(project_demo_link_label($demoUrl, 'Demo')) ?></a>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($p['status'] === 'rejected' && !empty($p['rejection_reason'])): ?>
                                                <div class="alert alert-danger p-2 small mt-2 mb-0">
                                                    <strong>Faculty Feedback:</strong> <?= htmlspecialchars($p['rejection_reason']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Scores & Actions -->
                                        <div class="col-md-3 text-center border-start-md">
                                            <?php if ($p['status'] === 'approved' && !empty($p['total_score'])): ?>
                                                <div class="mb-2">
                                                    <span class="small text-muted d-block fw-semibold">Faculty Marks</span>
                                                    <span class="h3 fw-extrabold text-success mb-0"><?= format_score($p['total_score']) ?></span>
                                                    <span class="text-muted small">/100</span>
                                                </div>
                                                <button type="button" class="btn btn-outline-success btn-sm w-100 mb-2" data-bs-toggle="modal" data-bs-target="#evalModal<?= $p['id'] ?>">
                                                    <i class="bi bi-award me-1"></i>View Evaluation
                                                </button>
                                            <?php elseif ($p['status'] === 'pending'): ?>
                                                <div class="mb-2 py-2">
                                                    <span class="badge bg-warning-subtle text-warning-emphasis p-2 w-100">
                                                        <i class="bi bi-clock me-1"></i>In Faculty Queue
                                                    </span>
                                                </div>
                                            <?php endif; ?>

                                            <div class="d-flex flex-column gap-1">
                                                <a href="<?= base_url('frontend/project-details.php?id=' . $p['id']) ?>" class="btn btn-light btn-sm border">
                                                    <i class="bi bi-eye me-1"></i>Showcase View
                                                </a>

                                                <?php if ($p['status'] === 'pending'): ?>
                                                    <a href="<?= base_url('frontend/student/edit-project.php?id=' . $p['id']) ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="bi bi-pencil me-1"></i>Edit Submission
                                                    </a>
                                                    <form method="POST" action="<?= base_url('frontend/student/my-projects.php') ?>" onsubmit="return confirm('Are you sure you want to delete this pending project draft?');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="delete_project_id" value="<?= $p['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                                            <i class="bi bi-trash me-1"></i>Delete Draft
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Evaluation Breakdown Modal -->
                            <?php if ($p['status'] === 'approved' && !empty($p['total_score'])): ?>
                                <div class="modal fade" id="evalModal<?= $p['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow">
                                            <div class="modal-header bg-light">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-award-fill text-warning me-2"></i>Official Project Evaluation</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="text-center mb-4">
                                                    <div class="small text-muted text-uppercase fw-semibold">Total Score Awarded</div>
                                                    <div class="display-4 fw-extrabold text-success"><?= format_score($p['total_score']) ?><span class="fs-4 text-muted">/100</span></div>
                                                    <?php if (!empty($p['overall_rank'])): ?>
                                                        <div class="mt-1"><?= get_rank_badge((int)$p['overall_rank']) ?></div>
                                                    <?php endif; ?>
                                                </div>

                                                <h6 class="fw-bold mb-3">100-Mark Rubric Breakdown:</h6>
                                                <ul class="list-group mb-4 small">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="bi bi-lightbulb me-2 text-warning"></i>Innovation & Uniqueness</span>
                                                        <strong><?= format_score($p['innovation_score']) ?> / 20</strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="bi bi-gear-wide-connected me-2 text-primary"></i>System Functionality</span>
                                                        <strong><?= format_score($p['functionality_score']) ?> / 30</strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="bi bi-palette me-2 text-info"></i>UI / UX & Responsive Design</span>
                                                        <strong><?= format_score($p['ui_design_score']) ?> / 20</strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="bi bi-cpu me-2 text-success"></i>Technology Stack Usage</span>
                                                        <strong><?= format_score($p['tech_usage_score']) ?> / 15</strong>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        <span><i class="bi bi-file-earmark-text me-2 text-secondary"></i>Presentation & Documentation</span>
                                                        <strong><?= format_score($p['presentation_score']) ?> / 15</strong>
                                                    </li>
                                                </ul>

                                                <h6 class="fw-bold mb-2">Faculty Remarks & Feedback:</h6>
                                                <div class="p-3 bg-light rounded-3 border small text-secondary fst-italic">
                                                    "<?= htmlspecialchars($p['feedback_text'] ?? 'Good project implementation.') ?>"
                                                </div>
                                                <div class="text-end small text-muted mt-2">
                                                    Evaluated by <strong><?= htmlspecialchars($p['evaluator_name'] ?? 'Faculty Board') ?></strong><br>
                                                    <span class="small"><?= date('F d, Y', strtotime($p['evaluated_at'])) ?></span>
                                                </div>
                                            </div>
                                            <div class="modal-footer bg-light">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../backend/core/footer.php'; ?>
