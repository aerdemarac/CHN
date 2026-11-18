<?php
/** Frontend only: fixture data, no account reads or persistence.
 * Integration: resolve identity/role from authenticated server state, never GET.
 * Enforce per-role field allowlists, validation and CSRF before connecting updates.
 */
$chnProjectRoot = dirname(__DIR__);
// Reuse the shared shell URL resolution before rendering asset links.
ob_start();

$accountRoles = ['patient' => 'Patient', 'doctor' => 'Doctor', 'healthcare_staff' => 'Healthcare Staff', 'admin' => 'Admin'];
$accountRole = isset($_GET['role']) && is_string($_GET['role']) && isset($accountRoles[$_GET['role']]) ? $_GET['role'] : 'patient';
$chnPageTitle = 'Account Settings';
$chnUserName = 'Alex Demo';
$chnUserRole = $accountRole; // Presentation only; never authorization.
require $chnProjectRoot . '/include/left-sidebar.php';
require $chnProjectRoot . '/include/top-bar.php';
$accountShell = ob_get_clean();
$accountUrl = $chnBaseUrl . 'pages/account-settings.php';
$account = ['name' => 'Alex', 'surname' => 'Demo', 'email' => 'alex.demo@example.invalid', 'phone' => '+905551234567', 'birth_date' => '1994-06-15', 'gender' => 'not_specified', 'nationality' => 'CY'];
// Presentation only. Future updates must enforce this rule using the authenticated role.
// Backend must enforce this same phone-only allowlist for non-admin sessions.
$accountCanManageSettings = $accountRole === 'admin';
$accountReadOnlyFields = $accountCanManageSettings ? [] : ['name', 'surname', 'email', 'birth_date', 'gender', 'nationality'];
$accountGenderOptions = ['female' => 'Female', 'male' => 'Male'];
$accountGenderSupported = isset($accountGenderOptions[$account['gender'] ?? '']);
function accountEscape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | CHN</title>
    <link rel="stylesheet" href="<?= accountEscape($chnBaseUrl . 'css/style.css') ?>">
    <link rel="stylesheet" href="<?= accountEscape($chnBaseUrl . 'css/account-settings.css') ?>">
    <script src="<?= accountEscape($chnBaseUrl . 'js/sidebar.js') ?>" defer></script>
    <script src="<?= accountEscape($chnBaseUrl . 'js/topbar.js') ?>" defer></script>
    <script src="<?= accountEscape($chnBaseUrl . 'js/account-settings.js') ?>" defer></script>
