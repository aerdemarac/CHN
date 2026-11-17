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

function patientsPhoneDigits(string $value): string
{
    return ltrim(preg_replace('/\D+/', '', $value), '0');
}

function patientsPhoneMatches(string $stored, string $query): bool
{
    $first = patientsPhoneDigits($stored);
    $second = patientsPhoneDigits($query);
    [$short, $long] = strlen($first) <= strlen($second) ? [$first, $second] : [$second, $first];

    return strlen($short) >= 7 && strlen($long) - strlen($short) <= 3 && str_ends_with($long, $short);
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

    $nationalId = patientsNationalId($value);

    return preg_match('/^[A-Za-z0-9\s-]+$/', $value) === 1 && strlen($nationalId) >= 5 && strlen($nationalId) <= 50
        ? $nationalId
        : null;
}

$patientsUsers = [
    'patient'          => 'Elif Arslan',
    'doctor'           => 'Dr. Andreas Kyrou',
    'healthcare_staff' => 'Zeynep Şahin',
    'admin'            => 'Nikos Demetriou',
];

$patientsDoctor = [
    'doctor_id'       => 11,
    'doctor_name'     => 'Dr. Andreas Kyrou',
    'department_id'   => 1,
    'department_name' => 'Cardiology',
    'hospital_name'   => 'Nicosia State Hospital',
];

$patientsAll = [
    1  => ['name' => 'Elif',     'surname' => 'Arslan',   'birth_date' => '1991-04-12', 'email' => 'elif.arslan@example.com',      'phone' => '+905330000101', 'national_id' => '2045180317',  'full_address' => '14 Dereboyu Avenue, Flat 3, Nicosia'],
    2  => ['name' => 'Maria',    'surname' => 'Kouppa',   'birth_date' => '1958-09-03', 'email' => 'maria.kouppa@example.com',     'phone' => '+35799000102',  'national_id' => 'K00418265',   'full_address' => '27 Ledra Street, Nicosia'],
    3  => ['name' => 'Mehmet',   'surname' => 'Yıldız',   'birth_date' => '1966-01-21', 'email' => 'mehmet.yildiz@example.com',    'phone' => '+905420000103', 'national_id' => '10000000146', 'full_address' => '5 Salamis Road, Famagusta'],
    4  => ['name' => 'Andreas',  'surname' => 'Georgiou', 'birth_date' => '1958-06-30', 'email' => 'andreas.georgiou@example.com', 'phone' => '+35796000104',  'national_id' => 'K00527713',   'full_address' => '8 Makarios Avenue, Nicosia'],
    5  => ['name' => 'Ayşe',     'surname' => 'Demir',    'birth_date' => '1979-11-08', 'email' => 'ayse.demir@example.com',       'phone' => '+905480000105', 'national_id' => '2190044621',  'full_address' => '31 Ziya Rızkı Avenue, Kyrenia'],
    6  => ['name' => 'Can',      'surname' => 'Yılmaz',   'birth_date' => '1995-02-17', 'email' => 'can.yilmaz@example.com',       'phone' => '+905330000106', 'national_id' => '2310078854',  'full_address' => '19 Mehmet Akif Avenue, Nicosia'],
    7  => ['name' => 'Lefteris', 'surname' => 'Pavlou',   'birth_date' => '1983-07-25', 'email' => 'lefteris.pavlou@example.com',  'phone' => '+35797000107',  'national_id' => 'AB5529014',   'full_address' => '42 Stasinou Avenue, Nicosia'],
    8  => ['name' => 'Deniz',    'surname' => 'Kara',     'birth_date' => '2017-05-14', 'email' => 'deniz.kara@example.com',       'phone' => '+905420000108', 'national_id' => '2417006093',  'full_address' => '7 Atatürk Street, Morphou'],
    9  => ['name' => 'Hasan',    'surname' => 'Çelik',    'birth_date' => '1972-03-09', 'email' => 'hasan.celik@example.com',      'phone' => '+905330000109', 'national_id' => '2084415570',  'full_address' => null],
    10 => ['name' => 'Sophia',   'surname' => 'Ioannou',  'birth_date' => '1988-12-01', 'email' => 'sophia.ioannou@example.com',   'phone' => '+35799000110',  'national_id' => 'AK2206487',   'full_address' => '3 Griva Digeni Avenue, Limassol'],
];

$patientsCases = [
    1011 => ['patient_id' => 1,  'department_id' => 3, 'case_state' => 'closed'],
    1033 => ['patient_id' => 9,  'department_id' => 1, 'case_state' => 'closed'],
    1038 => ['patient_id' => 1,  'department_id' => 2, 'case_state' => 'open'],
    1042 => ['patient_id' => 1,  'department_id' => 1, 'case_state' => 'open'],
    1049 => ['patient_id' => 4,  'department_id' => 1, 'case_state' => 'open'],
    1051 => ['patient_id' => 3,  'department_id' => 1, 'case_state' => 'open'],
    1056 => ['patient_id' => 2,  'department_id' => 1, 'case_state' => 'open'],
    1060 => ['patient_id' => 5,  'department_id' => 1, 'case_state' => 'open'],
    1063 => ['patient_id' => 6,  'department_id' => 2, 'case_state' => 'open'],
    1065 => ['patient_id' => 7,  'department_id' => 3, 'case_state' => 'open'],
    1067 => ['patient_id' => 8,  'department_id' => 4, 'case_state' => 'open'],
    1070 => ['patient_id' => 10, 'department_id' => 5, 'case_state' => 'open'],
    1072 => ['patient_id' => 9,  'department_id' => 6, 'case_state' => 'open'],
];

