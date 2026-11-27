<?php
declare(strict_types=1);

$chnProjectRoot = dirname(__DIR__);
require_once $chnProjectRoot . '/backend/auth.php';
require_once $chnProjectRoot . '/backend/db.php';
require_once $chnProjectRoot . '/include/i18n.php';
require_once $chnProjectRoot . '/include/departments.php';

$sessionUser = requireRole(['doctor', 'healthcare_staff'], false);
$patientsRole = $sessionUser['role'];

$scope = [
    'hospital_name' => null,
    'department_name' => null,
];
try {
    $pdo = chnGetPdo();
    $scope = chnGetUserHospitalScope($pdo, $sessionUser);
} catch (Throwable $e) {
    chnLogError('Patients page scope load error: ' . $e->getMessage());
}

$patientsScopes = [
    'doctor' => [
        'subtitle'  => t('patients.search_subtitle_doctor'),
        'no_result' => t('patients.no_result_doctor'),
        'cases'     => t('patients.cases_doctor'),
    ],
    'healthcare_staff' => [
        'subtitle'  => t('patients.search_subtitle_healthcare_staff'),
        'no_result' => t('patients.no_result_healthcare_staff'),
        'cases'     => t('patients.cases_healthcare_staff'),
    ],
];
$patientsScope = $patientsScopes[$patientsRole];

$patientsSearchTypes = [
    'name' => [
        'label'     => t('patients.type_name'),
        'field'     => t('patients.field_name'),
        'type'      => 'text',
        'inputmode' => 'text',
        'hint'      => t('patients.hint_name'),
        'empty'     => t('patients.empty_name'),
        'invalid'   => t('patients.invalid_name'),
    ],
    'phone' => [
        'label'     => t('register.phone'),
        'field'     => t('patients.field_phone'),
        'type'      => 'tel',
        'inputmode' => 'tel',
        'hint'      => t('patients.hint_phone'),
        'empty'     => t('patients.empty_phone'),
        'invalid'   => t('patients.invalid_phone'),
    ],
    'email' => [
        'label'     => t('auth.email'),
        'field'     => t('patients.field_email'),
        'type'      => 'email',
        'inputmode' => 'email',
        'hint'      => t('patients.hint_email'),
        'empty'     => t('patients.empty_email'),
        'invalid'   => t('auth.email_invalid'),
    ],
    'national_id' => [
        'label'     => t('patients.type_national_id'),
        'field'     => t('patients.field_national_id'),
        'type'      => 'text',
        'inputmode' => 'text',
        'hint'      => t('patients.hint_national_id'),
        'empty'     => t('patients.empty_national_id'),
        'invalid'   => t('register.national_id_invalid'),
    ],
];

$patientsSearchBy = 'name';
$patientsSearchType = $patientsSearchTypes[$patientsSearchBy];

function patientsEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$scopeParts = array_filter([
    $scope['hospital_name'] ?? null,
    !empty($scope['department_name']) ? chnDepartmentLabel((string) $scope['department_name']) : null,
]);
$scopeContextText = implode(' · ', $scopeParts);

$chnPageTitle = t('nav.patients');
$chnUserName = trim($sessionUser['name'] . ' ' . $sessionUser['surname']);
$chnUserRole = $patientsRole;
?>
<!DOCTYPE html>
<html lang="<?= patientsEscape(chnLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | <?= te('nav.patients') ?></title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime($chnProjectRoot . '/css/style.css') ?>">
    <link rel="stylesheet" href="../css/patients.css?v=<?= filemtime($chnProjectRoot . '/css/patients.css') ?>">
    <script src="../js/sidebar.js?v=<?= filemtime($chnProjectRoot . '/js/sidebar.js') ?>" defer></script>
    <script src="../js/topbar.js?v=<?= filemtime($chnProjectRoot . '/js/topbar.js') ?>" defer></script>
    <script src="../js/patients.js?v=<?= filemtime($chnProjectRoot . '/js/patients.js') ?>" defer></script>
</head>
<body>
<?php
require $chnProjectRoot . '/include/left-sidebar.php';
require $chnProjectRoot . '/include/top-bar.php';
?>