</head>
<body>
<?= $accountShell ?>
<main class="content account-settings" id="main-content" tabindex="-1">
<div class="content__inner">
    <header class="page-header"><div><h1 class="page-header__title">Account Settings</h1><p class="page-header__subtitle">Manage your personal details and communication preferences.</p></div></header>
    <?php // Test-page presentation selector only; does not modify an account role or grant permissions. ?>
    <form method="get" action="<?= accountEscape($accountUrl) ?>" class="account-settings__role-picker" aria-label="Choose account view">
        <div>
            <label class="form-label" for="account-role">Account view</label>
            <select class="form-control" id="account-role" name="role">
                <?php foreach ($accountRoles as $role => $label): ?>
                    <option value="<?= accountEscape($role) ?>" <?= $role === $accountRole ? 'selected' : '' ?>><?= accountEscape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn--outline" type="submit">View account</button>
    </form>
    <form id="account-form" method="post" action="<?= accountEscape($accountUrl . '?role=' . $accountRole) ?>" novalidate onsubmit="return false">
        <section class="card account-settings__section" aria-labelledby="personal-title">
            <header class="card__header"><div><h2 class="card__title" id="personal-title">Personal information</h2><p class="card__subtitle"><?= $accountCanManageSettings ? 'Keep your contact details clear and up to date. Required fields are marked *' : 'You can update your phone number. Your other personal details are managed by your care network.' ?></p></div></header>
            <p class="account-settings__identity">Account role: <strong><?= accountEscape($accountRoles[$accountRole]) ?></strong><span>Managed by your care network</span></p>
            <div class="account-settings__grid">
                <?php foreach (['name' => ['First name', 'text', 'given-name'], 'surname' => ['Last name', 'text', 'family-name'], 'email' => ['Email address', 'email', 'email'], 'phone' => ['Phone number', 'tel', 'tel'], 'birth_date' => ['Date of birth', 'date', 'bday'], 'nationality' => ['Nationality', 'text', 'country']] as $field => $config): ?>
                <?php $accountReadOnly = in_array($field, $accountReadOnlyFields, true); ?>
                <div class="account-settings__field"><label class="form-label" for="account-<?= $field ?>"><?= $config[0] ?><?= in_array($field, ['name', 'surname', 'email'], true) ? ' *' : '' ?></label>
                    <input class="form-control" id="account-<?= $field ?>" name="<?= $field ?>" type="<?= $config[1] ?>" autocomplete="<?= $config[2] ?>" <?= $accountReadOnly ? 'readonly aria-readonly="true"' : '' ?> value="<?= accountEscape($account[$field]) ?>" <?= in_array($field, ['name', 'surname', 'email'], true) ? 'required' : '' ?> <?= $field === 'phone' ? 'maxlength="16" inputmode="tel"' : ($field === 'nationality' ? 'maxlength="2" pattern="[A-Za-z]{2}"' : (in_array($field, ['name', 'surname', 'email'], true) ? 'maxlength="255"' : '')) ?> aria-describedby="account-<?= $field ?>-help account-<?= $field ?>-error">
                    <p class="form-hint" id="account-<?= $field ?>-help" <?= $accountReadOnly ? 'hidden' : '' ?>><?php if ($accountReadOnly): ?><?php elseif ($field === 'phone'): ?>International format: + followed by 7–15 digits.<?php elseif ($field === 'nationality'): ?>Two-letter country code, for example CY or TR.<?php elseif ($field === 'birth_date'): ?>Enter a valid date that is not in the future.<?php elseif ($field === 'email'): ?>Use an email address where you can be reached.<?php else: ?>As you would like it to appear on your account.<?php endif; ?></p>
                    <p class="form-error" id="account-<?= $field ?>-error"></p>
                </div>
                <?php endforeach; ?>
                <div class="account-settings__field">
                    <label class="form-label" for="account-gender">Gender</label>
                    <select class="form-control" id="account-gender" name="gender" <?= !$accountCanManageSettings ? 'disabled aria-disabled="true"' : '' ?> aria-describedby="account-gender-help">
                        <?php if (!$accountGenderSupported): ?><option value="" selected disabled><?= $accountCanManageSettings ? 'Choose a gender to change it' : 'Not specified' ?></option><?php endif; ?>
                        <?php foreach ($accountGenderOptions as $value => $label): ?><option value="<?= accountEscape($value) ?>" <?= $account['gender'] === $value ? 'selected' : '' ?>><?= accountEscape($label) ?></option><?php endforeach; ?>
                    </select>
                    <p class="form-hint" id="account-gender-help"><?php if (!$accountGenderSupported && $account['gender'] !== null && $account['gender'] !== ''): ?>Current recorded value: <?= accountEscape($account['gender']) ?>. <?php endif; ?><?php if ($accountCanManageSettings): ?>Leave unchanged to keep the recorded value. Select an option to update your selection.<?php endif; ?></p>
                </div>
            </div>
        </section>
        <section class="card account-settings__section" aria-labelledby="preferences-title">
            <header class="card__header"><div><h2 class="card__title" id="preferences-title">Preferences</h2><p class="card__subtitle">Choose how you would like to hear from CHN.</p></div></header>
            <div class="account-settings__grid">
                <div class="account-settings__field"><label class="form-label" for="account-language">Preferred language</label><select class="form-control" id="account-language" name="preferred_language"><?php foreach ($chnLanguages as $code => $language): ?><option value="<?= accountEscape($code) ?>" <?= $code === 'en' ? 'selected' : '' ?>><?= accountEscape($language['label']) ?></option><?php endforeach; ?></select><p class="form-hint">Your preferred language for communications.</p></div>
                <fieldset class="account-settings__notifications"><legend class="form-label">Notification preferences</legend><label><input type="checkbox" name="email_notifications" value="1" checked> Email reminders</label><p class="form-hint">Receive appointment reminders by email.</p></fieldset>
            </div>
        </section>
        <div class="card account-settings__actions">
            <div class="account-settings__buttons"><button class="btn btn--primary" id="account-save" type="button">Save Changes</button><button class="btn btn--outline" type="reset">Cancel changes</button></div>
            <p class="account-settings__result" id="account-result" role="status" aria-live="polite"></p>
            <p class="form-hint" id="account-unsaved" aria-live="polite">No unsaved changes.</p>
            <noscript><p class="form-hint">Enable JavaScript to use account settings.</p></noscript>
        </div>
    </form>
    <section class="card account-settings__section" aria-labelledby="security-title">
        <header class="card__header"><div><h2 class="card__title" id="security-title">Security</h2><p class="card__subtitle">Keep access to your account secure.</p></div></header>
        <?php if ($accountCanManageSettings): ?>
        <p>Choose a strong password to protect your account.</p>
        <button class="btn btn--outline" id="password-open" type="button">Change Password</button>
        <dialog class="account-settings__password" id="password-dialog" aria-labelledby="password-title" aria-describedby="password-help">
            <h2 class="card__title" id="password-title">Change password</h2>
            <p id="password-help">Check a new password here. Password updates cannot be sent yet.</p>
            <form id="password-form" onsubmit="return false" novalidate>
                <label class="form-label" for="password-new">New password</label>
                <input class="form-control" id="password-new" type="password" autocomplete="new-password" minlength="12" required aria-describedby="password-new-error">
                <p class="form-error" id="password-new-error"></p>
                <label class="form-label" for="password-confirm">Confirm new password</label>
                <input class="form-control" id="password-confirm" type="password" autocomplete="new-password" required aria-describedby="password-confirm-error">
                <p class="form-error" id="password-confirm-error"></p>
                <div class="account-settings__buttons"><button class="btn btn--primary" id="password-check" type="button">Check password</button><button class="btn btn--outline" id="password-cancel" type="button">Cancel</button></div>
                <p class="account-settings__result" id="password-result" role="status" aria-live="polite"></p>
            </form>
        </dialog>
        <?php else: ?>
        <p class="form-hint">Password settings are managed by your care network.</p>
        <?php endif; ?>
    </section>
</div>
</main>
</body>
</html>
