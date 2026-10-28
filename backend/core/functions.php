<?php
/**
 * ProjectSphere - Core Utility Functions
 */

/**
 * Return absolute or web-root relative URL for clean portable links
 */
function base_url(string $path = ''): string {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    
    // Check if hosted under /projectsphere subfolder (e.g. XAMPP htdocs/projectsphere)
    $pos = strpos($scriptName, '/projectsphere');
    if ($pos !== false) {
        $root = substr($scriptName, 0, $pos + strlen('/projectsphere'));
    } else {
        // Hosted on a virtual host or at the web root
        $root = '';
    }
    
    $cleanPath = ltrim($path, '/');
    if ($cleanPath === '') {
        return $root . '/frontend/';
    }

    // Auto-route paths for seamless frontend/backend two-folder architecture
    if (!str_starts_with($cleanPath, 'frontend/') && !str_starts_with($cleanPath, 'backend/')) {
        if (str_starts_with($cleanPath, 'assets/') || 
            str_starts_with($cleanPath, 'admin/') || 
            str_starts_with($cleanPath, 'student/') || 
            str_starts_with($cleanPath, 'project/') ||
            in_array($cleanPath, ['browse.php', 'login.php', 'register.php', 'logout.php', 'search.php', 'terms.php', 'privacy.php', 'project-details.php', '404.php', 'robots.php', 'sitemap.php'])) {
            $cleanPath = 'frontend/' . $cleanPath;
        } elseif (str_starts_with($cleanPath, 'uploads/') || 
                  str_starts_with($cleanPath, 'api/') || 
                  str_starts_with($cleanPath, 'core/') || 
                  str_starts_with($cleanPath, 'database/') || 
                  $cleanPath === 'install.php') {
            $cleanPath = 'backend/' . $cleanPath;
        }
    }
    
    return $root . '/' . $cleanPath;
}

/**
 * Sanitize string input
 */
