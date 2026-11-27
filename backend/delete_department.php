<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireRole(['admin'], true);
$input = chnRequireJsonPost();

$departmentId = (int) ($input['department_id'] ?? 0);
if ($departmentId <= 0) {
    chnJsonError('INVALID_INPUT', 'Valid department ID is required.', 422);
}

try {
    $pdo = chnGetPdo();
    $stmt = $pdo->prepare('DELETE FROM `department` WHERE department_id = :department_id');
    $stmt->execute([':department_id' => $departmentId]);
    if ($stmt->rowCount() === 0) {
        chnJsonError('DEPARTMENT_NOT_FOUND', 'Department not found.', 404);
    }
} catch (PDOException $e) {
    if ((string) $e->getCode() === '23000') {
        chnJsonError(
            'DEPARTMENT_IN_USE',
            'Cannot delete department because doctors, staff, cases, or appointments are assigned to it.',
            409
        );
    }
    chnLogError('Delete department error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to delete department.', 500);
} catch (Throwable $e) {
    chnLogError('Delete department error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to delete department.', 500);
}

chnJsonSuccess(['department_id' => $departmentId], 'Department deleted.', 200);