<main class="content" id="main-content" tabindex="-1">
    <div class="content__inner patients">
        <div class="page-header">
            <div>
                <h1 class="page-header__title"><?= te('nav.patients') ?></h1>
                <p class="page-header__subtitle">
                    <?= te('patients.subtitle') ?>
                    <?php if ($scopeContextText !== ''): ?>
                        · <strong><?= patientsEscape($scopeContextText) ?></strong>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <section class="card" aria-labelledby="patients-search-title">
            <header class="card__header">
                <div>
                    <h2 class="card__title" id="patients-search-title"><?= te('patients.search_title') ?></h2>
                    <p class="card__subtitle"><?= patientsEscape($patientsScope['subtitle']) ?></p>
                </div>
            </header>

<?php
$foundMap = chnLookup('patients.found');
$ageMap = chnLookup('patients.age_born');
$patientsJsMessages = json_encode([
    'no_result_title' => t('patients.no_result_title'),
    'found_one'       => is_array($foundMap) ? ($foundMap['one'] ?? '{count} patient found') : '{count} patient found',
    'found_other'     => is_array($foundMap) ? ($foundMap['other'] ?? $foundMap['one'] ?? '{count} patients found') : '{count} patients found',
    'age_one'         => is_array($ageMap) ? ($ageMap['one'] ?? '{count} year old · born {date}') : '{count} year old · born {date}',
    'age_other'       => is_array($ageMap) ? ($ageMap['other'] ?? $ageMap['one'] ?? '{count} years old · born {date}') : '{count} years old · born {date}',
    'birth_missing'   => t('patients.birth_not_provided'),
    'phone'           => t('patients.phone'),
    'email'           => t('patients.email'),
    'address'         => t('patients.address'),
    'not_provided'    => t('patients.not_provided'),
    'state_open'      => t('case_state.open'),
    'state_closed'    => t('case_state.closed'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
            <form class="patients-form" id="patients-form" method="post" novalidate
                  data-search-url="../backend/search_patients.php"
                  data-role="<?= patientsEscape($patientsRole) ?>"
                  data-messages="<?= patientsEscape((string) $patientsJsMessages) ?>"
                  data-no-result-tpl="<?= patientsEscape($patientsScope['no_result']) ?>"
                  data-cases-label="<?= patientsEscape($patientsScope['cases']) ?>">
                <fieldset class="patients-form__types">
                    <legend class="form-label"><?= te('patients.search_by') ?></legend>
                    <div class="patients-form__options">
                        <?php foreach ($patientsSearchTypes as $patientsTypeKey => $patientsType): ?>
                            <label class="patients-form__option">
                                <input class="patients-form__radio" type="radio" name="search_by"
                                       value="<?= patientsEscape($patientsTypeKey) ?>"
                                       data-label="<?= patientsEscape($patientsType['label']) ?>"
                                       data-field="<?= patientsEscape($patientsType['field']) ?>"
                                       data-type="<?= patientsEscape($patientsType['type']) ?>"
                                       data-inputmode="<?= patientsEscape($patientsType['inputmode']) ?>"
                                       data-hint="<?= patientsEscape($patientsType['hint']) ?>"
                                       data-empty="<?= patientsEscape($patientsType['empty']) ?>"
                                       data-invalid="<?= patientsEscape($patientsType['invalid']) ?>"
                                       <?= $patientsTypeKey === $patientsSearchBy ? 'checked' : '' ?>>
                                <span class="patients-form__option-label"><?= patientsEscape($patientsType['label']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="form-group">
                    <label class="form-label" for="patients-q" id="patients-q-label"><?= patientsEscape($patientsSearchType['label']) ?></label>
                    <div class="patients-form__control">
                        <div class="patients-form__field">
                            <input class="form-control" id="patients-q" name="q" maxlength="254" required
                                   type="<?= patientsEscape($patientsSearchType['type']) ?>"
                                   inputmode="<?= patientsEscape($patientsSearchType['inputmode']) ?>"
                                   autocomplete="off" autocapitalize="none" spellcheck="false"
                                   aria-describedby="patients-q-hint patients-q-error">
                            <p class="form-hint" id="patients-q-hint"><?= patientsEscape($patientsSearchType['hint']) ?></p>
                            <p class="form-error" id="patients-q-error" role="alert" hidden></p>
                        </div>
                        <button type="submit" class="btn btn--primary" id="patients-submit">
                            <span class="icon icon--search" aria-hidden="true"></span>
                            <?= te('patients.search') ?>
                        </button>
                        <button type="button" class="btn btn--outline" id="patients-clear"><?= te('patients.clear') ?></button>
                    </div>
                </div>
            </form>
        </section>

        <section class="card patients-results" id="patients-results" aria-labelledby="patients-results-title" hidden></section>
    </div>
</main>
</body>
</html>
