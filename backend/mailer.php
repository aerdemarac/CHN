
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

function sendEmail($to, $subject, $body)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        $mail->Username = 'cyprushealthcarenetwork@gmail.com';
        $mail->Password = 'btuu khhs scnq hghj';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom('cyprushealthcarenetwork@gmail.com', 'CHN Website');
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
