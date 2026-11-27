<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

$sessionUser = requireRole(['doctor'], true);
$input = chnRequireJsonPost();

$action = chnInputString($input, 'action');
if (!in_array($action, ['add', 'deactivate'], true)) {
    chnJsonError('INVALID_INPUT', 'Valid treatment action is required.', 422);
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

    if ($action === 'add') {
        $caseId = (int) ($input['case_id'] ?? 0);
        $desc = chnInputString($input, 'treatment_desc');
        if ($caseId <= 0 || $desc === '' || mb_strlen($desc, 'UTF-8') > 255) {
            chnJsonError('INVALID_INPUT', 'Enter a treatment description (1 to 255 characters).', 422);
        }
        $textPattern = '/^[' . CHN_ALLOWED_LETTERS . '0-9\s\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]+$/u';
        if (preg_match($textPattern, $desc) !== 1) {
            chnJsonError('INVALID_INPUT', 'Treatment description contains unsupported characters.', 400);
        }

        $caseStmt = $pdo->prepare('SELECT case_id, department_id, case_state FROM `case` WHERE case_id = :case_id LIMIT 1');
        $caseStmt->execute([':case_id' => $caseId]);
        $caseRow = $caseStmt->fetch();
        if ($caseRow === false) {
            chnJsonError('CASE_NOT_FOUND', 'Medical case not found.', 404);
        }
        if ((int) $caseRow['department_id'] !== $departmentId) {
            chnJsonError('FORBIDDEN', 'You can only manage treatments in your department.', 403);
        }
        if ((string) $caseRow['case_state'] !== 'open') {
            chnJsonError('CASE_CLOSED', 'Cannot add active treatment to a closed case.', 409);
        }

        $ins = $pdo->prepare(
            "INSERT INTO `treatment` (case_id, doctor_id, treatment_desc, treatment_status)
             VALUES (:case_id, :doctor_id, :treatment_desc, 'active')"
        );
        $ins->execute([
            ':case_id' => $caseId,
            ':doctor_id' => $doctorId,
            ':treatment_desc' => $desc,
        ]);
        chnJsonSuccess(['treatment_id' => (int) $pdo->lastInsertId()], 'Treatment added.', 201);
    }

    $treatmentId = (int) ($input['treatment_id'] ?? 0);
    if ($treatmentId <= 0) {
        chnJsonError('INVALID_INPUT', 'Valid treatment ID is required.', 422);
    }

    $tStmt = $pdo->prepare(
        'SELECT t.treatment_id, c.department_id
         FROM `treatment` t
         INNER JOIN `case` c ON c.case_id = t.case_id
         WHERE t.treatment_id = :treatment_id
         LIMIT 1'
    );
    $tStmt->execute([':treatment_id' => $treatmentId]);
    $tRow = $tStmt->fetch();
    if ($tRow === false) {
        chnJsonError('TREATMENT_NOT_FOUND', 'Treatment not found.', 404);
    }
    if ((int) $tRow['department_id'] !== $departmentId) {
        chnJsonError('FORBIDDEN', 'You can only manage treatments in your department.', 403);
    }

    $upd = $pdo->prepare(
        "UPDATE `treatment`
         SET treatment_status = 'inactive', deactivated_by_doctor_id = :doctor_id, completed_at = NOW()
         WHERE treatment_id = :treatment_id"
    );
    $upd->execute([':doctor_id' => $doctorId, ':treatment_id' => $treatmentId]);

    chnJsonSuccess(['treatment_id' => $treatmentId], 'Treatment completed.', 200);
} catch (PDOException $e) {
    if ((string) $e->getCode() === '45000') {
        chnJsonError('INVALID_STATE', $e->getMessage(), 409);
    }
    chnLogError('Manage treatment error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to update treatment.', 500);
} catch (Throwable $e) {
    chnLogError('Manage treatment error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to update treatment.', 500);
}
