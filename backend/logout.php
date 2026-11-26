<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

chnStartSession();
chnRequireJsonPost();

chnDestroySession();

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/backend/logout.php', 2));
$baseUrl = '/' . trim($scriptDir, '/') . '/';
$baseUrl = preg_replace('~/+~', '/', $baseUrl) ?: '/';

chnJsonSuccess(
    ['redirect' => $baseUrl . 'login.php'],
    'Logged out.',
    200
);