function sanitize(?string $data): string {
    if ($data === null) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Replace the old shared sample video with a search for videos related to this project.
 */
function project_demo_url(?string $demoUrl, string $projectTitle): ?string {
    $demoUrl = trim($demoUrl ?? '');
    if ($demoUrl === '') {
        return null;
    }

    $parts = parse_url($demoUrl);
    $query = [];
    if (is_array($parts)) {
        parse_str($parts['query'] ?? '', $query);
    }

    if (($query['v'] ?? '') === 'dQw4w9WgXcQ') {
        return 'https://www.youtube.com/results?search_query=' . rawurlencode($projectTitle . ' project demo');
    }

    return $demoUrl;
}

function project_demo_link_label(?string $demoUrl, string $defaultLabel = 'Watch Demo Video / Live URL'): string {
    $parts = parse_url($demoUrl ?? '');

    if (
        is_array($parts)
        && in_array($parts['host'] ?? '', ['youtube.com', 'www.youtube.com'], true)
        && ($parts['path'] ?? '') === '/results'
    ) {
        return 'Find Related Videos on YouTube';
    }

    return $defaultLabel;
}

function send_project_email(string $recipient, string $subject, string $message): bool {
    $sender = getenv('PROJECTSPHERE_MAIL_FROM') ?: (ini_get('sendmail_from') ?: 'no-reply@projectsphere.edu');
    
    // Always log outgoing emails to backend/uploads/mail_log.txt for audit & local inspection
    $logDir = __DIR__ . '/../../backend/uploads';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/mail_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "==================== {$timestamp} ====================\n"
              . "TO: {$recipient}\n"
              . "FROM: {$sender}\n"
              . "SUBJECT: {$subject}\n\n"
              . "{$message}\n\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);

    // 1. First attempt: Direct TLS/SSL SMTP (Gmail, Outlook, etc.)
    $smtpFile = __DIR__ . '/smtp.php';
    if (file_exists($smtpFile)) {
        require_once $smtpFile;
        $smtpRes = send_smtp_email($recipient, $subject, $message);
        if (!empty($smtpRes['success'])) {
            return true;
        }
    }

    // 2. Second attempt: PHP mail() function
    $mailSent = false;
    if (function_exists('mail')) {
        @ini_set('sendmail_from', $sender);
        $headers = [
            'From: ProjectSphere <' . $sender . '>',
            'Reply-To: ' . $sender,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion()
        ];
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $mailSent = @mail($recipient, $encodedSubject, $message, implode("\r\n", $headers));
    }

    // 3. Fallback: On localhost, local logging counts as accepted
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $httpHost   = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal    = in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost', ''])
               || str_contains($httpHost, 'localhost')
               || str_contains($httpHost, '127.0.0.1');

    if (!$mailSent && $isLocal) {
        return true;
    }

    return $mailSent;
}

/**
 * Generate URL slug from title
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'project-' . time() : $text;
}

/**
 * Calculate and sync project rankings across the platform
 * Called whenever an evaluation is added, modified, or project status changes
 */
function recalculate_rankings(PDO $pdo): void {
    // 1. Fetch all approved projects that have an evaluation
    $sql = "SELECT p.id AS project_id, p.category_id, e.total_score, e.evaluated_at
            FROM projects p
            INNER JOIN evaluations e ON p.id = e.project_id
            WHERE p.status = 'approved' AND e.is_published = 1
            ORDER BY e.total_score DESC, e.evaluated_at ASC, p.id ASC";
    
    $stmt = $pdo->query($sql);
    $projects = $stmt->fetchAll();

    if (empty($projects)) {
        // Clear rankings if none
        $pdo->exec("DELETE FROM rankings");
        return;
    }

    // 2. Compute Overall Rank (Standard Competition Ranking with tie handling)
    $overallRanks = [];
    $currentRank = 0;
    $position = 0;
    $prevScore = null;

    foreach ($projects as $proj) {
        $position++;
        if ($prevScore === null || $proj['total_score'] != $prevScore) {
            $currentRank = $position;
            $prevScore = $proj['total_score'];
        }
        $overallRanks[$proj['project_id']] = $currentRank;
    }

    // 3. Compute Category Ranks per Category
    $categoryGroups = [];
    foreach ($projects as $proj) {
        $categoryGroups[$proj['category_id']][] = $proj;
    }

    $categoryRanks = [];
    foreach ($categoryGroups as $catId => $catProjects) {
        $cRank = 0;
        $cPos = 0;
        $cPrevScore = null;
        foreach ($catProjects as $proj) {
            $cPos++;
            if ($cPrevScore === null || $proj['total_score'] != $cPrevScore) {
                $cRank = $cPos;
                $cPrevScore = $proj['total_score'];
            }
            $categoryRanks[$proj['project_id']] = $cRank;
        }
    }

    // 4. Update or Insert into rankings table
    $upsertStmt = $pdo->prepare("
        INSERT INTO rankings (project_id, category_id, total_score, overall_rank, category_rank)
        VALUES (:project_id, :category_id, :total_score, :overall_rank, :category_rank)
        ON DUPLICATE KEY UPDATE
            category_id = VALUES(category_id),
            total_score = VALUES(total_score),
            overall_rank = VALUES(overall_rank),
            category_rank = VALUES(category_rank),
            updated_at = CURRENT_TIMESTAMP
    ");

    $validProjectIds = [];
    foreach ($projects as $proj) {
        $pid = $proj['project_id'];
        $validProjectIds[] = $pid;
        $upsertStmt->execute([
            ':project_id'    => $pid,
            ':category_id'   => $proj['category_id'],
            ':total_score'   => $proj['total_score'],
            ':overall_rank'  => $overallRanks[$pid],
            ':category_rank' => $categoryRanks[$pid]
        ]);
    }

    // Clean up any rankings for projects that are no longer approved or evaluated
    if (!empty($validProjectIds)) {
        $inClause = implode(',', array_map('intval', $validProjectIds));
        $pdo->exec("DELETE FROM rankings WHERE project_id NOT IN ($inClause)");
    }
}

/**
 * Handle secure file uploads with strict MIME inspection and automated image compression
 */
function handle_file_upload(
    array $file, 
    string $targetDir, 
    array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 
    int $maxSizeMb = 10
): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'error' => 'No file was uploaded.', 'filename' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . $file['error'], 'filename' => null];
    }

    if ($file['size'] > ($maxSizeMb * 1024 * 1024)) {
        return ['success' => false, 'error' => "File size exceeds limit of {$maxSizeMb}MB.", 'filename' => null];
    }

    // 1. Strict extension validation
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'inc', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'shtml', 'cgi', 'pl', 'py'];
    if (in_array($ext, $dangerousExtensions, true)) {
        return ['success' => false, 'error' => 'Executable and script file uploads are strictly forbidden for security.', 'filename' => null];
    }

    if (!in_array($ext, $allowedExtensions, true)) {
        return ['success' => false, 'error' => "Invalid file format (.{$ext}). Allowed: " . implode(', ', $allowedExtensions), 'filename' => null];
    }

    // 2. MIME type verification via finfo magic bytes
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
            'svg'  => ['image/svg+xml', 'text/plain', 'text/xml'],
            'pdf'  => ['application/pdf'],
            'doc'  => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
            'rar'  => ['application/x-rar-compressed', 'application/octet-stream'],
            '7z'   => ['application/x-7z-compressed', 'application/octet-stream']
        ];

        if (isset($allowedMimes[$ext]) && !in_array($mime, $allowedMimes[$ext], true)) {
            return ['success' => false, 'error' => "Security check failed: File content does not match extension .{$ext} (detected MIME: {$mime}).", 'filename' => null];
        }
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $uniqueName = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = rtrim($targetDir, '/') . '/' . $uniqueName;

    // 3. Automated Image Compression & Metadata Stripping (for raster images)
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && extension_loaded('gd')) {
        $img = null;
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $img = @imagecreatefromjpeg($file['tmp_name']);
        } elseif ($ext === 'png') {
            $img = @imagecreatefrompng($file['tmp_name']);
        } elseif ($ext === 'webp') {
            $img = @imagecreatefromwebp($file['tmp_name']);
        }

        if ($img !== false && $img !== null) {
            // Compress and save
            $saved = false;
            if ($ext === 'jpg' || $ext === 'jpeg') {
                $saved = imagejpeg($img, $targetPath, 85);
            } elseif ($ext === 'png') {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                $saved = imagepng($img, $targetPath, 8);
            } elseif ($ext === 'webp') {
                $saved = imagewebp($img, $targetPath, 85);
            }
            imagedestroy($img);

            if ($saved) {
                return ['success' => true, 'filename' => $uniqueName, 'filepath' => $targetPath];
            }
        }
    }

    // Default save for documents or if GD not applicable
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file to disk.', 'filename' => null];
    }

    return ['success' => true, 'filename' => $uniqueName, 'filepath' => $targetPath];
}

