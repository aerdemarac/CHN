<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

$sessionUser = requireRole(['doctor', 'healthcare_staff'], true);
$input = chnRequireJsonPost();

$action = chnInputString($input, 'action');
$caseId = (int) ($input['case_id'] ?? 0);
$role = $sessionUser['role'];

if ($caseId <= 0 || !in_array($action, ['update_status', 'update_triage'], true)) {
    chnJsonError('INVALID_INPUT', 'Valid case ID and action are required.', 422);
}

if ($action === 'update_status' && $role !== 'doctor') {
    chnJsonError('FORBIDDEN', 'Only doctors can open or close medical cases.', 403);
}

try {
    $pdo = chnGetPdo();
    $scope = chnGetUserHospitalScope($pdo, $sessionUser);

    $caseStmt = $pdo->prepare(
        'SELECT c.case_id, c.department_id, c.case_state, c.tier_color, dep.hospital_id
         FROM `case` c
         INNER JOIN `department` dep ON dep.department_id = c.department_id
         WHERE c.case_id = :case_id
         LIMIT 1'
    );
    $caseStmt->execute([':case_id' => $caseId]);
    $caseRow = $caseStmt->fetch();

    if ($caseRow === false) {
        chnJsonError('CASE_NOT_FOUND', 'Medical case not found.', 404);
    }

    if ($role === 'doctor') {
        if ($scope['department_id'] === null || (int) $caseRow['department_id'] !== $scope['department_id']) {
            chnJsonError('FORBIDDEN', 'You can only update cases in your own department.', 403);
        }
    } else {
        if ($scope['hospital_id'] === null || (int) $caseRow['hospital_id'] !== $scope['hospital_id']) {
            chnJsonError('FORBIDDEN', 'You can only update cases in your assigned hospital.', 403);
        }
    }

    if ($action === 'update_status') {
        $newState = chnInputString($input, 'case_state');
        if (!in_array($newState, ['open', 'closed'], true)) {
            chnJsonError('INVALID_INPUT', 'Invalid case status.', 422);
        }

        $docStmt = $pdo->prepare('SELECT doctor_id FROM `doctor` WHERE user_id = :user_id LIMIT 1');
        $docStmt->execute([':user_id' => $sessionUser['user_id']]);
        $docRow = $docStmt->fetch();
        if ($docRow === false) {
            chnJsonError('FORBIDDEN', 'Doctor profile not found.', 403);
        }
        $doctorId = (int) $docRow['doctor_id'];

        if ($newState === 'closed') {
            $upd = $pdo->prepare(
                "UPDATE `case`
                 SET case_state = 'closed', closed_by_doctor_id = :doctor_id, closed_at = NOW()
                 WHERE case_id = :case_id"
            );
            $upd->execute([':doctor_id' => $doctorId, ':case_id' => $caseId]);
        } else {
            $upd = $pdo->prepare(
                "UPDATE `case`
                 SET case_state = 'open', closed_by_doctor_id = NULL, closed_at = NULL
                 WHERE case_id = :case_id"
            );
            $upd->execute([':case_id' => $caseId]);
        }

        chnJsonSuccess(['case_id' => $caseId, 'case_state' => $newState], 'Case status updated.', 200);
    }

    $tierColor = chnInputString($input, 'tier_color');
    if (!in_array($tierColor, ['green', 'yellow', 'orange'], true)) {
        chnJsonError('INVALID_INPUT', 'Invalid triage color.', 422);
    }

    $upd = $pdo->prepare('UPDATE `case` SET tier_color = :tier_color WHERE case_id = :case_id');
    $upd->execute([':tier_color' => $tierColor, ':case_id' => $caseId]);

    chnJsonSuccess(['case_id' => $caseId, 'tier_color' => $tierColor], 'Medical triage updated.', 200);
} catch (PDOException $e) {
    if ((string) $e->getCode() === '45000') {
        chnJsonError(
            'ACTIVE_TREATMENTS',
            'Cannot close case while active treatments exist. Deactivate active treatments first.',
            409
        );
    }
    chnLogError('Update case error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to update medical case.', 500);
} catch (Throwable $e) {
    chnLogError('Update case error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to update medical case.', 500);
}
