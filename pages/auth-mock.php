<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function authMockRespond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function authMockError(int $status, string $code, string $message): void
{
    authMockRespond($status, ['ok' => false, 'error' => $code, 'message' => $message]);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    authMockError(405, 'METHOD_NOT_ALLOWED', 'Only POST is allowed.');
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    authMockError(400, 'INVALID_JSON', 'The request body must be a JSON object.');
}

$field = static function (string $name) use ($input): string {
    $value = $input[$name] ?? '';
    return is_string($value) ? trim($value) : '';
};

$action = $_GET['action'] ?? '';

if ($action === 'login') {
    $email = strtolower($field('email'));
    if ($email === '' || $field('password') === '') {
        authMockError(422, 'INVALID_INPUT', 'Email and password are required.');
    }
    if ($email === 'unverified@example.com') {
        authMockError(403, 'ACCOUNT_NOT_VERIFIED', 'This account is not verified.');
    }
    if ($email === 'verified@example.com') {
        authMockRespond(200, [
            'ok' => true,
            'data' => ['redirect' => 'pages/dashboard-test.php?role=patient'],
            'message' => 'Logged in.',
        ]);
    }
    authMockError(401, 'INVALID_CREDENTIALS', 'Incorrect email or password.');
}

if ($action === 'register') {
    $required = ['first_name', 'last_name', 'birth_date', 'nationality', 'national_id', 'email', 'phone', 'password', 'password_confirm'];
    foreach ($required as $name) {
        if ($field($name) === '') {
            authMockError(422, 'INVALID_INPUT', 'All fields are required.');
        }
    }
    authMockRespond(201, ['ok' => true, 'data' => new stdClass(), 'message' => 'Registration successful.']);
}

authMockError(404, 'UNKNOWN_ACTION', 'Unknown action.');
