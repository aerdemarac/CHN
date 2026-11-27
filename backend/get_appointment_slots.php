<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    chnJsonError('METHOD_NOT_ALLOWED', 'Only GET is allowed.', 405);
}

requireRole(['patient'], true);

$doctorId = isset($_GET['doctor_id']) ? (int) $_GET['doctor_id'] : 0;
$date = isset($_GET['date']) && is_string($_GET['date']) ? trim($_GET['date']) : '';
$excludeId = isset($_GET['exclude_appointment_id']) ? (int) $_GET['exclude_appointment_id'] : 0;

$dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if ($doctorId <= 0 || $dt === false || $dt->format('Y-m-d') !== $date) {
    chnJsonError('INVALID_INPUT', 'Valid doctor_id and YYYY-MM-DD date are required.', 422);
}

try {
    $pdo = chnGetPdo();
    $sql = "SELECT appointment_time
            FROM `appointment`
            WHERE doctor_id = :doctor_id
              AND appointment_date = :appointment_date
              AND appointment_status <> 'cancelled'";
    $params = [
        ':doctor_id' => $doctorId,
        ':appointment_date' => $date,
    ];
    if ($excludeId > 0) {
        $sql .= ' AND appointment_id <> :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $taken = [];
    foreach ($stmt->fetchAll() as $row) {
        $taken[] = substr((string) $row['appointment_time'], 0, 5);
    }
} catch (Throwable $e) {
    chnLogError('Get appointment slots error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to load available slots.', 500);
}

chnJsonSuccess(['taken_slots' => array_values(array_unique($taken))], '', 200);