/**
 * Returns formatted HTML badge for project status
 */
function get_status_badge(string $status): string {
    switch (strtolower($status)) {
        case 'approved':
            return '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>Approved</span>';
        case 'rejected':
            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 rounded-pill"><i class="bi bi-x-circle-fill me-1"></i>Rejected</span>';
        case 'pending':
        default:
            return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2.5 py-1.5 rounded-pill"><i class="bi bi-hourglass-split me-1"></i>Pending Review</span>';
    }
}

/**
 * Returns formatted badge/medal for rank
 */
function get_rank_badge(int $rank): string {
    if ($rank === 1) {
        return '<span class="badge-rank rank-1"><i class="bi bi-trophy-fill me-1"></i>Rank #1</span>';
    } elseif ($rank === 2) {
        return '<span class="badge-rank rank-2"><i class="bi bi-award-fill me-1"></i>Rank #2</span>';
    } elseif ($rank === 3) {
        return '<span class="badge-rank rank-3"><i class="bi bi-award me-1"></i>Rank #3</span>';
    } else {
        return '<span class="badge-rank rank-other">Rank #' . $rank . '</span>';
    }
}

/**
 * Format score cleanly (e.g. 94 or 94.5)
 */
function format_score($score): string {
    $num = (float)$score;
    return (floor($num) == $num) ? number_format($num, 0) : number_format($num, 1);
}
