<?php
/**
 * ProjectSphere - Database Connection
 * Hosting database configuration
 */

$host     = 'sql207.infinityfree.com';
$dbname   = 'if0_43297350_projectsphere';
$username = 'if0_43297350';
$password = getenv('PROJECTSPHERE_DB_PASSWORD');
$charset  = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    if ($password === false) {
        throw new RuntimeException('Set the PROJECTSPHERE_DB_PASSWORD environment variable.');
    }

    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException | RuntimeException $e) {
    // Detect if this is an API request — return JSON if so
    $isApiContext = (strpos($_SERVER['SCRIPT_FILENAME'] ?? '', DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR) !== false)
                 || (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false);

    if ($isApiContext) {
        http_response_code(503);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed. Check the hosting database configuration.',
            'error'   => $e->getMessage()
        ]);
        exit;
    }

    die('<div style="font-family:sans-serif;padding:30px;max-width:600px;margin:50px auto;border:1px solid #f87171;background:#fef2f2;border-radius:12px;color:#991b1b;">
        <h2 style="margin-top:0;">Database Connection Error</h2>
        <p>Could not connect to MySQL database <strong>' . htmlspecialchars($dbname) . '</strong>.</p>
        <p style="font-size:14px;color:#4b5563;">Error details: ' . htmlspecialchars($e->getMessage()) . '</p>
        <hr style="border:none;border-top:1px solid #fecaca;margin:15px 0;">
        <p style="font-size:13px;margin-bottom:15px;"><strong>How to fix:</strong><br>
        Verify the hosting database host, name, and username, and set the
        <code>PROJECTSPHERE_DB_PASSWORD</code> environment variable in your hosting control panel.</p>
    </div>');
}
