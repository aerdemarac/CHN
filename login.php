<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Cyprus Health-Care Network</title>
    <link rel="preload" href="assets/fonts/InterVariable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/auth.css">
    <script src="js/auth.js" defer></script>
</head>
<body class="auth">

    <div class="auth__badge">
        <img src="assets/logos/chn-logo.png" alt="CHN">
    </div>

    <main class="auth__main">
        <section class="auth-card" aria-labelledby="login-title">
            <header class="auth-card__header">
                <img class="auth-card__logo" src="assets/logos/chn-logo.png" alt="Cyprus Health-Care Network">
                <h1 class="auth-card__title" id="login-title">Welcome back</h1>
                <p class="auth-card__subtitle">Appointments, test results and medical records in one place.</p>
            </header>

            <form class="auth-form" id="login-form" action="backend/login.php" method="post" novalidate>
                <div class="auth-form__field">
                    <label class="auth-form__label" for="email">Email address</label>
                    <input class="auth-form__input" type="email" id="email" name="email"
                           autocomplete="email" placeholder="name@example.com" required>
                    <p class="auth-form__error" id="email-error" role="alert"></p>
                </div>

                <div class="auth-form__field">
                    <label class="auth-form__label" for="password">Password</label>
                    <div class="auth-form__password">
                        <input class="auth-form__input" type="password" id="password" name="password"
                               autocomplete="current-password" placeholder="********" required>
                        <button class="auth-form__toggle" type="button" id="password-toggle"
                                aria-label="Show password" aria-controls="password" aria-pressed="false">
                            <span class="icon icon--eye" aria-hidden="true"></span>
                        </button>
                    </div>
                    <p class="auth-form__error" id="password-error" role="alert"></p>
                </div>

                <div class="auth-form__row">
                    <a class="auth-form__link" href="#">Forgot password?</a>
                </div>

                <button class="btn btn--primary" type="submit">Log in</button>
            </form>

            <p class="auth-card__footer">
                Don't have an account?
                <a class="auth-form__link" href="register.php">Register</a>
            </p>
        </section>
    </main>

    <a class="auth__emergency" href="tel:112">
        <span class="icon icon--phone" aria-hidden="true"></span>
        Emergency
    </a>

</body>
</html>
