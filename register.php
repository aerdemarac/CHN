<?php
$countries = require __DIR__ . '/lib/countries.php';
$pinnedCountries = ['XN', 'TR'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Cyprus Health-Care Network</title>
    <link rel="preload" href="assets/fonts/InterVariable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/auth.css">
    <script src="js/auth.js" defer></script>
</head>
<body class="auth">

    <div class="auth__badge">
        <img src="assets/logos/chn-logo.png" alt="CHN">
    </div>

    <main class="auth__main auth__main--wide">
        <section class="auth-card" aria-labelledby="register-title">
            <header class="auth-card__header">
                <img class="auth-card__logo" src="assets/logos/chn-logo.png" alt="Cyprus Health-Care Network">
                <h1 class="auth-card__title" id="register-title">Create your account</h1>
                <p class="auth-card__subtitle">Register to book appointments and access your medical records.</p>
            </header>

            <form class="auth-form auth-form--wide" id="register-form" action="backend/register.php" method="post" novalidate>
                <div class="auth-form__grid">
                    <div class="auth-form__field auth-form__field--third">
                        <label class="auth-form__label" for="first-name">First name</label>
                        <input class="auth-form__input" type="text" id="first-name" name="first_name"
                               autocomplete="given-name" maxlength="50" required>
                        <p class="auth-form__error" id="first-name-error" role="alert"></p>
                    </div>

                    <div class="auth-form__field auth-form__field--third">
                        <label class="auth-form__label" for="last-name">Last name</label>
                        <input class="auth-form__input" type="text" id="last-name" name="last_name"
                               autocomplete="family-name" maxlength="50" required>
                        <p class="auth-form__error" id="last-name-error" role="alert"></p>
                    </div>
                </div>

                <div class="auth-form__field auth-form__field--third">
                    <label class="auth-form__label" for="birth-date">Date of birth</label>
                    <input class="auth-form__input" type="date" id="birth-date" name="birth_date"
                           autocomplete="bday" min="1900-01-01" max="<?= date('Y-m-d') ?>" required>
                    <p class="auth-form__error" id="birth-date-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="nationality">Nationality</label>
                    <select class="auth-form__input" id="nationality" name="nationality" required>
                        <option value="">Select your nationality</option>
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
                    <label class="auth-form__label" for="national-id">National ID / Passport number</label>
                    <input class="auth-form__input" type="text" id="national-id" name="national_id"
                           autocomplete="off" spellcheck="false" maxlength="32"
                           aria-describedby="national-id-hint national-id-error" required>
                    <p class="auth-form__hint" id="national-id-hint">T.C. Kimlik No, national ID or passport number.</p>
                    <p class="auth-form__error" id="national-id-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="email">Email address</label>
                    <input class="auth-form__input" type="email" id="email" name="email"
                           autocomplete="email" maxlength="254" placeholder="name@example.com" required>
                    <p class="auth-form__error" id="email-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="phone">Phone number</label>
                    <input class="auth-form__input" type="tel" id="phone" name="phone"
                           autocomplete="tel" maxlength="20" placeholder="+90 533 123 45 67" required>
                    <p class="auth-form__error" id="phone-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="password">Password</label>
                    <div class="auth-form__password">
                        <input class="auth-form__input" type="password" id="password" name="password"
                               autocomplete="new-password" placeholder="********"
                               aria-describedby="password-hint password-error" required>
                        <button class="auth-form__toggle" type="button"
                                aria-label="Show password" aria-controls="password" aria-pressed="false">
                            <span class="icon icon--eye" aria-hidden="true"></span>
                        </button>
                    </div>
                    <p class="auth-form__hint" id="password-hint">At least 8 characters with a letter and a number.</p>
                    <p class="auth-form__error" id="password-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="password-confirm">Confirm password</label>
                    <div class="auth-form__password">
                        <input class="auth-form__input" type="password" id="password-confirm" name="password_confirm"
                               autocomplete="new-password" placeholder="********" required>
                        <button class="auth-form__toggle" type="button"
                                aria-label="Show password" aria-controls="password-confirm" aria-pressed="false">
                            <span class="icon icon--eye" aria-hidden="true"></span>
                        </button>
                    </div>
                    <p class="auth-form__error" id="password-confirm-error" role="alert"></p>
                </div>

                <button class="btn btn--primary" type="submit">Create account</button>
            </form>

            <p class="auth-card__footer">
                Already have an account?
                <a class="auth-form__link" href="login.php">Log in</a>
            </p>
        </section>
    </main>

    <a class="auth__emergency" href="tel:112">
        <span class="icon icon--phone" aria-hidden="true"></span>
        Emergency
    </a>

</body>
</html>
