<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

$sessionUser = requireRole(['patient', 'doctor', 'healthcare_staff', 'admin'], true);
$input = chnRequireJsonPost();

$newPassword = is_string($input['new_password'] ?? null) ? $input['new_password'] : '';
$passwordConfirm = is_string($input['password_confirm'] ?? null) ? $input['password_confirm'] : '';

if (mb_strlen($newPassword, 'UTF-8') < 12 || !chnIsValidPassword($newPassword)) {
    chnJsonError(
        'INVALID_PASSWORD',
        'Password must be at least 12 characters and include both a letter and a number.',
        422
    );
}

if ($newPassword !== $passwordConfirm) {
    chnJsonError('PASSWORD_MISMATCH', 'Passwords must match.', 422);
}

$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    $pdo = chnGetPdo();
    $stmt = $pdo->prepare('UPDATE `user` SET password = :password WHERE user_id = :user_id');
    $stmt->execute([
        ':password' => $passwordHash,
        ':user_id' => $sessionUser['user_id'],
    ]);
} catch (Throwable $e) {
    chnLogError('Password change error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to update password.', 500);
}

session_regenerate_id(true);

chnJsonSuccess([], 'Password updated.', 200);
