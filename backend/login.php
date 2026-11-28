<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/validation.php';

chnStartSession();
$input = chnRequireJsonPost();

$email = strtolower(chnInputString($input, 'email'));
$password = is_string($input['password'] ?? null) ? $input['password'] : '';

if (!chnIsValidEmail($email) || $password === '') {
    chnJsonError('INVALID_INPUT', 'Email and password are required.', 422);
}

loadChnEnv();
$maxAttempts = (int) (getenv('LOGIN_MAX_ATTEMPTS') ?: 5);
$lockoutSeconds = (int) (getenv('LOGIN_LOCKOUT_TIME') ?: 900);

try {
    $pdo = chnGetPdo();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT user_id, email, password, role, name, surname, verify, is_banned, failed_attempts, locked_until
         FROM `user`
         WHERE email = :email
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user === false) {
        $pdo->commit();
        chnJsonError('INVALID_CREDENTIALS', 'Incorrect email or password.', 401);
    }

    if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
        $pdo->commit();
        chnJsonError('ACCOUNT_LOCKED', 'Too many failed login attempts. Please try again in 15 minutes.', 429);
    }

    if (!password_verify($password, (string) $user['password'])) {
        $attempts = (int) $user['failed_attempts'] + 1;
        $isNowLocked = $attempts >= $maxAttempts;
        $lockedUntil = $isNowLocked
            ? date('Y-m-d H:i:s', time() + $lockoutSeconds)
            : null;

        $failStmt = $pdo->prepare(
            'UPDATE `user`
             SET failed_attempts = :failed_attempts, locked_until = :locked_until
             WHERE user_id = :user_id'
        );
        $failStmt->execute([
            ':failed_attempts' => $attempts,
            ':locked_until' => $lockedUntil,
            ':user_id' => (int) $user['user_id'],
        ]);

        $pdo->commit();
        if ($isNowLocked) {
            chnJsonError('ACCOUNT_LOCKED', 'Too many failed login attempts. Please try again in 15 minutes.', 429);
        }
        chnJsonError('INVALID_CREDENTIALS', 'Incorrect email or password.', 401);
    }

    if ((int) $user['is_banned'] === 1) {
        $pdo->commit();
        chnJsonError('ACCOUNT_BANNED', 'This account has been suspended by an administrator.', 403);
    }

    if ((int) $user['verify'] !== 1) {
        $pdo->commit();
        chnJsonError('ACCOUNT_NOT_VERIFIED', 'This account is not verified.', 403);
    }

    $resetStmt = $pdo->prepare(
        'UPDATE `user` SET failed_attempts = 0, locked_until = NULL WHERE user_id = :user_id'
    );
    $resetStmt->execute([':user_id' => (int) $user['user_id']]);

    $pdo->commit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    chnLogError('Login error: ' . $e->getMessage());
    chnJsonError('SERVER_ERROR', 'Unable to process login.', 500);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['user_id'];
$_SESSION['role'] = (string) $user['role'];
$_SESSION['name'] = (string) $user['name'];
$_SESSION['surname'] = (string) $user['surname'];
$_SESSION['last_activity'] = time();
chnSetLastAccountCookie((int) $user['user_id']);

chnJsonSuccess(
    ['redirect' => chnDashboardUrlForRole((string) $user['role'])],
    'Logged in.',
    200
);
