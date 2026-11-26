<?php
require_once __DIR__ . '/../include/i18n.php';

$usersRoles = [
    'patient'          => t('role.patient'),
    'doctor'           => t('role.doctor'),
    'healthcare_staff' => t('role.healthcare_staff'),
    'admin'            => t('role.admin'),
];

$usersRole = isset($_GET['role']) && is_string($_GET['role']) && isset($usersRoles[$_GET['role']])
    ? $_GET['role']
    : 'patient';

$usersRoleBadges = [
    'patient'          => 'neutral',
    'doctor'           => 'info',
    'healthcare_staff' => 'success',
    'admin'            => 'warning',
];

$usersRoleTabs = [
    'patient'          => t('users.tab_patient'),
    'doctor'           => t('users.tab_doctor'),
    'healthcare_staff' => t('users.tab_healthcare_staff'),
    'admin'            => t('users.tab_admin'),
];

$usersPrivilegedRoles = ['doctor', 'healthcare_staff', 'admin'];

$usersCountries = require __DIR__ . '/../lib/countries.php';
$usersPinnedCountries = ['XN', 'TR'];

function usersEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function usersDate(int $days): string
{
    return (new DateTimeImmutable('today'))->modify(sprintf('%+d days', $days))->format('Y-m-d');
}

function usersUrl(array $state, array $changes = []): string
{
    $params = array_filter(
        array_merge($state, $changes),
        fn ($value): bool => $value !== null && $value !== ''
    );

    return '?' . http_build_query($params);
}

function usersCountText(string $key, array $params, int $count): string
{
    $parts = explode('{count}', t($key), 2);
    $html = usersEscape(chnFill($parts[0], $params));

    if (count($parts) === 2) {
        $html .= '<span data-users-count="shown">' . $count . '</span>' . usersEscape(chnFill($parts[1], $params));
    }

    return $html;
}

$usersCurrentUserId = 1;

$usersNames = [
    'patient'          => 'Elif Arslan',
    'doctor'           => 'Dr. Andreas Kyrou',
    'healthcare_staff' => 'Zeynep Şahin',
    'admin'            => 'Nikos Demetriou',
];

$usersHospitals = [
    1 => 'Nicosia State Hospital',
    2 => 'Famagusta State Hospital',
];

$usersDepartments = [
    1 => ['department_name' => 'Cardiology',        'hospital_id' => 1],
    2 => ['department_name' => 'Dermatology',       'hospital_id' => 1],
    3 => ['department_name' => 'Internal Medicine', 'hospital_id' => 1],
    4 => ['department_name' => 'Pediatrics',        'hospital_id' => 1],
    5 => ['department_name' => 'Neurology',         'hospital_id' => 1],
    6 => ['department_name' => 'Cardiology',        'hospital_id' => 2],
];

