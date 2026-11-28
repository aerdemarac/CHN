<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

chnStartSession();
$input = chnRequireJsonPost();

$authenticatedUserId = null;
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    $authenticatedUserId = (int) $_SESSION['user_id'];
}

$useAccountAddress = (bool) ($input['use_account_address'] ?? false);
$responsibilityAccepted = (bool) ($input['responsibility_accepted'] ?? false);
$manualAddressRaw = chnInputString($input, 'manual_address');
$emergencyTypeRaw = chnInputString($input, 'emergency_type');
$anonNameRaw = chnInputString($input, 'anon_name');
$anonPhoneRaw = chnInputString($input, 'anon_phone');
$anonDobRaw = chnInputString($input, 'anon_dob');

$locationsMatch = null;
if (array_key_exists('locations_match', $input) && $input['locations_match'] !== null) {
    if (!is_bool($input['locations_match'])) {
        chnJsonError('INVALID_INPUT', 'Location match selection must be boolean.', 422);
    }
    $locationsMatch = $input['locations_match'];
}

$gpsLocation = null;
$hasGps = false;
$gpsLat = $input['gps_latitude'] ?? null;
$gpsLng = $input['gps_longitude'] ?? null;
if ($gpsLat !== null || $gpsLng !== null) {
    if (!chnIsValidGpsCoords($gpsLat, $gpsLng)) {
        chnJsonError('INVALID_INPUT', 'Invalid GPS coordinates.', 422);
    }
    $hasGps = true;
    $gpsLocation = sprintf('%.6f, %.6f', (float) $gpsLat, (float) $gpsLng);
}

$emergencyType = null;
if ($emergencyTypeRaw !== '') {
    if (!in_array($emergencyTypeRaw, CHN_EMERGENCY_TYPES, true)) {
        chnJsonError('INVALID_INPUT', 'Invalid emergency type selection.', 422);
    }
    $emergencyType = $emergencyTypeRaw;
}

if ($manualAddressRaw !== '' && !chnIsValidAddress($manualAddressRaw)) {
    chnJsonError('INVALID_INPUT', 'Address contains unsupported characters or exceeds 255 characters.', 400);
}

$anonName = null;
$anonPhone = null;
$anonDob = null;

if ($authenticatedUserId === null) {
    if ($anonNameRaw !== '') {
        if (!chnIsValidAnonFullName($anonNameRaw)) {
            chnJsonError('INVALID_INPUT', 'Full name must use valid Latin characters (max 100 characters).', 400);
        }
        $anonName = $anonNameRaw;
    }
    if ($anonPhoneRaw !== '') {
        if (!chnIsValidPhone($anonPhoneRaw)) {
            chnJsonError('INVALID_INPUT', 'Enter a valid international phone number.', 422);
        }
        $anonPhone = chnNormalizePhone($anonPhoneRaw);
    }
    if ($anonDobRaw !== '') {
        if (!chnIsValidBirthDate($anonDobRaw)) {
            chnJsonError('INVALID_INPUT', 'Enter a valid date of birth.', 422);
        }
        $anonDob = $anonDobRaw;
    }
}

$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$ipAddress = filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false ? $remoteAddr : null;

