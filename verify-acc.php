<?php
declare(strict_types=1);

require_once __DIR__ . '/include/i18n.php';
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/auth.php';

$token = trim((string) ($_GET['token'] ?? ''));
$verified = false;
$errorMessage = 'This verification link is invalid or has already been used.';

if (preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
    try {
        $pdo = chnGetPdo();
        $stmt = $pdo->prepare('SELECT user_id, verify FROM `user` WHERE verify_token = :token LIMIT 1');
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();

        if ($user !== false) {
            $updateStmt = $pdo->prepare(
                'UPDATE `user` SET verify = 1, verify_token = NULL WHERE user_id = :user_id'
            );
            $updateStmt->execute([':user_id' => (int) $user['user_id']]);
            $verified = true;
        }
    } catch (Throwable $e) {
        chnLogError('Verify account error: ' . $e->getMessage());
        $errorMessage = 'A server error occurred while verifying your account. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(chnLang()) ?>" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification | Cyprus Health-Care Network</title>
    <link rel="preload" href="assets/fonts/InterVariable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/auth.css?v=<?= filemtime(__DIR__ . '/css/auth.css') ?>">
</head>
<body class="auth">

    <div class="auth__corner">
        <div class="auth__badge">
            <img src="assets/logos/chn-logo.png" alt="CHN">
        </div>
        <?php require __DIR__ . '/include/auth-language.php'; ?>
    </div>

    <main class="auth__main">
        <section class="auth-card" aria-labelledby="verify-title">
            <header class="auth-card__header">
                <img class="auth-card__logo" src="assets/logos/chn-logo.png" alt="Cyprus Health-Care Network">
                <h1 class="auth-card__title" id="verify-title">
                    <?= $verified ? 'Account Verified' : 'Verification Failed' ?>
                </h1>
            </header>

            <?php if ($verified): ?>
                <div class="auth-success">
                    <p class="auth-success__text">Your email has been verified. You can now log in to your account.</p>
                    <a class="btn btn--primary" href="login.php"><?= te('register.log_in') ?></a>
                </div>
            <?php else: ?>
                <div class="auth-notice auth-notice--danger" role="alert">
                    <?= e($errorMessage) ?>
                </div>
                <p class="auth-card__footer">
                    <a class="auth-form__link" href="login.php"><?= te('register.log_in') ?></a>
                </p>
            <?php endif; ?>
        </section>
    </main>

</body>
</html>
