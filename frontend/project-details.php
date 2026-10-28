<?php
/**
 * ProjectSphere - Public / Student Project Showcase View
 * CRITICAL SECURITY: Never expose source code, private zips, or private repositories here!
 */
require_once __DIR__ . '/../backend/core/db.php';
require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($projectId <= 0) {
    header('Location: ' . base_url('frontend/browse.php'));
    exit;
}

// Fetch project details
$stmt = $pdo->prepare("
    SELECT p.*, 
           c.name AS category_name, c.icon AS category_icon,
           s.full_name AS student_name, s.roll_no AS student_roll, s.department, s.semester,
           e.innovation_score, e.functionality_score, e.ui_design_score, e.tech_usage_score, 
           e.presentation_score, e.total_score, e.feedback_text, e.is_published, e.evaluated_at,
           r.overall_rank, r.category_rank,
           a.full_name AS evaluator_name, a.designation AS evaluator_designation
    FROM projects p
    INNER JOIN project_categories c ON p.category_id = c.id
    INNER JOIN students s ON p.student_id = s.id
    LEFT JOIN evaluations e ON p.id = e.project_id
    LEFT JOIN rankings r ON p.id = r.project_id
    LEFT JOIN admins a ON e.admin_id = a.id
    WHERE p.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();

if (!$project) {
    $_SESSION['flash_error'] = 'The requested project could not be found.';
    header('Location: ' . base_url('frontend/browse.php'));
    exit;
}

$demoUrl = project_demo_url($project['demo_url'], $project['title']);

// Check authorization: Non-approved projects can only be viewed by the project author or admin
if ($project['status'] !== 'approved') {
    $canView = false;
    if (is_admin()) {
        $canView = true;
    } elseif (is_student() && isset($_SESSION['profile_id']) && $_SESSION['profile_id'] == $project['student_id']) {
        $canView = true;
    }

    if (!$canView) {
        $_SESSION['flash_error'] = 'This project is currently pending evaluation and is not publicly visible.';
        header('Location: ' . base_url('frontend/browse.php'));
        exit;
    }
}

// Fetch team members
$teamStmt = $pdo->prepare("SELECT * FROM project_team_members WHERE project_id = :pid ORDER BY id ASC");
$teamStmt->execute([':pid' => $projectId]);
$teamMembers = $teamStmt->fetchAll();

// Fetch screenshots / gallery images
$imgStmt = $pdo->prepare("SELECT * FROM project_images WHERE project_id = :pid ORDER BY id ASC");
$imgStmt->execute([':pid' => $projectId]);
$galleryImages = $imgStmt->fetchAll();

$pageTitle = ($project['title'] ?? 'Project Details') . ' - ProjectSphere';
require_once __DIR__ . '/../backend/core/header.php';
?>

<div class="container-xl py-4">
    <!-- Breadcrumb & Back -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <!-- Back to Explore Projects -->
        <div>
            <a href="<?= is_student() ? base_url('frontend/student/dashboard.php?tab=explore') : base_url('frontend/browse.php') ?>" 
               class="btn-back-explore">
                <i class="bi bi-arrow-left btn-back-icon"></i>
                <span>Back to Explore Projects</span>
            </a>
        </div>

        <?php if (is_admin()): ?>
            <div class="mt-2 mt-md-0">
                <span class="badge bg-secondary me-2">Faculty View</span>
                <a href="<?= base_url('frontend/admin/evaluate.php?id=' . $project['id']) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil-square me-1"></i>Evaluate / Edit Marks
                </a>
                <a href="<?= base_url('frontend/admin/review-project.php?id=' . $project['id']) ?>" class="btn btn-sm btn-dark">
                    <i class="bi bi-file-earmark-code me-1"></i>Private Code Review
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Non-approved banner notification if author/admin is viewing -->
    <?php if ($project['status'] !== 'approved'): ?>
        <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
            <div>
                <strong>Notice:</strong> This project is currently <strong><?= strtoupper($project['status']) ?></strong> and only visible to you.
                <?php if (!empty($project['rejection_reason'])): ?>
                    <div class="small mt-1 text-danger">Faculty remarks: <?= htmlspecialchars($project['rejection_reason']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Project Header Banner Card -->
    <div class="card border shadow-sm p-4 mb-4" style="border-radius: 16px;">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="category-pill fs-7">
                        <i class="bi <?= htmlspecialchars($project['category_icon']) ?> me-1"></i><?= htmlspecialchars($project['category_name']) ?>
                    </span>

                    <?php if ($project['status'] === 'approved' && !empty($project['overall_rank'])): ?>
                        <?= get_rank_badge((int)$project['overall_rank']) ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 rounded-pill small">
                            Category Rank #<?= $project['category_rank'] ?>
                        </span>
                    <?php else: ?>
                        <?= get_status_badge($project['status']) ?>
                    <?php endif; ?>
                </div>

                <h1 class="h2 fw-bold text-dark mb-2"><?= htmlspecialchars($project['title']) ?></h1>
                <p class="lead text-secondary fs-6 mb-3"><?= htmlspecialchars($project['short_description']) ?></p>

                <div class="d-flex flex-wrap align-items-center gap-4 text-muted small pt-2 border-top">
                    <div>
                        <i class="bi bi-person-fill text-primary me-1"></i> Lead: <strong><?= htmlspecialchars($project['student_name']) ?></strong> (<?= htmlspecialchars($project['student_roll']) ?>)
                    </div>
                    <div>
                        <i class="bi bi-building me-1"></i> <?= htmlspecialchars($project['department']) ?> &bull; <?= htmlspecialchars($project['semester']) ?>
                    </div>
                    <div>
                        <i class="bi bi-calendar-event me-1"></i> Submitted: <?= date('F d, Y', strtotime($project['submitted_at'])) ?>
                    </div>
                </div>
            </div>

            <!-- Scorecard Widget -->
            <div class="col-lg-4 text-center">
                <?php if ($project['status'] === 'approved' && !empty($project['total_score'])): ?>
                    <div class="p-3 bg-light rounded-4 border">
                        <div class="small fw-bold text-uppercase text-secondary mb-1">Total Academic Score</div>
                        <div class="display-5 fw-extrabold text-primary mb-1">
                            <?= format_score($project['total_score']) ?><span class="fs-4 text-muted">/100</span>
                        </div>
                        <div class="progress mb-2" style="height: 8px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= (float)$project['total_score'] ?>%;"></div>
                        </div>
                        <div class="small text-muted">Evaluated by <strong><?= htmlspecialchars($project['evaluator_name'] ?? 'Faculty Board') ?></strong></div>
                    </div>
                <?php else: ?>
                    <div class="p-3 bg-light rounded-4 border">
                        <i class="bi bi-hourglass-split fs-2 text-warning mb-2 d-block"></i>
                        <h6 class="fw-bold mb-1">Awaiting Evaluation</h6>
                        <p class="small text-muted mb-0">Score and ranking will appear once evaluated by the faculty coordinator.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Content & Sidebar -->
    <div class="row g-4">
        <!-- Left Column: Details -->
        <div class="col-lg-8">
            <!-- Project Image Banner -->
            <div class="card border shadow-sm mb-4 overflow-hidden" style="border-radius: 14px;">
                <img src="<?= base_url($project['thumbnail_image']) ?>" alt="<?= htmlspecialchars($project['title']) ?>" class="img-fluid w-100" style="max-height: 420px; object-fit: cover;">
            </div>

            <!-- Gallery screenshots if any -->
            <?php if (!empty($galleryImages)): ?>
                <div class="content-card mb-4">
                    <h5 class="fw-bold mb-3"><i class="bi bi-images me-2 text-primary"></i>Project Screenshots</h5>
                    <div class="row g-3">
                        <?php foreach ($galleryImages as $gImg): ?>
                            <div class="col-md-4 col-6">
                                <a href="<?= base_url($gImg['image_path']) ?>" target="_blank">
                                    <img src="<?= base_url($gImg['image_path']) ?>" class="img-thumbnail rounded-3 w-100" style="height: 140px; object-fit: cover;" alt="Screenshot">
                                </a>
                                <?php if (!empty($gImg['caption'])): ?>
                                    <div class="small text-muted text-center mt-1"><?= htmlspecialchars($gImg['caption']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Problem Statement -->
            <div class="content-card">
                <h5 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i>Problem Statement</h5>
                <div class="text-secondary lh-base" style="white-space: pre-line;"><?= htmlspecialchars($project['problem_statement']) ?></div>
            </div>

            <!-- Objectives -->
            <div class="content-card">
                <h5 class="fw-bold mb-3"><i class="bi bi-bullseye me-2 text-primary"></i>Project Objectives</h5>
                <div class="text-secondary lh-base" style="white-space: pre-line;"><?= htmlspecialchars($project['objectives']) ?></div>
            </div>

            <!-- Key Features -->
            <div class="content-card">
                <h5 class="fw-bold mb-3"><i class="bi bi-check-all me-2 text-success"></i>Key Features & Modules</h5>
                <div class="text-secondary lh-base" style="white-space: pre-line;"><?= htmlspecialchars($project['features']) ?></div>
            </div>

            <!-- Faculty Evaluation Rubric Breakdown -->
            <?php if ($project['status'] === 'approved' && !empty($project['total_score'])): ?>
                <div class="content-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-clipboard2-check-fill me-2 text-success"></i>Official Faculty Marks Breakdown</h5>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Official Rubric</span>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-sm-4 col-6">
                            <div class="eval-metric-box">
                                <div class="eval-metric-score"><?= format_score($project['innovation_score']) ?></div>
                                <div class="eval-metric-max">Max: 20</div>
                                <div class="eval-metric-title">Innovation</div>
                            </div>
                        </div>
                        <div class="col-sm-4 col-6">
                            <div class="eval-metric-box">
                                <div class="eval-metric-score"><?= format_score($project['functionality_score']) ?></div>
                                <div class="eval-metric-max">Max: 30</div>
                                <div class="eval-metric-title">Functionality</div>
                            </div>
                        </div>
                        <div class="col-sm-4 col-6">
                            <div class="eval-metric-box">
                                <div class="eval-metric-score"><?= format_score($project['ui_design_score']) ?></div>
                                <div class="eval-metric-max">Max: 20</div>
                                <div class="eval-metric-title">UI & UX Design</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-6">
                            <div class="eval-metric-box">
                                <div class="eval-metric-score"><?= format_score($project['tech_usage_score']) ?></div>
                                <div class="eval-metric-max">Max: 15</div>
                                <div class="eval-metric-title">Technology Usage</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-12">
                            <div class="eval-metric-box">
                                <div class="eval-metric-score"><?= format_score($project['presentation_score']) ?></div>
                                <div class="eval-metric-max">Max: 15</div>
                                <div class="eval-metric-title">Presentation & Docs</div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($project['feedback_text'])): ?>
                        <div class="p-3 bg-light rounded-3 border">
                            <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-chat-quote-fill me-2 text-primary"></i>Faculty Evaluator Feedback:</h6>
                            <p class="text-secondary small fst-italic mb-2">"<?= htmlspecialchars($project['feedback_text']) ?>"</p>
                            <div class="text-end small text-muted">
                                &mdash; <strong><?= htmlspecialchars($project['evaluator_name'] ?? 'Faculty Board') ?></strong>, <?= htmlspecialchars($project['evaluator_designation'] ?? 'Evaluator') ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Sidebar Meta -->
        <div class="col-lg-4">
            <!-- Technologies Used -->
            <div class="content-card">
                <h6 class="fw-bold mb-3"><i class="bi bi-cpu-fill me-2 text-primary"></i>Technologies Used</h6>
                <div class="d-flex flex-wrap gap-1">
                    <?php 
                    $techArray = array_map('trim', explode(',', $project['technologies']));
                    foreach ($techArray as $t): 
                    ?>
                        <span class="badge bg-light text-dark border p-2 fw-medium"><?= htmlspecialchars($t) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Demo Link & Documentation -->
            <div class="content-card">
                <h6 class="fw-bold mb-3"><i class="bi bi-link-45deg me-2 text-primary"></i>Project Resources</h6>
                <div class="d-grid gap-2">
                    <?php if ($demoUrl !== null): ?>
                        <a href="<?= htmlspecialchars($demoUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm text-start py-2">
                            <i class="bi bi-play-circle-fill me-2 text-danger"></i><?= htmlspecialchars(project_demo_link_label($demoUrl)) ?>
                        </a>
                    <?php else: ?>
                        <span class="btn btn-light btn-sm text-muted text-start py-2 disabled">
                            <i class="bi bi-camera-video-off me-2"></i>No Demo Link Provided
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($project['documentation_file'])): ?>
                        <a href="<?= base_url($project['documentation_file']) ?>" target="_blank" class="btn btn-outline-secondary btn-sm text-start py-2">
                            <i class="bi bi-file-earmark-pdf-fill me-2 text-danger"></i>View Project Report (PDF)
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Explicit Code Privacy Notice for Students/Examiners -->
                <div class="mt-3 p-2.5 bg-light rounded-3 border small text-muted">
                    <i class="bi bi-shield-lock-fill text-warning me-1"></i>
                    <strong>Code Protection Policy:</strong> Source code is strictly confidential for teacher evaluation and is not publicly downloadable.
                </div>
            </div>

            <!-- Team Members -->
            <div class="content-card">
                <h6 class="fw-bold mb-3"><i class="bi bi-people-fill me-2 text-primary"></i>Project Team</h6>
                <div class="list-group list-group-flush border rounded-3">
                    <!-- Lead -->
                    <div class="list-group-item d-flex justify-content-between align-items-center py-2.5 bg-light">
                        <div>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($project['student_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($project['student_roll']) ?></small>
                        </div>
                        <span class="badge bg-primary text-white">Project Lead</span>
                    </div>

                    <!-- Additional Members -->
                    <?php foreach ($teamMembers as $tm): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2.5">
                            <div>
                                <div class="fw-medium text-dark"><?= htmlspecialchars($tm['member_name']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($tm['roll_no']) ?></small>
                            </div>
                            <span class="badge bg-light text-secondary border"><?= htmlspecialchars($tm['role']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../backend/core/footer.php'; ?>
