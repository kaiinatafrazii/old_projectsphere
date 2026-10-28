<?php
/**
 * ProjectSphere - Direct Socket SMTP Client
 * Supports Gmail, Outlook, Yahoo, and Custom Mail Servers with TLS/STARTTLS
 */

function send_smtp_email(string $to, string $subject, string $body): array {
    $configFile = __DIR__ . '/smtp_config.php';
    if (!file_exists($configFile)) {
        return ['success' => false, 'error' => 'smtp_config.php not found'];
    }

    $config = require $configFile;
    $host     = $config['host'] ?? 'smtp.gmail.com';
    $port     = (int)($config['port'] ?? 587);
    $username = trim($config['username'] ?? '');
    $password = trim($config['password'] ?? '');
    $fromMail = trim($config['from_email'] ?? '') ?: ($username ?: 'no-reply@projectsphere.edu');
    $fromName = trim($config['from_name'] ?? '') ?: 'ProjectSphere Security';

    // If username and password are not configured, return false so caller can use fallback
    if (empty($username) || empty($password)) {
        return ['success' => false, 'error' => 'SMTP credentials empty'];
    }

    $timeout = 10;
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        return ['success' => false, 'error' => "Could not connect to {$host}:{$port} ($errstr)"];
    }

    stream_set_timeout($socket, $timeout);

    $getResponse = function() use ($socket) {
        $res = '';
        while ($line = fgets($socket, 512)) {
            $res .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $res;
    };

    $sendCommand = function(string $cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };

    $init = $getResponse();
    if (!str_starts_with($init, '220')) {
        fclose($socket);
        return ['success' => false, 'error' => 'Invalid greeting: ' . $init];
    }

    $sendCommand("EHLO localhost");
    $getResponse();

    if ($port == 587) {
        $sendCommand("STARTTLS");
        $tlsRes = $getResponse();
        if (!str_starts_with($tlsRes, '220')) {
            fclose($socket);
            return ['success' => false, 'error' => 'STARTTLS rejected: ' . $tlsRes];
        }

        $cryptoMethod = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        if (!@stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
            fclose($socket);
            return ['success' => false, 'error' => 'TLS handshake failed'];
        }

        $sendCommand("EHLO localhost");
        $getResponse();
    }

    $sendCommand("AUTH LOGIN");
    $authInit = $getResponse();
    if (!str_starts_with($authInit, '334')) {
        fclose($socket);
        return ['success' => false, 'error' => 'AUTH LOGIN failed: ' . $authInit];
    }

    $sendCommand(base64_encode($username));
    $userRes = $getResponse();
    if (!str_starts_with($userRes, '334')) {
        fclose($socket);
        return ['success' => false, 'error' => 'Username rejected: ' . $userRes];
    }

    // Google App Password: strip any spaces
    $cleanPass = str_replace(' ', '', $password);
    $sendCommand(base64_encode($cleanPass));
    $passRes = $getResponse();
    if (!str_starts_with($passRes, '235')) {
        fclose($socket);
        return ['success' => false, 'error' => 'Password rejected: ' . $passRes];
    }

    $sendCommand("MAIL FROM: <{$fromMail}>");
    $mailFromRes = $getResponse();
    if (!str_starts_with($mailFromRes, '250')) {
        fclose($socket);
        return ['success' => false, 'error' => 'MAIL FROM rejected: ' . $mailFromRes];
    }

    $sendCommand("RCPT TO: <{$to}>");
    $rcptRes = $getResponse();
    if (!str_starts_with($rcptRes, '250') && !str_starts_with($rcptRes, '251')) {
        fclose($socket);
        return ['success' => false, 'error' => 'RCPT TO rejected: ' . $rcptRes];
    }

    $sendCommand("DATA");
    $dataRes = $getResponse();
    if (!str_starts_with($dataRes, '354')) {
        fclose($socket);
        return ['success' => false, 'error' => 'DATA command rejected: ' . $dataRes];
    }

    $boundary = "----=_Part_" . bin2hex(random_bytes(8));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        "From: {$fromName} <{$fromMail}>",
        "To: <{$to}>",
        "Subject: {$encodedSubject}",
        "Date: " . date('r'),
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        "X-Mailer: ProjectSphere-SecureMailer"
    ];

    $htmlBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    $styledHtml = "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; padding: 30px; margin: 0;'>
        <div style='max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 32px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.06);'>
            <div style='text-align: center; margin-bottom: 24px;'>
                <h2 style='color: #4f46e5; margin: 0; font-size: 24px; font-weight: 800;'>ProjectSphere</h2>
                <span style='display: inline-block; background: #eef2ff; color: #4f46e5; padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: bold; text-transform: uppercase; margin-top: 8px;'>Security Verification</span>
            </div>
            <div style='font-size: 15px; color: #1e293b; line-height: 1.6;'>
                {$htmlBody}
            </div>
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 28px 0;'>
            <p style='font-size: 12px; color: #94a3b8; text-align: center; margin: 0;'>
                &copy; " . date('Y') . " ProjectSphere Academic Portal. All rights reserved.
            </p>
        </div>
    </body>
    </html>";

    $messageContent = implode("\r\n", $headers) . "\r\n\r\n"
                    . "--{$boundary}\r\n"
                    . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                    . $body . "\r\n\r\n"
                    . "--{$boundary}\r\n"
                    . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
                    . $styledHtml . "\r\n\r\n"
                    . "--{$boundary}--\r\n.";

    $sendCommand($messageContent);
    $sendRes = $getResponse();
    
    $sendCommand("QUIT");
    $getResponse();
    fclose($socket);

    if (str_starts_with($sendRes, '250')) {
        return ['success' => true];
    }

    return ['success' => false, 'error' => 'Delivery failed: ' . $sendRes];
}
