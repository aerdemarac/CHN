<?php
require_once __DIR__ . '/include/i18n.php';
$countries = require __DIR__ . '/lib/countries.php';
$pinnedCountries = ['XN', 'TR'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(chnLang(), ENT_QUOTES, 'UTF-8') ?>" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('register.page_title') ?> | Cyprus Health-Care Network</title>
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

    <main class="auth__main auth__main--wide">
        <section class="auth-card" aria-labelledby="register-title">
            <header class="auth-card__header">
                <img class="auth-card__logo" src="assets/logos/chn-logo.png" alt="Cyprus Health-Care Network">
                <h1 class="auth-card__title" id="register-title"><?= te('register.title') ?></h1>
                <p class="auth-card__subtitle"><?= te('register.subtitle') ?></p>
            </header>

            <div class="auth-notice" id="register-notice" role="alert" hidden></div>

            <div class="auth-success" id="register-success" hidden>
                <h2 class="auth-success__title" id="register-success-title" tabindex="-1"><?= te('register.success_title') ?></h2>
                <p class="auth-success__text"><?= te('register.success_text') ?></p>
                <a class="btn btn--primary" href="login.php"><?= te('register.log_in') ?></a>
            </div>

            <form class="auth-form auth-form--wide" id="register-form" action="backend/register.php" method="post" novalidate
                  data-messages="<?= chnJsMessages([
                      'server_error' => 'auth.server_error',
                      'network_error' => 'auth.network_error',
                      'first_name_required' => 'register.first_name_required',
                      'first_name_invalid' => 'register.first_name_invalid',
                      'last_name_required' => 'register.last_name_required',
                      'last_name_invalid' => 'register.last_name_invalid',
                      'birth_date_required' => 'register.birth_date_required',
                      'birth_date_invalid' => 'register.birth_date_invalid',
                      'nationality_required' => 'register.nationality_required',
                      'national_id_required' => 'register.national_id_required',
                      'national_id_invalid' => 'register.national_id_invalid',
                      'tc_invalid' => 'register.tc_invalid',
                      'email_required' => 'auth.email_required',
                      'email_invalid' => 'auth.email_invalid',
                      'phone_required' => 'register.phone_required',
                      'phone_invalid' => 'register.phone_invalid',
                      'password_required' => 'register.password_required',
                      'password_weak' => 'register.password_weak',
                      'password_confirm_required' => 'register.password_confirm_required',
                      'password_mismatch' => 'register.password_mismatch',
                  ]) ?>">
                <div class="auth-form__grid">
                    <div class="auth-form__field auth-form__field--third">
                        <label class="auth-form__label" for="first-name"><?= te('register.first_name') ?></label>
                        <input class="auth-form__input" type="text" id="first-name" name="first_name"
                               autocomplete="given-name" maxlength="50" required>
                        <p class="auth-form__error" id="first-name-error" role="alert"></p>
                    </div>

                    <div class="auth-form__field auth-form__field--third">
                        <label class="auth-form__label" for="last-name"><?= te('register.last_name') ?></label>
                        <input class="auth-form__input" type="text" id="last-name" name="last_name"
                               autocomplete="family-name" maxlength="50" required>
                        <p class="auth-form__error" id="last-name-error" role="alert"></p>
                    </div>
                </div>

                <div class="auth-form__field auth-form__field--third">
                    <label class="auth-form__label" for="birth-date"><?= te('register.birth_date') ?></label>
                    <input class="auth-form__input" type="date" id="birth-date" name="birth_date"
                           autocomplete="bday" min="1900-01-01" max="<?= date('Y-m-d') ?>" required>
                    <p class="auth-form__error" id="birth-date-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="nationality"><?= te('register.nationality') ?></label>
                    <select class="auth-form__input" id="nationality" name="nationality" required>
                        <option value=""><?= te('register.nationality_placeholder') ?></option>
                        <?php foreach ($pinnedCountries as $code): ?>
                            <option value="<?= $code ?>"><?= htmlspecialchars($countries[$code], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                        <?php foreach ($countries as $code => $name): ?>
                            <?php if (in_array($code, $pinnedCountries, true)) continue; ?>
                            <option value="<?= $code ?>"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="auth-form__error" id="nationality-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="national-id"><?= te('register.national_id') ?></label>
                    <input class="auth-form__input" type="text" id="national-id" name="national_id"
                           autocomplete="off" spellcheck="false" maxlength="50"
                           aria-describedby="national-id-hint national-id-error" required>
                    <p class="auth-form__hint" id="national-id-hint"><?= te('register.national_id_hint') ?></p>
                    <p class="auth-form__error" id="national-id-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="email"><?= te('auth.email') ?></label>
                    <input class="auth-form__input" type="email" id="email" name="email"
                           autocomplete="email" maxlength="254" placeholder="name@example.com" required>
                    <p class="auth-form__error" id="email-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="phone"><?= te('register.phone') ?></label>
                    <input class="auth-form__input" type="tel" id="phone" name="phone"
                           autocomplete="tel" maxlength="16" placeholder="+905331234567" required>
                    <p class="auth-form__error" id="phone-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="password"><?= te('auth.password') ?></label>
                    <div class="auth-form__password">
                        <input class="auth-form__input" type="password" id="password" name="password"
                               autocomplete="new-password" placeholder="********"
                               aria-describedby="password-hint password-error" required>
                        <button class="auth-form__toggle" type="button"
                                aria-label="<?= te('auth.show_password') ?>" aria-controls="password" aria-pressed="false"
                                data-label-show="<?= te('auth.show_password') ?>" data-label-hide="<?= te('auth.hide_password') ?>">
                            <span class="icon icon--eye" aria-hidden="true"></span>
                        </button>
                    </div>
                    <p class="auth-form__hint" id="password-hint"><?= te('register.password_hint') ?></p>
                    <p class="auth-form__error" id="password-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="password-confirm"><?= te('register.password_confirm') ?></label>
                    <div class="auth-form__password">
                        <input class="auth-form__input" type="password" id="password-confirm" name="password_confirm"
                               autocomplete="new-password" placeholder="********" required>
                        <button class="auth-form__toggle" type="button"
                                aria-label="<?= te('auth.show_password') ?>" aria-controls="password-confirm" aria-pressed="false"
                                data-label-show="<?= te('auth.show_password') ?>" data-label-hide="<?= te('auth.hide_password') ?>">
                            <span class="icon icon--eye" aria-hidden="true"></span>
                        </button>
                    </div>
                    <p class="auth-form__error" id="password-confirm-error" role="alert"></p>
                </div>

                <button class="btn btn--primary" type="submit"><?= te('register.submit') ?></button>
            </form>

            <p class="auth-card__footer" id="register-footer">
                <?= te('register.has_account') ?>
                <a class="auth-form__link" href="login.php"><?= te('register.log_in') ?></a>
            </p>
        </section>
    </main>

    <a class="auth__emergency" href="<?= htmlspecialchars('pages/emergency-call.php?' . http_build_query(['lang' => chnLang()]), ENT_QUOTES, 'UTF-8') ?>">
        <span class="icon icon--phone" aria-hidden="true"></span>
        <?= te('common.emergency') ?>
    </a>

</body>
</html>
