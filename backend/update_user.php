<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

$sessionUser = requireRole(['admin'], true);
$input = chnRequireJsonPost();

$userId = (int) ($input['user_id'] ?? 0);
if ($userId <= 0) {
    chnJsonError('INVALID_INPUT', 'Valid user ID is required.', 422);
}

$firstName = chnInputString($input, 'first_name');
$lastName = chnInputString($input, 'last_name');
$email = strtolower(chnInputString($input, 'email'));
$phoneRaw = chnInputString($input, 'phone');
$birthDateRaw = chnInputString($input, 'birth_date');
$genderRaw = chnInputString($input, 'gender');
$nationalityRaw = strtoupper(chnInputString($input, 'nationality'));
$nationalIdRaw = chnInputString($input, 'national_id');
$password = is_string($input['password'] ?? null) ? $input['password'] : '';
$passwordConfirm = is_string($input['password_confirm'] ?? null) ? $input['password_confirm'] : '';

$hospitalId = (int) ($input['hospital_id'] ?? 0);
$departmentId = (int) ($input['department_id'] ?? 0);

if (!chnIsValidName($firstName) || !chnIsValidName($lastName)) {
    chnJsonError('INVALID_INPUT', 'First and last name must use valid Latin characters.', 400);
}
if (!chnIsValidEmail($email)) {
    chnJsonError('INVALID_INPUT', 'Enter a valid email address.', 422);
}

$phone = null;
if ($phoneRaw !== '') {
    if (!chnIsValidPhone($phoneRaw)) {
        chnJsonError('INVALID_INPUT', 'Enter a valid international phone number.', 422);
    }
    $phone = chnNormalizePhone($phoneRaw);
}

$birthDate = null;
if ($birthDateRaw !== '') {
    if (!chnIsValidBirthDate($birthDateRaw)) {
        chnJsonError('INVALID_INPUT', 'Enter a valid birth date.', 422);
    }
    $birthDate = $birthDateRaw;
}

$gender = null;
if ($genderRaw !== '') {
    if (!in_array($genderRaw, CHN_GENDERS, true)) {
        chnJsonError('INVALID_INPUT', 'Select a valid gender.', 422);
    }
    $gender = $genderRaw;
}

$nationality = null;
if ($nationalityRaw !== '') {
    if (preg_match('/^[A-Z]{2}$/', $nationalityRaw) !== 1) {
        chnJsonError('INVALID_INPUT', 'Select a valid 2-letter nationality code.', 422);
    }
    $nationality = $nationalityRaw;
}

$nationalId = null;
if ($nationalIdRaw !== '') {
    if ($nationality !== null && !chnIsValidNationalId($nationalIdRaw, $nationality)) {
        chnJsonError('INVALID_INPUT', 'Enter a valid national ID or passport number.', 422);
    }
    if ($nationality === null && strlen($nationalIdRaw) > 50) {
        chnJsonError('INVALID_INPUT', 'National ID must be at most 50 characters.', 422);
    }
    $nationalId = $nationalIdRaw;
}

$updatePassword = false;
$passwordHash = '';
if ($password !== '' || $passwordConfirm !== '') {
    if (!chnIsValidPassword($password)) {
        chnJsonError('INVALID_INPUT', 'Password must be at least 8 characters with a letter and a number.', 422);
    }
    if ($password !== $passwordConfirm) {
        chnJsonError('PASSWORD_MISMATCH', 'Passwords must match.', 422);
    }
    $updatePassword = true;
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
}

try {
    $pdo = chnGetPdo();
    $pdo->beginTransaction();

    $userStmt = $pdo->prepare('SELECT user_id, role FROM `user` WHERE user_id = :user_id LIMIT 1 FOR UPDATE');
    $userStmt->execute([':user_id' => $userId]);
    $existing = $userStmt->fetch();

    if ($existing === false) {
        $pdo->rollBack();
        chnJsonError('USER_NOT_FOUND', 'User account not found.', 404);
    }

    $role = (string) $existing['role'];
    $needsWorkplace = in_array($role, ['doctor', 'healthcare_staff'], true);

    if ($needsWorkplace && ($hospitalId <= 0 || $departmentId <= 0)) {
        $pdo->rollBack();
        chnJsonError('INVALID_INPUT', 'Hospital and department are required for doctors and healthcare staff.', 422);
    }

    $emailCheck = $pdo->prepare('SELECT user_id FROM `user` WHERE email = :email AND user_id <> :user_id LIMIT 1');
    $emailCheck->execute([
        ':email' => $email,
        ':user_id' => $userId,
    ]);
    if ($emailCheck->fetch() !== false) {
        $pdo->rollBack();
        chnJsonError('EMAIL_EXISTS', 'An account with this email already exists.', 409);
    }

    if ($needsWorkplace) {
        $deptCheck = $pdo->prepare(
            'SELECT department_id FROM `department` WHERE department_id = :department_id AND hospital_id = :hospital_id LIMIT 1'
        );
        $deptCheck->execute([
            ':department_id' => $departmentId,
            ':hospital_id' => $hospitalId,
        ]);
        if ($deptCheck->fetch() === false) {
            $pdo->rollBack();
            chnJsonError('INVALID_INPUT', 'Selected department does not belong to the selected hospital.', 422);
        }
    }

    $sql = 'UPDATE `user`
            SET email = :email, name = :name, surname = :surname, gender = :gender, phone = :phone,
                birth_date = :birth_date, nationality = :nationality, national_id = :national_id';
    $params = [
        ':email' => $email,
        ':name' => $firstName,
        ':surname' => $lastName,
        ':gender' => $gender,
        ':phone' => $phone,
        ':birth_date' => $birthDate,
        ':nationality' => $nationality,
        ':national_id' => $nationalId,
        ':user_id' => $userId,
    ];

    if ($updatePassword) {
        $sql .= ', password = :password';
        $params[':password'] = $passwordHash;
    }

    $sql .= ' WHERE user_id = :user_id';
    $updateStmt = $pdo->prepare($sql);
    $updateStmt->execute($params);

    if ($role === 'doctor') {
        $docStmt = $pdo->prepare('UPDATE `doctor` SET department_id = :department_id WHERE user_id = :user_id');
        $docStmt->execute([
            ':department_id' => $departmentId,
            ':user_id' => $userId,
        ]);
    } elseif ($role === 'healthcare_staff') {
        $staffStmt = $pdo->prepare(
            'UPDATE `staff` SET hospital_id = :hospital_id, department_id = :department_id WHERE user_id = :user_id'
        );
        $staffStmt->execute([
            ':hospital_id' => $hospitalId,
            ':department_id' => $departmentId,
            ':user_id' => $userId,
        ]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((string) $e->getCode() === '23000') {
        chnJsonError(
            'WORKPLACE_LOCKED',
            'Cannot change department because this doctor is linked to existing cases or appointments.',
            409
        );
    }
    chnLogError('Update user error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to update user account.', 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    chnLogError('Update user error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to update user account.', 500);
}

if ($userId === $sessionUser['user_id']) {
    $_SESSION['name'] = $firstName;
    $_SESSION['surname'] = $lastName;
}

chnJsonSuccess(['user_id' => $userId], 'User account updated.', 200);