$usersAll = [
    1  => ['name' => 'Nikos',    'surname' => 'Demetriou', 'email' => 'nikos.demetriou@example.com',  'role' => 'admin',            'phone' => '+35799000201',  'birth_date' => '1980-05-19', 'nationality' => 'CY', 'national_id' => 'K00311204',   'hospital_id' => null, 'department_id' => null, 'created_at' => usersDate(-400)],
    2  => ['name' => 'Selma',    'surname' => 'Tuncer',    'email' => 'selma.tuncer@example.com',     'role' => 'admin',            'phone' => '+905330000202', 'birth_date' => '1986-10-02', 'nationality' => 'XN', 'national_id' => '2130046718',  'hospital_id' => null, 'department_id' => null, 'created_at' => usersDate(-310)],
    3  => ['name' => 'Andreas',  'surname' => 'Kyrou',     'email' => 'andreas.kyrou@example.com',    'role' => 'doctor',           'phone' => '+35799000203',  'birth_date' => '1974-02-11', 'nationality' => 'CY', 'national_id' => 'K00290417',   'hospital_id' => null, 'department_id' => 1,    'created_at' => usersDate(-380)],
    4  => ['name' => 'Eleni',    'surname' => 'Markou',    'email' => 'eleni.markou@example.com',     'role' => 'doctor',           'phone' => '+35796000204',  'birth_date' => '1982-08-27', 'nationality' => 'GR', 'national_id' => 'AM4410295',   'hospital_id' => null, 'department_id' => 1,    'created_at' => usersDate(-350)],
    5  => ['name' => 'Selin',    'surname' => 'Aydın',     'email' => 'selin.aydin@example.com',      'role' => 'doctor',           'phone' => '+905420000205', 'birth_date' => '1985-12-05', 'nationality' => 'XN', 'national_id' => '2245011963',  'hospital_id' => null, 'department_id' => 2,    'created_at' => usersDate(-330)],
    6  => ['name' => 'Can',      'surname' => 'Öztürk',    'email' => 'can.ozturk@example.com',       'role' => 'doctor',           'phone' => '+905480000206', 'birth_date' => '1978-03-30', 'nationality' => 'XN', 'national_id' => '2098830412',  'hospital_id' => null, 'department_id' => 3,    'created_at' => usersDate(-290)],
    7  => ['name' => 'Elena',    'surname' => 'Costa',     'email' => 'elena.costa@example.com',      'role' => 'doctor',           'phone' => '+35797000207',  'birth_date' => '1989-06-14', 'nationality' => 'CY', 'national_id' => 'K00633081',   'hospital_id' => null, 'department_id' => 4,    'created_at' => usersDate(-250)],
    8  => ['name' => 'Mert',     'surname' => 'Kaya',      'email' => 'mert.kaya@example.com',        'role' => 'doctor',           'phone' => '+905330000208', 'birth_date' => '1981-09-22', 'nationality' => 'XN', 'national_id' => '2177405526',  'hospital_id' => null, 'department_id' => 5,    'created_at' => usersDate(-200)],
    9  => ['name' => 'Sophia',   'surname' => 'Pavlidou',  'email' => 'sophia.pavlidou@example.com',  'role' => 'doctor',           'phone' => '+35799000209',  'birth_date' => '1987-01-08', 'nationality' => 'CY', 'national_id' => 'K00580926',   'hospital_id' => null, 'department_id' => 6,    'created_at' => usersDate(-160)],
    10 => ['name' => 'Zeynep',   'surname' => 'Şahin',     'email' => 'zeynep.sahin@example.com',     'role' => 'healthcare_staff', 'phone' => '+905420000210', 'birth_date' => '1990-04-17', 'nationality' => 'XN', 'national_id' => '2301157740',  'hospital_id' => 1,    'department_id' => null, 'created_at' => usersDate(-300)],
    11 => ['name' => 'Petros',   'surname' => 'Nikolaou',  'email' => 'petros.nikolaou@example.com',  'role' => 'healthcare_staff', 'phone' => '+35796000211',  'birth_date' => '1984-11-29', 'nationality' => 'CY', 'national_id' => 'K00472350',   'hospital_id' => 2,    'department_id' => 6,    'created_at' => usersDate(-120)],
    12 => ['name' => 'Elif',     'surname' => 'Arslan',    'email' => 'elif.arslan@example.com',      'role' => 'patient',          'phone' => '+905330000101', 'birth_date' => '1991-04-12', 'nationality' => 'XN', 'national_id' => '2045180317',  'hospital_id' => null, 'department_id' => null, 'created_at' => usersDate(-90)],
    13 => ['name' => 'Maria',    'surname' => 'Kouppa',    'email' => 'maria.kouppa@example.com',     'role' => 'patient',          'phone' => '+35799000102',  'birth_date' => '1958-09-03', 'nationality' => 'CY', 'national_id' => 'K00418265',   'hospital_id' => null, 'department_id' => null, 'created_at' => usersDate(-75)],
    14 => ['name' => 'Mehmet',   'surname' => 'Yıldız',    'email' => 'mehmet.yildiz@example.com',    'role' => 'patient',          'phone' => '+905420000103', 'birth_date' => '1966-01-21', 'nationality' => 'TR', 'national_id' => '10000000146', 'hospital_id' => null, 'department_id' => null, 'created_at' => usersDate(-40)],
    15 => ['name' => 'Andreas',  'surname' => 'Georgiou',  'email' => 'andreas.georgiou@example.com', 'role' => 'patient',          'phone' => '+35796000104',  'birth_date' => '1958-06-30', 'nationality' => 'CY', 'national_id' => 'K00527713',   'hospital_id' => null, 'department_id' => null, 'created_at' => usersDate(-21)],
    16 => ['name' => 'Ayşe',     'surname' => 'Demir',     'email' => 'ayse.demir@example.com',       'role' => 'patient',          'phone' => null,            'birth_date' => '1979-11-08', 'nationality' => 'XN', 'national_id' => '2190044621',  'hospital_id' => null, 'department_id' => null, 'created_at' => usersDate(-9)],
    17 => ['name' => 'Can',      'surname' => 'Yılmaz',    'email' => 'can.yilmaz@example.com',       'role' => 'patient',          'phone' => '+905330000106', 'birth_date' => '1995-02-17', 'nationality' => 'XN', 'national_id' => '2310078854',  'hospital_id' => null, 'department_id' => null, 'created_at' => usersDate(-3)],
    18 => ['name' => 'Lefteris', 'surname' => 'Pavlou',    'email' => 'lefteris.pavlou@example.com',  'role' => 'patient',          'phone' => '+35797000107',  'birth_date' => '1983-07-25', 'nationality' => 'CY', 'national_id' => 'AB5529014',   'hospital_id' => null, 'department_id' => null, 'created_at' => usersDate(-1)],
];