$patientsExamples = [
    ['search_by' => 'phone',       'q' => '+90 533 000 01 01',        'label' => 'Phone'],
    ['search_by' => 'email',       'q' => 'maria.kouppa@example.com', 'label' => 'Email'],
    ['search_by' => 'national_id', 'q' => '10000000146',              'label' => 'National ID'],
    ['search_by' => 'email',       'q' => 'can.yilmaz@example.com',   'label' => 'Other department'],
];

$patientsDepartmentCases = [];
foreach ($patientsCases as $patientsCaseId => $patientsCase) {
    if ($patientsCase['department_id'] === $patientsDoctor['department_id']) {
        $patientsDepartmentCases[$patientsCase['patient_id']][$patientsCaseId] = $patientsCase['case_state'];
    }
}

$patientsSearchBy = array_key_first($patientsSearchTypes);
$patientsQueryText = '';
$patientsError = '';
$patientsSearched = false;
$patientsResults = [];

if ($patientsRole === 'doctor' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
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

        foreach ($patientsAll as $patientsId => $patientsItem) {
            if (!isset($patientsDepartmentCases[$patientsId])) {
                continue;
            }

            if ($patientsSearchBy === 'phone') {
                $patientsFound = $patientsItem['phone'] !== null && patientsPhoneMatches($patientsItem['phone'], $patientsValue);
            } elseif ($patientsSearchBy === 'email') {
                $patientsFound = mb_strtolower($patientsItem['email']) === $patientsValue;
            } else {
                $patientsFound = $patientsItem['national_id'] !== null && patientsNationalId($patientsItem['national_id']) === $patientsValue;
            }

            if ($patientsFound) {
                $patientsResults[$patientsId] = $patientsItem;
            }
        }
    }
}

$patientsSearchType = $patientsSearchTypes[$patientsSearchBy];
$patientsSubtitle = $patientsRole === 'doctor'
    ? $patientsDoctor['doctor_name'] . ' · ' . $patientsDoctor['department_name'] . ', ' . $patientsDoctor['hospital_name']
    : 'Contact lookup for doctors.';

$chnPageTitle = 'Patients';
$chnUserName = $patientsUsers[$patientsRole];
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
        <aside class="alert alert--info patients-demo" aria-label="Demo notice">
            <p class="patients-demo__text"><strong>Demo data.</strong> All names and records are fictional. Preview a role:</p>
            <nav aria-label="Preview role">
                <ul class="patients-demo__list">
                    <?php foreach ($patientsRoles as $patientsRoleKey => $patientsRoleLabel): ?>
                        <li>
                            <a class="patients-demo__link"
                               href="<?= patientsEscape(patientsQuery(['role' => $patientsRoleKey])) ?>"
                               <?= $patientsRoleKey === $patientsRole ? 'aria-current="page"' : '' ?>>
                                <?= patientsEscape($patientsRoleLabel) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <?php if ($patientsRole === 'doctor'): ?>
                <div class="patients-demo__examples">
                    <p class="patients-demo__text">Try a search:</p>
                    <ul class="patients-demo__list">
                        <?php foreach ($patientsExamples as $patientsExample): ?>
                            <li>
                                <button type="button" class="patients-demo__link"
                                        data-patients-example
                                        data-search-by="<?= patientsEscape($patientsExample['search_by']) ?>"
                                        data-q="<?= patientsEscape($patientsExample['q']) ?>">
                                    <?= patientsEscape($patientsExample['label'] . ': ' . $patientsExample['q']) ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </aside>

        <div class="page-header">
            <div>
                <h1 class="page-header__title">Patients</h1>
                <p class="page-header__subtitle"><?= patientsEscape($patientsSubtitle) ?></p>
            </div>
        </div>

        <?php if ($patientsRole !== 'doctor'): ?>
            <section class="card" aria-labelledby="patients-denied-title">
                <div class="empty-state">
                    <h2 class="empty-state__title" id="patients-denied-title">Only available for doctors</h2>
                    <p class="empty-state__text">Doctors use this page to look up the contact details of patients who have a case in their department.</p>
                    <a class="btn btn--outline" href="<?= patientsEscape('dashboard-test.php' . patientsQuery(['role' => $patientsRole])) ?>">Back to dashboard</a>
                </div>
            </section>
        <?php else: ?>
            <section class="card" aria-labelledby="patients-search-title">
                <header class="card__header">
                    <div>
                        <h2 class="card__title" id="patients-search-title">Find a patient</h2>
                        <p class="card__subtitle">Search with an exact phone number, email address or national ID. Only patients who have a case in <?= patientsEscape($patientsDoctor['department_name']) ?> can be found.</p>
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
                            <p class="empty-state__text">No patient with a case in <?= patientsEscape($patientsDoctor['department_name']) ?> matches this <?= patientsEscape(mb_strtolower($patientsSearchType['label'])) ?>. Check the value and try again.</p>
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
                                        <dt class="patients-result__term"><span class="icon icon--cases" aria-hidden="true"></span>Cases in <?= patientsEscape($patientsDoctor['department_name']) ?></dt>
                                        <dd class="patients-result__value">
                                            <ul class="patients-result__cases">
                                                <?php foreach ($patientsDepartmentCases[$patientsId] as $patientsCaseId => $patientsCaseState): ?>
                                                    <li>
                                                        #<?= (int) $patientsCaseId ?>
                                                        <span class="badge badge--<?= patientsEscape($patientsCaseStates[$patientsCaseState]['badge']) ?>"><?= patientsEscape($patientsCaseStates[$patientsCaseState]['label']) ?></span>
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
