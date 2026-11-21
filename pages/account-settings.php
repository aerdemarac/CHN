<?php
require_once dirname(__DIR__) . '/include/i18n.php';
/** Frontend only: fixture data, no account reads or persistence.
 * Integration: resolve identity/role from authenticated server state, never GET.
 * Enforce per-role field allowlists, validation and CSRF before connecting updates.
 */
$chnProjectRoot = dirname(__DIR__);
// Reuse the shared shell URL resolution before rendering asset links.
ob_start();

$accountRoles = ['patient' => t('role.patient'), 'doctor' => t('role.doctor'), 'healthcare_staff' => t('role.healthcare_staff'), 'admin' => t('role.admin')];
$accountRole = isset($_GET['role']) && is_string($_GET['role']) && isset($accountRoles[$_GET['role']]) ? $_GET['role'] : 'patient';
$chnPageTitle = t('account.page_title');
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
$accountGenderOptions = ['female' => t('account.gender_female'), 'male' => t('account.gender_male')];
$accountGenderSupported = isset($accountGenderOptions[$account['gender'] ?? '']);
$accountGenderValueLabels = $accountGenderOptions + ['not_specified' => t('account.gender_not_specified')];
function accountEscape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="<?= htmlspecialchars(chnLang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('account.page_title') ?> | CHN</title>
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
    <header class="page-header"><div><h1 class="page-header__title"><?= te('account.page_title') ?></h1><p class="page-header__subtitle"><?= te('account.subtitle') ?></p></div></header>
    <?php // Test-page presentation selector only; does not modify an account role or grant permissions. ?>
    <form method="get" action="<?= accountEscape($accountUrl) ?>" class="account-settings__role-picker" aria-label="<?= te('account.view_picker') ?>">
        <div>
            <label class="form-label" for="account-role"><?= te('account.view_label') ?></label>
            <select class="form-control" id="account-role" name="role">
                <?php foreach ($accountRoles as $role => $label): ?>
                    <option value="<?= accountEscape($role) ?>" <?= $role === $accountRole ? 'selected' : '' ?>><?= accountEscape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn--outline" type="submit"><?= te('account.view_button') ?></button>
    </form>
    <form id="account-form" method="post" action="<?= accountEscape($accountUrl . '?role=' . $accountRole) ?>" novalidate onsubmit="return false" data-messages="<?= chnJsMessages(['unsaved' => 'account.unsaved', 'noUnsaved' => 'account.no_unsaved', 'required' => 'account.field_required', 'maxChars' => 'account.max_chars', 'email' => 'account.email_invalid', 'phone' => 'account.phone_invalid', 'nationality' => 'account.nationality_invalid', 'birthDate' => 'account.birth_date_hint', 'review' => 'account.review_fields', 'validated' => 'account.validated', 'cancelled' => 'account.cancelled', 'passwordShort' => 'account.password_short', 'passwordMismatch' => 'account.password_mismatch', 'passwordPassed' => 'account.password_passed']) ?>">
        <section class="card account-settings__section" aria-labelledby="personal-title">
            <header class="card__header"><div><h2 class="card__title" id="personal-title"><?= te('account.personal_title') ?></h2><p class="card__subtitle"><?= $accountCanManageSettings ? te('account.personal_subtitle_admin') : te('account.personal_subtitle_user') ?></p></div></header>
            <p class="account-settings__identity"><?= strtr(te('account.role_line'), ['{role}' => '<strong>' . accountEscape($accountRoles[$accountRole]) . '</strong>']) ?><span><?= te('account.managed') ?></span></p>
            <div class="account-settings__grid">
                <?php foreach (['name' => [t('register.first_name'), 'text', 'given-name'], 'surname' => [t('register.last_name'), 'text', 'family-name'], 'email' => [t('auth.email'), 'email', 'email'], 'phone' => [t('register.phone'), 'tel', 'tel'], 'birth_date' => [t('register.birth_date'), 'date', 'bday'], 'nationality' => [t('register.nationality'), 'text', 'country']] as $field => $config): ?>
                <?php $accountReadOnly = in_array($field, $accountReadOnlyFields, true); ?>
                <div class="account-settings__field"><label class="form-label" for="account-<?= $field ?>"><?= accountEscape($config[0]) ?><?= in_array($field, ['name', 'surname', 'email'], true) ? ' *' : '' ?></label>
                    <input class="form-control" id="account-<?= $field ?>" name="<?= $field ?>" type="<?= $config[1] ?>" autocomplete="<?= $config[2] ?>" <?= $accountReadOnly ? 'readonly aria-readonly="true"' : '' ?> value="<?= accountEscape($account[$field]) ?>" <?= in_array($field, ['name', 'surname', 'email'], true) ? 'required' : '' ?> <?= $field === 'phone' ? 'maxlength="16" inputmode="tel"' : ($field === 'nationality' ? 'maxlength="2" pattern="[A-Za-z]{2}"' : (in_array($field, ['name', 'surname', 'email'], true) ? 'maxlength="255"' : '')) ?> aria-describedby="account-<?= $field ?>-help account-<?= $field ?>-error">
                    <p class="form-hint" id="account-<?= $field ?>-help" <?= $accountReadOnly ? 'hidden' : '' ?>><?php if ($accountReadOnly): ?><?php elseif ($field === 'phone'): ?><?= te('account.phone_hint') ?><?php elseif ($field === 'nationality'): ?><?= te('account.nationality_hint') ?><?php elseif ($field === 'birth_date'): ?><?= te('account.birth_date_hint') ?><?php elseif ($field === 'email'): ?><?= te('account.email_hint') ?><?php else: ?><?= te('account.name_hint') ?><?php endif; ?></p>
                    <p class="form-error" id="account-<?= $field ?>-error"></p>
                </div>
                <?php endforeach; ?>
                <div class="account-settings__field">
                    <label class="form-label" for="account-gender"><?= te('account.gender') ?></label>
                    <select class="form-control" id="account-gender" name="gender" <?= !$accountCanManageSettings ? 'disabled aria-disabled="true"' : '' ?> aria-describedby="account-gender-help">
                        <?php if (!$accountGenderSupported): ?><option value="" selected disabled><?= $accountCanManageSettings ? te('account.gender_choose') : te('account.gender_not_specified') ?></option><?php endif; ?>
                        <?php foreach ($accountGenderOptions as $value => $label): ?><option value="<?= accountEscape($value) ?>" <?= $account['gender'] === $value ? 'selected' : '' ?>><?= accountEscape($label) ?></option><?php endforeach; ?>
                    </select>
                    <p class="form-hint" id="account-gender-help"><?php if (!$accountGenderSupported && $account['gender'] !== null && $account['gender'] !== ''): ?><?= te('account.gender_current', ['value' => $accountGenderValueLabels[$account['gender']] ?? $account['gender']]) ?> <?php endif; ?><?php if ($accountCanManageSettings): ?><?= te('account.gender_keep') ?><?php endif; ?></p>
                </div>
            </div>
        </section>
        <section class="card account-settings__section" aria-labelledby="preferences-title">
            <header class="card__header"><div><h2 class="card__title" id="preferences-title"><?= te('account.preferences_title') ?></h2><p class="card__subtitle"><?= te('account.preferences_subtitle') ?></p></div></header>
            <div class="account-settings__grid">
                <div class="account-settings__field"><label class="form-label" for="account-language"><?= te('account.language') ?></label><select class="form-control" id="account-language" name="preferred_language"><?php foreach ($chnLanguages as $code => $language): ?><option value="<?= accountEscape($code) ?>" <?= $code === chnLang() ? 'selected' : '' ?>><?= accountEscape($language['label']) ?></option><?php endforeach; ?></select><p class="form-hint"><?= te('account.language_hint') ?></p></div>
                <fieldset class="account-settings__notifications"><legend class="form-label"><?= te('account.notifications') ?></legend><label><input type="checkbox" name="email_notifications" value="1" checked> <?= te('account.email_reminders') ?></label><p class="form-hint"><?= te('account.email_reminders_hint') ?></p></fieldset>
            </div>
        </section>
        <div class="card account-settings__actions">
            <div class="account-settings__buttons"><button class="btn btn--primary" id="account-save" type="button"><?= te('account.save') ?></button><button class="btn btn--outline" type="reset"><?= te('account.cancel_changes') ?></button></div>
            <p class="account-settings__result" id="account-result" role="status" aria-live="polite"></p>
            <p class="form-hint" id="account-unsaved" aria-live="polite"><?= te('account.no_unsaved') ?></p>
            <noscript><p class="form-hint"><?= te('account.noscript') ?></p></noscript>
        </div>
    </form>
    <section class="card account-settings__section" aria-labelledby="security-title">
        <header class="card__header"><div><h2 class="card__title" id="security-title"><?= te('account.security_title') ?></h2><p class="card__subtitle"><?= te('account.security_subtitle') ?></p></div></header>
        <?php if ($accountCanManageSettings): ?>
        <p><?= te('account.password_intro') ?></p>
        <button class="btn btn--outline" id="password-open" type="button"><?= te('account.change_password_button') ?></button>
        <dialog class="account-settings__password" id="password-dialog" aria-labelledby="password-title" aria-describedby="password-help">
            <h2 class="card__title" id="password-title"><?= te('account.change_password') ?></h2>
            <p id="password-help"><?= te('account.password_help') ?></p>
            <form id="password-form" onsubmit="return false" novalidate>
                <label class="form-label" for="password-new"><?= te('account.password_new') ?></label>
                <input class="form-control" id="password-new" type="password" autocomplete="new-password" minlength="12" required aria-describedby="password-new-error">
                <p class="form-error" id="password-new-error"></p>
                <label class="form-label" for="password-confirm"><?= te('account.password_confirm') ?></label>
                <input class="form-control" id="password-confirm" type="password" autocomplete="new-password" required aria-describedby="password-confirm-error">
                <p class="form-error" id="password-confirm-error"></p>
                <div class="account-settings__buttons"><button class="btn btn--primary" id="password-check" type="button"><?= te('account.password_check') ?></button><button class="btn btn--outline" id="password-cancel" type="button"><?= te('account.cancel') ?></button></div>
                <p class="account-settings__result" id="password-result" role="status" aria-live="polite"></p>
            </form>
        </dialog>
        <?php else: ?>
        <p class="form-hint"><?= te('account.password_managed') ?></p>
        <?php endif; ?>
    </section>
</div>
</main>
</body>
</html>
