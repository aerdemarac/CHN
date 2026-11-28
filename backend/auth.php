<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/db.php';

/**
 * Escape untrusted values for safe HTML output.
 */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Configure and start the native PHP session with 30-minute inactivity timeout.
 */
function chnStartSession(): void
{
    loadChnEnv();

    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    $lifetime = (int) (getenv('SESSION_LIFETIME') ?: 1800);
    if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $lifetime) {
        chnDestroySession();
        session_start();
        return;
    }

    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
        $langCookie = $_COOKIE['chn_lang'] ?? '';
        if (is_string($langCookie) && in_array($langCookie, ['en', 'tr', 'ru'], true)) {
            $_SESSION['lang'] = $langCookie;
        }
    }
}

/**
 * Clear and destroy the active PHP session and its cookie.
 */
function chnDestroySession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        return;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?: 'Lax',
            ]
        );
    }

    session_destroy();
}

/**
 * Map an authenticated user role to its dashboard URL.
 */
function chnDashboardUrlForRole(string $role): string
{
    $allowed = ['patient', 'doctor', 'healthcare_staff', 'admin'];
    $safeRole = in_array($role, $allowed, true) ? $role : 'patient';
    return 'pages/dashboard-test.php?' . http_build_query(['role' => $safeRole]);
}

/**
 * Emit a standardized JSON success response and exit.
 */
function chnJsonSuccess(array|object $data = [], string $message = '', int $status = 200): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    http_response_code($status);
    echo json_encode([
        'ok' => true,
        'data' => $data === [] ? new stdClass() : $data,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Emit a standardized JSON error response and exit.
 */
function chnJsonError(string $errorCode, string $message, int $status = 400): never
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'error' => $errorCode,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Enforce POST method and decode a JSON request body.
 */
function chnRequireJsonPost(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        chnJsonError('METHOD_NOT_ALLOWED', 'Only POST is allowed.', 405);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        chnJsonError('INVALID_JSON', 'The request body must be a JSON object.', 400);
    }

    return $input;
}

/**
 * Enforce authentication and role-based authorization for API or HTML requests.
 */
function requireRole(array $allowedRoles, bool $isApi = true): array
{
    chnStartSession();

    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        if ($isApi) {
            chnJsonError('UNAUTHORIZED', 'Authentication is required.', 401);
        }
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $baseUrl = str_ends_with($scriptDir, '/pages') ? '../' : '';
        header('Location: ' . $baseUrl . 'login.php');
        exit;
    }

    $role = (string) $_SESSION['role'];
    if (!in_array($role, $allowedRoles, true)) {
        if ($isApi) {
            chnJsonError('FORBIDDEN', 'You do not have permission to perform this action.', 403);
        }
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $baseUrl = str_ends_with($scriptDir, '/pages') ? '../' : '';
        header('Location: ' . $baseUrl . chnDashboardUrlForRole($role));
        exit;
    }

    try {
        $pdo = chnGetPdo();
        $banStmt = $pdo->prepare('SELECT is_banned FROM `user` WHERE user_id = :user_id LIMIT 1');
        $banStmt->execute([':user_id' => (int) $_SESSION['user_id']]);
        $banRow = $banStmt->fetch();
        if ($banRow === false || (int) $banRow['is_banned'] === 1) {
            chnDestroySession();
            if ($isApi) {
                $code = $banRow === false ? 'UNAUTHORIZED' : 'ACCOUNT_BANNED';
                $msg = $banRow === false ? 'Authentication is required.' : 'This account has been suspended by an administrator.';
                chnJsonError($code, $msg, $banRow === false ? 401 : 403);
            }
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
            $baseUrl = str_ends_with($scriptDir, '/pages') ? '../' : '';
            header('Location: ' . $baseUrl . 'login.php');
            exit;
        }
    } catch (Throwable $e) {
        chnLogError('Session verification error: ' . $e->getMessage());
    }

    return [
        'user_id' => (int) $_SESSION['user_id'],
        'role' => $role,
        'name' => (string) ($_SESSION['name'] ?? ''),
        'surname' => (string) ($_SESSION['surname'] ?? ''),
    ];
}

