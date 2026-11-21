<?php
$patientsRoles = [
    'patient'          => 'Patient',
    'doctor'           => 'Doctor',
    'healthcare_staff' => 'Healthcare Staff',
    'admin'            => 'Admin',
];

$patientsRole = isset($_GET['role']) && is_string($_GET['role']) && isset($patientsRoles[$_GET['role']])
    ? $_GET['role']
    : 'patient';

$patientsScopes = [
    'doctor'           => 'your department',
    'healthcare_staff' => 'your hospital',
];

$patientsHasAccess = isset($patientsScopes[$patientsRole]);
$patientsScope = $patientsScopes[$patientsRole] ?? '';

$patientsToday = new DateTimeImmutable('today');

$patientsSearchTypes = [
    'phone' => [
        'label'     => 'Phone number',
        'type'      => 'tel',
        'inputmode' => 'tel',
        'hint'      => 'Enter the full number, with or without the country code. Example: +90 533 000 01 01',
        'empty'     => 'Please enter a phone number.',
        'invalid'   => 'Please enter a valid phone number (7 to 15 digits).',
    ],
    'email' => [
        'label'     => 'Email address',
        'type'      => 'email',
        'inputmode' => 'email',
        'hint'      => 'Enter the complete email address. Example: name@example.com',
        'empty'     => 'Please enter an email address.',
        'invalid'   => 'Please enter a valid email address.',
    ],
    'national_id' => [
        'label'     => 'National ID',
        'type'      => 'text',
        'inputmode' => 'text',
        'hint'      => 'Enter the complete national ID or passport number.',
        'empty'     => 'Please enter a national ID or passport number.',
        'invalid'   => 'Please enter a valid national ID or passport number.',
    ],
    'name' => [
        'label'     => 'Name',
        'type'      => 'text',
        'inputmode' => 'text',
        'hint'      => 'Enter the patient\'s first name, last name or both.',
        'empty'     => 'Please enter a name.',
        'invalid'   => 'Please enter a valid name (letters only, 2 to 100 characters).',
    ],
];

$patientsCaseStates = [
    'open'   => ['label' => 'Open',   'badge' => 'success'],
    'closed' => ['label' => 'Closed', 'badge' => 'neutral'],
];

function patientsEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function patientsQuery(array $params): string
{
    return '?' . http_build_query($params);
}

function patientsInitials(string $name, string $surname): string
{
    return mb_strtoupper(mb_substr($name, 0, 1) . mb_substr($surname, 0, 1));
}

function patientsNationalId(string $value): string
{
    return strtoupper(preg_replace('/[\s-]+/', '', $value));
}

function patientsSearchValue(string $type, string $value): ?string
{
    if ($type === 'phone') {
        $digits = preg_replace('/\D+/', '', $value);

        return preg_match('/^\+?[0-9\s()-]+$/', $value) === 1 && strlen($digits) >= 7 && strlen($digits) <= 15
            ? $digits
            : null;
    }

    if ($type === 'email') {
        return strlen($value) <= 254 && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
            ? mb_strtolower($value)
            : null;
    }

    if ($type === 'name') {
        $name = preg_replace('/\s+/u', ' ', $value);

        return is_string($name) && preg_match('/^\p{L}[\p{L}\p{M}\s\'.-]*$/u', $name) === 1 && mb_strlen($name) >= 2 && mb_strlen($name) <= 100
            ? mb_strtolower($name)
            : null;
    }

    $nationalId = patientsNationalId($value);

    return preg_match('/^[A-Za-z0-9\s-]+$/', $value) === 1 && strlen($nationalId) >= 5 && strlen($nationalId) <= 50
        ? $nationalId
        : null;
}

function patientsFind(string $role, string $searchBy, string $value): array
{
    return [];
}

$patientsSearchBy = array_key_first($patientsSearchTypes);
$patientsQueryText = '';
$patientsError = '';
$patientsSearched = false;
$patientsResults = [];