try {
    $pdo = chnGetPdo();

    if ($authenticatedUserId !== null) {
        $authStmt = $pdo->prepare('SELECT user_id, is_banned FROM `user` WHERE user_id = :user_id LIMIT 1');
        $authStmt->execute([':user_id' => $authenticatedUserId]);
        $authRow = $authStmt->fetch();
        if ($authRow === false) {
            chnDestroySession();
            $authenticatedUserId = null;
        } elseif ((int) $authRow['is_banned'] === 1) {
            chnDestroySession();
            chnJsonError('ACCOUNT_BANNED', 'This account has been suspended by an administrator.', 403);
        }
    }

    $accountAddress = null;
    $addressLookupUserId = $authenticatedUserId;
    if ($addressLookupUserId === null && $useAccountAddress) {
        $addressLookupUserId = chnGetLastAccountUserId();
    }

    if ($addressLookupUserId !== null) {
        $addrStmt = $pdo->prepare(
            'SELECT p.full_address
             FROM `user` u
             LEFT JOIN `patient` p ON p.user_id = u.user_id
             WHERE u.user_id = :user_id AND u.is_banned = 0
             LIMIT 1'
        );
        $addrStmt->execute([':user_id' => $addressLookupUserId]);
        $addrRow = $addrStmt->fetch();
        if ($addrRow !== false && is_string($addrRow['full_address']) && trim($addrRow['full_address']) !== '') {
            $accountAddress = trim($addrRow['full_address']);
        }
    }

    $hasAccountAddress = $accountAddress !== null && ($authenticatedUserId !== null || $useAccountAddress);

    // Enforce the 4 location decision scenarios server-side.
    $finalGpsLocation = null;
    $finalManualAddress = null;

    if ($hasAccountAddress && $hasGps) {
        if ($locationsMatch === null) {
            chnJsonError('MATCH_CONFIRMATION_REQUIRED', 'Please confirm whether your GPS location matches your account address.', 422);
        }
        $finalGpsLocation = $gpsLocation;
        if ($locationsMatch === false) {
            $finalManualAddress = $manualAddressRaw !== '' ? $manualAddressRaw : null;
        }
    } elseif ($hasAccountAddress && !$hasGps) {
        if (!$responsibilityAccepted) {
            chnJsonError('RESPONSIBILITY_REQUIRED', 'You must accept responsibility for the accuracy of the saved address.', 422);
        }
        $finalManualAddress = $accountAddress;
    } elseif (!$hasAccountAddress && $hasGps) {
        $finalGpsLocation = $gpsLocation;
        $finalManualAddress = $manualAddressRaw !== '' ? $manualAddressRaw : null;
    } else {
        if ($manualAddressRaw === '') {
            chnJsonError('MANUAL_ADDRESS_REQUIRED', 'A manual address is required when no GPS or account location is available.', 422);
        }
        $finalManualAddress = $manualAddressRaw;
    }

    $pdo->beginTransaction();

    $insertStmt = $pdo->prepare(
        "INSERT INTO `emergency_log` (
            user_id, hospital_id, ip_address, emergency_date, emergency_time,
            gps_location, manual_address, emergency_type,
            anon_name, anon_phone, anon_dob,
            status, handled_by_staff_id, handled_at, created_at
        ) VALUES (
            :user_id, NULL, :ip_address, CURDATE(), CURTIME(),
            :gps_location, :manual_address, :emergency_type,
            :anon_name, :anon_phone, :anon_dob,
            'pending', NULL, NULL, NOW()
        )"
    );
    $insertStmt->execute([
        ':user_id' => $authenticatedUserId,
        ':ip_address' => $ipAddress,
        ':gps_location' => $finalGpsLocation,
        ':manual_address' => $finalManualAddress,
        ':emergency_type' => $emergencyType,
        ':anon_name' => $anonName,
        ':anon_phone' => $anonPhone,
        ':anon_dob' => $anonDob,
    ]);

    $emergencyId = (int) $pdo->lastInsertId();

    $notifStmt = $pdo->prepare(
        "INSERT INTO `notification` (user_id, message)
         SELECT u.user_id, :message
         FROM `user` u
         WHERE u.role = 'healthcare_staff' AND u.is_banned = 0"
    );
    $notifStmt->execute([
        ':message' => 'New emergency alert #' . $emergencyId . ' requires triage review.',
    ]);

    $pdo->commit();

    chnJsonSuccess([
        'emergency_id' => $emergencyId,
        'status' => 'pending',
        'gps_location' => $finalGpsLocation,
        'manual_address' => $finalManualAddress,
        'emergency_type' => $emergencyType,
    ], 'Emergency request submitted.', 201);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    chnLogError('Create emergency error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to submit emergency request.', 500);
}
