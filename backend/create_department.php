<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

requireRole(['admin'], true);
$input = chnRequireJsonPost();

$hospitalId = (int) ($input['hospital_id'] ?? 0);
$departmentName = chnInputString($input, 'department_name');

if ($hospitalId <= 0) {
    chnJsonError('INVALID_INPUT', 'Select a hospital.', 422);
}

$namePattern = '/^[' . CHN_ALLOWED_LETTERS . '0-9\s,.\-\/&()]+$/u';
if ($departmentName === '' || mb_strlen($departmentName, 'UTF-8') > 255 || preg_match($namePattern, $departmentName) !== 1) {
    chnJsonError('INVALID_INPUT', 'Enter a valid department name using Latin characters.', 400);
}

try {
    $pdo = chnGetPdo();
    $hosp = $pdo->prepare('SELECT hospital_id FROM `hospital` WHERE hospital_id = :id LIMIT 1');
    $hosp->execute([':id' => $hospitalId]);
    if ($hosp->fetch() === false) {
        chnJsonError('HOSPITAL_NOT_FOUND', 'Selected hospital does not exist.', 404);
    }

    $dup = $pdo->prepare(
        'SELECT department_id FROM `department` WHERE hospital_id = :hospital_id AND department_name = :department_name LIMIT 1'
    );
    $dup->execute([
        ':hospital_id' => $hospitalId,
        ':department_name' => $departmentName,
    ]);
    if ($dup->fetch() !== false) {
        chnJsonError('DEPARTMENT_EXISTS', 'This department already exists in the selected hospital.', 409);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO `department` (department_name, hospital_id) VALUES (:department_name, :hospital_id)'
    );
    $stmt->execute([
        ':department_name' => $departmentName,
        ':hospital_id' => $hospitalId,
    ]);
    $departmentId = (int) $pdo->lastInsertId();
} catch (Throwable $e) {
    chnLogError('Create department error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to create department.', 500);
}

chnJsonSuccess(['department_id' => $departmentId], 'Department created.', 201);
