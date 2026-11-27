<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

$sessionUser = requireRole(['doctor'], true);
$input = chnRequireJsonPost();

$caseId = (int) ($input['case_id'] ?? 0);
$diagnosisText = chnInputString($input, 'diagnosis_text');
$icdCode = chnInputString($input, 'icd_code');

if ($caseId <= 0) {
    chnJsonError('INVALID_INPUT', 'Valid case ID is required.', 422);
}

if ($diagnosisText === '' || strlen($diagnosisText) > 65535) {
    chnJsonError('INVALID_INPUT', 'Enter a diagnosis description (up to 65,535 bytes).', 422);
}

$textPattern = '/^[' . CHN_ALLOWED_LETTERS . '0-9\s\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]+$/u';
if (preg_match($textPattern, $diagnosisText) !== 1) {
    chnJsonError('INVALID_INPUT', 'Diagnosis contains unsupported characters.', 400);
}

if ($icdCode !== '' && (mb_strlen($icdCode, 'UTF-8') > 20 || preg_match('/^[A-Za-z0-9.\-]+$/', $icdCode) !== 1)) {
    chnJsonError('INVALID_INPUT', 'ICD code must be at most 20 alphanumeric/dot/dash characters.', 422);
}

try {
    $pdo = chnGetPdo();

    $docStmt = $pdo->prepare('SELECT doctor_id, department_id FROM `doctor` WHERE user_id = :user_id LIMIT 1');
    $docStmt->execute([':user_id' => $sessionUser['user_id']]);
    $docRow = $docStmt->fetch();
    if ($docRow === false) {
        chnJsonError('FORBIDDEN', 'Doctor profile not found.', 403);
    }
    $doctorId = (int) $docRow['doctor_id'];
    $departmentId = (int) $docRow['department_id'];

    $caseStmt = $pdo->prepare('SELECT case_id, department_id, case_state FROM `case` WHERE case_id = :case_id LIMIT 1');
    $caseStmt->execute([':case_id' => $caseId]);
    $caseRow = $caseStmt->fetch();

    if ($caseRow === false) {
        chnJsonError('CASE_NOT_FOUND', 'Medical case not found.', 404);
    }
    if ((int) $caseRow['department_id'] !== $departmentId) {
        chnJsonError('FORBIDDEN', 'You can only add diagnoses to cases in your department.', 403);
    }
    if ((string) $caseRow['case_state'] !== 'open') {
        chnJsonError('CASE_CLOSED', 'Cannot add diagnosis to a closed case.', 409);
    }

    $ins = $pdo->prepare(
        'INSERT INTO `diagnosis` (case_id, doctor_id, diagnosis_text, icd_code)
         VALUES (:case_id, :doctor_id, :diagnosis_text, :icd_code)'
    );
    $ins->execute([
        ':case_id' => $caseId,
        ':doctor_id' => $doctorId,
        ':diagnosis_text' => $diagnosisText,
        ':icd_code' => $icdCode !== '' ? $icdCode : null,
    ]);
    $diagnosisId = (int) $pdo->lastInsertId();
} catch (Throwable $e) {
    chnLogError('Add diagnosis error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to save diagnosis.', 500);
}

chnJsonSuccess([
    'diagnosis_id' => $diagnosisId,
    'case_id' => $caseId,
    'diagnosis_text' => $diagnosisText,
    'icd_code' => $icdCode,
    'doctor_name' => trim($sessionUser['name'] . ' ' . $sessionUser['surname']),
    'diagnosed_at' => date('Y-m-d H:i:s'),
], 'Diagnosis added.', 201);
