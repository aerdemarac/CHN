<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

$sessionUser = requireRole(['healthcare_staff', 'admin'], true);
$input = chnRequireJsonPost();

$action = chnInputString($input, 'action');
$role = $sessionUser['role'];

if (!in_array($action, ['start_handling', 'mark_resolved', 'flag_fraud', 'ban_user'], true)) {
    chnJsonError('INVALID_INPUT', 'Unsupported emergency action.', 422);
}

if (in_array($action, ['start_handling', 'mark_resolved'], true) && $role !== 'healthcare_staff') {
    chnJsonError('FORBIDDEN', 'Only healthcare staff can handle or resolve emergencies.', 403);
}

if ($action === 'ban_user' && $role !== 'admin') {
    chnJsonError('FORBIDDEN', 'Only administrators can ban user accounts.', 403);
}

try {
    $pdo = chnGetPdo();

    if ($action === 'ban_user') {
        $targetUserId = (int) ($input['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            chnJsonError('INVALID_INPUT', 'A registered user ID is required to ban.', 422);
        }
        if ($targetUserId === $sessionUser['user_id']) {
            chnJsonError('CANNOT_BAN_SELF', 'You cannot ban your own account.', 400);
        }

        $pdo->beginTransaction();
        $userStmt = $pdo->prepare(
            'SELECT user_id, role, is_banned FROM `user` WHERE user_id = :user_id LIMIT 1 FOR UPDATE'
        );
        $userStmt->execute([':user_id' => $targetUserId]);
        $targetUser = $userStmt->fetch();

        if ($targetUser === false) {
            $pdo->rollBack();
            chnJsonError('USER_NOT_FOUND', 'User account not found.', 404);
        }
        if ((string) $targetUser['role'] === 'admin') {
            $pdo->rollBack();
            chnJsonError('CANNOT_BAN_ADMIN', 'Administrator accounts cannot be banned here.', 403);
        }
        if ((int) $targetUser['is_banned'] === 1) {
            $pdo->commit();
            chnJsonSuccess(['user_id' => $targetUserId, 'is_banned' => true], 'User account is already banned.', 200);
        }

        $banStmt = $pdo->prepare('UPDATE `user` SET is_banned = 1 WHERE user_id = :user_id');
        $banStmt->execute([':user_id' => $targetUserId]);
        $pdo->commit();

        chnJsonSuccess(['user_id' => $targetUserId, 'is_banned' => true], 'User account has been banned.', 200);
    }

    $emergencyId = (int) ($input['emergency_id'] ?? 0);
    if ($emergencyId <= 0) {
        chnJsonError('INVALID_INPUT', 'Valid emergency ID is required.', 422);
    }

    $staffId = null;
    $staffHospitalId = null;
    $staffHospitalName = null;
    $staffName = trim($sessionUser['name'] . ' ' . $sessionUser['surname']);

    if ($role === 'healthcare_staff') {
        $staffStmt = $pdo->prepare(
            'SELECT s.staff_id, s.hospital_id, h.hospital_name
             FROM `staff` s
             LEFT JOIN `hospital` h ON h.hospital_id = s.hospital_id
             WHERE s.user_id = :user_id
             LIMIT 1'
        );
        $staffStmt->execute([':user_id' => $sessionUser['user_id']]);
        $staffRow = $staffStmt->fetch();
        if ($staffRow === false || $staffRow['hospital_id'] === null) {
            chnJsonError('STAFF_NO_HOSPITAL', 'Your staff profile must be assigned to a hospital before managing emergencies.', 403);
        }
        $staffId = (int) $staffRow['staff_id'];
        $staffHospitalId = (int) $staffRow['hospital_id'];
        $staffHospitalName = $staffRow['hospital_name'] !== null ? (string) $staffRow['hospital_name'] : null;
    }

    $pdo->beginTransaction();

    $emStmt = $pdo->prepare(
        'SELECT emergency_id, hospital_id, status, handled_by_staff_id, handled_at
         FROM `emergency_log`
         WHERE emergency_id = :emergency_id
         LIMIT 1
         FOR UPDATE'
    );
    $emStmt->execute([':emergency_id' => $emergencyId]);
    $record = $emStmt->fetch();

    if ($record === false) {
        $pdo->rollBack();
        chnJsonError('EMERGENCY_NOT_FOUND', 'Emergency record not found.', 404);
    }

    $currentStatus = (string) $record['status'];
    $recordHospitalId = $record['hospital_id'] !== null ? (int) $record['hospital_id'] : null;
    $recordStaffId = $record['handled_by_staff_id'] !== null ? (int) $record['handled_by_staff_id'] : null;

    if ($action === 'start_handling') {
        if ($currentStatus !== 'pending' || $recordStaffId !== null || $recordHospitalId !== null) {
            $pdo->rollBack();
            chnJsonError('ALREADY_CLAIMED', 'This emergency has already been claimed or processed.', 409);
        }

        $upd = $pdo->prepare(
            "UPDATE `emergency_log`
             SET hospital_id = :hospital_id,
                 handled_by_staff_id = :staff_id,
                 handled_at = NOW(),
                 status = 'in_progress'
             WHERE emergency_id = :emergency_id AND status = 'pending' AND handled_by_staff_id IS NULL"
        );
        $upd->execute([
            ':hospital_id' => $staffHospitalId,
            ':staff_id' => $staffId,
            ':emergency_id' => $emergencyId,
        ]);

        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            chnJsonError('ALREADY_CLAIMED', 'This emergency was claimed by another staff member.', 409);
        }

        $handledAt = date('Y-m-d H:i:s');
        $pdo->commit();

        chnJsonSuccess([
            'emergency_id' => $emergencyId,
            'status' => 'in_progress',
            'hospital_id' => $staffHospitalId,
            'hospital_name' => $staffHospitalName,
            'handled_by_staff_id' => $staffId,
            'handled_by_staff_name' => $staffName,
            'handled_at' => $handledAt,
        ], 'Emergency claimed and marked in progress.', 200);
    }

    if ($action === 'mark_resolved') {
        if ($currentStatus !== 'in_progress') {
            $pdo->rollBack();
            chnJsonError('INVALID_STATE', 'Only in-progress emergencies can be marked resolved.', 409);
        }
        if ($recordHospitalId !== null && $recordHospitalId !== $staffHospitalId) {
            $pdo->rollBack();
            chnJsonError('FORBIDDEN', 'This emergency belongs to another hospital.', 403);
        }

        $upd = $pdo->prepare(
            "UPDATE `emergency_log`
             SET status = 'resolved'
             WHERE emergency_id = :emergency_id"
        );
        $upd->execute([':emergency_id' => $emergencyId]);
        $pdo->commit();

        chnJsonSuccess([
            'emergency_id' => $emergencyId,
            'status' => 'resolved',
        ], 'Emergency marked as resolved.', 200);
    }

    // action === 'flag_fraud'
    if ($currentStatus === 'fraud') {
        $pdo->commit();
        chnJsonSuccess(['emergency_id' => $emergencyId, 'status' => 'fraud'], 'Emergency is already flagged as fraud.', 200);
    }

    if ($role === 'healthcare_staff') {
        if (!in_array($currentStatus, ['pending', 'in_progress'], true)) {
            $pdo->rollBack();
            chnJsonError('INVALID_STATE', 'Only pending or in-progress emergencies can be flagged by staff.', 409);
        }
        if ($recordHospitalId !== null && $recordHospitalId !== $staffHospitalId) {
            $pdo->rollBack();
            chnJsonError('FORBIDDEN', 'This emergency belongs to another hospital.', 403);
        }

        $newHospitalId = $recordHospitalId ?? $staffHospitalId;
        $newStaffId = $recordStaffId ?? $staffId;

        $upd = $pdo->prepare(
            "UPDATE `emergency_log`
             SET status = 'fraud',
                 hospital_id = :hospital_id,
                 handled_by_staff_id = :staff_id,
                 handled_at = COALESCE(handled_at, NOW())
             WHERE emergency_id = :emergency_id"
        );
        $upd->execute([
            ':hospital_id' => $newHospitalId,
            ':staff_id' => $newStaffId,
            ':emergency_id' => $emergencyId,
        ]);

        $pdo->commit();

        chnJsonSuccess([
            'emergency_id' => $emergencyId,
            'status' => 'fraud',
            'hospital_id' => $newHospitalId,
            'hospital_name' => $staffHospitalName,
            'handled_by_staff_id' => $newStaffId,
            'handled_by_staff_name' => $recordStaffId === null ? $staffName : null,
            'handled_at' => $record['handled_at'] ?? date('Y-m-d H:i:s'),
        ], 'Emergency flagged as fraud.', 200);
    }

    // Admin flagging any record as fraud
    $upd = $pdo->prepare(
        "UPDATE `emergency_log`
         SET status = 'fraud',
             handled_at = COALESCE(handled_at, NOW())
         WHERE emergency_id = :emergency_id"
    );
    $upd->execute([':emergency_id' => $emergencyId]);
    $pdo->commit();

    chnJsonSuccess([
        'emergency_id' => $emergencyId,
        'status' => 'fraud',
    ], 'Emergency flagged as fraud.', 200);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    chnLogError('Manage emergency error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to update emergency record.', 500);
}
