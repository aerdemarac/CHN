<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

requireRole(['admin'], true);
$input = chnRequireJsonPost();

$role = chnInputString($input, 'user_role');
$firstName = chnInputString($input, 'first_name');
$lastName = chnInputString($input, 'last_name');
$email = strtolower(chnInputString($input, 'email'));
$phoneRaw = chnInputString($input, 'phone');
$birthDate = chnInputString($input, 'birth_date');
$gender = chnInputString($input, 'gender');
$nationality = strtoupper(chnInputString($input, 'nationality'));
$nationalId = chnInputString($input, 'national_id');
$password = is_string($input['password'] ?? null) ? $input['password'] : '';
$passwordConfirm = is_string($input['password_confirm'] ?? null) ? $input['password_confirm'] : '';

$hospitalId = (int) ($input['hospital_id'] ?? 0);
$departmentId = (int) ($input['department_id'] ?? 0);

if (!in_array($role, ['patient', 'doctor', 'healthcare_staff', 'admin'], true)) {
    chnJsonError('INVALID_INPUT', 'Select a valid role.', 422);
}
if (!chnIsValidName($firstName) || !chnIsValidName($lastName)) {
    chnJsonError('INVALID_INPUT', 'First and last name must use valid Latin characters.', 400);
}
if (!chnIsValidEmail($email)) {
    chnJsonError('INVALID_INPUT', 'Enter a valid email address.', 422);
}
if (!chnIsValidPhone($phoneRaw)) {
    chnJsonError('INVALID_INPUT', 'Enter a valid international phone number.', 422);
}
$phone = chnNormalizePhone($phoneRaw);

if (!chnIsValidBirthDate($birthDate)) {
    chnJsonError('INVALID_INPUT', 'Enter a valid birth date.', 422);
}
if (!in_array($gender, CHN_GENDERS, true)) {
    chnJsonError('INVALID_INPUT', 'Select a valid gender.', 422);
}
if (preg_match('/^[A-Z]{2}$/', $nationality) !== 1) {
    chnJsonError('INVALID_INPUT', 'Select a valid 2-letter nationality code.', 422);
}
if (!chnIsValidNationalId($nationalId, $nationality)) {
    chnJsonError('INVALID_INPUT', 'Enter a valid national ID or passport number.', 422);
}
if (!chnIsValidPassword($password)) {
    chnJsonError('INVALID_INPUT', 'Password must be at least 8 characters with a letter and a number.', 422);
}
if ($password !== $passwordConfirm) {
    chnJsonError('PASSWORD_MISMATCH', 'Passwords must match.', 422);
}

$needsWorkplace = in_array($role, ['doctor', 'healthcare_staff'], true);
if ($needsWorkplace && ($hospitalId <= 0 || $departmentId <= 0)) {
    chnJsonError('INVALID_INPUT', 'Hospital and department are required for doctors and healthcare staff.', 422);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo = chnGetPdo();
    $pdo->beginTransaction();

    $emailCheck = $pdo->prepare('SELECT user_id FROM `user` WHERE email = :email LIMIT 1');
    $emailCheck->execute([':email' => $email]);
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

    $insertUser = $pdo->prepare(
        'INSERT INTO `user` (email, password, role, name, surname, gender, birth_date, nationality, national_id, phone, verify, verify_token)
         VALUES (:email, :password, :role, :name, :surname, :gender, :birth_date, :nationality, :national_id, :phone, 1, NULL)'
    );
    $insertUser->execute([
        ':email' => $email,
        ':password' => $passwordHash,
        ':role' => $role,
        ':name' => $firstName,
        ':surname' => $lastName,
        ':gender' => $gender,
        ':birth_date' => $birthDate,
        ':nationality' => $nationality,
        ':national_id' => $nationalId,
        ':phone' => $phone,
    ]);

    $newUserId = (int) $pdo->lastInsertId();

    if ($role === 'patient') {
        $roleStmt = $pdo->prepare('INSERT INTO `patient` (user_id) VALUES (:user_id)');
        $roleStmt->execute([':user_id' => $newUserId]);
    } elseif ($role === 'doctor') {
        $roleStmt = $pdo->prepare(
            'INSERT INTO `doctor` (department_id, user_id) VALUES (:department_id, :user_id)'
        );
        $roleStmt->execute([
            ':department_id' => $departmentId,
            ':user_id' => $newUserId,
        ]);
    } elseif ($role === 'healthcare_staff') {
        $roleStmt = $pdo->prepare(
            'INSERT INTO `staff` (user_id, hospital_id, department_id) VALUES (:user_id, :hospital_id, :department_id)'
        );
        $roleStmt->execute([
            ':user_id' => $newUserId,
            ':hospital_id' => $hospitalId,
            ':department_id' => $departmentId,
        ]);
    } else {
        $roleStmt = $pdo->prepare(
            'INSERT INTO `admin` (user_id) VALUES (:user_id)'
        );
        $roleStmt->execute([':user_id' => $newUserId]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    chnLogError('Create user error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to create user account.', 500);
}

chnJsonSuccess(['user_id' => $newUserId], 'User account created.', 201);
