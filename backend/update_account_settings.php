<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

$sessionUser = requireRole(['patient', 'doctor', 'healthcare_staff', 'admin'], true);
$input = chnRequireJsonPost();

$userId = $sessionUser['user_id'];
$role = $sessionUser['role'];
$isAdmin = $role === 'admin';
$isPatient = $role === 'patient';

$phoneRaw = chnInputString($input, 'phone');
$phone = null;
if ($phoneRaw !== '') {
    if (!chnIsValidPhone($phoneRaw)) {
        chnJsonError('INVALID_INPUT', 'Enter a valid international phone number.', 422);
    }
    $phone = chnNormalizePhone($phoneRaw);
}

$preferredLang = chnInputString($input, 'preferred_language');
if ($preferredLang !== '' && !in_array($preferredLang, ['en', 'tr', 'ru'], true)) {
    chnJsonError('INVALID_INPUT', 'Unsupported language selection.', 422);
}

$fullAddress = null;
$hasAddress = false;
if ($isPatient && array_key_exists('full_address', $input)) {
    $hasAddress = true;
    $addressRaw = chnInputString($input, 'full_address');
    if ($addressRaw !== '') {
        if (mb_strlen($addressRaw, 'UTF-8') > 255) {
            chnJsonError('INVALID_INPUT', 'Address must be at most 255 characters.', 422);
        }
        $addressPattern = '/^[' . CHN_ALLOWED_LETTERS . '0-9\s,.\-\/:#\'()]+$/u';
        if (preg_match($addressPattern, $addressRaw) !== 1) {
            chnJsonError('INVALID_INPUT', 'Address contains unsupported characters.', 400);
        }
        $fullAddress = $addressRaw;
    }
}

$adminFields = [];
if ($isAdmin) {
    $name = chnInputString($input, 'name');
    $surname = chnInputString($input, 'surname');
    $email = strtolower(chnInputString($input, 'email'));
    $birthDateRaw = chnInputString($input, 'birth_date');
    $nationalityRaw = strtoupper(chnInputString($input, 'nationality'));
    $genderRaw = chnInputString($input, 'gender');

    if (!chnIsValidName($name) || !chnIsValidName($surname)) {
        chnJsonError('INVALID_INPUT', 'First and last name must use valid Latin characters.', 400);
    }
    if (!chnIsValidEmail($email)) {
        chnJsonError('INVALID_INPUT', 'Enter a valid email address.', 422);
    }

    $birthDate = null;
    if ($birthDateRaw !== '') {
        if (!chnIsValidBirthDate($birthDateRaw)) {
            chnJsonError('INVALID_INPUT', 'Enter a valid birth date not in the future.', 422);
        }
        $birthDate = $birthDateRaw;
    }

    $nationality = null;
    if ($nationalityRaw !== '') {
        if (preg_match('/^[A-Z]{2}$/', $nationalityRaw) !== 1) {
            chnJsonError('INVALID_INPUT', 'Nationality must be a 2-letter country code.', 422);
        }
        $nationality = $nationalityRaw;
    }

    $gender = null;
    if ($genderRaw !== '') {
        if (!in_array($genderRaw, CHN_GENDERS, true)) {
            chnJsonError('INVALID_INPUT', 'Invalid gender selection.', 422);
        }
        $gender = $genderRaw;
    }

    $adminFields = [
        'name' => $name,
        'surname' => $surname,
        'email' => $email,
        'birth_date' => $birthDate,
        'nationality' => $nationality,
        'gender' => $gender,
    ];
}

try {
    $pdo = chnGetPdo();
    $pdo->beginTransaction();

    if ($isAdmin) {
        $emailCheck = $pdo->prepare(
            'SELECT user_id FROM `user` WHERE email = :email AND user_id <> :user_id LIMIT 1'
        );
        $emailCheck->execute([
            ':email' => $adminFields['email'],
            ':user_id' => $userId,
        ]);
        if ($emailCheck->fetch() !== false) {
            $pdo->rollBack();
            chnJsonError('EMAIL_EXISTS', 'This email address is already in use.', 409);
        }

        $updateUser = $pdo->prepare(
            'UPDATE `user`
             SET name = :name, surname = :surname, email = :email, phone = :phone,
                 birth_date = :birth_date, nationality = :nationality, gender = :gender
             WHERE user_id = :user_id'
        );
        $updateUser->execute([
            ':name' => $adminFields['name'],
            ':surname' => $adminFields['surname'],
            ':email' => $adminFields['email'],
            ':phone' => $phone,
            ':birth_date' => $adminFields['birth_date'],
            ':nationality' => $adminFields['nationality'],
            ':gender' => $adminFields['gender'],
            ':user_id' => $userId,
        ]);
    } else {
        $updateUser = $pdo->prepare(
            'UPDATE `user` SET phone = :phone WHERE user_id = :user_id'
        );
        $updateUser->execute([
            ':phone' => $phone,
            ':user_id' => $userId,
        ]);
    }

    if ($isPatient && $hasAddress) {
        $patientCheck = $pdo->prepare('SELECT patient_id FROM `patient` WHERE user_id = :user_id LIMIT 1');
        $patientCheck->execute([':user_id' => $userId]);
        if ($patientCheck->fetch() !== false) {
            $updatePatient = $pdo->prepare(
                'UPDATE `patient` SET full_address = :full_address WHERE user_id = :user_id'
            );
            $updatePatient->execute([
                ':full_address' => $fullAddress,
                ':user_id' => $userId,
            ]);
        } else {
            $insertPatient = $pdo->prepare(
                'INSERT INTO `patient` (user_id, full_address) VALUES (:user_id, :full_address)'
            );
            $insertPatient->execute([
                ':user_id' => $userId,
                ':full_address' => $fullAddress,
            ]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    chnLogError('Account settings update error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to save account settings.', 500);
}

if ($isAdmin) {
    $_SESSION['name'] = $adminFields['name'];
    $_SESSION['surname'] = $adminFields['surname'];
}

$languageChanged = false;
if ($preferredLang !== '') {
    $currentLang = $_COOKIE['chn_lang'] ?? 'en';
    $languageChanged = $preferredLang !== $currentLang;
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('chn_lang', $preferredLang, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    $_SESSION['lang'] = $preferredLang;
}

chnJsonSuccess(
    [
        'phone' => $phone ?? '',
        'full_address' => $fullAddress ?? '',
        'preferred_language' => $preferredLang !== '' ? $preferredLang : ($_COOKIE['chn_lang'] ?? 'en'),
        'language_changed' => $languageChanged,
    ],
    'Account settings updated.',
    200
);
