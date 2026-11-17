<?php
$usersRoles = [
    'patient'          => 'Patient',
    'doctor'           => 'Doctor',
    'healthcare_staff' => 'Healthcare Staff',
    'admin'            => 'Admin',
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
    'patient'          => 'Patients',
    'doctor'           => 'Doctors',
    'healthcare_staff' => 'Healthcare staff',
    'admin'            => 'Admins',
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

$usersForm = null;
$usersFormId = null;

if ($usersRole === 'admin' && isset($_GET['form']) && is_string($_GET['form'])) {
    if ($_GET['form'] === 'create') {
        $usersForm = 'create';
    } elseif ($_GET['form'] === 'edit' && isset($_GET['user_id']) && is_string($_GET['user_id'])
        && ctype_digit($_GET['user_id']) && isset($usersAll[(int) $_GET['user_id']])) {
        $usersForm = 'edit';
        $usersFormId = (int) $_GET['user_id'];
    }
}

$usersFormUser = $usersFormId !== null ? $usersAll[$usersFormId] : [
    'name' => '', 'surname' => '', 'email' => '', 'role' => '', 'phone' => null, 'birth_date' => null,
    'nationality' => null, 'national_id' => null, 'hospital_id' => null, 'department_id' => null,
];
$usersFormIsSelf = $usersFormId === $usersCurrentUserId;
$usersFormRoles = $usersForm === 'edit' ? array_keys($usersRoles) : $usersPrivilegedRoles;
$usersFormHospital = $usersFormUser['hospital_id']
    ?? ($usersFormUser['department_id'] !== null ? $usersDepartments[$usersFormUser['department_id']]['hospital_id'] : null);
$usersFormHasWorkplace = in_array($usersFormUser['role'], ['doctor', 'healthcare_staff'], true);

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

$chnPageTitle = 'User Management';
$chnUserName = $usersNames[$usersRole];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | User Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/user-management.css">
    <script src="../js/sidebar.js" defer></script>
    <script src="../js/topbar.js" defer></script>
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
        <aside class="alert alert--info users-demo" aria-label="Demo notice">
            <p class="users-demo__text"><strong>Demo data.</strong> All names and records are fictional. Preview a role:</p>
            <nav aria-label="Preview role">
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
                <h1 class="page-header__title">User management</h1>
                <p class="page-header__subtitle">
                    <?= $usersRole === 'admin' ? 'Create privileged accounts and manage every user of the network.' : 'Account administration for admins.' ?>
                </p>
            </div>
            <?php if ($usersRole === 'admin'): ?>
                <div class="page-header__actions">
                    <a class="btn btn--primary" href="<?= usersEscape(usersUrl($usersState, ['form' => 'create']) . '#users-form') ?>">Create account</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($usersRole !== 'admin'): ?>
            <section class="card" aria-labelledby="users-denied-title">
                <div class="empty-state">
                    <h2 class="empty-state__title" id="users-denied-title">Only available for admins</h2>
                    <p class="empty-state__text">Admins create doctor, healthcare staff and admin accounts here and manage existing users.</p>
                    <a class="btn btn--outline" href="<?= usersEscape('dashboard-test.php' . usersUrl(['role' => $usersRole])) ?>">Back to dashboard</a>
                </div>
            </section>
        <?php else: ?>
            <p class="alert alert--info users-notice" id="users-notice" role="status" tabindex="-1" hidden></p>

            <?php if ($usersForm !== null): ?>
                <section class="card users-form-card" id="users-form" aria-labelledby="users-form-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="users-form-title">
                                <?= $usersForm === 'create' ? 'Create account' : 'Edit ' . usersEscape($usersFormUser['name'] . ' ' . $usersFormUser['surname']) ?>
                            </h2>
                            <p class="card__subtitle">
                                <?= $usersForm === 'create'
                                    ? 'For doctors, healthcare staff and admins. Patients sign up themselves. The new user gets an email with a link to set their own password.'
                                    : 'Changes apply the next time the user opens a page.' ?>
                            </p>
                        </div>
                    </header>

                    <form class="users-form" id="users-account-form" method="post" novalidate
                          action="<?= usersEscape('../backend/users.php' . usersUrl(['role' => $usersRole])) ?>"
                          data-mode="<?= usersEscape($usersForm) ?>">
                        <input type="hidden" name="action" value="<?= $usersForm === 'create' ? 'create_user' : 'update_user' ?>">
                        <?php if ($usersFormId !== null): ?>
                            <input type="hidden" name="user_id" value="<?= (int) $usersFormId ?>">
                        <?php endif; ?>

                        <div class="users-form__grid">
                            <div class="form-group">
                                <label class="form-label" for="users-user-role">Role</label>
                                <select class="form-control" id="users-user-role" name="user_role" required
                                        aria-describedby="users-user-role-hint users-user-role-error"
                                        <?= $usersFormIsSelf ? 'disabled' : '' ?>>
                                    <?php if ($usersForm === 'create'): ?>
                                        <option value="">Select a role</option>
                                    <?php endif; ?>
                                    <?php foreach ($usersFormRoles as $usersRoleKey): ?>
                                        <option value="<?= usersEscape($usersRoleKey) ?>" <?= $usersFormUser['role'] === $usersRoleKey ? 'selected' : '' ?>>
                                            <?= usersEscape($usersRoles[$usersRoleKey]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="form-hint" id="users-user-role-hint" <?= $usersFormIsSelf ? '' : 'hidden' ?>>You cannot change your own role.</p>
                                <p class="form-error" id="users-user-role-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-first-name">First name</label>
                                <input class="form-control" type="text" id="users-first-name" name="first_name" maxlength="100" required
                                       autocomplete="off" value="<?= usersEscape($usersFormUser['name']) ?>"
                                       aria-describedby="users-first-name-error">
                                <p class="form-error" id="users-first-name-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-last-name">Last name</label>
                                <input class="form-control" type="text" id="users-last-name" name="last_name" maxlength="100" required
                                       autocomplete="off" value="<?= usersEscape($usersFormUser['surname']) ?>"
                                       aria-describedby="users-last-name-error">
                                <p class="form-error" id="users-last-name-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-email">Email</label>
                                <input class="form-control" type="email" id="users-email" name="email" maxlength="254" required
                                       autocomplete="off" autocapitalize="none" spellcheck="false"
                                       value="<?= usersEscape($usersFormUser['email']) ?>"
                                       aria-describedby="users-email-error">
                                <p class="form-error" id="users-email-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group" data-users-workplace <?= $usersFormHasWorkplace ? '' : 'hidden' ?>>
                                <label class="form-label" for="users-hospital-id">Hospital</label>
                                <select class="form-control" id="users-hospital-id" name="hospital_id"
                                        aria-describedby="users-hospital-id-error" <?= $usersFormHasWorkplace ? '' : 'disabled' ?>>
                                    <option value="">Select a hospital</option>
                                    <?php foreach ($usersHospitals as $usersHospitalId => $usersHospitalName): ?>
                                        <option value="<?= (int) $usersHospitalId ?>" <?= $usersFormHospital === $usersHospitalId ? 'selected' : '' ?>>
                                            <?= usersEscape($usersHospitalName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="form-error" id="users-hospital-id-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group" data-users-workplace <?= $usersFormHasWorkplace ? '' : 'hidden' ?>>
                                <label class="form-label" for="users-department-id">
                                    Department <span class="users-form__optional" id="users-department-optional" <?= $usersFormUser['role'] === 'healthcare_staff' ? '' : 'hidden' ?>>(optional)</span>
                                </label>
                                <select class="form-control" id="users-department-id" name="department_id"
                                        aria-describedby="users-department-id-error" <?= $usersFormHasWorkplace ? '' : 'disabled' ?>>
                                    <option value="" data-label-doctor="Select a department" data-label-staff="All departments">
                                        <?= $usersFormUser['role'] === 'healthcare_staff' ? 'All departments' : 'Select a department' ?>
                                    </option>
                                    <?php foreach ($usersDepartments as $usersDepartmentId => $usersDepartment): ?>
                                        <option value="<?= (int) $usersDepartmentId ?>"
                                                data-hospital-id="<?= (int) $usersDepartment['hospital_id'] ?>"
                                                <?= $usersFormUser['department_id'] === $usersDepartmentId ? 'selected' : '' ?>>
                                            <?= usersEscape($usersDepartment['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="form-error" id="users-department-id-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-phone">Phone <span class="users-form__optional">(optional)</span></label>
                                <input class="form-control" type="tel" id="users-phone" name="phone" maxlength="24" inputmode="tel"
                                       autocomplete="off" value="<?= usersEscape($usersFormUser['phone'] ?? '') ?>"
                                       aria-describedby="users-phone-error">
                                <p class="form-error" id="users-phone-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-birth-date">Date of birth <span class="users-form__optional">(optional)</span></label>
                                <input class="form-control" type="date" id="users-birth-date" name="birth_date"
                                       min="1900-01-01" max="<?= usersEscape(usersDate(0)) ?>"
                                       value="<?= usersEscape($usersFormUser['birth_date'] ?? '') ?>"
                                       aria-describedby="users-birth-date-error">
                                <p class="form-error" id="users-birth-date-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-nationality">Nationality <span class="users-form__optional">(optional)</span></label>
                                <select class="form-control" id="users-nationality" name="nationality" aria-describedby="users-nationality-error">
                                    <option value="">Select a nationality</option>
                                    <?php foreach ($usersPinnedCountries as $usersCode): ?>
                                        <option value="<?= usersEscape($usersCode) ?>" <?= $usersFormUser['nationality'] === $usersCode ? 'selected' : '' ?>>
                                            <?= usersEscape($usersCountries[$usersCode]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php foreach ($usersCountries as $usersCode => $usersCountry): ?>
                                        <?php if (in_array($usersCode, $usersPinnedCountries, true)) continue; ?>
                                        <option value="<?= usersEscape($usersCode) ?>" <?= $usersFormUser['nationality'] === $usersCode ? 'selected' : '' ?>>
                                            <?= usersEscape($usersCountry) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="form-error" id="users-nationality-error" role="alert" hidden></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="users-national-id">National ID <span class="users-form__optional">(optional)</span></label>
                                <input class="form-control" type="text" id="users-national-id" name="national_id" maxlength="50"
                                       autocomplete="off" autocapitalize="characters" spellcheck="false"
                                       value="<?= usersEscape($usersFormUser['national_id'] ?? '') ?>"
                                       aria-describedby="users-national-id-error">
                                <p class="form-error" id="users-national-id-error" role="alert" hidden></p>
                            </div>
                        </div>

                        <div class="card__footer">
                            <a class="btn btn--outline" href="<?= usersEscape(usersUrl($usersState) . '#users-list') ?>">Cancel</a>
                            <button type="submit" class="btn btn--primary"><?= $usersForm === 'create' ? 'Create account' : 'Save changes' ?></button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <section class="card users-list" id="users-list" aria-labelledby="users-list-title">
                <header class="card__header">
                    <div>
                        <h2 class="card__title" id="users-list-title">Users</h2>
                        <p class="card__subtitle">
                            <span data-users-count="shown"><?= count($usersRows) ?></span> shown<?= $usersQuery !== '' ? ' for “' . usersEscape($usersQuery) . '”' : '' ?>, newest first.
                        </p>
                    </div>
                </header>

                <nav aria-label="Filter by role">
                    <ul class="users-tabs">
                        <li>
                            <a class="users-tabs__link" href="<?= usersEscape(usersUrl($usersState, ['filter_role' => null]) . '#users-list') ?>"
                               <?= $usersFilterRole === null ? 'aria-current="page"' : '' ?>>
                                All <span class="users-tabs__count" data-users-count="all"><?= count($usersMatching) ?></span>
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
                    <label class="form-label" for="users-q">Name or email</label>
                    <div class="users-search__control">
                        <input class="form-control" type="search" id="users-q" name="q" maxlength="100"
                               autocomplete="off" value="<?= usersEscape($usersQuery) ?>">
                        <button type="submit" class="btn btn--primary">Search</button>
                        <a class="btn btn--outline" href="<?= usersEscape(usersUrl($usersState, ['q' => null]) . '#users-list') ?>">Clear</a>
                    </div>
                </form>

                <div class="empty-state" id="users-empty" <?= $usersRows === [] ? '' : 'hidden' ?>>
                    <p class="empty-state__title">No users found</p>
                    <p class="empty-state__text">Try another name, email or role.</p>
                </div>

                <div class="table-responsive" id="users-table" <?= $usersRows === [] ? 'hidden' : '' ?>>
                    <table class="table users-table">
                        <thead>
                            <tr>
                                <th scope="col">User</th>
                                <th scope="col">Role</th>
                                <th scope="col">Workplace</th>
                                <th scope="col">Phone</th>
                                <th scope="col">Registered</th>
                                <th scope="col">Actions</th>
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
                                                <span class="badge badge--neutral">You</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="users-table__sub"><?= usersEscape($usersItem['email']) ?></span>
                                    </th>
                                    <td><span class="badge badge--<?= usersEscape($usersRoleBadges[$usersItem['role']]) ?>"><?= usersEscape($usersRoles[$usersItem['role']]) ?></span></td>
                                    <td class="users-table__workplace"><?= $usersWorkplace !== '' ? usersEscape($usersWorkplace) : '<span class="users-table__none">Not applicable</span>' ?></td>
                                    <td class="users-table__nowrap"><?= $usersItem['phone'] !== null ? usersEscape($usersItem['phone']) : '<span class="users-table__none">Not provided</span>' ?></td>
                                    <td class="users-table__nowrap"><time datetime="<?= usersEscape($usersCreated->format('Y-m-d')) ?>"><?= usersEscape($usersCreated->format('j M Y')) ?></time></td>
                                    <td>
                                        <div class="users-table__actions">
                                            <a class="btn btn--outline btn--sm"
                                               href="<?= usersEscape(usersUrl($usersState, ['form' => 'edit', 'user_id' => $usersId]) . '#users-form') ?>"
                                               aria-label="Edit <?= usersEscape($usersFullName) ?>">Edit</a>
                                            <form method="post" data-users-delete data-users-name="<?= usersEscape($usersFullName) ?>"
                                                  action="<?= usersEscape('../backend/users.php' . usersUrl(['role' => $usersRole])) ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= (int) $usersId ?>">
                                                <button type="submit" class="btn btn--outline btn--sm users-table__delete"
                                                        aria-label="Delete <?= usersEscape($usersFullName) ?>"
                                                        <?= $usersId === $usersCurrentUserId ? 'disabled title="You cannot delete your own account"' : '' ?>>Delete</button>
                                            </form>
                                        </div>
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
