<?php

require_once __DIR__ . '/../backend/mailer.php';

$result = sendEmail(
    'izgihanizgi@hotmail.com',
    'Welcome!',
    '<h1>Welcome to CHN!</h1>'
);

if ($result) {
    echo 'Email sent successfully!';
} else {
    echo 'Email sending failed!';
}