function usersWorkplace(array $user, array $hospitals, array $departments): string
{
    $department = $user['department_id'] !== null ? $departments[$user['department_id']] : null;
    $hospitalId = $user['hospital_id'] ?? ($department['hospital_id'] ?? null);

    if ($hospitalId === null) {
        return '';
    }

    return ($department !== null ? $department['department_name'] . ' · ' : '') . $hospitals[$hospitalId];
}

$usersFilterRole = isset($_GET['filter_role']) && is_string($_GET['filter_role']) && isset($usersRoles[$_GET['filter_role']])
    ? $_GET['filter_role']
    : null;

$usersQuery = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';

$usersState = ['role' => $usersRole, 'filter_role' => $usersFilterRole, 'q' => $usersQuery];

$usersShowForm = $usersRole === 'admin' && isset($_GET['form']) && $_GET['form'] === 'create';

$usersMatching = array_filter($usersAll, function (array $user) use ($usersQuery): bool {
    return $usersQuery === ''
        || mb_stripos($user['name'] . ' ' . $user['surname'], $usersQuery) !== false
        || mb_stripos($user['email'], $usersQuery) !== false;
});

$usersCounts = array_fill_keys(array_keys($usersRoles), 0);
foreach ($usersMatching as $usersItem) {
    $usersCounts[$usersItem['role']] += 1;
}

$usersRows = array_filter(
    $usersMatching,
    fn (array $user): bool => $usersFilterRole === null || $user['role'] === $usersFilterRole
);

