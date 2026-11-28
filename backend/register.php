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
$gender = chnInputString($input, 'gender');
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
    !in_array($gender, CHN_GENDERS, true) ||
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
            gender, birth_date, nationality, national_id, phone,
            verify, verify_token
        ) VALUES (
            :email, :password, :role, :name, :surname,
            :gender, :birth_date, :nationality, :national_id, :phone,
            0, :verify_token
        )'
    );

    $insertUserStmt->execute([
        ':email' => $email,
        ':password' => $passwordHash,
        ':role' => 'patient',
        ':name' => $firstName,
        ':surname' => $lastName,
        ':gender' => $gender,
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
$year = date('Y');
$mailBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Verify your CHN account</title>
</head>
<body style="margin:0; padding:0; background-color:#F4F7FA; color:#243447;">
    <div style="display:none; max-height:0; max-width:0; overflow:hidden; opacity:0; mso-hide:all;">
        Confirm your email address to finish setting up your CHN account.
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#F4F7FA" style="width:100%; background-color:#F4F7FA;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <!--[if mso]><table role="presentation" width="560" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#FFFFFF" style="width:100%; max-width:560px; background-color:#FFFFFF; border:1px solid #E2E8F0; border-top:4px solid #167D8D; border-radius:8px; box-shadow:0 4px 6px rgba(18,59,93,0.05); font-family:Inter,Arial,Helvetica,sans-serif;">
                    <tr>
                        <td style="padding:24px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td valign="middle" style="padding-right:16px; border-right:1px solid #E2E8F0;">
                                        <img src="cid:chn-logo" width="112" alt="CHN" style="display:block; width:112px; height:auto; border:0; color:#123B5D; font-size:20px; font-weight:600;">
                                    </td>
                                    <td valign="middle" style="padding-left:16px; color:#123B5D; font-size:14px; line-height:20px; font-weight:600;">Cyprus Health-Care Network</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;">
                                <tr>
                                    <td bgcolor="#123B5D" style="padding:24px; background-color:#123B5D; border-radius:8px; color:#FFFFFF;">
                                        <h1 style="margin:0; color:#FFFFFF; font-size:28px; line-height:36px; font-weight:600;">Verify your email address</h1>
                                        <p style="margin:8px 0 0; color:#FFFFFF; font-size:16px; line-height:24px;">You're one step away from activating your CHN account.</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;">
                            <p style="margin:0 0 8px; color:#123B5D; font-size:20px; line-height:28px; font-weight:600;">Hello {$safeName},</p>
                            <p style="margin:0 0 24px; font-size:16px; line-height:24px; color:#243447;">Thanks for registering. Confirm your email below to finish signing up and log in.</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="#167D8D" style="background-color:#167D8D; border-radius:8px; mso-padding-alt:16px 24px;">
                                        <a href="{$safeUrl}" style="display:inline-block; padding:16px 24px; mso-padding-alt:0; color:#FFFFFF; font-size:16px; line-height:20px; font-weight:600; text-decoration:none; border-radius:8px;">Verify my email</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#F4F7FA" style="width:100%; table-layout:fixed; border:1px solid #E2E8F0; background-color:#F4F7FA; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px;">
                                        <p style="margin:0 0 4px; font-size:14px; line-height:20px; font-weight:600; color:#123B5D;">Trouble with the button?</p>
                                        <p style="margin:0 0 8px; font-size:14px; line-height:20px; color:#64748B;">Copy and paste this link into your browser.</p>
                                        <a href="{$safeUrl}" style="font-size:14px; line-height:20px; color:#11626E; text-decoration:underline; overflow-wrap:anywhere; word-break:break-all;">{$safeUrl}</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:24px 0 0; font-size:14px; line-height:20px; color:#64748B;">If you didn't create a CHN account, you can ignore this email. Keep your verification link private.</p>
                        </td>
                    </tr>
                    <tr>
                        <td bgcolor="#F4F7FA" style="padding:16px 24px; background-color:#F4F7FA; border-top:1px solid #E2E8F0; border-radius:0 0 8px 8px;">
                            <p style="margin:0 0 4px; color:#123B5D; font-size:14px; line-height:20px; font-weight:600;">The CHN Team</p>
                            <p style="margin:0; color:#64748B; font-size:12px; line-height:16px;">&copy; {$year} Cyprus Health-Care Network</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]></td></tr></table><![endif]-->
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

$mailText = <<<TEXT
Hello {$firstName},

Thank you for registering with Cyprus Health-Care Network (CHN).
To complete your registration, verify your email address using the link below:
{$verifyUrl}

After verification, you can log in to your account.
If you did not create this account, you can safely ignore this email.
Do not share your verification link with anyone.

Best regards,
The CHN Team
TEXT;

sendEmail($email, 'Verify your CHN account', $mailBody, $mailText, true);

chnJsonSuccess([], 'Registration successful. Please check your email to verify your account.', 201);
