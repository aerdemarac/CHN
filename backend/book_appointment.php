<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

$sessionUser = requireRole(['patient'], true);
$input = chnRequireJsonPost();

$ALLOWED_SLOTS = [
    '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
    '13:00', '13:30', '14:00', '14:30', '15:00', '15:30',
];

$appointmentId = (int) ($input['appointment_id'] ?? 0);
$caseId = (int) ($input['case_id'] ?? 0);
$doctorId = (int) ($input['doctor_id'] ?? 0);
$dateStr = chnInputString($input, 'appointment_date');
$timeStr = chnInputString($input, 'appointment_time');

if ($caseId <= 0 || $doctorId <= 0) {
    chnJsonError('INVALID_INPUT', 'Select an open medical case and a doctor.', 422);
}

$dateObj = DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr);
$tomorrow = (new DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');
$maxDate = (new DateTimeImmutable('today'))->modify('+60 days')->format('Y-m-d');
if ($dateObj === false || $dateObj->format('Y-m-d') !== $dateStr || $dateStr < $tomorrow || $dateStr > $maxDate) {
    chnJsonError('INVALID_INPUT', 'Appointments must be booked between tomorrow and 60 days ahead.', 422);
}

if (!in_array($timeStr, $ALLOWED_SLOTS, true)) {
    chnJsonError('INVALID_INPUT', 'Select a valid appointment time slot.', 422);
}
$timeSql = $timeStr . ':00';

try {
    $pdo = chnGetPdo();
    $pdo->beginTransaction();

    $patStmt = $pdo->prepare('SELECT patient_id FROM `patient` WHERE user_id = :user_id LIMIT 1');
    $patStmt->execute([':user_id' => $sessionUser['user_id']]);
    $patRow = $patStmt->fetch();
    if ($patRow === false) {
        $pdo->rollBack();
        chnJsonError('PATIENT_NOT_FOUND', 'Patient profile not found.', 404);
    }
    $patientId = (int) $patRow['patient_id'];

    $caseStmt = $pdo->prepare(
        'SELECT case_id, patient_id, department_id, case_state
         FROM `case`
         WHERE case_id = :case_id AND patient_id = :patient_id
         LIMIT 1'
    );
    $caseStmt->execute([':case_id' => $caseId, ':patient_id' => $patientId]);
    $caseRow = $caseStmt->fetch();
    if ($caseRow === false) {
        $pdo->rollBack();
        chnJsonError('CASE_NOT_FOUND', 'Selected medical case was not found.', 404);
    }
    if ((string) $caseRow['case_state'] !== 'open') {
        $pdo->rollBack();
        chnJsonError('CASE_CLOSED', 'Appointments can only be booked for open cases.', 409);
    }
    $departmentId = (int) $caseRow['department_id'];

    $docStmt = $pdo->prepare(
        'SELECT doctor_id FROM `doctor` WHERE doctor_id = :doctor_id AND department_id = :department_id LIMIT 1'
    );
    $docStmt->execute([':doctor_id' => $doctorId, ':department_id' => $departmentId]);
    if ($docStmt->fetch() === false) {
        $pdo->rollBack();
        chnJsonError('INVALID_INPUT', 'Selected doctor does not belong to the case department.', 422);
    }

    if ($appointmentId > 0) {
        $existingStmt = $pdo->prepare(
            "SELECT appointment_id, appointment_status
             FROM `appointment`
             WHERE appointment_id = :id AND patient_id = :patient_id
             LIMIT 1
             FOR UPDATE"
        );
        $existingStmt->execute([':id' => $appointmentId, ':patient_id' => $patientId]);
        $existing = $existingStmt->fetch();
        if ($existing === false) {
            $pdo->rollBack();
            chnJsonError('APPOINTMENT_NOT_FOUND', 'Appointment not found.', 404);
        }
        if ((string) $existing['appointment_status'] !== 'confirmed') {
            $pdo->rollBack();
            chnJsonError('INVALID_STATE', 'Only confirmed appointments can be rescheduled.', 409);
        }
    }

    $slotSql = "SELECT appointment_id
                FROM `appointment`
                WHERE doctor_id = :doctor_id
                  AND appointment_date = :appointment_date
                  AND appointment_time = :appointment_time
                  AND appointment_status <> 'cancelled'";
    $slotParams = [
        ':doctor_id' => $doctorId,
        ':appointment_date' => $dateStr,
        ':appointment_time' => $timeSql,
    ];
    if ($appointmentId > 0) {
        $slotSql .= ' AND appointment_id <> :exclude_id';
        $slotParams[':exclude_id'] = $appointmentId;
    }
    $slotSql .= ' LIMIT 1 FOR UPDATE';

    $slotCheck = $pdo->prepare($slotSql);
    $slotCheck->execute($slotParams);
    if ($slotCheck->fetch() !== false) {
        $pdo->rollBack();
        chnJsonError('SLOT_TAKEN', 'This time slot is already booked for the selected doctor.', 409);
    }

    if ($appointmentId > 0) {
        $upd = $pdo->prepare(
            "UPDATE `appointment`
             SET case_id = :case_id, doctor_id = :doctor_id, department_id = :department_id,
                 appointment_date = :appointment_date, appointment_time = :appointment_time,
                 appointment_status = 'confirmed'
             WHERE appointment_id = :id AND patient_id = :patient_id"
        );
        $upd->execute([
            ':case_id' => $caseId,
            ':doctor_id' => $doctorId,
            ':department_id' => $departmentId,
            ':appointment_date' => $dateStr,
            ':appointment_time' => $timeSql,
            ':id' => $appointmentId,
            ':patient_id' => $patientId,
        ]);
        $pdo->commit();
        chnJsonSuccess(['appointment_id' => $appointmentId], 'Appointment rescheduled.', 200);
    }

    $ins = $pdo->prepare(
        "INSERT INTO `appointment` (case_id, doctor_id, patient_id, department_id, appointment_date, appointment_time, appointment_status)
         VALUES (:case_id, :doctor_id, :patient_id, :department_id, :appointment_date, :appointment_time, 'confirmed')"
    );
    $ins->execute([
        ':case_id' => $caseId,
        ':doctor_id' => $doctorId,
        ':patient_id' => $patientId,
        ':department_id' => $departmentId,
        ':appointment_date' => $dateStr,
        ':appointment_time' => $timeSql,
    ]);
    $newId = (int) $pdo->lastInsertId();
    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    chnLogError('Book appointment error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to save appointment.', 500);
}

chnJsonSuccess(['appointment_id' => $newId], 'Appointment booked.', 201);