uasort($usersRows, fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at']);

$chnPageTitle = t('users.page_title');
$chnUserName = $usersNames[$usersRole];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(chnLang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | <?= te('users.page_title') ?></title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/user-management.css">
    <script src="../js/sidebar.js" defer></script>
    <script src="../js/topbar.js?v=<?= filemtime(__DIR__ . '/../js/topbar.js') ?>" defer></script>
    <script src="../js/user-management.js" defer></script>
</head>
<body>
<?php
$chnUserRole = $usersRole;
require __DIR__ . '/../include/left-sidebar.php';

$chnUserRole = $usersRoles[$usersRole];
require __DIR__ . '/../include/top-bar.php';
?>

<main class="content" id="main-content" tabindex="-1">
    <div class="content__inner users">
        <aside class="alert alert--info users-demo" aria-label="<?= te('users.demo_notice') ?>">
            <p class="users-demo__text"><strong><?= te('common.demo_title') ?></strong> <?= te('common.demo_text') ?></p>
            <nav aria-label="<?= te('users.preview_role') ?>">
                <ul class="users-demo__roles">
                    <?php foreach ($usersRoles as $usersRoleKey => $usersRoleLabel): ?>
                        <li>
                            <a class="users-demo__link"
                               href="<?= usersEscape(usersUrl(['role' => $usersRoleKey])) ?>"
                               <?= $usersRoleKey === $usersRole ? 'aria-current="page"' : '' ?>>
                                <?= usersEscape($usersRoleLabel) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>

        <div class="page-header">
            <div>
                <h1 class="page-header__title"><?= te('users.title') ?></h1>
                <p class="page-header__subtitle">
                    <?= $usersRole === 'admin' ? te('users.subtitle_admin') : te('users.subtitle_other') ?>
                </p>
            </div>
            <?php if ($usersRole === 'admin'): ?>
                <div class="page-header__actions">
                    <a class="btn btn--primary" href="<?= usersEscape(usersUrl($usersState, ['form' => 'create']) . '#users-form') ?>"><?= te('users.create_account') ?></a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($usersRole !== 'admin'): ?>
            <section class="card" aria-labelledby="users-denied-title">
                <div class="empty-state">
                    <h2 class="empty-state__title" id="users-denied-title"><?= te('common.admins_only') ?></h2>
                    <p class="empty-state__text"><?= te('users.denied_text') ?></p>
                    <a class="btn btn--outline" href="<?= usersEscape('dashboard-test.php' . usersUrl(['role' => $usersRole])) ?>"><?= te('users.back_to_dashboard') ?></a>
                </div>
            </section>
        <?php else: ?>
            <p class="alert alert--info users-notice" id="users-notice" role="status" tabindex="-1" hidden
               data-messages="<?= chnJsMessages([
                   'role_required'             => 'users.role_required',
                   'first_name_required'       => 'users.first_name_required',
                   'first_name_invalid'        => 'register.first_name_invalid',
                   'last_name_required'        => 'users.last_name_required',
                   'last_name_invalid'         => 'register.last_name_invalid',
                   'email_required'            => 'users.email_required',
                   'email_invalid'             => 'auth.email_invalid',
                   'hospital_required'         => 'users.hospital_required',
                   'department_required'       => 'users.department_required',
                   'phone_required'            => 'users.phone_required',
                   'phone_invalid'             => 'register.phone_invalid',
                   'birth_date_required'       => 'users.birth_date_required',
                   'birth_date_invalid'        => 'register.birth_date_invalid',
                   'nationality_required'      => 'users.nationality_required',
                   'national_id_required'      => 'users.national_id_required',
                   'national_id_invalid'       => 'register.national_id_invalid',
                   'tc_invalid'                => 'register.tc_invalid',
                   'password_required'         => 'register.password_required',
                   'password_weak'             => 'register.password_weak',
                   'password_confirm_required' => 'users.password_confirm_required',
                   'password_mismatch'         => 'register.password_mismatch',
                   'show_password'             => 'auth.show_password',
                   'hide_password'             => 'auth.hide_password',
                   'create_notice'             => 'users.create_notice',
                   'delete_confirm'            => 'users.delete_confirm',
                   'delete_notice'             => 'users.delete_notice',
               ]) ?>"></p>

            <?php if ($usersShowForm): ?>
                <section class="card users-form-card" id="users-form" aria-labelledby="users-form-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="users-form-title"><?= te('users.create_account') ?></h2>
                            <p class="card__subtitle"><?= te('users.form_subtitle') ?></p>
                        </div>
                    </header>

                    <form class="users-form" id="users-account-form" method="post" novalidate
                          action="<?= usersEscape('../backend/users.php' . usersUrl(['role' => $usersRole])) ?>">
                        <input type="hidden" name="action" value="create_user">

                        <div class="users-form__grid">
                            <div class="form-group">
                                <label class="form-label" for="users-user-role"><?= te('users.role') ?></label>
                                <select class="form-control" id="users-user-role" name="user_role" required
                                        aria-describedby="users-user-role-error">
                                    <option value=""><?= te('users.role_placeholder') ?></option>
                                    <?php foreach ($usersPrivilegedRoles as $usersRoleKey): ?>
                                        <option value="<?= usersEscape($usersRoleKey) ?>"><?= usersEscape($usersRoles[$usersRoleKey]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="form-error" id="users-user-role-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-first-name"><?= te('register.first_name') ?></label>
                                <input class="form-control" type="text" id="users-first-name" name="first_name" maxlength="100" required
                                       autocomplete="off" aria-describedby="users-first-name-error">
                                <p class="form-error" id="users-first-name-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-last-name"><?= te('register.last_name') ?></label>
                                <input class="form-control" type="text" id="users-last-name" name="last_name" maxlength="100" required
                                       autocomplete="off" aria-describedby="users-last-name-error">
                                <p class="form-error" id="users-last-name-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-email"><?= te('users.email') ?></label>
                                <input class="form-control" type="email" id="users-email" name="email" maxlength="254" required
                                       autocomplete="off" autocapitalize="none" spellcheck="false"
                                       aria-describedby="users-email-error">
                                <p class="form-error" id="users-email-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group" data-users-workplace hidden>
                                <label class="form-label" for="users-hospital-id"><?= te('users.hospital') ?></label>
                                <select class="form-control" id="users-hospital-id" name="hospital_id" required
                                        aria-describedby="users-hospital-id-error" disabled>
                                    <option value=""><?= te('users.hospital_placeholder') ?></option>
                                    <?php foreach ($usersHospitals as $usersHospitalId => $usersHospitalName): ?>
                                        <option value="<?= (int) $usersHospitalId ?>"><?= usersEscape($usersHospitalName) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="form-error" id="users-hospital-id-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group" data-users-workplace hidden>
                                <label class="form-label" for="users-department-id"><?= te('users.department') ?></label>
                                <select class="form-control" id="users-department-id" name="department_id" required
                                        aria-describedby="users-department-id-error" disabled>
                                    <option value=""><?= te('users.department_placeholder') ?></option>
                                    <?php foreach ($usersDepartments as $usersDepartmentId => $usersDepartment): ?>
                                        <option value="<?= (int) $usersDepartmentId ?>" data-hospital-id="<?= (int) $usersDepartment['hospital_id'] ?>">
                                            <?= usersEscape($usersDepartment['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="form-error" id="users-department-id-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-phone"><?= te('users.phone') ?></label>
                                <input class="form-control" type="tel" id="users-phone" name="phone" maxlength="24" inputmode="tel" required
                                       autocomplete="off" aria-describedby="users-phone-error">
                                <p class="form-error" id="users-phone-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-birth-date"><?= te('register.birth_date') ?></label>
                                <input class="form-control" type="date" id="users-birth-date" name="birth_date" required
                                       min="1900-01-01" max="<?= usersEscape(usersDate(0)) ?>"
                                       aria-describedby="users-birth-date-error">
                                <p class="form-error" id="users-birth-date-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-nationality"><?= te('register.nationality') ?></label>
                                <select class="form-control" id="users-nationality" name="nationality" required aria-describedby="users-nationality-error">
                                    <option value=""><?= te('users.nationality_placeholder') ?></option>
                                    <?php foreach ($usersPinnedCountries as $usersCode): ?>
                                        <option value="<?= usersEscape($usersCode) ?>"><?= usersEscape($usersCountries[$usersCode]) ?></option>
                                    <?php endforeach; ?>
                                    <?php foreach ($usersCountries as $usersCode => $usersCountry): ?>
                                        <?php if (in_array($usersCode, $usersPinnedCountries, true)) continue; ?>
                                        <option value="<?= usersEscape($usersCode) ?>"><?= usersEscape($usersCountry) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="form-error" id="users-nationality-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-national-id"><?= te('users.national_id') ?></label>
                                <input class="form-control" type="text" id="users-national-id" name="national_id" maxlength="50" required
                                       autocomplete="off" autocapitalize="characters" spellcheck="false"
                                       aria-describedby="users-national-id-error">
                                <p class="form-error" id="users-national-id-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-password"><?= te('auth.password') ?></label>
                                <div class="users-form__password">
                                    <input class="form-control" type="password" id="users-password" name="password" maxlength="255" required
                                           autocomplete="new-password" aria-describedby="users-password-hint users-password-error">
                                    <button class="users-form__toggle" type="button"
                                            aria-label="<?= te('auth.show_password') ?>" aria-controls="users-password" aria-pressed="false">
                                        <span class="icon icon--eye" aria-hidden="true"></span>
                                    </button>
                                </div>
                                <p class="form-hint" id="users-password-hint"><?= te('register.password_hint') ?></p>
                                <p class="form-error" id="users-password-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-password-confirm"><?= te('register.password_confirm') ?></label>
                                <div class="users-form__password">
                                    <input class="form-control" type="password" id="users-password-confirm" name="password_confirm" maxlength="255" required
                                           autocomplete="new-password" aria-describedby="users-password-confirm-error">
                                    <button class="users-form__toggle" type="button"
                                            aria-label="<?= te('auth.show_password') ?>" aria-controls="users-password-confirm" aria-pressed="false">
                                        <span class="icon icon--eye" aria-hidden="true"></span>
                                    </button>
                                </div>
                                <p class="form-error" id="users-password-confirm-error" role="alert" hidden></p>
                            </div>
                        </div>

                        <div class="card__footer">
                            <a class="btn btn--outline" href="<?= usersEscape(usersUrl($usersState) . '#users-list') ?>"><?= te('users.cancel') ?></a>
                            <button type="submit" class="btn btn--primary"><?= te('users.create_account') ?></button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <section class="card users-list" id="users-list" aria-labelledby="users-list-title">
                <header class="card__header">
                    <div>
                        <h2 class="card__title" id="users-list-title"><?= te('users.list_title') ?></h2>
                        <p class="card__subtitle">
                            <?= $usersQuery !== ''
                                ? usersCountText('users.shown_query', ['query' => $usersQuery], count($usersRows))
                                : usersCountText('users.shown', [], count($usersRows)) ?>
                        </p>
                    </div>
                </header>

                <nav aria-label="<?= te('users.filter_by_role') ?>">
                    <ul class="users-tabs">
                        <li>
                            <a class="users-tabs__link" href="<?= usersEscape(usersUrl($usersState, ['filter_role' => null]) . '#users-list') ?>"
                               <?= $usersFilterRole === null ? 'aria-current="page"' : '' ?>>
                                <?= te('users.tab_all') ?> <span class="users-tabs__count" data-users-count="all"><?= count($usersMatching) ?></span>
                            </a>
                        </li>
                        <?php foreach ($usersRoleTabs as $usersRoleKey => $usersRoleLabel): ?>
                            <li>
                                <a class="users-tabs__link" href="<?= usersEscape(usersUrl($usersState, ['filter_role' => $usersRoleKey]) . '#users-list') ?>"
                                   <?= $usersFilterRole === $usersRoleKey ? 'aria-current="page"' : '' ?>>
                                    <?= usersEscape($usersRoleLabel) ?> <span class="users-tabs__count" data-users-count="<?= usersEscape($usersRoleKey) ?>"><?= (int) $usersCounts[$usersRoleKey] ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>

                <form class="users-search" method="get" action="<?= usersEscape('user-management-test.php#users-list') ?>" role="search">
                    <input type="hidden" name="role" value="<?= usersEscape($usersRole) ?>">
                    <?php if ($usersFilterRole !== null): ?>
                        <input type="hidden" name="filter_role" value="<?= usersEscape($usersFilterRole) ?>">
                    <?php endif; ?>
                    <label class="form-label" for="users-q"><?= te('users.search_label') ?></label>
                    <div class="users-search__control">
                        <input class="form-control" type="search" id="users-q" name="q" maxlength="100"
                               autocomplete="off" value="<?= usersEscape($usersQuery) ?>">
                        <button type="submit" class="btn btn--primary"><?= te('users.search') ?></button>
                        <a class="btn btn--outline" href="<?= usersEscape(usersUrl($usersState, ['q' => null]) . '#users-list') ?>"><?= te('users.clear') ?></a>
                    </div>
                </form>

                <div class="empty-state" id="users-empty" <?= $usersRows === [] ? '' : 'hidden' ?>>
                    <p class="empty-state__title"><?= te('users.empty_title') ?></p>
                    <p class="empty-state__text"><?= te('users.empty_text') ?></p>
                </div>

                <div class="table-responsive" id="users-table" <?= $usersRows === [] ? 'hidden' : '' ?>>
                    <table class="table users-table">
                        <thead>
                            <tr>
                                <th scope="col"><?= te('users.col_user') ?></th>
                                <th scope="col"><?= te('users.role') ?></th>
                                <th scope="col"><?= te('users.col_workplace') ?></th>
                                <th scope="col"><?= te('users.phone') ?></th>
                                <th scope="col"><?= te('users.col_registered') ?></th>
                                <th scope="col"><?= te('users.col_actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usersRows as $usersId => $usersItem): ?>
                                <?php
                                    $usersFullName = $usersItem['name'] . ' ' . $usersItem['surname'];
                                    $usersWorkplace = usersWorkplace($usersItem, $usersHospitals, $usersDepartments);
                                    $usersCreated = new DateTimeImmutable($usersItem['created_at']);
                                ?>
                                <tr data-users-row="<?= (int) $usersId ?>" data-users-role="<?= usersEscape($usersItem['role']) ?>">
                                    <th scope="row">
                                        <span class="users-table__name">
                                            <?= usersEscape($usersFullName) ?>
                                            <?php if ($usersId === $usersCurrentUserId): ?>
                                                <span class="badge badge--neutral"><?= te('users.you') ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="users-table__sub"><?= usersEscape($usersItem['email']) ?></span>
                                    </th>
                                    <td><span class="badge badge--<?= usersEscape($usersRoleBadges[$usersItem['role']]) ?>"><?= usersEscape($usersRoles[$usersItem['role']]) ?></span></td>
                                    <td class="users-table__workplace"><?= $usersWorkplace !== '' ? usersEscape($usersWorkplace) : '<span class="users-table__none">' . te('users.not_applicable') . '</span>' ?></td>
                                    <td class="users-table__nowrap"><?= $usersItem['phone'] !== null ? usersEscape($usersItem['phone']) : '<span class="users-table__none">' . te('users.not_provided') . '</span>' ?></td>
                                    <td class="users-table__nowrap"><time datetime="<?= usersEscape($usersCreated->format('Y-m-d')) ?>"><?= usersEscape(chnDate($usersCreated, 'medium')) ?></time></td>
                                    <td>
                                        <form method="post" data-users-delete data-users-name="<?= usersEscape($usersFullName) ?>"
                                              action="<?= usersEscape('../backend/users.php' . usersUrl(['role' => $usersRole])) ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= (int) $usersId ?>">
                                            <button type="submit" class="btn btn--outline btn--sm users-table__delete"
                                                    aria-label="<?= te('users.delete_label', ['name' => $usersFullName]) ?>"
                                                    <?= $usersId === $usersCurrentUserId ? 'disabled title="' . te('users.delete_self') . '"' : '' ?>><?= te('users.delete') ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
