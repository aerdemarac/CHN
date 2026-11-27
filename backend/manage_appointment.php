<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

$sessionUser = requireRole(['patient', 'doctor'], true);
$input = chnRequireJsonPost();

$action = chnInputString($input, 'action');
$appointmentId = (int) ($input['appointment_id'] ?? 0);
$role = $sessionUser['role'];

if ($appointmentId <= 0 || !in_array($action, ['cancel', 'complete'], true)) {
    chnJsonError('INVALID_INPUT', 'Valid appointment ID and action are required.', 422);
}

if ($action === 'complete' && $role !== 'doctor') {
    chnJsonError('FORBIDDEN', 'Only doctors can complete appointments.', 403);
}

try {
    $pdo = chnGetPdo();

    if ($role === 'patient') {
        $patStmt = $pdo->prepare('SELECT patient_id FROM `patient` WHERE user_id = :user_id LIMIT 1');
        $patStmt->execute([':user_id' => $sessionUser['user_id']]);
        $patRow = $patStmt->fetch();
        if ($patRow === false) {
            chnJsonError('PATIENT_NOT_FOUND', 'Patient profile not found.', 404);
        }
        $patientId = (int) $patRow['patient_id'];

        $stmt = $pdo->prepare(
            'SELECT appointment_id, appointment_date, appointment_time, appointment_status
             FROM `appointment`
             WHERE appointment_id = :id AND patient_id = :patient_id
             LIMIT 1'
        );
        $stmt->execute([':id' => $appointmentId, ':patient_id' => $patientId]);
        $appt = $stmt->fetch();
        if ($appt === false) {
            chnJsonError('APPOINTMENT_NOT_FOUND', 'Appointment not found.', 404);
        }
        if ((string) $appt['appointment_status'] !== 'confirmed') {
            chnJsonError('INVALID_STATE', 'Only confirmed appointments can be cancelled.', 409);
        }

        $apptTimestamp = strtotime((string) $appt['appointment_date'] . ' ' . (string) $appt['appointment_time']);
        if ($apptTimestamp === false || ($apptTimestamp - time()) < 7200) {
            chnJsonError(
                'TOO_LATE_TO_CANCEL',
                'Appointments can only be cancelled at least 2 hours before the scheduled time.',
                409
            );
        }

        // Hard delete frees the slot immediately per BACKEND_STRUCTURE_DESIGN.md.
        $del = $pdo->prepare('DELETE FROM `appointment` WHERE appointment_id = :id AND patient_id = :patient_id');
        $del->execute([':id' => $appointmentId, ':patient_id' => $patientId]);

        chnJsonSuccess(['appointment_id' => $appointmentId, 'deleted' => true], 'Appointment cancelled.', 200);
    }

    // Doctor actions
    $docStmt = $pdo->prepare('SELECT doctor_id FROM `doctor` WHERE user_id = :user_id LIMIT 1');
    $docStmt->execute([':user_id' => $sessionUser['user_id']]);
    $docRow = $docStmt->fetch();
    if ($docRow === false) {
        chnJsonError('FORBIDDEN', 'Doctor profile not found.', 403);
    }
    $doctorId = (int) $docRow['doctor_id'];

    $stmt = $pdo->prepare(
        'SELECT appointment_id, appointment_status
         FROM `appointment`
         WHERE appointment_id = :id AND doctor_id = :doctor_id
         LIMIT 1'
    );
    $stmt->execute([':id' => $appointmentId, ':doctor_id' => $doctorId]);
    $appt = $stmt->fetch();
    if ($appt === false) {
        chnJsonError('APPOINTMENT_NOT_FOUND', 'Appointment not found.', 404);
    }

    if ((string) $appt['appointment_status'] !== 'confirmed') {
        chnJsonError('INVALID_STATE', 'Only confirmed appointments can be updated.', 409);
    }

    $nextStatus = $action === 'cancel' ? 'cancelled' : 'completed';
    $upd = $pdo->prepare(
        'UPDATE `appointment` SET appointment_status = :status WHERE appointment_id = :id AND doctor_id = :doctor_id'
    );
    $upd->execute([':status' => $nextStatus, ':id' => $appointmentId, ':doctor_id' => $doctorId]);

    chnJsonSuccess(['appointment_id' => $appointmentId, 'appointment_status' => $nextStatus], 'Appointment updated.', 200);
} catch (Throwable $e) {
    chnLogError('Manage appointment error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to update appointment.', 500);
}
