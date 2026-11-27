<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

$sessionUser = requireRole(['patient', 'doctor'], true);
$input = chnRequireJsonPost();

$role = $sessionUser['role'];
$userId = $sessionUser['user_id'];
$description = chnInputString($input, 'case_description');

if ($description === '' || strlen($description) > 65535) {
    chnJsonError('INVALID_INPUT', 'Enter a detailed case description (up to 65,535 bytes).', 422);
}

$textPattern = '/^[' . CHN_ALLOWED_LETTERS . '0-9\s\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]+$/u';
if (preg_match($textPattern, $description) !== 1) {
    chnJsonError('INVALID_INPUT', 'Case description contains unsupported characters. Use English or Turkish Latin letters.', 400);
}

try {
    $pdo = chnGetPdo();

    if ($role === 'patient') {
        $hospitalId = (int) ($input['hospital_id'] ?? 0);
        $departmentId = (int) ($input['department_id'] ?? 0);
        $doctorId = (int) ($input['doctor_id'] ?? 0);

        if ($hospitalId <= 0 || $departmentId <= 0 || $doctorId <= 0) {
            chnJsonError('INVALID_INPUT', 'Select a hospital, department, and preferred doctor.', 422);
        }

        $patStmt = $pdo->prepare('SELECT patient_id FROM `patient` WHERE user_id = :user_id LIMIT 1');
        $patStmt->execute([':user_id' => $userId]);
        $patientRow = $patStmt->fetch();
        if ($patientRow === false) {
            chnJsonError('PATIENT_NOT_FOUND', 'Patient profile not found.', 404);
        }
        $patientId = (int) $patientRow['patient_id'];

        $docStmt = $pdo->prepare(
            'SELECT d.doctor_id
             FROM `doctor` d
             INNER JOIN `department` dep ON dep.department_id = d.department_id
             WHERE d.doctor_id = :doctor_id AND d.department_id = :department_id AND dep.hospital_id = :hospital_id
             LIMIT 1'
        );
        $docStmt->execute([
            ':doctor_id' => $doctorId,
            ':department_id' => $departmentId,
            ':hospital_id' => $hospitalId,
        ]);
        if ($docStmt->fetch() === false) {
            chnJsonError('INVALID_INPUT', 'Selected doctor does not belong to the chosen hospital and department.', 422);
        }
    } else {
        $nationalIdRaw = chnInputString($input, 'national_id');
        $cleanNid = strtoupper(preg_replace('/[\s-]+/', '', $nationalIdRaw) ?? '');
        if ($cleanNid === '' || preg_match('/^[A-Za-z0-9\s-]+$/', $nationalIdRaw) !== 1 || strlen($cleanNid) < 3 || strlen($cleanNid) > 50) {
            chnJsonError('INVALID_INPUT', 'Enter a valid patient National ID.', 422);
        }

        $docStmt = $pdo->prepare('SELECT doctor_id, department_id FROM `doctor` WHERE user_id = :user_id LIMIT 1');
        $docStmt->execute([':user_id' => $userId]);
        $docRow = $docStmt->fetch();
        if ($docRow === false) {
            chnJsonError('FORBIDDEN', 'Doctor profile not found.', 403);
        }
        $doctorId = (int) $docRow['doctor_id'];
        $departmentId = (int) $docRow['department_id'];

        $patStmt = $pdo->prepare(
            "SELECT p.patient_id
             FROM `patient` p
             INNER JOIN `user` u ON u.user_id = p.user_id
             WHERE UPPER(REPLACE(REPLACE(u.national_id, ' ', ''), '-', '')) = :nid
               AND u.role = 'patient'
             LIMIT 1"
        );
        $patStmt->execute([':nid' => $cleanNid]);
        $patientRow = $patStmt->fetch();
        if ($patientRow === false) {
            chnJsonError('PATIENT_NOT_FOUND', 'No patient found with that National ID.', 404);
        }
        $patientId = (int) $patientRow['patient_id'];
    }

    $insertStmt = $pdo->prepare(
        "INSERT INTO `case` (patient_id, department_id, doctor_id, case_description, tier_color, case_state)
         VALUES (:patient_id, :department_id, :doctor_id, :case_description, 'green', 'open')"
    );
    $insertStmt->execute([
        ':patient_id' => $patientId,
        ':department_id' => $departmentId,
        ':doctor_id' => $doctorId,
        ':case_description' => $description,
    ]);
    $caseId = (int) $pdo->lastInsertId();
} catch (Throwable $e) {
    chnLogError('Create case error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to create medical case.', 500);
}

chnJsonSuccess(['case_id' => $caseId], 'Medical case created.', 201);
