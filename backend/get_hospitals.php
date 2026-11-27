<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    chnJsonError('METHOD_NOT_ALLOWED', 'Only GET is allowed.', 405);
}

$sessionUser = requireRole(['patient', 'doctor', 'healthcare_staff', 'admin'], true);
$hospitalIdFilter = isset($_GET['hospital_id']) ? (int) $_GET['hospital_id'] : 0;

try {
    $pdo = chnGetPdo();
    $scope = chnGetUserHospitalScope($pdo, $sessionUser);

    if (!$scope['is_island_wide'] && $scope['hospital_id'] !== null) {
        $hospitalIdFilter = $scope['hospital_id'];
    }

    $hospSql = 'SELECT hospital_id, hospital_name, hospital_type, city, address, phone
                FROM `hospital`';
    $hospParams = [];
    if ($hospitalIdFilter > 0) {
        $hospSql .= ' WHERE hospital_id = :hospital_id';
        $hospParams[':hospital_id'] = $hospitalIdFilter;
    }
    $hospSql .= ' ORDER BY hospital_name ASC';

    $hospStmt = $pdo->prepare($hospSql);
    $hospStmt->execute($hospParams);
    $hospitals = [];
    foreach ($hospStmt->fetchAll() as $h) {
        $hid = (int) $h['hospital_id'];
        $hospitals[$hid] = [
            'hospital_id' => $hid,
            'hospital_name' => (string) $h['hospital_name'],
            'hospital_type' => (string) $h['hospital_type'],
            'city' => (string) ($h['city'] ?? ''),
            'address' => (string) ($h['address'] ?? ''),
            'phone' => (string) ($h['phone'] ?? ''),
            'departments' => [],
        ];
    }

    $deptRows = $pdo->query(
        'SELECT department_id, department_name, hospital_id
         FROM `department`
         ORDER BY department_name ASC'
    )->fetchAll();

    $departmentsById = [];
    foreach ($deptRows as $d) {
        $hid = (int) $d['hospital_id'];
        if (!isset($hospitals[$hid])) {
            continue;
        }
        $did = (int) $d['department_id'];
        $dept = [
            'department_id' => $did,
            'department_name' => (string) $d['department_name'],
            'hospital_id' => $hid,
            'doctors' => [],
        ];
        $hospitals[$hid]['departments'][] = $dept;
        $departmentsById[$did] = [$hid, count($hospitals[$hid]['departments']) - 1];
    }

    $docRows = $pdo->query(
        'SELECT d.doctor_id, d.department_id, u.name, u.surname
         FROM `doctor` d
         INNER JOIN `user` u ON u.user_id = d.user_id
         ORDER BY u.name ASC, u.surname ASC'
    )->fetchAll();

    foreach ($docRows as $doc) {
        $did = (int) $doc['department_id'];
        if (!isset($departmentsById[$did])) {
            continue;
        }
        [$hid, $idx] = $departmentsById[$did];
        $hospitals[$hid]['departments'][$idx]['doctors'][] = [
            'doctor_id' => (int) $doc['doctor_id'],
            'doctor_name' => trim((string) $doc['name'] . ' ' . (string) $doc['surname']),
        ];
    }
} catch (Throwable $e) {
    chnLogError('Get hospitals error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to load hospitals.', 500);
}

chnJsonSuccess([
    'scope' => $scope,
    'hospitals' => array_values($hospitals),
], '', 200);
