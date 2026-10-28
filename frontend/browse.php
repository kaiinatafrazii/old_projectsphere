<?php
/**
 * ProjectSphere - Browse & Search Approved Projects
 * If student is logged in, seamlessly redirect to In-Dashboard Explore Hub ("dashboard me he khule")
 */
require_once __DIR__ . '/../backend/core/db.php';
require_once __DIR__ . '/../backend/core/auth.php';
require_once __DIR__ . '/../backend/core/functions.php';

// Seamless In-Dashboard Redirection for Logged-In Students
if (is_student()) {
    header('Location: ' . base_url('frontend/student/dashboard.php?tab=explore'));
    exit;
}

$pageTitle = 'Explore Approved Capstone Projects | ProjectSphere';
$pageDescription = 'Search approved student capstone projects by title, category, student, and technology.';
require_once __DIR__ . '/../backend/core/header.php';

// Fetch All Approved Projects
$sql = "
    SELECT p.*, c.name AS category_name, c.icon AS category_icon, 
           s.full_name AS student_name, s.roll_no AS student_roll, s.department AS student_dept,
           e.total_score, e.innovation_score, e.functionality_score, e.ui_design_score, e.tech_usage_score, e.presentation_score, e.feedback_text,
           r.overall_rank, r.category_rank
    FROM projects p
    INNER JOIN project_categories c ON p.category_id = c.id
    INNER JOIN students s ON p.student_id = s.id
    LEFT JOIN evaluations e ON p.id = e.project_id AND e.is_published = 1
    LEFT JOIN rankings r ON p.id = r.project_id
    WHERE p.status = 'approved'
    ORDER BY (r.overall_rank IS NULL), r.overall_rank ASC, p.submitted_at DESC
";
$projects = $pdo->query($sql)->fetchAll();

// Fetch all categories for filter dropdown
$allCategories = $pdo->query("SELECT id, name FROM project_categories ORDER BY name ASC")->fetchAll();

// Fetch team members mapped by project_id
$approvedIds = array_column($projects, 'id');
$teamMembersMap = [];
if (!empty($approvedIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($approvedIds), '?'));
    $tmStmt = $pdo->prepare("SELECT * FROM project_team_members WHERE project_id IN ($inPlaceholders) ORDER BY id ASC");
    $tmStmt->execute($approvedIds);
    foreach ($tmStmt->fetchAll() as $tm) {
        $teamMembersMap[$tm['project_id']][] = $tm;
    }
}

// Highest score computation
$highestScore = 0;
foreach ($projects as $p) {
    if (!empty($p['total_score']) && $p['total_score'] > $highestScore) {
        $highestScore = $p['total_score'];
    }
}

$popularTechs = ['All', 'PHP', 'MySQL', 'Python', 'Android', 'Java', 'IoT', 'Bootstrap', 'JavaScript'];
?>

