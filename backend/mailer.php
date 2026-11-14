<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

/**
 * Lightweight helper to load .env variables into getenv()/$_ENV without third-party dependencies.
 */
function loadChnEnv()
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $candidates = [
        dirname(__DIR__) . '/config/.env',
        dirname(__DIR__) . '/.env',
    ];

    $envPath = null;
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $envPath = $candidate;
            break;
        }
    }

    if ($envPath === null) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function sendEmail($to, $subject, $body)
{
    loadChnEnv();

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = (string) (getenv('SMTP_HOST') ?: '');
        $mail->SMTPAuth = true;

        $mail->Username = (string) (getenv('SMTP_USERNAME') ?: '');
        $mail->Password = (string) (getenv('SMTP_PASSWORD') ?: '');

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) (getenv('SMTP_PORT') ?: 587);

        $mail->CharSet = 'UTF-8';

        $fromEmail = (string) (getenv('SMTP_FROM_EMAIL') ?: $mail->Username);
        $fromName  = (string) (getenv('SMTP_FROM_NAME') ?: 'CHN Website');

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();

        return true;

    } catch (Exception $e) {
        echo '<pre>';
        echo 'Error: ' . $e->getMessage() . "\n";
        echo 'SMTP Error: ' . $mail->ErrorInfo;
        echo '</pre>';

        return false;
    }
}
