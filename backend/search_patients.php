<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';
require_once dirname(__DIR__) . '/include/departments.php';

$sessionUser = requireRole(['doctor', 'healthcare_staff'], true);
$input = chnRequireJsonPost();

$searchBy = chnInputString($input, 'search_by');
$rawQuery = chnInputString($input, 'q');

if ($rawQuery !== '' && !in_array($searchBy, ['phone', 'email', 'national_id', 'name'], true)) {
    chnJsonError('INVALID_INPUT', 'Select a valid search type.', 422);
}

$whereSearch = '1 = 1';
$searchParams = [];

if ($rawQuery === '') {
    // Empty query returns all associated patients within the caller's scope.
} elseif ($searchBy === 'phone') {
    if (!chnIsValidPhone($rawQuery)) {
        chnJsonError('INVALID_INPUT', 'Enter a valid phone number (7 to 15 digits).', 422);
    }
    $normalized = chnNormalizePhone($rawQuery);
    $digits = preg_replace('/\D/', '', $normalized) ?? '';
    $whereSearch = "(u.phone = :phone_exact OR REPLACE(u.phone, '+', '') = :phone_digits OR RIGHT(REPLACE(u.phone, '+', ''), LENGTH(:phone_tail)) = :phone_tail)";
    $searchParams[':phone_exact'] = $normalized;
    $searchParams[':phone_digits'] = $digits;
    $searchParams[':phone_tail'] = $digits;
} elseif ($searchBy === 'email') {
    $email = strtolower($rawQuery);
    if (!chnIsValidEmail($email)) {
        chnJsonError('INVALID_INPUT', 'Enter a valid email address.', 422);
    }
    $whereSearch = 'LOWER(u.email) = :email';
    $searchParams[':email'] = $email;
} elseif ($searchBy === 'national_id') {
    $clean = preg_replace('/[\s-]+/', '', $rawQuery) ?? '';
    if (preg_match('/^[A-Za-z0-9\s-]+$/', $rawQuery) !== 1 || strlen($clean) < 5 || strlen($clean) > 50) {
        chnJsonError('INVALID_INPUT', 'Enter a valid national ID or passport number.', 422);
    }
    $whereSearch = "UPPER(REPLACE(REPLACE(u.national_id, ' ', ''), '-', '')) = :national_id";
    $searchParams[':national_id'] = strtoupper($clean);
} else {
    $collapsed = preg_replace('/\s+/u', ' ', $rawQuery) ?? '';
    $len = mb_strlen($collapsed, 'UTF-8');
    $namePattern = '/^[' . CHN_ALLOWED_LETTERS . '][' . CHN_ALLOWED_LETTERS . ' \'.-]*$/u';
    if ($len < 2 || $len > 100 || preg_match($namePattern, $collapsed) !== 1) {
        chnJsonError('INVALID_INPUT', 'Enter a valid name using English or Turkish Latin letters.', 400);
    }
    $escapedLike = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $collapsed);
    $whereSearch = "CONCAT(u.name, ' ', u.surname) LIKE :name_like ESCAPE '\\\\'";
    $searchParams[':name_like'] = '%' . $escapedLike . '%';
}

try {
    $pdo = chnGetPdo();
    $scope = chnGetUserHospitalScope($pdo, $sessionUser);

    if ($sessionUser['role'] === 'doctor') {
        if ($scope['department_id'] === null) {
            chnJsonSuccess(['patients' => [], 'scope' => $scope], '', 200);
        }
        $scopeSql = 'c.department_id = :scope_id';
        $searchParams[':scope_id'] = $scope['department_id'];
    } else {
        if ($scope['hospital_id'] === null) {
            chnJsonSuccess(['patients' => [], 'scope' => $scope], '', 200);
        }
        $scopeSql = 'dep.hospital_id = :scope_id';
        $searchParams[':scope_id'] = $scope['hospital_id'];
    }

    $sql = "SELECT p.patient_id, u.name, u.surname, u.birth_date, u.phone, u.email, p.full_address,
                   c.case_id, c.case_state, dep.department_name, h.hospital_name
            FROM `patient` p
            INNER JOIN `user` u ON u.user_id = p.user_id
            INNER JOIN `case` c ON c.patient_id = p.patient_id
            INNER JOIN `department` dep ON dep.department_id = c.department_id
            INNER JOIN `hospital` h ON h.hospital_id = dep.hospital_id
            WHERE u.role = 'patient'
              AND {$scopeSql}
              AND {$whereSearch}
            ORDER BY u.surname ASC, u.name ASC, c.case_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($searchParams);

    $patients = [];
    $today = new DateTimeImmutable('today');

    foreach ($stmt->fetchAll() as $row) {
        $pid = (int) $row['patient_id'];
        if (!isset($patients[$pid])) {
            $birthDate = $row['birth_date'] !== null ? (string) $row['birth_date'] : null;
            $age = null;
            if ($birthDate !== null && $birthDate !== '') {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d', $birthDate);
                if ($dt !== false) {
                    $age = (int) $dt->diff($today)->y;
                }
            }
            $patients[$pid] = [
                'patient_id' => $pid,
                'name' => (string) $row['name'],
                'surname' => (string) $row['surname'],
                'birth_date' => $birthDate,
                'age' => $age,
                'phone' => $row['phone'] !== null ? (string) $row['phone'] : null,
                'email' => (string) $row['email'],
                'full_address' => $row['full_address'] !== null ? (string) $row['full_address'] : null,
                'cases' => [],
            ];
        }

        $patients[$pid]['cases'][] = [
            'case_id' => (int) $row['case_id'],
            'case_state' => (string) $row['case_state'],
            'department_name' => (string) $row['department_name'],
            'department_label' => chnDepartmentLabel((string) $row['department_name']),
            'hospital_name' => (string) $row['hospital_name'],
        ];
    }
} catch (Throwable $e) {
    chnLogError('Search patients error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to search patients.', 500);
}

chnJsonSuccess([
    'patients' => array_values($patients),
    'scope' => $scope,
], '', 200);
