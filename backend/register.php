<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/mailer.php';

$input = chnRequireJsonPost();

$firstName = chnInputString($input, 'first_name');
$lastName = chnInputString($input, 'last_name');
$birthDate = chnInputString($input, 'birth_date');
$nationality = strtoupper(chnInputString($input, 'nationality'));
$nationalId = chnInputString($input, 'national_id');
$email = strtolower(chnInputString($input, 'email'));
$phone = chnInputString($input, 'phone');
$password = is_string($input['password'] ?? null) ? $input['password'] : '';
$passwordConfirm = is_string($input['password_confirm'] ?? null) ? $input['password_confirm'] : '';

$countries = require dirname(__DIR__) . '/lib/countries.php';

if (
    !chnIsValidName($firstName) ||
    !chnIsValidName($lastName) ||
    !chnIsValidBirthDate($birthDate) ||
    !isset($countries[$nationality]) ||
    !chnIsValidNationalId($nationalId, $nationality) ||
    !chnIsValidEmail($email) ||
    !chnIsValidPhone($phone) ||
    !chnIsValidPassword($password) ||
    $password !== $passwordConfirm
) {
    chnJsonError('INVALID_INPUT', 'Please check your registration details and try again.', 400);
}

$normalizedPhone = chnNormalizePhone($phone);
$normalizedNationalId = $nationality === 'TR'
    ? (preg_replace('/\s+/', '', $nationalId) ?? $nationalId)
    : $nationalId;

try {
    $pdo = chnGetPdo();

    $checkStmt = $pdo->prepare('SELECT user_id FROM `user` WHERE email = :email LIMIT 1');
    $checkStmt->execute([':email' => $email]);
    if ($checkStmt->fetch() !== false) {
        chnJsonError('EMAIL_EXISTS', 'An account with this email already exists.', 409);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $verifyToken = bin2hex(random_bytes(32));

    $pdo->beginTransaction();

    $insertUserStmt = $pdo->prepare(
        'INSERT INTO `user` (
            email, password, role, name, surname,
            birth_date, nationality, national_id, phone,
            verify, verify_token
        ) VALUES (
            :email, :password, :role, :name, :surname,
            :birth_date, :nationality, :national_id, :phone,
            0, :verify_token
        )'
    );

    $insertUserStmt->execute([
        ':email' => $email,
        ':password' => $passwordHash,
        ':role' => 'patient',
        ':name' => $firstName,
        ':surname' => $lastName,
        ':birth_date' => $birthDate,
        ':nationality' => $nationality,
        ':national_id' => $normalizedNationalId,
        ':phone' => $normalizedPhone,
        ':verify_token' => $verifyToken,
    ]);

    $userId = (int) $pdo->lastInsertId();

    $insertPatientStmt = $pdo->prepare('INSERT INTO `patient` (user_id) VALUES (:user_id)');
    $insertPatientStmt->execute([':user_id' => $userId]);

    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    chnLogError('Register error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to complete registration.', 500);
}

loadChnEnv();
$appUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost/CHN'), '/');
$verifyUrl = $appUrl . '/verify-acc.php?' . http_build_query(['token' => $verifyToken]);

$safeName = e($firstName);
$safeUrl = e($verifyUrl);
$mailBody = <<<HTML
<p>Hello {$safeName},</p>
<p>Thank you for registering with Cyprus Health-Care Network (CHN).</p>
<p>Please verify your account by clicking the link below:</p>
<p><a href="{$safeUrl}">Verify My Account</a></p>
<p>If you did not create this account, you can safely ignore this email.</p>
HTML;

sendEmail($email, 'Verify your CHN account', $mailBody);

chnJsonSuccess([], 'Registration successful. Please check your email to verify your account.', 201);
