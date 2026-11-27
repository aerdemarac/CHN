<?php
declare(strict_types=1);

$chnProjectRoot = dirname(__DIR__);
require_once $chnProjectRoot . '/backend/auth.php';
require_once $chnProjectRoot . '/backend/db.php';
require_once $chnProjectRoot . '/include/i18n.php';

$sessionUser = requireRole(['patient', 'doctor', 'healthcare_staff', 'admin'], false);
$accountRole = $sessionUser['role'];

try {
    $pdo = chnGetPdo();
    $stmt = $pdo->prepare(
        'SELECT u.user_id, u.email, u.role, u.name, u.surname, u.gender,
                u.birth_date, u.nationality, u.phone, p.full_address
         FROM `user` u
         LEFT JOIN `patient` p ON p.user_id = u.user_id
         WHERE u.user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([':user_id' => $sessionUser['user_id']]);
    $dbUser = $stmt->fetch();
} catch (Throwable $e) {
    chnLogError('Account settings load error: ' . $e->getMessage());
    $dbUser = false;
}

if ($dbUser === false) {
    chnDestroySession();
    header('Location: ../login.php');
    exit;
}

$accountRoles = [
    'patient' => t('role.patient'),
    'doctor' => t('role.doctor'),
    'healthcare_staff' => t('role.healthcare_staff'),
    'admin' => t('role.admin'),
];

$account = [
    'name' => (string) ($dbUser['name'] ?? ''),
    'surname' => (string) ($dbUser['surname'] ?? ''),
    'email' => (string) ($dbUser['email'] ?? ''),
    'phone' => (string) ($dbUser['phone'] ?? ''),
    'birth_date' => (string) ($dbUser['birth_date'] ?? ''),
    'nationality' => (string) ($dbUser['nationality'] ?? ''),
    'gender' => $dbUser['gender'] !== null && $dbUser['gender'] !== '' ? (string) $dbUser['gender'] : 'not_specified',
    'full_address' => (string) ($dbUser['full_address'] ?? ''),
];

$accountCanManageSettings = $accountRole === 'admin';
$accountIsPatient = $accountRole === 'patient';
$accountReadOnlyFields = $accountCanManageSettings ? [] : ['name', 'surname', 'email', 'birth_date', 'nationality'];
$accountGenderOptions = ['female' => t('account.gender_female'), 'male' => t('account.gender_male')];
$accountGenderSupported = isset($accountGenderOptions[$account['gender']]);
$accountGenderValueLabels = $accountGenderOptions + ['not_specified' => t('account.gender_not_specified')];

function accountEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

ob_start();
$chnPageTitle = t('account.page_title');
$chnUserName = trim($account['name'] . ' ' . $account['surname']);
$chnUserRole = $accountRole;
require $chnProjectRoot . '/include/left-sidebar.php';
require $chnProjectRoot . '/include/top-bar.php';
$accountShell = ob_get_clean();

$updateEndpoint = $chnBaseUrl . 'backend/update_account_settings.php';
$passwordEndpoint = $chnBaseUrl . 'backend/change_password.php';
?>
<!doctype html>
<html lang="<?= accountEscape(chnLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('account.page_title') ?> | CHN</title>
    <link rel="stylesheet" href="<?= accountEscape($chnBaseUrl . 'css/style.css?v=' . filemtime($chnProjectRoot . '/css/style.css')) ?>">
    <link rel="stylesheet" href="<?= accountEscape($chnBaseUrl . 'css/account-settings.css?v=' . filemtime($chnProjectRoot . '/css/account-settings.css')) ?>">
    <script src="<?= accountEscape($chnBaseUrl . 'js/sidebar.js?v=' . filemtime($chnProjectRoot . '/js/sidebar.js')) ?>" defer></script>
    <script src="<?= accountEscape($chnBaseUrl . 'js/topbar.js?v=' . filemtime($chnProjectRoot . '/js/topbar.js')) ?>" defer></script>
    <script src="<?= accountEscape($chnBaseUrl . 'js/account-settings.js?v=' . filemtime($chnProjectRoot . '/js/account-settings.js')) ?>" defer></script>
</head>
<body>
<?= $accountShell ?>
<main class="content account-settings" id="main-content" tabindex="-1">
<div class="content__inner">
    <header class="page-header">
        <div>
            <h1 class="page-header__title"><?= te('account.page_title') ?></h1>
            <p class="page-header__subtitle"><?= te('account.subtitle') ?></p>
        </div>
    </header>

    <form id="account-form" method="post" action="<?= accountEscape($updateEndpoint) ?>"
          data-update-url="<?= accountEscape($updateEndpoint) ?>"
          novalidate onsubmit="return false"
          data-messages="<?= chnJsMessages([
              'unsaved' => 'account.unsaved',
              'noUnsaved' => 'account.no_unsaved',
              'required' => 'account.field_required',
              'maxChars' => 'account.max_chars',
              'email' => 'account.email_invalid',
              'emailExists' => 'account.email_exists',
              'phone' => 'account.phone_invalid',
              'nationality' => 'account.nationality_invalid',
              'birthDate' => 'account.birth_date_hint',
              'addressInvalid' => 'account.address_invalid',
              'invalidChars' => 'account.invalid_chars',
              'review' => 'account.review_fields',
              'validated' => 'account.validated',
              'saveError' => 'account.save_error',
              'cancelled' => 'account.cancelled',
              'passwordShort' => 'account.password_short',
              'passwordMismatch' => 'account.password_mismatch',
              'passwordPassed' => 'account.password_passed',
              'passwordError' => 'account.password_error',
          ]) ?>">
        <section class="card account-settings__section" aria-labelledby="personal-title">
            <header class="card__header">
                <div>
                    <h2 class="card__title" id="personal-title"><?= te('account.personal_title') ?></h2>
                    <p class="card__subtitle"><?= $accountCanManageSettings ? te('account.personal_subtitle_admin') : te('account.personal_subtitle_user') ?></p>
                </div>
            </header>
            <p class="account-settings__identity">
                <?= strtr(te('account.role_line'), ['{role}' => '<strong>' . accountEscape($accountRoles[$accountRole] ?? $accountRole) . '</strong>']) ?>
                <span><?= te('account.managed') ?></span>
            </p>
            <div class="account-settings__grid">
                <?php
                $personalFields = [
                    'name' => [t('register.first_name'), 'text', 'given-name'],
                    'surname' => [t('register.last_name'), 'text', 'family-name'],
                    'email' => [t('auth.email'), 'email', 'email'],
                    'phone' => [t('register.phone'), 'tel', 'tel'],
                    'birth_date' => [t('register.birth_date'), 'date', 'bday'],
                    'nationality' => [t('register.nationality'), 'text', 'country'],
                ];
                foreach ($personalFields as $field => $config):
                    $accountReadOnly = in_array($field, $accountReadOnlyFields, true);
                    $isRequired = $accountCanManageSettings && in_array($field, ['name', 'surname', 'email'], true);
                ?>
                <div class="account-settings__field">
                    <label class="form-label" for="account-<?= $field ?>"><?= accountEscape($config[0]) ?><?= $isRequired ? ' *' : '' ?></label>
                    <input class="form-control" id="account-<?= $field ?>" name="<?= $field ?>" type="<?= $config[1] ?>"
                           autocomplete="<?= $config[2] ?>"
                           <?= $accountReadOnly ? 'readonly aria-readonly="true"' : '' ?>
                           value="<?= accountEscape($account[$field]) ?>"
                           <?= $isRequired ? 'required' : '' ?>
                           <?= $field === 'phone' ? 'maxlength="16" inputmode="tel"' : ($field === 'nationality' ? 'maxlength="2" pattern="[A-Za-z]{2}"' : (in_array($field, ['name', 'surname', 'email'], true) ? 'maxlength="255"' : '')) ?>
                           aria-describedby="account-<?= $field ?>-help account-<?= $field ?>-error">
                    <p class="form-hint" id="account-<?= $field ?>-help" <?= $accountReadOnly ? 'hidden' : '' ?>><?php if ($accountReadOnly): ?><?php elseif ($field === 'phone'): ?><?= te('account.phone_hint') ?><?php elseif ($field === 'nationality'): ?><?= te('account.nationality_hint') ?><?php elseif ($field === 'birth_date'): ?><?= te('account.birth_date_hint') ?><?php elseif ($field === 'email'): ?><?= te('account.email_hint') ?><?php else: ?><?= te('account.name_hint') ?><?php endif; ?></p>
                    <p class="form-error" id="account-<?= $field ?>-error"></p>
                </div>
                <?php endforeach; ?>

                <div class="account-settings__field">
                    <label class="form-label" for="account-gender"><?= te('account.gender') ?></label>
                    <select class="form-control" id="account-gender" name="gender" <?= !$accountCanManageSettings ? 'disabled aria-disabled="true"' : '' ?> aria-describedby="account-gender-help">
                        <?php if (!$accountGenderSupported): ?>
                            <option value="" selected disabled><?= $accountCanManageSettings ? te('account.gender_choose') : te('account.gender_not_specified') ?></option>
                        <?php endif; ?>
                        <?php foreach ($accountGenderOptions as $value => $label): ?>
                            <option value="<?= accountEscape($value) ?>" <?= $account['gender'] === $value ? 'selected' : '' ?>><?= accountEscape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint" id="account-gender-help"><?php if (!$accountGenderSupported && $account['gender'] !== ''): ?><?= te('account.gender_current', ['value' => $accountGenderValueLabels[$account['gender']] ?? $account['gender']]) ?> <?php endif; ?><?php if ($accountCanManageSettings): ?><?= te('account.gender_keep') ?><?php endif; ?></p>
                </div>

                <?php if ($accountIsPatient): ?>
                <div class="account-settings__field">
                    <label class="form-label" for="account-full_address"><?= te('account.address') ?></label>
                    <input class="form-control" id="account-full_address" name="full_address" type="text"
                           autocomplete="street-address" maxlength="255"
                           value="<?= accountEscape($account['full_address']) ?>"
                           aria-describedby="account-full_address-help account-full_address-error">
                    <p class="form-hint" id="account-full_address-help"><?= te('account.address_hint') ?></p>
                    <p class="form-error" id="account-full_address-error"></p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card account-settings__section" aria-labelledby="preferences-title">
            <header class="card__header">
                <div>
                    <h2 class="card__title" id="preferences-title"><?= te('account.preferences_title') ?></h2>
                    <p class="card__subtitle"><?= te('account.preferences_subtitle') ?></p>
                </div>
            </header>
            <div class="account-settings__grid">
                <div class="account-settings__field">
                    <label class="form-label" for="account-language"><?= te('account.language') ?></label>
                    <select class="form-control" id="account-language" name="preferred_language">
                        <?php foreach ($chnLanguages as $code => $language): ?>
                            <option value="<?= accountEscape($code) ?>" <?= $code === chnLang() ? 'selected' : '' ?>><?= accountEscape($language['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-hint"><?= te('account.language_hint') ?></p>
                </div>
            </div>
        </section>

        <div class="card account-settings__actions">
            <div class="account-settings__buttons">
                <button class="btn btn--primary" id="account-save" type="button"><?= te('account.save') ?></button>
                <button class="btn btn--outline" type="reset"><?= te('account.cancel_changes') ?></button>
            </div>
            <p class="account-settings__result" id="account-result" role="status" aria-live="polite"></p>
            <p class="form-hint" id="account-unsaved" aria-live="polite"><?= te('account.no_unsaved') ?></p>
            <noscript><p class="form-hint"><?= te('account.noscript') ?></p></noscript>
        </div>
    </form>

    <section class="card account-settings__section" aria-labelledby="security-title">
        <header class="card__header">
            <div>
                <h2 class="card__title" id="security-title"><?= te('account.security_title') ?></h2>
                <p class="card__subtitle"><?= te('account.security_subtitle') ?></p>
            </div>
        </header>
        <p><?= te('account.password_intro') ?></p>
        <button class="btn btn--outline" id="password-open" type="button"><?= te('account.change_password_button') ?></button>
        <dialog class="account-settings__password" id="password-dialog" aria-labelledby="password-title" aria-describedby="password-help">
            <h2 class="card__title" id="password-title"><?= te('account.change_password') ?></h2>
            <p id="password-help"><?= te('account.password_help') ?></p>
            <form id="password-form" method="post" action="<?= accountEscape($passwordEndpoint) ?>"
                  data-password-url="<?= accountEscape($passwordEndpoint) ?>"
                  onsubmit="return false" novalidate>
                <label class="form-label" for="password-new"><?= te('account.password_new') ?></label>
                <input class="form-control" id="password-new" name="new_password" type="password" autocomplete="new-password" minlength="12" maxlength="255" required aria-describedby="password-new-error">
                <p class="form-error" id="password-new-error"></p>
                <label class="form-label" for="password-confirm"><?= te('account.password_confirm') ?></label>
                <input class="form-control" id="password-confirm" name="password_confirm" type="password" autocomplete="new-password" maxlength="255" required aria-describedby="password-confirm-error">
                <p class="form-error" id="password-confirm-error"></p>
                <div class="account-settings__buttons">
                    <button class="btn btn--primary" id="password-check" type="button"><?= te('account.password_check') ?></button>
                    <button class="btn btn--outline" id="password-cancel" type="button"><?= te('account.cancel') ?></button>
                </div>
                <p class="account-settings__result" id="password-result" role="status" aria-live="polite"></p>
            </form>
        </dialog>
    </section>
</div>
</main>
</body>
</html>
