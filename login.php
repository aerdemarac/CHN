<?php
require_once __DIR__ . '/include/i18n.php';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(chnLang(), ENT_QUOTES, 'UTF-8') ?>" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('login.page_title') ?> | Cyprus Health-Care Network</title>
    <link rel="preload" href="assets/fonts/InterVariable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/auth.css?v=<?= filemtime(__DIR__ . '/css/auth.css') ?>">
    <script src="js/auth.js?v=<?= filemtime(__DIR__ . '/js/auth.js') ?>" defer></script>
</head>
<body class="auth">

    <div class="auth__corner">
        <div class="auth__badge">
            <img src="assets/logos/chn-logo.png" alt="CHN">
        </div>
        <?php require __DIR__ . '/include/auth-language.php'; ?>
    </div>

    <main class="auth__main">
        <section class="auth-card" aria-labelledby="login-title">
            <header class="auth-card__header">
                <img class="auth-card__logo" src="assets/logos/chn-logo.png" alt="Cyprus Health-Care Network">
                <h1 class="auth-card__title" id="login-title"><?= te('login.title') ?></h1>
                <p class="auth-card__subtitle"><?= te('login.subtitle') ?></p>
            </header>

            <form class="auth-form" id="login-form" action="backend/login.php" method="post" novalidate
                  data-messages="<?= chnJsMessages([
                      'email_required' => 'auth.email_required',
                      'email_invalid' => 'auth.email_invalid',
                      'password_required' => 'login.password_required',
                  ]) ?>">
                <div class="auth-form__field">
                    <label class="auth-form__label" for="email"><?= te('auth.email') ?></label>
                    <input class="auth-form__input" type="email" id="email" name="email"
                           autocomplete="email" placeholder="name@example.com" required>
                    <p class="auth-form__error" id="email-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="password"><?= te('auth.password') ?></label>
                    <div class="auth-form__password">
                        <input class="auth-form__input" type="password" id="password" name="password"
                               autocomplete="current-password" placeholder="********" required>
                        <button class="auth-form__toggle" type="button" id="password-toggle"
                                aria-label="<?= te('auth.show_password') ?>" aria-controls="password" aria-pressed="false"
                                data-label-show="<?= te('auth.show_password') ?>" data-label-hide="<?= te('auth.hide_password') ?>">
                            <span class="icon icon--eye" aria-hidden="true"></span>
                        </button>
                    </div>
                    <p class="auth-form__error" id="password-error" role="alert"></p>
                </div>

                <div class="auth-form__row">
                    <a class="auth-form__link" href="#"><?= te('login.forgot') ?></a>
                </div>

                <button class="btn btn--primary" type="submit"><?= te('login.submit') ?></button>
            </form>

            <p class="auth-card__footer">
                <?= te('login.no_account') ?>
                <a class="auth-form__link" href="register.php"><?= te('login.register') ?></a>
            </p>
        </section>
    </main>

    <a class="auth__emergency" href="tel:112">
        <span class="icon icon--phone" aria-hidden="true"></span>
        <?= te('common.emergency') ?>
    </a>

</body>
</html>
