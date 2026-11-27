<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

requireRole(['admin'], true);
$input = chnRequireJsonPost();

$hospitalId = (int) ($input['hospital_id'] ?? 0);
$hospitalName = chnInputString($input, 'hospital_name');
$city = chnInputString($input, 'city');
$address = chnInputString($input, 'address');
$phoneRaw = chnInputString($input, 'phone');

if ($hospitalId <= 0) {
    chnJsonError('INVALID_INPUT', 'Valid hospital ID is required.', 422);
}

$textPattern = '/^[' . CHN_ALLOWED_LETTERS . '0-9\s,.\-\/:#\'()]+$/u';
if ($hospitalName === '' || mb_strlen($hospitalName, 'UTF-8') > 200 || preg_match($textPattern, $hospitalName) !== 1) {
    chnJsonError('INVALID_INPUT', 'Enter a valid public hospital name using Latin characters.', 400);
}
if ($city === '' || mb_strlen($city, 'UTF-8') > 100 || preg_match($textPattern, $city) !== 1) {
    chnJsonError('INVALID_INPUT', 'Enter a valid city name.', 400);
}
if ($address !== '' && (mb_strlen($address, 'UTF-8') > 255 || preg_match($textPattern, $address) !== 1)) {
    chnJsonError('INVALID_INPUT', 'Address contains unsupported characters or exceeds 255 characters.', 400);
}

$phone = null;
if ($phoneRaw !== '') {
    if (!chnIsValidPhone($phoneRaw)) {
        chnJsonError('INVALID_INPUT', 'Enter a valid phone number.', 422);
    }
    $phone = chnNormalizePhone($phoneRaw);
}

try {
    $pdo = chnGetPdo();
    $exists = $pdo->prepare('SELECT hospital_id FROM `hospital` WHERE hospital_id = :id LIMIT 1');
    $exists->execute([':id' => $hospitalId]);
    if ($exists->fetch() === false) {
        chnJsonError('HOSPITAL_NOT_FOUND', 'Hospital not found.', 404);
    }

    $dup = $pdo->prepare('SELECT hospital_id FROM `hospital` WHERE hospital_name = :name AND hospital_id <> :id LIMIT 1');
    $dup->execute([':name' => $hospitalName, ':id' => $hospitalId]);
    if ($dup->fetch() !== false) {
        chnJsonError('HOSPITAL_EXISTS', 'A hospital with this name already exists.', 409);
    }

    $stmt = $pdo->prepare(
        'UPDATE `hospital`
         SET hospital_name = :hospital_name, city = :city, address = :address, phone = :phone
         WHERE hospital_id = :hospital_id'
    );
    $stmt->execute([
        ':hospital_name' => $hospitalName,
        ':city' => $city,
        ':address' => $address !== '' ? $address : null,
        ':phone' => $phone,
        ':hospital_id' => $hospitalId,
    ]);
} catch (Throwable $e) {
    chnLogError('Update hospital error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to update hospital.', 500);
}

chnJsonSuccess(['hospital_id' => $hospitalId], 'Hospital updated.', 200);