if ($patientsHasAccess && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (isset($_POST['search_by']) && is_string($_POST['search_by']) && isset($patientsSearchTypes[$_POST['search_by']])) {
        $patientsSearchBy = $_POST['search_by'];
    }

    $patientsRawQuery = isset($_POST['q']) && is_string($_POST['q']) ? trim($_POST['q']) : '';
    $patientsQueryText = mb_substr($patientsRawQuery, 0, 254);
    $patientsValue = $patientsRawQuery === '' ? null : patientsSearchValue($patientsSearchBy, $patientsRawQuery);

    if ($patientsRawQuery === '') {
        $patientsError = $patientsSearchTypes[$patientsSearchBy]['empty'];
    } elseif ($patientsValue === null) {
        $patientsError = $patientsSearchTypes[$patientsSearchBy]['invalid'];
    } else {
        $patientsSearched = true;
        $patientsResults = patientsFind($patientsRole, $patientsSearchBy, $patientsValue);
    }
}

$patientsSearchType = $patientsSearchTypes[$patientsSearchBy];
$chnPageTitle = 'Patients';
$chnUserName = $patientsRoles[$patientsRole] . ' User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | Patients</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/patients.css">
    <script src="../js/sidebar.js" defer></script>
    <script src="../js/topbar.js" defer></script>
    <script src="../js/patients.js" defer></script>
</head>
<body>
<?php
$chnUserRole = $patientsRole;
require __DIR__ . '/../include/left-sidebar.php';

$chnUserRole = $patientsRoles[$patientsRole];
require __DIR__ . '/../include/top-bar.php';
?>

