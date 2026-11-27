<?php
declare(strict_types=1);

$chnProjectRoot = dirname(__DIR__);
require_once $chnProjectRoot . '/backend/auth.php';
require_once $chnProjectRoot . '/backend/db.php';
require_once $chnProjectRoot . '/include/i18n.php';
require_once $chnProjectRoot . '/include/departments.php';

$sessionUser = requireRole(['admin'], false);
$usersCurrentUserId = $sessionUser['user_id'];

$usersRoles = [
    'patient'          => t('role.patient'),
    'doctor'           => t('role.doctor'),
    'healthcare_staff' => t('role.healthcare_staff'),
    'admin'            => t('role.admin'),
];

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
$usersCountries = require $chnProjectRoot . '/lib/countries.php';
$usersPinnedCountries = ['XN', 'TR'];

function usersEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function usersDate(int $days): string
{
    return (new DateTimeImmutable('today'))->modify(sprintf('%+d days', $days))->format('Y-m-d');
}

function usersUrl(array $state, array $changes = []): string
{
    $params = array_filter(
        array_merge($state, $changes),
        static fn ($value): bool => $value !== null && $value !== ''
    );

    return $params === [] ? 'user-management.php' : 'user-management.php?' . http_build_query($params);
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

function usersWorkplace(array $user, array $hospitals, array $departments): string
{
    $deptId = $user['department_id'] !== null ? (int) $user['department_id'] : null;
    $department = $deptId !== null && isset($departments[$deptId]) ? $departments[$deptId] : null;
    $hospitalId = $user['hospital_id'] !== null
        ? (int) $user['hospital_id']
        : ($department !== null && $department['hospital_id'] !== null ? (int) $department['hospital_id'] : null);

    if ($hospitalId === null || !isset($hospitals[$hospitalId])) {
        return '';
    }

    return ($department !== null ? chnDepartmentLabel($department['department_name']) . ' · ' : '') . $hospitals[$hospitalId];
}

$usersHospitals = [];
$usersDepartments = [];
$usersAll = [];

try {
    $pdo = chnGetPdo();

    $hospRows = $pdo->query('SELECT hospital_id, hospital_name FROM `hospital` ORDER BY hospital_name ASC')->fetchAll();
    foreach ($hospRows as $row) {
        $usersHospitals[(int) $row['hospital_id']] = (string) $row['hospital_name'];
    }

    $deptRows = $pdo->query('SELECT department_id, department_name, hospital_id FROM `department` ORDER BY department_name ASC')->fetchAll();
    foreach ($deptRows as $row) {
        $usersDepartments[(int) $row['department_id']] = [
            'department_name' => (string) $row['department_name'],
            'hospital_id' => $row['hospital_id'] !== null ? (int) $row['hospital_id'] : null,
        ];
    }

    $userRows = $pdo->query(
        'SELECT u.user_id, u.email, u.role, u.name, u.surname, u.gender, u.phone,
                u.birth_date, u.nationality, u.national_id, u.created_at,
                COALESCE(s.hospital_id, dd.hospital_id) AS hospital_id,
                COALESCE(d.department_id, s.department_id) AS department_id
         FROM `user` u
         LEFT JOIN `doctor` d ON d.user_id = u.user_id
         LEFT JOIN `department` dd ON dd.department_id = d.department_id
         LEFT JOIN `staff` s ON s.user_id = u.user_id
         ORDER BY u.created_at DESC, u.user_id DESC'
    )->fetchAll();

    foreach ($userRows as $row) {
        $uid = (int) $row['user_id'];
        $usersAll[$uid] = [
            'user_id' => $uid,
            'name' => (string) $row['name'],
            'surname' => (string) $row['surname'],
            'email' => (string) $row['email'],
            'role' => (string) $row['role'],
            'gender' => $row['gender'] !== null ? (string) $row['gender'] : '',
            'phone' => $row['phone'] !== null ? (string) $row['phone'] : null,
            'birth_date' => $row['birth_date'] !== null ? (string) $row['birth_date'] : '',
            'nationality' => $row['nationality'] !== null ? (string) $row['nationality'] : '',
            'national_id' => $row['national_id'] !== null ? (string) $row['national_id'] : '',
            'hospital_id' => $row['hospital_id'] !== null ? (int) $row['hospital_id'] : null,
            'department_id' => $row['department_id'] !== null ? (int) $row['department_id'] : null,
            'created_at' => (string) $row['created_at'],
        ];
    }
} catch (Throwable $e) {
    chnLogError('User management load error: ' . $e->getMessage());
}

$usersFilterRole = isset($_GET['filter_role']) && is_string($_GET['filter_role']) && isset($usersRoles[$_GET['filter_role']])
    ? $_GET['filter_role']
    : null;

$usersQuery = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$usersState = ['filter_role' => $usersFilterRole, 'q' => $usersQuery];
$usersShowForm = isset($_GET['form']) && $_GET['form'] === 'create';

$usersMatching = array_filter($usersAll, static function (array $user) use ($usersQuery): bool {
    return $usersQuery === ''
        || mb_stripos($user['name'] . ' ' . $user['surname'], $usersQuery) !== false
        || mb_stripos($user['email'], $usersQuery) !== false;
});

$usersCounts = array_fill_keys(array_keys($usersRoles), 0);
foreach ($usersMatching as $usersItem) {
    if (isset($usersCounts[$usersItem['role']])) {
        $usersCounts[$usersItem['role']] += 1;
    }
}

$usersRows = array_filter(
    $usersMatching,
    static fn (array $user): bool => $usersFilterRole === null || $user['role'] === $usersFilterRole
);

$chnPageTitle = t('users.page_title');
$chnUserName = trim($sessionUser['name'] . ' ' . $sessionUser['surname']);
$chnUserRole = 'admin';
?>
<!DOCTYPE html>
<html lang="<?= usersEscape(chnLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | <?= te('users.page_title') ?></title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime($chnProjectRoot . '/css/style.css') ?>">
    <link rel="stylesheet" href="../css/user-management.css?v=<?= filemtime($chnProjectRoot . '/css/user-management.css') ?>">
    <script src="../js/sidebar.js?v=<?= filemtime($chnProjectRoot . '/js/sidebar.js') ?>" defer></script>
    <script src="../js/topbar.js?v=<?= filemtime($chnProjectRoot . '/js/topbar.js') ?>" defer></script>
    <script src="../js/user-management.js?v=<?= filemtime($chnProjectRoot . '/js/user-management.js') ?>" defer></script>
</head>
<body>
<?php
require $chnProjectRoot . '/include/left-sidebar.php';
require $chnProjectRoot . '/include/top-bar.php';
?>

<main class="content" id="main-content" tabindex="-1">
    <div class="content__inner users">
        <div class="page-header">
            <div>
                <h1 class="page-header__title"><?= te('users.title') ?></h1>
                <p class="page-header__subtitle"><?= te('users.subtitle_admin') ?></p>
            </div>
            <div class="page-header__actions">
                <button type="button" class="btn btn--primary" id="users-open-create"><?= te('users.create_account') ?></button>
            </div>
        </div>

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
               'gender_required'           => 'register.gender_required',
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
               'create_title'              => 'users.create_account',
               'create_subtitle'           => 'users.form_subtitle',
               'create_submit'             => 'users.create_account',
               'create_notice'             => 'users.create_notice',
               'edit_title'                => 'users.edit_account',
               'edit_subtitle'             => 'users.edit_subtitle',
               'edit_submit'               => 'users.save_changes',
               'update_notice'             => 'users.update_notice',
               'delete_confirm'            => 'users.delete_confirm',
               'delete_notice'             => 'users.delete_notice',
               'request_failed'            => 'users.request_failed',
           ]) ?>"></p>

        <section class="card users-form-card" id="users-form" aria-labelledby="users-form-title" <?= $usersShowForm ? '' : 'hidden' ?>>
            <header class="card__header">
                <div>
                    <h2 class="card__title" id="users-form-title"><?= te('users.create_account') ?></h2>
                    <p class="card__subtitle" id="users-form-subtitle"><?= te('users.form_subtitle') ?></p>
                </div>
            </header>

            <form class="users-form" id="users-account-form" method="post" novalidate
                  data-create-url="../backend/create_user.php"
                  data-update-url="../backend/update_user.php"
                  data-mode="create">
                <input type="hidden" name="user_id" id="users-user-id" value="">

                <div class="users-form__grid">
                    <div class="form-group">
                        <label class="form-label" for="users-user-role"><?= te('users.role') ?></label>
                        <select class="form-control" id="users-user-role" name="user_role" required
                                aria-describedby="users-user-role-error">
                            <option value=""><?= te('users.role_placeholder') ?></option>
                            <?php foreach ($usersRoles as $usersRoleKey => $usersRoleLabel): ?>
                                <option value="<?= usersEscape($usersRoleKey) ?>"><?= usersEscape($usersRoleLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-error" id="users-user-role-error" role="alert" hidden></p>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="users-first-name"><?= te('register.first_name') ?></label>
                        <input class="form-control" type="text" id="users-first-name" name="first_name" maxlength="50" required
                               autocomplete="off" aria-describedby="users-first-name-error">
                        <p class="form-error" id="users-first-name-error" role="alert" hidden></p>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="users-last-name"><?= te('register.last_name') ?></label>
                        <input class="form-control" type="text" id="users-last-name" name="last_name" maxlength="50" required
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
                                    <?= usersEscape(chnDepartmentLabel($usersDepartment['department_name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-error" id="users-department-id-error" role="alert" hidden></p>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="users-phone"><?= te('users.phone') ?></label>
                        <input class="form-control" type="tel" id="users-phone" name="phone" maxlength="16" inputmode="tel" required
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
                        <label class="form-label" for="users-gender"><?= te('register.gender') ?></label>
                        <select class="form-control" id="users-gender" name="gender" required aria-describedby="users-gender-error">
                            <option value=""><?= te('register.gender_placeholder') ?></option>
                            <option value="female"><?= te('register.gender_female') ?></option>
                            <option value="male"><?= te('register.gender_male') ?></option>
                        </select>
                        <p class="form-error" id="users-gender-error" role="alert" hidden></p>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="users-nationality"><?= te('register.nationality') ?></label>
                        <select class="form-control" id="users-nationality" name="nationality" required aria-describedby="users-nationality-error">
                            <option value=""><?= te('users.nationality_placeholder') ?></option>
                            <?php foreach ($usersPinnedCountries as $usersCode): ?>
                                <?php if (isset($usersCountries[$usersCode])): ?>
                                    <option value="<?= usersEscape($usersCode) ?>"><?= usersEscape($usersCountries[$usersCode]) ?></option>
                                <?php endif; ?>
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
                    <button type="button" class="btn btn--outline" id="users-cancel-form"><?= te('users.cancel') ?></button>
                    <button type="submit" class="btn btn--primary" id="users-submit-form"><?= te('users.create_account') ?></button>
                </div>
            </form>
        </section>

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

            <form class="users-search" method="get" action="user-management.php#users-list" role="search">
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
                                $usersEditPayload = json_encode([
                                    'user_id' => $usersId,
                                    'role' => $usersItem['role'],
                                    'first_name' => $usersItem['name'],
                                    'last_name' => $usersItem['surname'],
                                    'email' => $usersItem['email'],
                                    'phone' => $usersItem['phone'] ?? '',
                                    'birth_date' => $usersItem['birth_date'],
                                    'gender' => $usersItem['gender'],
                                    'nationality' => $usersItem['nationality'],
                                    'national_id' => $usersItem['national_id'],
                                    'hospital_id' => $usersItem['hospital_id'],
                                    'department_id' => $usersItem['department_id'],
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
                                <td><span class="badge badge--<?= usersEscape($usersRoleBadges[$usersItem['role']] ?? 'neutral') ?>"><?= usersEscape($usersRoles[$usersItem['role']] ?? $usersItem['role']) ?></span></td>
                                <td class="users-table__workplace"><?= $usersWorkplace !== '' ? usersEscape($usersWorkplace) : '<span class="users-table__none">' . te('users.not_applicable') . '</span>' ?></td>
                                <td class="users-table__nowrap"><?= $usersItem['phone'] !== null && $usersItem['phone'] !== '' ? usersEscape($usersItem['phone']) : '<span class="users-table__none">' . te('users.not_provided') . '</span>' ?></td>
                                <td class="users-table__nowrap"><time datetime="<?= usersEscape($usersCreated->format('Y-m-d')) ?>"><?= usersEscape(chnDate($usersCreated, 'medium')) ?></time></td>
                                <td>
                                    <div class="users-table__actions">
                                        <button type="button" class="btn btn--outline btn--sm"
                                                data-users-edit
                                                data-user="<?= usersEscape((string) $usersEditPayload) ?>"
                                                aria-label="<?= te('users.edit_label', ['name' => $usersFullName]) ?>"><?= te('users.edit') ?></button>
                                        <form method="post" data-users-delete data-users-name="<?= usersEscape($usersFullName) ?>"
                                              data-delete-url="../backend/delete_user.php">
                                            <input type="hidden" name="user_id" value="<?= (int) $usersId ?>">
                                            <button type="submit" class="btn btn--outline btn--sm users-table__delete"
                                                    aria-label="<?= te('users.delete_label', ['name' => $usersFullName]) ?>"
                                                    <?= $usersId === $usersCurrentUserId ? 'disabled title="' . te('users.delete_self') . '"' : '' ?>><?= te('users.delete') ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
</body>
</html>
