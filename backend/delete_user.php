<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$sessionUser = requireRole(['admin'], true);
$input = chnRequireJsonPost();

$userId = (int) ($input['user_id'] ?? 0);
if ($userId <= 0) {
    chnJsonError('INVALID_INPUT', 'Valid user ID is required.', 422);
}

if ($userId === $sessionUser['user_id']) {
    chnJsonError('CANNOT_DELETE_SELF', 'You cannot delete your own account.', 400);
}

try {
    $pdo = chnGetPdo();
    $stmt = $pdo->prepare('DELETE FROM `user` WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);

    if ($stmt->rowCount() === 0) {
        chnJsonError('USER_NOT_FOUND', 'User account not found.', 404);
    }
} catch (PDOException $e) {
    if ((string) $e->getCode() === '23000') {
        chnJsonError(
            'USER_IN_USE',
            'Cannot delete this user because they have linked medical cases, appointments, feedback, or logs.',
            409
        );
    }
    chnLogError('Delete user error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to delete user account.', 500);
} catch (Throwable $e) {
    chnLogError('Delete user error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to delete user account.', 500);
}

chnJsonSuccess(['user_id' => $userId], 'User account deleted.', 200);