<main class="content" id="main-content" tabindex="-1">
    <div class="content__inner patients">
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Patients</h1>
                <p class="page-header__subtitle">Contact lookup for doctors and healthcare staff.</p>
            </div>
        </div>

        <?php if (!$patientsHasAccess): ?>
            <section class="card" aria-labelledby="patients-denied-title">
                <div class="empty-state">
                    <h2 class="empty-state__title" id="patients-denied-title">Only available for doctors and healthcare staff</h2>
                    <p class="empty-state__text">Doctors and healthcare staff use this page to look up the contact details of patients who have a case in their department or hospital.</p>
                    <a class="btn btn--outline" href="<?= patientsEscape('dashboard-test.php' . patientsQuery(['role' => $patientsRole])) ?>">Back to dashboard</a>
                </div>
            </section>
        <?php else: ?>
            <section class="card" aria-labelledby="patients-search-title">
                <header class="card__header">
                    <div>
                        <h2 class="card__title" id="patients-search-title">Find a patient</h2>
                        <p class="card__subtitle">Search with an exact phone number, email address or national ID, or with the patient's name. Only patients who have a case in <?= patientsEscape($patientsScope) ?> can be found.</p>
                    </div>
                </header>

                <form class="patients-form" id="patients-form" method="post" novalidate
                      action="<?= patientsEscape('patients-test.php' . patientsQuery(['role' => $patientsRole]) . '#patients-results') ?>">
                    <fieldset class="patients-form__types">
                        <legend class="form-label">Search by</legend>
                        <div class="patients-form__options">
                            <?php foreach ($patientsSearchTypes as $patientsTypeKey => $patientsType): ?>
                                <label class="patients-form__option">
                                    <input class="patients-form__radio" type="radio" name="search_by"
                                           value="<?= patientsEscape($patientsTypeKey) ?>"
                                           data-label="<?= patientsEscape($patientsType['label']) ?>"
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
                                       value="<?= patientsEscape($patientsQueryText) ?>"
                                       autocomplete="off" autocapitalize="none" spellcheck="false"
                                       aria-describedby="patients-q-hint patients-q-error"
                                       <?= $patientsError !== '' ? 'aria-invalid="true"' : '' ?>>
                                <p class="form-hint" id="patients-q-hint"><?= patientsEscape($patientsSearchType['hint']) ?></p>
                                <p class="form-error" id="patients-q-error" role="alert" <?= $patientsError === '' ? 'hidden' : '' ?>><?= patientsEscape($patientsError) ?></p>
                            </div>
                            <button type="submit" class="btn btn--primary">
                                <span class="icon icon--search" aria-hidden="true"></span>
                                Search
                            </button>
                            <a class="btn btn--outline" href="<?= patientsEscape(patientsQuery(['role' => $patientsRole])) ?>">Clear</a>
                        </div>
                    </div>
                </form>
            </section>

            <?php if ($patientsSearched): ?>
                <section class="card patients-results" id="patients-results" aria-labelledby="patients-results-title">
                    <?php if ($patientsResults === []): ?>
                        <div class="empty-state">
                            <h2 class="empty-state__title" id="patients-results-title">No patient found</h2>
                            <p class="empty-state__text">No patient with a case in <?= patientsEscape($patientsScope) ?> matches this <?= patientsEscape(mb_strtolower($patientsSearchType['label'])) ?>. Check the value and try again.</p>
                        </div>
                    <?php else: ?>
                        <header class="card__header">
                            <h2 class="card__title" id="patients-results-title">
                                <?= count($patientsResults) === 1 ? '1 patient found' : count($patientsResults) . ' patients found' ?>
                            </h2>
                        </header>

                        <?php foreach ($patientsResults as $patientsId => $patientsItem): ?>
                            <?php
                                $patientsFullName = $patientsItem['name'] . ' ' . $patientsItem['surname'];
                                $patientsBirth = $patientsItem['birth_date'] !== null ? new DateTimeImmutable($patientsItem['birth_date']) : null;
                            ?>
                            <article class="patients-result" aria-labelledby="patients-result-<?= (int) $patientsId ?>">
                                <header class="patients-result__header">
                                    <span class="patients-result__avatar" aria-hidden="true"><?= patientsEscape(patientsInitials($patientsItem['name'], $patientsItem['surname'])) ?></span>
                                    <div>
                                        <h3 class="patients-result__name" id="patients-result-<?= (int) $patientsId ?>"><?= patientsEscape($patientsFullName) ?></h3>
                                        <p class="patients-result__meta">
                                            <?php if ($patientsBirth !== null): ?>
                                                <?= (int) $patientsBirth->diff($patientsToday)->y ?> years old · born
                                                <time datetime="<?= patientsEscape($patientsBirth->format('Y-m-d')) ?>"><?= patientsEscape($patientsBirth->format('j M Y')) ?></time>
                                            <?php else: ?>
                                                Birth date not provided
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </header>

                                <dl class="patients-result__list">
                                    <div class="patients-result__item">
                                        <dt class="patients-result__term"><span class="icon icon--phone" aria-hidden="true"></span>Phone</dt>
                                        <dd class="patients-result__value">
                                            <?php if ($patientsItem['phone'] !== null): ?>
                                                <a class="patients-result__link" href="<?= patientsEscape('tel:' . preg_replace('/[^0-9+]/', '', $patientsItem['phone'])) ?>"><?= patientsEscape($patientsItem['phone']) ?></a>
                                            <?php else: ?>
                                                Not provided
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                    <div class="patients-result__item">
                                        <dt class="patients-result__term"><span class="icon icon--envelope" aria-hidden="true"></span>Email</dt>
                                        <dd class="patients-result__value">
                                            <a class="patients-result__link" href="<?= patientsEscape('mailto:' . str_replace('%40', '@', rawurlencode($patientsItem['email']))) ?>"><?= patientsEscape($patientsItem['email']) ?></a>
                                        </dd>
                                    </div>
                                    <div class="patients-result__item">
                                        <dt class="patients-result__term"><span class="icon icon--geo" aria-hidden="true"></span>Address</dt>
                                        <dd class="patients-result__value"><?= patientsEscape($patientsItem['full_address'] ?? 'Not provided') ?></dd>
                                    </div>
                                    <div class="patients-result__item">
                                        <dt class="patients-result__term"><span class="icon icon--cases" aria-hidden="true"></span>Cases in <?= patientsEscape($patientsScope) ?></dt>
                                        <dd class="patients-result__value">
                                            <ul class="patients-result__cases">
                                                <?php foreach ($patientsItem['cases'] as $patientsCase): ?>
                                                    <li>
                                                        #<?= (int) $patientsCase['case_id'] ?>
                                                        <?php if ($patientsRole === 'healthcare_staff'): ?>
                                                            · <?= patientsEscape($patientsCase['department_name']) ?>
                                                        <?php endif; ?>
                                                        <span class="badge badge--<?= patientsEscape($patientsCaseStates[$patientsCase['case_state']]['badge']) ?>"><?= patientsEscape($patientsCaseStates[$patientsCase['case_state']]['label']) ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </dd>
                                    </div>
                                </dl>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
