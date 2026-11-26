<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';
require_once __DIR__ . '/env.php';

/**
 * Send an HTML email using SMTP settings from .env.
 */
function sendEmail(string $to, string $subject, string $body): bool
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
        $fromName = (string) (getenv('SMTP_FROM_NAME') ?: 'CHN Website');

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();

        return true;
    } catch (Exception $e) {
        chnLogError('Mailer error: ' . $e->getMessage());
        return false;
    }
}
