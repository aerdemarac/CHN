<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/auth.php';

chnStartSession();

if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    header('Location: ' . chnDashboardUrlForRole((string) $_SESSION['role']));
    exit;
}

header('Location: login.php');
exit;