/**
 * Derive an application signing key for HMAC-protected device cookies.
 */
function chnSigningKey(): string
{
    loadChnEnv();
    $configured = getenv('APP_SECRET');
    if (is_string($configured) && $configured !== '') {
        return $configured;
    }

    return hash(
        'sha256',
        __DIR__ . '|' . (string) getenv('DB_NAME') . '|' . (string) getenv('DB_PASS') . '|' . (string) getenv('SMTP_PASSWORD')
    );
}

/**
 * Persist an HMAC-signed HttpOnly cookie remembering the last authenticated user_id on this device.
 */
function chnSetLastAccountCookie(int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $expires = time() + 60 * 60 * 24 * 30;
    $payload = $userId . '.' . $expires;
    $signature = hash_hmac('sha256', $payload, chnSigningKey());
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    setcookie('chn_last_acct', $payload . '.' . $signature, [
        'expires' => $expires,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Verify and return the last authenticated user_id from the HMAC-signed device cookie, or null.
 */
function chnGetLastAccountUserId(): ?int
{
    $raw = $_COOKIE['chn_last_acct'] ?? null;
    if (!is_string($raw) || preg_match('/^([1-9]\d*)\.(\d+)\.([a-f0-9]{64})$/', $raw, $m) !== 1) {
        return null;
    }

    $userId = (int) $m[1];
    $expires = (int) $m[2];
    $sig = $m[3];
    if ($expires < time()) {
        return null;
    }

    $expected = hash_hmac('sha256', $userId . '.' . $expires, chnSigningKey());
    if (!hash_equals($expected, $sig)) {
        return null;
    }

    return $userId;
}

/**
 * Clear the last active account cookie on this device.
 */
function chnClearLastAccountCookie(): void
{
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('chn_last_acct', '', [
        'expires' => time() - 42000,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Resolve the authenticated user's island-wide vs hospital/department scope.
 */
function chnGetUserHospitalScope(PDO $pdo, array $sessionUser): array
{
    $role = (string) ($sessionUser['role'] ?? '');
    $userId = (int) ($sessionUser['user_id'] ?? 0);

    if ($role === 'admin' || $role === 'patient') {
        return [
            'is_island_wide' => true,
            'hospital_id' => null,
            'department_id' => null,
            'hospital_name' => null,
            'department_name' => null,
        ];
    }

    if ($role === 'doctor') {
        $stmt = $pdo->prepare(
            'SELECT dep.hospital_id, d.department_id, h.hospital_name, dep.department_name
             FROM `doctor` d
             INNER JOIN `department` dep ON dep.department_id = d.department_id
             INNER JOIN `hospital` h ON h.hospital_id = dep.hospital_id
             WHERE d.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return [
                'is_island_wide' => false,
                'hospital_id' => (int) $row['hospital_id'],
                'department_id' => (int) $row['department_id'],
                'hospital_name' => (string) $row['hospital_name'],
                'department_name' => (string) $row['department_name'],
            ];
        }
    }

    if ($role === 'healthcare_staff') {
        $stmt = $pdo->prepare(
            'SELECT s.hospital_id, s.department_id, h.hospital_name, dep.department_name
             FROM `staff` s
             LEFT JOIN `hospital` h ON h.hospital_id = s.hospital_id
             LEFT JOIN `department` dep ON dep.department_id = s.department_id
             WHERE s.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();
        if ($row !== false) {
            return [
                'is_island_wide' => $row['hospital_id'] === null,
                'hospital_id' => $row['hospital_id'] !== null ? (int) $row['hospital_id'] : null,
                'department_id' => $row['department_id'] !== null ? (int) $row['department_id'] : null,
                'hospital_name' => $row['hospital_name'] !== null ? (string) $row['hospital_name'] : null,
                'department_name' => $row['department_name'] !== null ? (string) $row['department_name'] : null,
            ];
        }
    }

    return [
        'is_island_wide' => false,
        'hospital_id' => null,
        'department_id' => null,
        'hospital_name' => null,
        'department_name' => null,
    ];
}
