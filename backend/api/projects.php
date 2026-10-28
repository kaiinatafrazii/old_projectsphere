<?php
/**
 * ProjectSphere - Projects REST API Endpoint
 */
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$currentUser = get_current_user_data($pdo);

// -------------------------------------------------------------
// GET: Fetch Projects
// -------------------------------------------------------------
if ($method === 'GET') {
    // 1. Single Project Details
    if (isset($_GET['id'])) {
        $projId = (int)$_GET['id'];
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
        $stmt->execute([':id' => $projId]);
        $project = $stmt->fetch();

        if (!$project) {
            json_response(['success' => false, 'message' => 'Project not found.'], 404);
        }

        $project['demo_url'] = project_demo_url($project['demo_url'], $project['title']);

        // Authorization check for non-approved
        if ($project['status'] !== 'approved') {
            $canView = ($currentUser && ($currentUser['role'] === 'admin' || ($currentUser['role'] === 'student' && $currentUser['profile_id'] == $project['student_id'])));
            if (!$canView) {
                json_response(['success' => false, 'message' => 'Project is currently pending review.'], 403);
            }
        }

        // Team members
        $tStmt = $pdo->prepare("SELECT * FROM project_team_members WHERE project_id = :pid ORDER BY id ASC");
        $tStmt->execute([':pid' => $projId]);
        $team = $tStmt->fetchAll();

        // Screenshots
        $iStmt = $pdo->prepare("SELECT * FROM project_images WHERE project_id = :pid ORDER BY id ASC");
        $iStmt->execute([':pid' => $projId]);
        $images = $iStmt->fetchAll();

        // STRICT SECURITY: Remove private files unless user is admin
        $isAdmin = ($currentUser && $currentUser['role'] === 'admin');
        if (!$isAdmin) {
            unset($project['private_source_code_file']);
            unset($project['private_source_repo']);
        }

        json_response([
            'success'      => true,
            'project'      => $project,
            'team_members' => $team,
            'images'       => $images
        ]);
    }

    // 2. My Projects (Student)
    if (isset($_GET['my'])) {
        $studentUser = require_student_api($pdo);
        $sStmt = $pdo->prepare("
            SELECT p.*, c.name AS category_name,
                   e.innovation_score, e.functionality_score, e.ui_design_score, 
                   e.tech_usage_score, e.presentation_score, e.total_score, 
                   e.feedback_text, e.is_published, e.evaluated_at,
                   r.overall_rank, r.category_rank,
                   a.full_name AS evaluator_name
            FROM projects p
            INNER JOIN project_categories c ON p.category_id = c.id
            LEFT JOIN evaluations e ON p.id = e.project_id
            LEFT JOIN rankings r ON p.id = r.project_id
            LEFT JOIN admins a ON e.admin_id = a.id
            WHERE p.student_id = :sid
            ORDER BY p.submitted_at DESC
        ");
        $sStmt->execute([':sid' => $studentUser['profile_id']]);
        $myProjects = $sStmt->fetchAll();
        foreach ($myProjects as &$project) {
            $project['demo_url'] = project_demo_url($project['demo_url'], $project['title']);
        }
        unset($project);
        json_response(['success' => true, 'projects' => $myProjects]);
    }

    // 3. All Projects for Admin
    if (isset($_GET['all'])) {
        require_admin_api($pdo);
        $status = $_GET['status'] ?? '';
        $cat    = (int)($_GET['category'] ?? 0);
        $search = trim($_GET['search'] ?? '');

        $sql = "
            SELECT p.*, c.name AS category_name, s.full_name AS student_name, s.roll_no AS student_roll,
                   e.total_score, r.overall_rank
            FROM projects p
            INNER JOIN project_categories c ON p.category_id = c.id
            INNER JOIN students s ON p.student_id = s.id
            LEFT JOIN evaluations e ON p.id = e.project_id
            LEFT JOIN rankings r ON p.id = r.project_id
            WHERE 1=1
        ";
        $params = [];
        if (!empty($status)) {
            $sql .= " AND p.status = :status";
            $params[':status'] = $status;
        }
        if ($cat > 0) {
            $sql .= " AND p.category_id = :cat";
            $params[':cat'] = $cat;
        }
        if (!empty($search)) {
            $sql .= " AND (p.title LIKE :s1 OR s.full_name LIKE :s2 OR s.roll_no LIKE :s3)";
            $params[':s1'] = "%{$search}%";
            $params[':s2'] = "%{$search}%";
            $params[':s3'] = "%{$search}%";
        }
        $sql .= " ORDER BY p.submitted_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $projects = $stmt->fetchAll();
        foreach ($projects as &$project) {
            $project['demo_url'] = project_demo_url($project['demo_url'], $project['title']);
        }
        unset($project);
        json_response(['success' => true, 'projects' => $projects]);
    }

    // 4. Public Approved Projects Catalog
    $search = trim($_GET['search'] ?? '');
    $cat    = (int)($_GET['category'] ?? 0);
    $tech   = trim($_GET['tech'] ?? '');
    $sort   = trim($_GET['sort'] ?? 'latest');

    $sql = "
        SELECT p.id, p.title, p.slug, p.short_description, p.technologies, p.thumbnail_image, p.submitted_at,
               c.id AS category_id, c.name AS category_name, c.icon AS category_icon,
               s.full_name AS student_name, s.roll_no AS student_roll,
               e.total_score, r.overall_rank, r.category_rank
        FROM projects p
        INNER JOIN project_categories c ON p.category_id = c.id
        INNER JOIN students s ON p.student_id = s.id
        LEFT JOIN evaluations e ON p.id = e.project_id AND e.is_published = 1
        LEFT JOIN rankings r ON p.id = r.project_id
        WHERE p.status = 'approved'
    ";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (p.title LIKE :s1 OR p.short_description LIKE :s2 OR p.technologies LIKE :s3 OR s.full_name LIKE :s4)";
        $params[':s1'] = "%{$search}%";
        $params[':s2'] = "%{$search}%";
        $params[':s3'] = "%{$search}%";
        $params[':s4'] = "%{$search}%";
    }
    if ($cat > 0) {
        $sql .= " AND p.category_id = :cat";
        $params[':cat'] = $cat;
    }
    if (!empty($tech)) {
        $sql .= " AND p.technologies LIKE :tech";
        $params[':tech'] = "%{$tech}%";
    }

    switch ($sort) {
        case 'rank':
            $sql .= " ORDER BY (r.overall_rank IS NULL), r.overall_rank ASC, p.submitted_at DESC";
            break;
        case 'score':
            $sql .= " ORDER BY (e.total_score IS NULL), e.total_score DESC, p.submitted_at DESC";
            break;
        case 'title':
            $sql .= " ORDER BY p.title ASC";
            break;
        case 'latest':
        default:
            $sql .= " ORDER BY p.submitted_at DESC";
            break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
    foreach ($projects as &$project) {
        $project['demo_url'] = project_demo_url($project['demo_url'], $project['title']);
    }
    unset($project);
    json_response(['success' => true, 'projects' => $projects]);
}

// -------------------------------------------------------------
// POST: Submit Project, Edit, or Change Status
// -------------------------------------------------------------
if ($method === 'POST') {
    // A. Admin Change Status (Approve / Reject)
    if ($action === 'status') {
        require_admin_api($pdo);
        $input = get_json_input();
        $projId = (int)($input['project_id'] ?? $_POST['project_id'] ?? 0);
        $newStatus = trim($input['status'] ?? $_POST['status'] ?? '');
        $reason = trim($input['rejection_reason'] ?? $_POST['rejection_reason'] ?? null);

        if ($projId <= 0 || !in_array($newStatus, ['pending', 'approved', 'rejected'])) {
            json_response(['success' => false, 'message' => 'Invalid project or status.'], 400);
        }

        $up = $pdo->prepare("UPDATE projects SET status = :st, rejection_reason = :reason WHERE id = :id");
        $up->execute([
            ':st'     => $newStatus,
            ':reason' => ($newStatus === 'rejected') ? $reason : null,
            ':id'     => $projId
        ]);
        recalculate_rankings($pdo);

        json_response(['success' => true, 'message' => "Project status updated to {$newStatus}."]);
    }

    // B. Student Project Edit
    if ($action === 'edit') {
        $user = require_student_api($pdo);
        $projId = (int)($_GET['id'] ?? 0);

        $chk = $pdo->prepare("SELECT * FROM projects WHERE id = :id AND student_id = :sid");
        $chk->execute([':id' => $projId, ':sid' => $user['profile_id']]);
        $existing = $chk->fetch();

        if (!$existing) json_response(['success' => false, 'message' => 'Project not found.'], 404);
        if ($existing['status'] !== 'pending') json_response(['success' => false, 'message' => 'Reviewed projects cannot be edited.'], 400);

        $title      = trim($_POST['title'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $shortDesc  = trim($_POST['short_description'] ?? '');
        $problem    = trim($_POST['problem_statement'] ?? '');
        $objectives = trim($_POST['objectives'] ?? '');
        $features   = trim($_POST['features'] ?? '');
        $tech       = trim($_POST['technologies'] ?? '');
        $demoUrl    = trim($_POST['demo_url'] ?? '');
        $privateRepo= trim($_POST['private_repo'] ?? '');

        $thumb = $existing['thumbnail_image'];
        if (isset($_FILES['thumbnail_image']) && $_FILES['thumbnail_image']['error'] === UPLOAD_ERR_OK) {
            $u = handle_file_upload($_FILES['thumbnail_image'], __DIR__ . '/../uploads/project-images/', ['jpg', 'jpeg', 'png', 'webp', 'svg'], 5);
            if ($u['success']) $thumb = 'uploads/project-images/' . $u['filename'];
        }

        $doc = $existing['documentation_file'];
        if (isset($_FILES['documentation_file']) && $_FILES['documentation_file']['error'] === UPLOAD_ERR_OK) {
            $d = handle_file_upload($_FILES['documentation_file'], __DIR__ . '/../uploads/documents/', ['pdf', 'doc', 'docx'], 15);
            if ($d['success']) $doc = 'uploads/documents/' . $d['filename'];
        }

        $code = $existing['private_source_code_file'];
        if (isset($_FILES['private_source_code']) && $_FILES['private_source_code']['error'] === UPLOAD_ERR_OK) {
            $c = handle_file_upload($_FILES['private_source_code'], __DIR__ . '/../uploads/private-code/', ['zip', 'rar', 'tar', 'gz', '7z'], 50);
            if ($c['success']) $code = 'uploads/private-code/' . $c['filename'];
        }

        $up = $pdo->prepare("
            UPDATE projects SET 
                category_id = :cat, title = :title, short_description = :short,
                problem_statement = :prob, objectives = :obj, features = :feat,
                technologies = :tech, demo_url = :demo, thumbnail_image = :thumb,
                documentation_file = :doc, private_source_code_file = :code, private_source_repo = :repo
            WHERE id = :id AND student_id = :sid
        ");
        $up->execute([
            ':cat'   => $categoryId,
            ':title' => $title,
            ':short' => $shortDesc,
            ':prob'  => $problem,
            ':obj'   => $objectives,
            ':feat'  => $features,
            ':tech'  => $tech,
            ':demo'  => $demoUrl,
            ':thumb' => $thumb,
            ':doc'   => $doc,
            ':code'  => $code,
            ':repo'  => $privateRepo,
            ':id'    => $projId,
            ':sid'   => $user['profile_id']
        ]);

        json_response(['success' => true, 'message' => 'Project updated successfully.']);
    }

    // C. Student New Project Submission
    $user = require_student_api($pdo);

    $title      = trim($_POST['title'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $shortDesc  = trim($_POST['short_description'] ?? '');
    $problem    = trim($_POST['problem_statement'] ?? '');
    $objectives = trim($_POST['objectives'] ?? '');
    $features   = trim($_POST['features'] ?? '');
    $tech       = trim($_POST['technologies'] ?? '');
    $demoUrl    = trim($_POST['demo_url'] ?? '');
    $privateRepo= trim($_POST['private_repo'] ?? '');

    if (empty($title) || empty($categoryId) || empty($shortDesc) || empty($problem) || empty($objectives) || empty($features) || empty($tech)) {
        json_response(['success' => false, 'message' => 'Please fill in all required fields.'], 400);
    }

    $thumb = 'assets/images/project-default.svg';
    if (isset($_FILES['thumbnail_image']) && $_FILES['thumbnail_image']['error'] === UPLOAD_ERR_OK) {
        $u = handle_file_upload($_FILES['thumbnail_image'], __DIR__ . '/../uploads/project-images/', ['jpg', 'jpeg', 'png', 'webp', 'svg'], 5);
        if ($u['success']) $thumb = 'uploads/project-images/' . $u['filename'];
    }

    $doc = null;
    if (isset($_FILES['documentation_file']) && $_FILES['documentation_file']['error'] === UPLOAD_ERR_OK) {
        $d = handle_file_upload($_FILES['documentation_file'], __DIR__ . '/../uploads/documents/', ['pdf', 'doc', 'docx'], 15);
        if ($d['success']) $doc = 'uploads/documents/' . $d['filename'];
    }

    $code = null;
    if (isset($_FILES['private_source_code']) && $_FILES['private_source_code']['error'] === UPLOAD_ERR_OK) {
        $c = handle_file_upload($_FILES['private_source_code'], __DIR__ . '/../uploads/private-code/', ['zip', 'rar', 'tar', 'gz', '7z'], 50);
        if ($c['success']) $code = 'uploads/private-code/' . $c['filename'];
    }

    try {
        $pdo->beginTransaction();

        $slug = slugify($title) . '-' . rand(100, 999);
        $stmt = $pdo->prepare("
            INSERT INTO projects (
                student_id, category_id, title, slug, short_description, 
                problem_statement, objectives, features, technologies, 
                demo_url, thumbnail_image, documentation_file, 
                private_source_code_file, private_source_repo, status
            ) VALUES (
                :sid, :cid, :title, :slug, :short,
                :prob, :obj, :feat, :tech,
                :demo, :thumb, :doc, :code, :repo, 'pending'
            )
        ");
        $stmt->execute([
            ':sid'   => $user['profile_id'],
            ':cid'   => $categoryId,
            ':title' => $title,
            ':slug'  => $slug,
            ':short' => $shortDesc,
            ':prob'  => $problem,
            ':obj'   => $objectives,
            ':feat'  => $features,
            ':tech'  => $tech,
            ':demo'  => $demoUrl,
            ':thumb' => $thumb,
            ':doc'   => $doc,
            ':code'  => $code,
            ':repo'  => $privateRepo
        ]);
        $newId = $pdo->lastInsertId();

        // Team members
        $names = $_POST['member_names'] ?? [];
        $rolls = $_POST['member_rolls'] ?? [];
        $roles = $_POST['member_roles'] ?? [];
        if (is_array($names)) {
            $tmIns = $pdo->prepare("INSERT INTO project_team_members (project_id, member_name, roll_no, role) VALUES (:pid, :name, :roll, :role)");
            for ($i = 0; $i < count($names); $i++) {
                $n = trim($names[$i] ?? '');
                $r = trim($rolls[$i] ?? '');
                $ro = trim($roles[$i] ?? 'Member');
                if (!empty($n) && !empty($r)) {
                    $tmIns->execute([':pid' => $newId, ':name' => $n, ':roll' => $r, ':role' => $ro]);
                }
            }
        }

        $pdo->commit();
        json_response(['success' => true, 'message' => 'Project submitted successfully! It is now pending faculty review.', 'project_id' => $newId], 201);

    } catch (Exception $e) {
        $pdo->rollBack();
        json_response(['success' => false, 'message' => 'Submission failed: ' . $e->getMessage()], 500);
    }
}

// -------------------------------------------------------------
// DELETE: Remove Project
// -------------------------------------------------------------
if ($method === 'DELETE') {
    $projId = (int)($_GET['id'] ?? 0);
    if ($projId <= 0) json_response(['success' => false, 'message' => 'Invalid project ID.'], 400);

    if ($currentUser['role'] === 'admin') {
        $pdo->prepare("DELETE FROM projects WHERE id = :id")->execute([':id' => $projId]);
        recalculate_rankings($pdo);
        json_response(['success' => true, 'message' => 'Project deleted by admin.']);
    } elseif ($currentUser['role'] === 'student') {
        $chk = $pdo->prepare("SELECT status FROM projects WHERE id = :id AND student_id = :sid");
        $chk->execute([':id' => $projId, ':sid' => $currentUser['profile_id']]);
        $p = $chk->fetch();

        if ($p && $p['status'] === 'pending') {
            $pdo->prepare("DELETE FROM projects WHERE id = :id")->execute([':id' => $projId]);
            recalculate_rankings($pdo);
            json_response(['success' => true, 'message' => 'Pending project draft deleted.']);
        } else {
            json_response(['success' => false, 'message' => 'Evaluated or approved projects cannot be deleted.'], 400);
        }
    } else {
        json_response(['success' => false, 'message' => 'Unauthorized.'], 401);
    }
}