<div class="container-xl py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= base_url() ?>">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Explore Projects</li>
        </ol>
    </nav>

    <!-- Redesigned Hero Banner -->
    <div class="explore-hero-card mb-4">
        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-8">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-white text-primary fw-bold px-3 py-1.5 rounded-pill shadow-sm">
                        <i class="bi bi-patch-check-fill text-primary me-1"></i> Faculty Accredited
                    </span>
                    <span class="badge bg-white bg-opacity-25 text-white fw-medium px-2.5 py-1 rounded-pill">
                        Computer Engineering Repository
                    </span>
                </div>
                <h1 class="h3 fw-bold mb-2 text-white">Explore Approved Capstone Projects</h1>
                <p class="text-white-50 small mb-0" style="max-width: 580px;">
                    Discover benchmark diploma student capstones evaluated by faculty panels. Study architectures, review problem statements, and inspect verified marks.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <div class="d-inline-flex flex-column gap-2 text-start">
                    <div class="explore-stat-badge">
                        <i class="bi bi-check2-circle fs-4 text-success"></i>
                        <div>
                            <div class="fw-bold text-white fs-6 mb-0"><?= count($projects) ?> Approved</div>
                            <div class="small text-white-50" style="font-size: 0.75rem;">Verified Capstones</div>
                        </div>
                    </div>
                    <?php if ($highestScore > 0): ?>
                    <div class="explore-stat-badge">
                        <i class="bi bi-trophy-fill fs-4 text-warning"></i>
                        <div>
                            <div class="fw-bold text-white fs-6 mb-0"><?= format_score($highestScore) ?> / 100</div>
                            <div class="small text-white-50" style="font-size: 0.75rem;">Top Faculty Score</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Instant Filter & Live Search Panel -->
    <div class="explore-filter-panel mb-4">
        <div class="row g-3 align-items-center">
            <!-- Search Box -->
            <div class="col-lg-5 col-md-12">
                <label class="form-label small fw-semibold text-muted mb-1">Search Keywords</label>
                <div class="search-input-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input type="text" 
                           id="exploreSearchInput" 
                           class="form-control" 
                           placeholder="Search title, student, stack, or problem..." 
                           oninput="filterExploreProjects()">
                    <button type="button" id="clearSearchBtn" class="clear-search-btn" onclick="clearExploreSearch()" title="Clear">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                </div>
            </div>

            <!-- Category Selector -->
            <div class="col-lg-3 col-md-6 col-6">
                <label class="form-label small fw-semibold text-muted mb-1">Category</label>
                <select id="exploreCategorySelect" class="form-select form-select-sm py-2 rounded-3" onchange="filterExploreProjects()">
                    <option value="">All Categories</option>
                    <?php foreach ($allCategories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sort By Selector -->
            <div class="col-lg-3 col-md-6 col-6">
                <label class="form-label small fw-semibold text-muted mb-1">Sort By</label>
                <select id="exploreSortSelect" class="form-select form-select-sm py-2 rounded-3" onchange="filterExploreProjects()">
                    <option value="rank">Leaderboard Rank (Highest First)</option>
                    <option value="score">Marks Score (100 - 0)</option>
                    <option value="latest">Latest Submissions</option>
                    <option value="title">Project Title (A - Z)</option>
                </select>
            </div>

            <!-- Reset Button -->
            <div class="col-lg-1 col-md-12 d-flex align-items-end">
                <button type="button" class="btn btn-outline-secondary w-100 py-2 rounded-3" onclick="resetExploreFilters()" title="Reset Filters">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </div>

        <!-- Quick Tech Chips -->
        <div class="mt-3 pt-3 border-top d-flex flex-wrap align-items-center gap-1.5" id="quickTechPills">
            <span class="small text-muted me-2 fw-semibold"><i class="bi bi-funnel me-1"></i>Quick Stack:</span>
            <?php foreach ($popularTechs as $pt): ?>
                <button type="button" 
                        class="filter-chip <?= ($pt === 'All') ? 'active' : '' ?>" 
                        data-tech="<?= ($pt === 'All') ? '' : htmlspecialchars($pt) ?>" 
                        onclick="selectTechFilter(this)">
                    <?= htmlspecialchars($pt) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Live Counter Banner -->
        <div class="mt-3 pt-2 d-flex justify-content-between align-items-center text-muted small">
            <div>
                Showing <strong id="visibleProjectsCount" class="text-dark"><?= count($projects) ?></strong> of <strong class="text-dark"><?= count($projects) ?></strong> approved capstones
            </div>
            <div class="small text-secondary">
                <i class="bi bi-shield-check text-success me-1"></i>Verified by Computer Engineering Faculty
            </div>
        </div>
    </div>

    <!-- Projects Grid -->
    <div class="row g-4" id="exploreProjectsGrid">
        <?php foreach ($projects as $proj): 
            $teamMembers = $teamMembersMap[$proj['id']] ?? [];
            $demoUrl = project_demo_url($proj['demo_url'], $proj['title']);
            $projPayload = [
                'id' => $proj['id'],
                'title' => $proj['title'],
                'student_name' => $proj['student_name'],
                'student_roll' => $proj['student_roll'],
                'student_dept' => $proj['student_dept'] ?? 'Computer Engineering',
                'category_name' => $proj['category_name'],
                'category_id' => $proj['category_id'],
                'short_description' => $proj['short_description'],
                'problem_statement' => $proj['problem_statement'],
                'objectives' => $proj['objectives'],
                'features' => $proj['features'],
                'technologies' => $proj['technologies'],
                'thumbnail_image' => base_url($proj['thumbnail_image']),
                'demo_url' => $demoUrl,
                'demo_link_label' => project_demo_link_label($demoUrl, 'Live Demo / Video'),
                'submitted_at' => date('M d, Y', strtotime($proj['submitted_at'])),
                'overall_rank' => $proj['overall_rank'],
                'total_score' => $proj['total_score'],
                'innovation_score' => $proj['innovation_score'] ?? 0,
                'functionality_score' => $proj['functionality_score'] ?? 0,
                'ui_design_score' => $proj['ui_design_score'] ?? 0,
                'tech_usage_score' => $proj['tech_usage_score'] ?? 0,
                'presentation_score' => $proj['presentation_score'] ?? 0,
                'feedback_text' => $proj['feedback_text'] ?? '',
                'team_members' => $teamMembers
            ];
            $jsonPayload = htmlspecialchars(json_encode($projPayload), ENT_QUOTES, 'UTF-8');

            $rankClass = 'verified';
            $rankLabel = 'Verified';
            if (!empty($proj['overall_rank'])) {
                if ((int)$proj['overall_rank'] === 1) {
                    $rankClass = 'gold';
                    $rankLabel = 'Rank #1';
                } elseif ((int)$proj['overall_rank'] === 2) {
                    $rankClass = 'silver';
                    $rankLabel = 'Rank #2';
                } elseif ((int)$proj['overall_rank'] === 3) {
                    $rankClass = 'bronze';
                    $rankLabel = 'Rank #3';
                } else {
                    $rankClass = 'verified';
                    $rankLabel = 'Rank #' . $proj['overall_rank'];
                }
            }
        ?>
            <div class="col-lg-4 col-md-6 explore-card-col" 
                 data-id="<?= $proj['id'] ?>"
                 data-title="<?= strtolower(htmlspecialchars($proj['title'])) ?>"
                 data-desc="<?= strtolower(htmlspecialchars($proj['short_description'])) ?>"
                 data-tech="<?= strtolower(htmlspecialchars($proj['technologies'])) ?>"
                 data-author="<?= strtolower(htmlspecialchars($proj['student_name'])) ?>"
                 data-cat="<?= $proj['category_id'] ?>"
                 data-rank="<?= !empty($proj['overall_rank']) ? (int)$proj['overall_rank'] : 999 ?>"
                 data-score="<?= !empty($proj['total_score']) ? (float)$proj['total_score'] : 0 ?>"
                 data-date="<?= strtotime($proj['submitted_at']) ?>">
                
                <div class="explore-project-card">
                    <div class="explore-card-media">
                        <img src="<?= base_url($proj['thumbnail_image']) ?>" 
                             alt="<?= htmlspecialchars($proj['title']) ?>" 
                             loading="lazy">
                        
                        <div class="rank-pill <?= $rankClass ?>">
                            <?php if (!empty($proj['overall_rank']) && (int)$proj['overall_rank'] <= 3): ?>
                                <i class="bi bi-award-fill"></i>
                            <?php else: ?>
                                <i class="bi bi-check-circle-fill"></i>
                            <?php endif; ?>
                            <?= $rankLabel ?>
                        </div>

                        <?php if (!empty($proj['total_score'])): ?>
                            <div class="score-badge">
                                <i class="bi bi-star-fill"></i>
                                <?= format_score($proj['total_score']) ?>/100
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="explore-card-content">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="explore-cat-pill"><?= htmlspecialchars($proj['category_name']) ?></span>
                            <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('M Y', strtotime($proj['submitted_at'])) ?></small>
                        </div>

                        <h4 class="explore-card-title" 
                            onclick="openProjectQuickView(<?= $jsonPayload ?>)" 
                            title="<?= htmlspecialchars($proj['title']) ?>">
                            <?= htmlspecialchars($proj['title']) ?>
                        </h4>

                        <p class="explore-card-desc"><?= htmlspecialchars($proj['short_description']) ?></p>

                        <div class="mb-3 d-flex flex-wrap gap-1">
                            <?php 
                            $techArr = array_map('trim', explode(',', $proj['technologies']));
                            foreach (array_slice($techArr, 0, 3) as $t): 
                            ?>
                                <span class="explore-tech-badge"><?= htmlspecialchars($t) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($techArr) > 3): ?>
                                <span class="explore-tech-badge text-muted">+<?= count($techArr) - 3 ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="explore-author-box">
                            <div class="explore-author-avatar">
                                <?= strtoupper(substr($proj['student_name'], 0, 1)) ?>
                            </div>
                            <div class="overflow-hidden">
                                <div class="fw-semibold text-dark text-truncate" style="font-size: 0.85rem;">
                                    <?= htmlspecialchars($proj['student_name']) ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <?= htmlspecialchars($proj['student_roll']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="explore-card-actions">
                            <button type="button" 
                                    class="btn btn-primary w-100 shadow-sm" 
                                    onclick="openProjectQuickView(<?= $jsonPayload ?>)">
                                <i class="bi bi-eye-fill me-1"></i>Quick View
                            </button>
                            <a href="<?= base_url('frontend/project-details.php?id=' . $proj['id']) ?>" 
                               class="btn btn-outline-secondary" 
                               title="Open Full Page">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Empty State -->
    <div id="noExploreMatchesState" class="text-center py-5 bg-white rounded-4 border p-4 mt-3" style="display: none;">
        <div class="rounded-circle bg-light d-inline-flex p-3 mb-3 text-muted">
            <i class="bi bi-search fs-1"></i>
        </div>
        <h5 class="fw-bold text-dark mb-1">No Projects Match Your Filter</h5>
        <p class="text-secondary small mb-3">Try clearing search terms or selecting another technology stack.</p>
        <button type="button" class="btn btn-primary btn-sm px-4 rounded-pill" onclick="resetExploreFilters()">
            <i class="bi bi-arrow-clockwise me-1"></i>Reset All Filters
        </button>
    </div>
</div>

<!-- Quick View Modal -->
<div class="modal fade" id="exploreQuickViewModal" tabindex="-1" aria-labelledby="modalProjTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 18px; overflow: hidden;">
            <div class="modal-explore-header d-flex justify-content-between align-items-start">
                <div>
                    <span id="modalProjCategory" class="badge bg-white text-primary fw-semibold px-2.5 py-1 rounded-pill mb-2">Category</span>
                    <h3 class="h4 fw-bold text-white mb-1" id="modalProjTitle">Project Title</h3>
                    <div class="text-white-50 small">
                        Submitted by <span id="modalProjAuthor" class="text-white fw-semibold">Student Name</span> 
                        (<span id="modalProjRoll">Roll</span>) &bull; 
                        <span id="modalProjDept">Dept</span> &bull; 
                        <span id="modalProjDate">Date</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="modal-score-card mb-4" id="modalScoreContainer">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success text-white px-2.5 py-1.5 rounded-pill fw-bold">
                                <i class="bi bi-patch-check-fill me-1"></i>Official Faculty Marks
                            </span>
                            <span id="modalRankBadge" class="badge bg-dark text-warning fw-bold px-2.5 py-1.5 rounded-pill">Rank #</span>
                        </div>
                        <div class="h4 fw-bold text-dark mb-0">
                            <span id="modalTotalScore" class="text-primary">95</span> <span class="text-muted fs-6">/ 100</span>
                        </div>
                    </div>

                    <div class="row g-3 small">
                        <div class="col-md-4 col-6">
                            <div class="d-flex justify-content-between text-muted">
                                <span>Innovation:</span>
                                <strong id="modalInnovScore" class="text-dark">18/20</strong>
                            </div>
                            <div class="modal-score-bar"><div id="modalInnovBar" class="modal-score-bar-fill" style="width: 90%;"></div></div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="d-flex justify-content-between text-muted">
                                <span>Functionality:</span>
                                <strong id="modalFuncScore" class="text-dark">28/30</strong>
                            </div>
                            <div class="modal-score-bar"><div id="modalFuncBar" class="modal-score-bar-fill" style="width: 93%;"></div></div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="d-flex justify-content-between text-muted">
                                <span>UI/UX Design:</span>
                                <strong id="modalUiScore" class="text-dark">19/20</strong>
                            </div>
                            <div class="modal-score-bar"><div id="modalUiBar" class="modal-score-bar-fill" style="width: 95%;"></div></div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="d-flex justify-content-between text-muted">
                                <span>Tech Usage:</span>
                                <strong id="modalTechScore" class="text-dark">14/15</strong>
                            </div>
                            <div class="modal-score-bar"><div id="modalTechBar" class="modal-score-bar-fill" style="width: 93%;"></div></div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="d-flex justify-content-between text-muted">
                                <span>Presentation:</span>
                                <strong id="modalPresScore" class="text-dark">14/15</strong>
                            </div>
                            <div class="modal-score-bar"><div id="modalPresBar" class="modal-score-bar-fill" style="width: 93%;"></div></div>
                        </div>
                    </div>

                    <div id="modalFeedbackWrapper" class="mt-3 pt-2 border-top small" style="display: none;">
                        <span class="text-muted fw-semibold">Faculty Remarks:</span>
                        <p id="modalFeedbackText" class="fst-italic text-secondary mb-0 mt-1"></p>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-lightbulb-fill text-warning"></i>Problem Statement
                    </h6>
                    <div class="p-3 bg-light rounded-3 text-secondary small" id="modalProblemStatement" style="white-space: pre-line; line-height: 1.6;"></div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-flag-fill text-primary"></i>Objectives
                        </h6>
                        <div class="p-3 bg-light rounded-3 text-secondary small h-100" id="modalObjectives" style="white-space: pre-line; line-height: 1.6;"></div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-stars text-info"></i>Core Features
                        </h6>
                        <div class="p-3 bg-light rounded-3 text-secondary small h-100" id="modalFeatures" style="white-space: pre-line; line-height: 1.6;"></div>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-code-slash text-indigo"></i>Technologies &amp; Frameworks
                    </h6>
                    <div id="modalTechContainer" class="d-flex flex-wrap gap-1.5"></div>
                </div>

                <div id="modalTeamSection" class="mb-2">
                    <h6 class="fw-bold text-dark d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-people-fill text-secondary"></i>Team Members
                    </h6>
                    <div id="modalTeamList" class="row g-2"></div>
                </div>
            </div>

            <div class="modal-footer bg-light border-top p-3 justify-content-between">
                <div id="modalDemoLinkWrapper">
                    <a href="#" id="modalDemoLink" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill" style="display: none;">
                        <i class="bi bi-play-circle-fill me-1"></i><span id="modalDemoLinkLabel">Live Demo / Video</span>
                    </a>
                </div>
                <div class="d-flex gap-2">
                    <a href="#" id="modalFullPageLink" class="btn btn-outline-secondary btn-sm rounded-pill">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Full Dossier Page
                    </a>
                    <button type="button" class="btn btn-primary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentSelectedTech = '';

function filterExploreProjects() {
    const searchVal = document.getElementById('exploreSearchInput').value.trim().toLowerCase();
    const catVal = document.getElementById('exploreCategorySelect').value;
    const sortVal = document.getElementById('exploreSortSelect').value;
    const clearBtn = document.getElementById('clearSearchBtn');
    
    clearBtn.style.display = searchVal ? 'block' : 'none';

    const cards = Array.from(document.querySelectorAll('.explore-card-col'));
    let visibleCount = 0;

    cards.forEach(card => {
        const title = card.getAttribute('data-title') || '';
        const desc = card.getAttribute('data-desc') || '';
        const tech = card.getAttribute('data-tech') || '';
        const author = card.getAttribute('data-author') || '';
        const cat = card.getAttribute('data-cat') || '';

        const matchesSearch = !searchVal || 
            title.includes(searchVal) || 
            desc.includes(searchVal) || 
            tech.includes(searchVal) || 
            author.includes(searchVal);

        const matchesCategory = !catVal || cat === catVal;
        const matchesTech = !currentSelectedTech || tech.includes(currentSelectedTech.toLowerCase());

        if (matchesSearch && matchesCategory && matchesTech) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    document.getElementById('visibleProjectsCount').textContent = visibleCount;
    const emptyState = document.getElementById('noExploreMatchesState');
    if (emptyState) {
        emptyState.style.display = (visibleCount === 0) ? 'block' : 'none';
    }

    sortCardsInDOM(sortVal);
}

function sortCardsInDOM(sortType) {
    const grid = document.getElementById('exploreProjectsGrid');
    const cards = Array.from(grid.querySelectorAll('.explore-card-col'));

    cards.sort((a, b) => {
        if (sortType === 'rank') {
            const rankA = parseInt(a.getAttribute('data-rank')) || 999;
            const rankB = parseInt(b.getAttribute('data-rank')) || 999;
            return rankA - rankB;
        } else if (sortType === 'score') {
            const scoreA = parseFloat(a.getAttribute('data-score')) || 0;
            const scoreB = parseFloat(b.getAttribute('data-score')) || 0;
            return scoreB - scoreA;
        } else if (sortType === 'title') {
            const titleA = a.getAttribute('data-title') || '';
            const titleB = b.getAttribute('data-title') || '';
            return titleA.localeCompare(titleB);
        } else {
            const dateA = parseInt(a.getAttribute('data-date')) || 0;
            const dateB = parseInt(b.getAttribute('data-date')) || 0;
            return dateB - dateA;
        }
    });

    cards.forEach(card => grid.appendChild(card));
}

function selectTechFilter(btn) {
    document.querySelectorAll('#quickTechPills .filter-chip').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentSelectedTech = btn.getAttribute('data-tech') || '';
    filterExploreProjects();
}

function clearExploreSearch() {
    document.getElementById('exploreSearchInput').value = '';
    filterExploreProjects();
    document.getElementById('exploreSearchInput').focus();
}

function resetExploreFilters() {
    document.getElementById('exploreSearchInput').value = '';
    document.getElementById('exploreCategorySelect').value = '';
    document.getElementById('exploreSortSelect').value = 'rank';
    currentSelectedTech = '';
    document.querySelectorAll('#quickTechPills .filter-chip').forEach((b, i) => {
        if (i === 0) b.classList.add('active');
        else b.classList.remove('active');
    });
    filterExploreProjects();
}

function openProjectQuickView(proj) {
    document.getElementById('modalProjTitle').textContent = proj.title;
    document.getElementById('modalProjCategory').textContent = proj.category_name;
    document.getElementById('modalProjAuthor').textContent = proj.student_name;
    document.getElementById('modalProjRoll').textContent = proj.student_roll;
    document.getElementById('modalProjDept').textContent = proj.student_dept;
    document.getElementById('modalProjDate').textContent = proj.submitted_at;

    const scoreContainer = document.getElementById('modalScoreContainer');
    if (proj.total_score && parseFloat(proj.total_score) > 0) {
        scoreContainer.style.display = 'block';
        document.getElementById('modalTotalScore').textContent = parseFloat(proj.total_score).toFixed(1);
        document.getElementById('modalRankBadge').textContent = proj.overall_rank ? ('Rank #' + proj.overall_rank) : 'Accredited';

        document.getElementById('modalInnovScore').textContent = parseFloat(proj.innovation_score).toFixed(1) + '/20';
        document.getElementById('modalInnovBar').style.width = ((parseFloat(proj.innovation_score) / 20) * 100) + '%';

        document.getElementById('modalFuncScore').textContent = parseFloat(proj.functionality_score).toFixed(1) + '/30';
        document.getElementById('modalFuncBar').style.width = ((parseFloat(proj.functionality_score) / 30) * 100) + '%';

        document.getElementById('modalUiScore').textContent = parseFloat(proj.ui_design_score).toFixed(1) + '/20';
        document.getElementById('modalUiBar').style.width = ((parseFloat(proj.ui_design_score) / 20) * 100) + '%';

        document.getElementById('modalTechScore').textContent = parseFloat(proj.tech_usage_score).toFixed(1) + '/15';
        document.getElementById('modalTechBar').style.width = ((parseFloat(proj.tech_usage_score) / 15) * 100) + '%';

        document.getElementById('modalPresScore').textContent = parseFloat(proj.presentation_score).toFixed(1) + '/15';
        document.getElementById('modalPresBar').style.width = ((parseFloat(proj.presentation_score) / 15) * 100) + '%';

        const fbWrap = document.getElementById('modalFeedbackWrapper');
        if (proj.feedback_text) {
            fbWrap.style.display = 'block';
            document.getElementById('modalFeedbackText').textContent = '"' + proj.feedback_text + '"';
        } else {
            fbWrap.style.display = 'none';
        }
    } else {
        scoreContainer.style.display = 'none';
    }

    document.getElementById('modalProblemStatement').textContent = proj.problem_statement || proj.short_description;
    document.getElementById('modalObjectives').textContent = proj.objectives || 'Not specified';
    document.getElementById('modalFeatures').textContent = proj.features || 'Standard academic module delivery';

    const techBox = document.getElementById('modalTechContainer');
    techBox.innerHTML = '';
    if (proj.technologies) {
        proj.technologies.split(',').map(s => s.trim()).forEach(tech => {
            if (tech) {
                const span = document.createElement('span');
                span.className = 'explore-tech-badge px-2 py-1';
                span.textContent = tech;
                techBox.appendChild(span);
            }
        });
    }

    const teamSection = document.getElementById('modalTeamSection');
    const teamList = document.getElementById('modalTeamList');
    teamList.innerHTML = '';
    if (proj.team_members && proj.team_members.length > 0) {
        teamSection.style.display = 'block';
        proj.team_members.forEach(m => {
            const col = document.createElement('div');
            col.className = 'col-md-6';
            col.innerHTML = `
                <div class="p-2 border rounded-3 bg-white d-flex align-items-center gap-2 small">
                    <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.75rem;">
                        ${(m.member_name || 'U').charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <div class="fw-bold text-dark">${m.member_name}</div>
                        <div class="text-muted" style="font-size: 0.75rem;">${m.roll_no} &bull; ${m.role || 'Member'}</div>
                    </div>
                </div>
            `;
            teamList.appendChild(col);
        });
    } else {
        teamSection.style.display = 'none';
    }

    const demoLink = document.getElementById('modalDemoLink');
    if (proj.demo_url) {
        demoLink.href = proj.demo_url;
        document.getElementById('modalDemoLinkLabel').textContent = proj.demo_link_label;
        demoLink.style.display = 'inline-block';
    } else {
        demoLink.style.display = 'none';
    }

    document.getElementById('modalFullPageLink').href = '<?= base_url('frontend/project-details.php?id=') ?>' + proj.id;

    const modalEl = document.getElementById('exploreQuickViewModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../backend/core/footer.php'; ?>
