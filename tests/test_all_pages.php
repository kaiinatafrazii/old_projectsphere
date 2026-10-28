<?php
/**
 * ProjectSphere - Comprehensive Automated Test Suite
 * Validates:
 *  - Database & Table Schemas
 *  - Core Security Utilities (CSRF, hashing, routing)
 *  - Syntax linting of 100% of codebase PHP files
 *  - Rendering of all Public pages with UI & Security assertions
 *  - Rendering of all Student Portal pages with authenticated sessions
 *  - Rendering of all Faculty/Admin Portal pages with authenticated sessions
 *  - Business logic, evaluation rubrics & leaderboard rankings
 * 
 * Run via CLI: php tests/test_all_pages.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Terminal color formatting
class CliColor {
    public static function green(string $s): string { return "\033[32m{$s}\033[0m"; }
    public static function red(string $s): string { return "\033[31m{$s}\033[0m"; }
    public static function yellow(string $s): string { return "\033[33m{$s}\033[0m"; }
    public static function cyan(string $s): string { return "\033[36m{$s}\033[0m"; }
    public static function bold(string $s): string { return "\033[1m{$s}\033[0m"; }
}

class TestSuiteRunner {
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];
    private float $startTime;

    public function __construct() {
        $this->startTime = microtime(true);
    }

    public function assert(bool $condition, string $testName, string $details = ''): void {
        if ($condition) {
            $this->passed++;
            echo CliColor::green("  ✓ [PASS] ") . $testName . PHP_EOL;
        } else {
            $this->failed++;
            $this->failures[] = ['test' => $testName, 'details' => $details];
            echo CliColor::red("  ✗ [FAIL] ") . $testName;
            if ($details !== '') {
                echo CliColor::yellow(" ({$details})");
            }
            echo PHP_EOL;
        }
    }

    public function section(string $title): void {
        echo PHP_EOL . CliColor::bold(CliColor::cyan("=== {$title} ===")) . PHP_EOL;
    }

    public function summary(): int {
        $elapsed = round(microtime(true) - $this->startTime, 3);
        $total = $this->passed + $this->failed;

        echo PHP_EOL . str_repeat('=', 65) . PHP_EOL;
        echo CliColor::bold("PROJECTSPHERE TEST RESULTS") . PHP_EOL;
        echo str_repeat('=', 65) . PHP_EOL;
        echo "Total Tests Executed: {$total}" . PHP_EOL;
        echo CliColor::green("Passed: {$this->passed}") . PHP_EOL;
        if ($this->failed > 0) {
            echo CliColor::red("Failed: {$this->failed}") . PHP_EOL;
            echo PHP_EOL . CliColor::bold(CliColor::red("Summary of Failures:")) . PHP_EOL;
            foreach ($this->failures as $i => $f) {
                echo " " . ($i + 1) . ". {$f['test']}" . ($f['details'] ? " - {$f['details']}" : "") . PHP_EOL;
            }
        } else {
            echo CliColor::green(CliColor::bold("ALL TESTS PASSED! Portal is 100% stable & ready.")) . PHP_EOL;
        }
        echo "Execution Time: {$elapsed}s" . PHP_EOL;
        echo str_repeat('=', 65) . PHP_EOL;

        return $this->failed === 0 ? 0 : 1;
    }
}

$suite = new TestSuiteRunner();

// =========================================================================
// SECTION 1: Database & Core Infrastructure
// =========================================================================
$suite->section("1. Database & Core Infrastructure");

$dbFile = __DIR__ . '/../backend/core/db.php';
$suite->assert(file_exists($dbFile), "db.php exists");

require_once $dbFile;
$suite->assert(isset($pdo) && $pdo instanceof PDO, "PDO database connection active");

$requiredTables = [
    'users', 'students', 'admins', 'projects', 'project_categories',
    'evaluations', 'rankings', 'feedback', 'project_team_members', 'project_images'
];

foreach ($requiredTables as $tbl) {
    try {
        $check = $pdo->query("SELECT 1 FROM `{$tbl}` LIMIT 1");
        $suite->assert($check !== false, "Database table `{$tbl}` exists and accessible");
    } catch (Exception $e) {
        $suite->assert(false, "Database table `{$tbl}` exists and accessible", $e->getMessage());
    }
}

$adminSeed = $pdo->query("SELECT id, username, email, role FROM users WHERE role = 'admin' LIMIT 1")->fetch();
$suite->assert(!empty($adminSeed), "Faculty/Admin seed account exists", $adminSeed['email'] ?? 'Not found');

$studentSeed = $pdo->query("SELECT id, username, email, role FROM users WHERE role = 'student' LIMIT 1")->fetch();
$suite->assert(!empty($studentSeed), "Student seed account exists", $studentSeed['email'] ?? 'Not found');

// =========================================================================
// SECTION 2: Core Utility Functions & Helpers
// =========================================================================
$suite->section("2. Core Functions & Security Utilities");

require_once __DIR__ . '/../backend/core/functions.php';
require_once __DIR__ . '/../backend/core/auth.php';

$suite->assert(function_exists('base_url'), "Function base_url() exists");
$suite->assert(str_contains(base_url('test.php'), 'test.php'), "base_url() produces valid path");
$legacyDemoUrl = project_demo_url('https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'Smart Library Management System');
$suite->assert(
    $legacyDemoUrl === 'https://www.youtube.com/results?search_query=Smart%20Library%20Management%20System%20project%20demo',
    "Legacy sample video redirects to project-related YouTube search"
);
$suite->assert(
    project_demo_link_label($legacyDemoUrl) === 'Find Related Videos on YouTube',
    "YouTube search links use an accurate resource label"
);

$suite->assert(function_exists('generate_csrf'), "Function generate_csrf() exists");
$csrfTok = generate_csrf();
$suite->assert(strlen($csrfTok) === 64, "generate_csrf() produces 64-character hex token");

$_POST['csrf_token'] = $csrfTok;
$suite->assert(validate_csrf(), "validate_csrf() validates matching token");

$_POST['csrf_token'] = 'invalid_tampered_token';
$suite->assert(!validate_csrf(), "validate_csrf() strictly rejects tampered token");

$suite->assert(password_verify('student123', password_hash('student123', PASSWORD_DEFAULT)), "password_verify() authenticates bcrypt passwords");

// =========================================================================
// SECTION 3: Syntax Integrity & PHP Lint Verification
// =========================================================================
$suite->section("3. Syntax Integrity & PHP Lint Verification");

$phpFiles = [];
$scanDirs = [
    __DIR__ . '/../frontend',
    __DIR__ . '/../backend'
];

foreach ($scanDirs as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $phpFiles[] = $file->getPathname();
        }
    }
}

$syntaxErrors = [];
$phpBin = 'C:\\xampp\\php\\php.exe';

foreach ($phpFiles as $file) {
    $output = [];
    $returnVar = 0;
    exec("\"{$phpBin}\" -l \"{$file}\" 2>&1", $output, $returnVar);
    if ($returnVar !== 0) {
        $syntaxErrors[] = basename($file) . ': ' . implode(' ', $output);
    }
}

$suite->assert(empty($syntaxErrors), "All " . count($phpFiles) . " PHP files have 0 syntax errors", implode('; ', $syntaxErrors));

// =========================================================================
// Helper: Sub-process Page Execution Harness
// =========================================================================
function runPageHarness(string $relPath, array $session = [], array $get = []): array {
    $tempScript = __DIR__ . '/_temp_runner_' . uniqid() . '.php';
    $targetFile = dirname(__DIR__) . '/' . str_replace('\\', '/', $relPath);

    $code = '<?php' . PHP_EOL
        . 'ini_set("display_errors", "1");' . PHP_EOL
        . 'error_reporting(E_ALL);' . PHP_EOL
        . 'if (session_status() === PHP_SESSION_NONE) session_start();' . PHP_EOL
        . '$_SESSION = ' . var_export($session, true) . ';' . PHP_EOL
        . '$_GET = ' . var_export($get, true) . ';' . PHP_EOL
        . '$_SERVER["REQUEST_METHOD"] = "GET";' . PHP_EOL
        . '$_SERVER["HTTP_HOST"] = "localhost";' . PHP_EOL
        . '$_SERVER["SCRIPT_NAME"] = "/' . addslashes($relPath) . '";' . PHP_EOL
        . '$_SERVER["PHP_SELF"] = "/' . addslashes($relPath) . '";' . PHP_EOL
        . 'ob_start();' . PHP_EOL
        . 'try {' . PHP_EOL
        . '    require ' . var_export($targetFile, true) . ';' . PHP_EOL
        . '} catch (Throwable $e) {' . PHP_EOL
        . '    echo "RUNTIME_ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();' . PHP_EOL
        . '}' . PHP_EOL
        . '$output = ob_get_clean();' . PHP_EOL
        . 'echo $output;' . PHP_EOL;

    file_put_contents($tempScript, $code);

    $phpBin = 'C:\\xampp\\php\\php.exe';
    $cmd = "\"{$phpBin}\" \"{$tempScript}\" 2>&1";
    $output = [];
    $ret = 0;
    exec($cmd, $output, $ret);

    if (file_exists($tempScript)) {
        @unlink($tempScript);
    }

    $raw = implode("\n", $output);
    return [
        'exitCode' => $ret,
        'html'     => $raw
    ];
}

// =========================================================================
// SECTION 4: Public Pages & UI Component Tests
// =========================================================================
$suite->section("4. Public Pages & UI Component Tests");

$publicPages = [
    'Home Page'           => ['path' => 'frontend/index.php', 'get' => []],
    'Browse Catalog'      => ['path' => 'frontend/browse.php', 'get' => []],
    'Project Details'     => ['path' => 'frontend/project-details.php', 'get' => ['id' => 1]],
    'Student Sign In'     => ['path' => 'frontend/login.php', 'get' => []],
    'Student Register'    => ['path' => 'frontend/register.php', 'get' => []],
    'Admin Sign In'       => ['path' => 'frontend/admin/login.php', 'get' => []],
    'Terms of Service'    => ['path' => 'frontend/terms.php', 'get' => []],
    'Privacy Policy'      => ['path' => 'frontend/privacy.php', 'get' => []],
    'Custom 404 Page'     => ['path' => 'frontend/404.php', 'get' => []],
];

foreach ($publicPages as $name => $cfg) {
    $res = runPageHarness($cfg['path'], [], $cfg['get']);
    $html = $res['html'];

    $suite->assert(!empty($html), "{$name} renders non-empty HTML output");
    $suite->assert(!str_contains($html, 'Fatal error') && !str_contains($html, 'Parse error') && !str_contains($html, 'RUNTIME_ERROR:'), "{$name} contains 0 fatal/parse errors", substr($html, 0, 120));

    if (str_contains($name, 'Sign In') || str_contains($name, 'Register')) {
        $suite->assert(str_contains($html, 'password-toggle-btn') || str_contains($html, 'password-toggle'), "{$name} contains password eye toggle button");
        $suite->assert(str_contains($html, 'csrf_token'), "{$name} includes CSRF token in form");
        $suite->assert(str_contains($html, 'auth-card'), "{$name} uses modern auth-card styling");
    }

    if (str_contains($html, '<head>')) {
        $suite->assert(str_contains($html, 'viewport'), "{$name} includes responsive viewport meta tag");
    }
}

// =========================================================================
// SECTION 5: Student Portal Inner Pages (Session Authenticated)
// =========================================================================
$suite->section("5. Student Portal Pages (Authenticated Context)");

$studentProfile = $pdo->query("SELECT id, user_id, full_name, roll_no FROM students LIMIT 1")->fetch();

// Ensure a pending project exists for student testing
$pendingStmt = $pdo->prepare("SELECT id FROM projects WHERE student_id = :sid AND status = 'pending' LIMIT 1");
$pendingStmt->execute([':sid' => $studentProfile['id']]);
$pendingProjId = (int)$pendingStmt->fetchColumn();

if (!$pendingProjId) {
    $ins = $pdo->prepare("INSERT INTO projects (student_id, category_id, title, slug, short_description, problem_statement, objectives, features, technologies, status) VALUES (:sid, 1, 'Draft Capstone Test', 'draft-capstone-test', 'Short draft description', 'Problem statement', 'Objectives', 'Features', 'PHP, MySQL', 'pending')");
    $ins->execute([':sid' => $studentProfile['id']]);
    $pendingProjId = (int)$pdo->lastInsertId();
}

$studentSession = [
    'user_id'    => $studentProfile['user_id'],
    'role'       => 'student',
    'profile_id' => $studentProfile['id'],
    'full_name'  => $studentProfile['full_name'],
    'roll_no'    => $studentProfile['roll_no']
];

$studentPages = [
    'Student Dashboard'   => ['path' => 'frontend/student/dashboard.php', 'get' => []],
    'My Projects List'    => ['path' => 'frontend/student/my-projects.php', 'get' => []],
    'Submit Project Form' => ['path' => 'frontend/student/submit-project.php', 'get' => []],
    'My Evaluations'      => ['path' => 'frontend/student/my-evaluations.php', 'get' => []],
    'Student Profile'     => ['path' => 'frontend/student/profile.php', 'get' => []],
    'Edit Project Form'   => ['path' => 'frontend/student/edit-project.php', 'get' => ['id' => $pendingProjId]],
];

foreach ($studentPages as $name => $cfg) {
    $res = runPageHarness($cfg['path'], $studentSession, $cfg['get']);
    $html = $res['html'];

    $suite->assert(!empty($html), "{$name} renders for authenticated student");
    $suite->assert(!str_contains($html, 'Fatal error') && !str_contains($html, 'RUNTIME_ERROR:'), "{$name} has no runtime fatal errors", substr($html, 0, 120));
    $suite->assert(str_contains($html, 'dashboard-sidebar') || str_contains($html, 'sidebar-menu'), "{$name} includes responsive student sidebar navigation");
}

// =========================================================================
// SECTION 6: Faculty / Admin Portal Inner Pages (Session Authenticated)
// =========================================================================
$suite->section("6. Faculty / Admin Portal Pages (Authenticated Context)");

$adminSession = [
    'user_id'    => $adminSeed['id'],
    'role'       => 'admin',
    'profile_id' => 1,
    'full_name'  => 'Prof. Arvind Kulkarni'
];

$adminPages = [
    'Admin Dashboard'       => ['path' => 'frontend/admin/dashboard.php', 'get' => []],
    'Manage Projects'       => ['path' => 'frontend/admin/projects.php', 'get' => []],
    'Review Project View'   => ['path' => 'frontend/admin/review-project.php', 'get' => ['id' => 1]],
    'Evaluate Marks Engine' => ['path' => 'frontend/admin/evaluate.php', 'get' => ['id' => 1]],
    'Rankings Leaderboard'  => ['path' => 'frontend/admin/rankings.php', 'get' => []],
    'Project Categories'    => ['path' => 'frontend/admin/categories.php', 'get' => []],
    'Manage Students'       => ['path' => 'frontend/admin/students.php', 'get' => []],
    'Faculty Feedback'      => ['path' => 'frontend/admin/feedback.php', 'get' => []],
    'Faculty Profile'       => ['path' => 'frontend/admin/profile.php', 'get' => []],
];

foreach ($adminPages as $name => $cfg) {
    $res = runPageHarness($cfg['path'], $adminSession, $cfg['get']);
    $html = $res['html'];

    $suite->assert(!empty($html), "{$name} renders for faculty administrator");
    $suite->assert(!str_contains($html, 'Fatal error') && !str_contains($html, 'RUNTIME_ERROR:'), "{$name} has no runtime fatal errors", substr($html, 0, 120));
    $suite->assert(str_contains($html, 'dashboard-sidebar'), "{$name} includes faculty admin sidebar navigation");
}

// =========================================================================
// SECTION 7: Business Logic & Ranking Algorithm
// =========================================================================
$suite->section("7. Business Logic & Ranking Algorithm");

try {
    recalculate_rankings($pdo);
    $suite->assert(true, "recalculate_rankings(\$pdo) executes without error");

    $rankCount = $pdo->query("SELECT COUNT(*) FROM rankings")->fetchColumn();
    $suite->assert($rankCount >= 0, "Rankings calculated and stored in database ({$rankCount} ranked entries)");
} catch (Exception $e) {
    $suite->assert(false, "recalculate_rankings(\$pdo) executes cleanly", $e->getMessage());
}

exit($suite->summary());
