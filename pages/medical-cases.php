<?php
declare(strict_types=1);

$chnProjectRoot = dirname(__DIR__);
require_once $chnProjectRoot . '/backend/auth.php';
require_once $chnProjectRoot . '/backend/db.php';
require_once $chnProjectRoot . '/include/i18n.php';
require_once $chnProjectRoot . '/include/departments.php';

$sessionUser = requireRole(['patient', 'doctor', 'healthcare_staff', 'admin'], false);
$role = $sessionUser['role'];
$userId = $sessionUser['user_id'];

$hospitals = [];
$departments = [];
$doctors = [];
$cases = [];
$scope = ['is_island_wide' => true, 'hospital_id' => null, 'department_id' => null, 'hospital_name' => null, 'department_name' => null];

try {
    $pdo = chnGetPdo();
    $scope = chnGetUserHospitalScope($pdo, $sessionUser);

    foreach ($pdo->query('SELECT hospital_id, hospital_name FROM `hospital` ORDER BY hospital_name ASC')->fetchAll() as $row) {
        $hospitals[(int) $row['hospital_id']] = (string) $row['hospital_name'];
    }

    foreach ($pdo->query('SELECT department_id, department_name, hospital_id FROM `department` ORDER BY department_name ASC')->fetchAll() as $row) {
        $departments[(int) $row['department_id']] = [
            'department_id' => (int) $row['department_id'],
            'department_name' => (string) $row['department_name'],
            'department_label' => chnDepartmentLabel((string) $row['department_name']),
            'hospital_id' => (int) $row['hospital_id'],
        ];
    }

    foreach ($pdo->query(
        'SELECT d.doctor_id, d.department_id, u.name, u.surname
         FROM `doctor` d
         INNER JOIN `user` u ON u.user_id = d.user_id
         ORDER BY u.name ASC, u.surname ASC'
    )->fetchAll() as $row) {
        $doctors[] = [
            'doctor_id' => (int) $row['doctor_id'],
            'department_id' => (int) $row['department_id'],
            'doctor_name' => trim((string) $row['name'] . ' ' . (string) $row['surname']),
        ];
    }

    if ($role !== 'admin') {
        $baseSql = "SELECT c.case_id, c.patient_id, c.department_id, c.doctor_id,
                           c.case_description, c.tier_color, c.case_state, c.created_at,
                           dep.department_name, dep.hospital_id, h.hospital_name,
                           CONCAT(pu.name, ' ', pu.surname) AS patient_name,
                           CONCAT(du.name, ' ', du.surname) AS doctor_name
                    FROM `case` c
                    INNER JOIN `patient` p ON p.patient_id = c.patient_id
                    INNER JOIN `user` pu ON pu.user_id = p.user_id
                    INNER JOIN `department` dep ON dep.department_id = c.department_id
                    INNER JOIN `hospital` h ON h.hospital_id = dep.hospital_id
                    LEFT JOIN `doctor` d ON d.doctor_id = c.doctor_id
                    LEFT JOIN `user` du ON du.user_id = d.user_id";

        $where = '';
        $params = [];
        if ($role === 'patient') {
            $where = ' WHERE p.user_id = :user_id';
            $params[':user_id'] = $userId;
        } elseif ($role === 'doctor') {
            if ($scope['department_id'] !== null) {
                $where = ' WHERE c.department_id = :dept_id';
                $params[':dept_id'] = $scope['department_id'];
            } else {
                $where = ' WHERE 1 = 0';
            }
        } elseif ($role === 'healthcare_staff') {
            if ($scope['hospital_id'] !== null) {
                $where = ' WHERE dep.hospital_id = :hosp_id';
                $params[':hosp_id'] = $scope['hospital_id'];
            } else {
                $where = ' WHERE 1 = 0';
            }
        }

        $stmt = $pdo->prepare($baseSql . $where . ' ORDER BY c.created_at DESC, c.case_id DESC');
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $cases[] = [
                'case_id' => (int) $row['case_id'],
                'patient_id' => (int) $row['patient_id'],
                'patient_name' => (string) $row['patient_name'],
                'department_id' => (int) $row['department_id'],
                'department_name' => (string) $row['department_name'],
                'department_label' => chnDepartmentLabel((string) $row['department_name']),
                'hospital_id' => (int) $row['hospital_id'],
                'hospital_name' => (string) $row['hospital_name'],
                'doctor_id' => $row['doctor_id'] !== null ? (int) $row['doctor_id'] : null,
                'doctor_name' => $row['doctor_name'] !== null ? (string) $row['doctor_name'] : null,
                'case_description' => (string) ($row['case_description'] ?? ''),
                'tier_color' => (string) $row['tier_color'],
                'case_state' => (string) $row['case_state'],
                'created_at' => (string) $row['created_at'],
            ];
        }
    }
} catch (Throwable $e) {
    chnLogError('Medical cases load error: ' . $e->getMessage());
}

$view = [
    'patient' => ['title' => t('cases.patient_title'), 'subtitle' => t('cases.patient_subtitle'), 'overview' => t('cases.patient_overview'), 'action' => t('cases.patient_action'), 'action_aria' => 'cases.patient_action_aria'],
    'doctor' => ['title' => t('cases.doctor_title'), 'subtitle' => t('cases.doctor_subtitle'), 'overview' => t('cases.doctor_overview'), 'action' => t('cases.doctor_action'), 'action_aria' => 'cases.doctor_action_aria'],
    'healthcare_staff' => ['title' => t('cases.staff_title'), 'subtitle' => t('cases.staff_subtitle'), 'overview' => t('cases.staff_overview'), 'action' => t('cases.staff_action'), 'action_aria' => 'cases.staff_action_aria'],
    'admin' => ['title' => t('cases.admin_title'), 'subtitle' => t('cases.admin_subtitle'), 'overview' => '', 'action' => '', 'action_aria' => ''],
][$role];

$canViewClinicalDetails = in_array($role, ['patient', 'doctor', 'healthcare_staff'], true);
$canSearchClinicalText = in_array($role, ['patient', 'doctor'], true);

function mcEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mcParam(string $key): string
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
}

function mcDate(?string $value): string
{
    if (!$value) {
        return t('cases.not_available');
    }
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    return $date ? chnDate($date, 'medium_time') : t('cases.not_available');
}

function mcUrl(array $params = [], string $hash = ''): string
{
    $clean = array_filter($params, static fn ($v): bool => $v !== null && $v !== '');
    $url = 'medical-cases.php' . ($clean !== [] ? '?' . http_build_query($clean) : '');
    return $hash !== '' ? $url . '#' . $hash : $url;
}

$stateLabels = ['open' => t('cases.state_open'), 'closed' => t('cases.state_closed')];
$query = mcParam('q');
$status = in_array(mcParam('status'), ['open', 'closed'], true) ? mcParam('status') : '';
$hospitalFilter = mcParam('hospital');
if ($hospitalFilter !== '' && !isset($hospitals[(int) $hospitalFilter])) {
    $hospitalFilter = '';
}
$departmentFilter = mcParam('department');
$filterDepartments = [];
foreach ($cases as $c) {
    $filterDepartments[$c['department_id']] = $c['department_label'] . ' · ' . $c['hospital_name'];
}
if ($departmentFilter !== '' && !isset($filterDepartments[(int) $departmentFilter])) {
    $departmentFilter = '';
}

$filtered = array_values(array_filter($cases, static function (array $case) use ($query, $status, $hospitalFilter, $departmentFilter, $canSearchClinicalText, $role): bool {
    $search = implode(' ', [
        '#' . $case['case_id'],
        $case['department_label'],
        $case['hospital_name'],
        $case['doctor_name'] ?? '',
        $canSearchClinicalText ? $case['case_description'] : '',
        $role === 'doctor' ? $case['patient_name'] : '',
        $role !== 'patient' ? 'P-' . $case['patient_id'] : '',
    ]);
    return ($status === '' || $case['case_state'] === $status)
        && ($hospitalFilter === '' || (string) $case['hospital_id'] === $hospitalFilter)
        && ($departmentFilter === '' || (string) $case['department_id'] === $departmentFilter)
        && ($query === '' || mb_stripos($search, $query) !== false);
}));

$selected = null;
foreach ($filtered as $case) {
    if ((string) $case['case_id'] === mcParam('case')) {
        $selected = $case;
        break;
    }
}

$selectedAppointments = [];
$selectedTreatments = [];
$selectedDiagnoses = [];

if ($selected !== null && $canViewClinicalDetails && isset($pdo)) {
    try {
        $apptStmt = $pdo->prepare(
            "SELECT a.appointment_id, a.appointment_date, a.appointment_time, a.appointment_status,
                    CONCAT(u.name, ' ', u.surname) AS doctor_name, dep.department_name, h.hospital_name
             FROM `appointment` a
             INNER JOIN `doctor` d ON d.doctor_id = a.doctor_id
             INNER JOIN `user` u ON u.user_id = d.user_id
             INNER JOIN `department` dep ON dep.department_id = a.department_id
             INNER JOIN `hospital` h ON h.hospital_id = dep.hospital_id
             WHERE a.case_id = :case_id
             ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.appointment_id DESC"
        );
        $apptStmt->execute([':case_id' => $selected['case_id']]);
        $selectedAppointments = $apptStmt->fetchAll();

        $treatStmt = $pdo->prepare(
            "SELECT t.treatment_id, t.treatment_desc, t.treatment_date, t.treatment_status, t.completed_at,
                    CONCAT(u.name, ' ', u.surname) AS doctor_name
             FROM `treatment` t
             LEFT JOIN `doctor` d ON d.doctor_id = t.doctor_id
             LEFT JOIN `user` u ON u.user_id = d.user_id
             WHERE t.case_id = :case_id
             ORDER BY t.treatment_status ASC, t.treatment_date DESC, t.treatment_id DESC"
        );
        $treatStmt->execute([':case_id' => $selected['case_id']]);
        $selectedTreatments = $treatStmt->fetchAll();

        $diagStmt = $pdo->prepare(
            "SELECT diag.diagnosis_id, diag.diagnosis_text, diag.icd_code, diag.diagnosed_at,
                    CONCAT(u.name, ' ', u.surname) AS doctor_name
             FROM `diagnosis` diag
             LEFT JOIN `doctor` d ON d.doctor_id = diag.doctor_id
             LEFT JOIN `user` u ON u.user_id = d.user_id
             WHERE diag.case_id = :case_id
             ORDER BY diag.diagnosed_at DESC, diag.diagnosis_id DESC"
        );
        $diagStmt->execute([':case_id' => $selected['case_id']]);
        $selectedDiagnoses = $diagStmt->fetchAll();
    } catch (Throwable $e) {
        chnLogError('Selected case history load error: ' . $e->getMessage());
    }
}

$canAddDiagnosis = $role === 'doctor' && $selected !== null && $selected['case_state'] === 'open';
$appointmentBadges = ['confirmed' => 'success', 'completed' => 'neutral', 'cancelled' => 'danger'];

$scopeContextParts = array_filter([
    $scope['hospital_name'] ?? null,
    !empty($scope['department_name']) ? chnDepartmentLabel((string) $scope['department_name']) : null,
]);
$scopeContextText = implode(' · ', $scopeContextParts);

$chnPageTitle = t('cases.page_title');
$chnUserName = trim($sessionUser['name'] . ' ' . $sessionUser['surname']);
$chnUserRole = $role;
?>
<!DOCTYPE html>
<html lang="<?= mcEscape(chnLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= mcEscape($view['title']) ?> | CHN</title>

    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime($chnProjectRoot . '/css/style.css') ?>">
    <link rel="stylesheet" href="../css/medical-cases.css?v=<?= filemtime($chnProjectRoot . '/css/medical-cases.css') ?>">

    <script src="../js/sidebar.js?v=<?= filemtime($chnProjectRoot . '/js/sidebar.js') ?>" defer></script>
    <script src="../js/topbar.js?v=<?= filemtime($chnProjectRoot . '/js/topbar.js') ?>" defer></script>
    <script src="../js/medical-cases.js?v=<?= filemtime($chnProjectRoot . '/js/medical-cases.js') ?>" defer></script>
</head>
<body>
<?php require $chnProjectRoot . '/include/left-sidebar.php'; ?>
<?php require $chnProjectRoot . '/include/top-bar.php'; ?>
<main class="content medical-cases" id="main-content" tabindex="-1">
<div class="content__inner">
    <header class="page-header">
        <div>
            <h1 class="page-header__title"><?= mcEscape($view['title']) ?></h1>
            <p class="page-header__subtitle">
                <?= mcEscape($view['subtitle']) ?>
                <?php if ($scopeContextText !== ''): ?>
                    · <strong><?= mcEscape($scopeContextText) ?></strong>
                <?php endif; ?>
            </p>
        </div>
    </header>

    <p class="alert alert--info medical-cases__notice" id="case-result-notice" role="status" tabindex="-1" hidden
       data-messages="<?= chnJsMessages([
           'selectHospital' => 'cases.select_hospital',
           'selectDepartment' => 'cases.select_department',
           'selectDoctor' => 'cases.select_doctor',
           'doctorRequired' => 'cases.doctor_select_required',
           'noDoctors' => 'cases.no_doctors',
           'formInvalid' => 'cases.form_invalid',
           'createdSuccess' => 'cases.created_success',
           'updatedSuccess' => 'cases.updated_success',
           'requestFailed' => 'cases.request_failed',
           'open' => 'cases.state_open',
           'closed' => 'cases.state_closed',
           'green' => 'tier.green',
           'yellow' => 'tier.yellow',
           'orange' => 'tier.orange',
       ]) ?>"></p>

    <?php if (in_array($role, ['patient', 'doctor'], true)): ?>
    <section class="card medical-cases__creation" id="create-case">
        <header class="card__header">
            <div>
                <h2 class="card__title"><?= te('cases.create_case') ?></h2>
                <p class="card__subtitle"><?= te('cases.create_subtitle') ?></p>
            </div>
        </header>
        <form id="create-case-form" method="post" action="../backend/create_case.php" data-create-url="../backend/create_case.php" novalidate>
            <div class="medical-cases__create-grid">
            <?php if ($role === 'doctor'): ?>
                <div>
                    <label class="form-label" for="create-national_id"><?= te('cases.national_id') ?></label>
                    <input class="form-control" id="create-national_id" name="national_id" required minlength="3" maxlength="50" autocomplete="off">
                    <p class="form-hint"><?= te('cases.national_id_hint') ?></p>
                </div>
                <div>
                    <p class="form-label"><?= te('cases.hospital') ?> · <?= te('cases.department') ?></p>
                    <p class="form-hint"><strong><?= mcEscape($scopeContextText !== '' ? $scopeContextText : t('cases.not_available')) ?></strong></p>
                </div>
                <div>
                    <p class="form-label"><?= te('cases.responsible_doctor') ?></p>
                    <p class="form-hint"><?= mcEscape($chnUserName) ?></p>
                </div>
            <?php else: ?>
                <div>
                    <label class="form-label" for="create-hospital_id"><?= te('cases.hospital') ?></label>
                    <select class="form-control" id="create-hospital_id" name="hospital_id" required>
                        <option value=""><?= te('cases.select_hospital') ?></option>
                        <?php foreach ($hospitals as $hid => $hName): ?>
                            <option value="<?= (int) $hid ?>"><?= mcEscape($hName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="create-department_id"><?= te('cases.department') ?></label>
                    <select class="form-control" id="create-department_id" name="department_id" required disabled>
                        <option value=""><?= te('cases.select_department') ?></option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= (int) $dept['department_id'] ?>" data-hospital="<?= (int) $dept['hospital_id'] ?>">
                                <?= mcEscape($dept['department_label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="create-doctor_id"><?= te('cases.preferred_doctor') ?></label>
                    <select class="form-control" id="create-doctor_id" name="doctor_id" required disabled>
                        <option value=""><?= te('cases.select_doctor') ?></option>
                        <?php foreach ($doctors as $doc): ?>
                            <option value="<?= (int) $doc['doctor_id'] ?>" data-department="<?= (int) $doc['department_id'] ?>">
                                <?= mcEscape($doc['doctor_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="doctor-availability" class="form-hint"><?= te('cases.doctor_hint') ?></p>
                </div>
            <?php endif; ?>
                <div class="medical-cases__create-description">
                    <label class="form-label" for="create-case_description"><?= te('cases.case_description') ?></label>
                    <textarea class="form-control" id="create-case_description" name="case_description" required rows="5"></textarea>
                    <p class="form-hint"><?= te('cases.case_description_hint') ?></p>
                </div>
            </div>
            <button class="btn btn--primary" type="submit" id="create-case-submit"><?= te('cases.save') ?></button>
        </form>
    </section>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
    <section class="card"><h2 class="card__title"><?= te('cases.admin_access_title') ?></h2><p><?= te('cases.admin_access_text') ?></p></section>
    <?php else: ?>
    <div class="grid grid--2 medical-cases__summary" aria-label="<?= te('cases.summary_label') ?>">
        <?php foreach (['open' => t('cases.active_cases'), 'closed' => t('cases.closed_cases')] as $state => $label): ?>
        <div class="stat-card"><div>
            <p class="stat-card__label"><?= mcEscape($label) ?></p>
            <p class="stat-card__value" data-case-count="<?= $state ?>" data-initial-count="<?= count(array_filter($cases, static fn (array $case): bool => $case['case_state'] === $state)) ?>">
                <?= count(array_filter($cases, static fn (array $case): bool => $case['case_state'] === $state)) ?>
            </p>
        </div></div>
        <?php endforeach; ?>
    </div>

    <section class="card" aria-labelledby="cases-title">
        <header class="card__header">
            <div>
                <h2 class="card__title" id="cases-title"><?= mcEscape($view['overview']) ?></h2>
                <p class="card__subtitle"><?= $role === 'healthcare_staff' ? te('cases.overview_staff') : te('cases.overview_clinical') ?></p>
            </div>
        </header>
        <form method="get" action="medical-cases.php" class="medical-cases__filters" role="search" aria-label="<?= te('cases.filter_label') ?>">
            <div>
                <label class="form-label" for="case-search"><?= te('cases.search_label') ?></label>
                <input class="form-control" type="search" name="q" id="case-search" value="<?= mcEscape($query) ?>" placeholder="<?= $canSearchClinicalText ? te('cases.search_placeholder_clinical') : te('cases.search_placeholder_staff') ?>">
            </div>
            <div>
                <label class="form-label" for="case-status"><?= te('cases.status_label') ?></label>
                <select class="form-control" name="status" id="case-status">
                    <option value=""><?= te('cases.all_statuses') ?></option>
                    <?php foreach ($stateLabels as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= mcEscape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($role === 'patient'): ?>
            <div>
                <label class="form-label" for="case-hospital"><?= te('cases.hospital') ?></label>
                <select class="form-control" name="hospital" id="case-hospital">
                    <option value=""><?= te('cases.all_hospitals') ?></option>
                    <?php foreach ($hospitals as $hid => $hName): ?>
                        <option value="<?= (int) $hid ?>" <?= $hospitalFilter === (string) $hid ? 'selected' : '' ?>><?= mcEscape($hName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div>
                <label class="form-label" for="case-department"><?= te('cases.department') ?></label>
                <select class="form-control" name="department" id="case-department">
                    <option value=""><?= te('cases.all_departments') ?></option>
                    <?php foreach ($filterDepartments as $id => $label): ?>
                        <option value="<?= (int) $id ?>" <?= $departmentFilter === (string) $id ? 'selected' : '' ?>><?= mcEscape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="medical-cases__filter-actions">
                <button class="btn btn--primary" type="submit"><?= te('cases.apply_filters') ?></button>
                <a class="btn btn--outline" href="medical-cases.php"><?= te('cases.reset') ?></a>
            </div>
        </form>

        <p class="card__subtitle medical-cases__count"><?= mcEscape(tn('cases.count', count($cases), ['shown' => count($filtered)])) ?></p>
        <?php if ($filtered): ?>
        <div class="table-responsive" tabindex="0" role="region" aria-label="<?= te('cases.table_label') ?>">
        <table class="table"><caption class="medical-cases__caption"><?= te('cases.caption') ?></caption>
        <thead>
            <tr>
                <th scope="col"><?= te('cases.col_case_id') ?></th>
                <?php if ($role !== 'patient'): ?>
                    <th scope="col"><?= $role === 'doctor' ? te('cases.col_patient') : te('cases.col_patient_ref') ?></th>
                <?php endif; ?>
                <th scope="col"><?= te('cases.department') ?></th>
                <th scope="col"><?= te('cases.responsible_doctor') ?></th>
                <th scope="col"><?= te('cases.created') ?></th>
                <th scope="col"><?= te('cases.status_label') ?></th>
                <th scope="col"><?= te('cases.triage') ?></th>
                <th scope="col"><?= te('cases.details') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($filtered as $case): ?>
        <tr data-case-id="<?= (int) $case['case_id'] ?>" class="<?= $selected && $selected['case_id'] === $case['case_id'] ? 'medical-cases__selected' : '' ?>">
            <th scope="row">#<?= (int) $case['case_id'] ?></th>
            <?php if ($role !== 'patient'): ?>
                <td><?= $role === 'doctor' ? mcEscape($case['patient_name']) . '<br>' : '' ?>P-<?= (int) $case['patient_id'] ?></td>
            <?php endif; ?>
            <td><?= mcEscape($case['department_label'] . ' · ' . $case['hospital_name']) ?></td>
            <td><?= mcEscape($case['doctor_name'] ?? t('cases.not_assigned')) ?></td>
            <td><?= mcEscape(mcDate($case['created_at'])) ?></td>
            <td><span data-case-state class="badge <?= $case['case_state'] === 'open' ? 'badge--info' : 'badge--neutral' ?>"><?= mcEscape($stateLabels[$case['case_state']] ?? $case['case_state']) ?></span></td>
            <td><span data-case-triage class="badge medical-cases__triage medical-cases__triage--<?= mcEscape($case['tier_color']) ?>"><?= te('tier.' . $case['tier_color']) ?></span></td>
            <td>
                <a class="btn btn--outline btn--sm"
                   href="<?= mcEscape(mcUrl(['q' => $query, 'status' => $status, 'hospital' => $hospitalFilter, 'department' => $departmentFilter, 'case' => $case['case_id']], 'case-details')) ?>"
                   <?= $selected && $selected['case_id'] === $case['case_id'] ? 'aria-current="true"' : '' ?>
                   aria-label="<?= te($view['action_aria'], ['id' => '#' . $case['case_id']]) ?>">
                    <?= mcEscape($view['action']) ?>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php else: ?>
        <div class="medical-cases__empty">
            <h3><?= te('cases.no_match_title') ?></h3>
            <p><?= te('cases.no_match_text') ?></p>
            <a class="btn btn--outline" href="medical-cases.php"><?= te('cases.reset_filters') ?></a>
        </div>
        <?php endif; ?>
    </section>

    <section class="card medical-cases__details" id="case-details" aria-labelledby="case-details-title" tabindex="-1">
        <header class="card__header">
            <h2 class="card__title" id="case-details-title"><?= $selected ? te('cases.case_heading', ['id' => '#' . $selected['case_id']]) : te('cases.case_details') ?></h2>
        </header>
        <?php if ($selected): ?>
        <dl class="medical-cases__metadata">
            <?php if ($role !== 'patient'): ?>
                <div><dt><?= $role === 'healthcare_staff' ? te('cases.col_patient_ref') : te('cases.col_patient') ?></dt><dd><?= $role === 'doctor' ? mcEscape($selected['patient_name']) . ' · ' : '' ?>P-<?= (int) $selected['patient_id'] ?></dd></div>
            <?php endif; ?>
            <div><dt><?= te('cases.hospital') ?> · <?= te('cases.department') ?></dt><dd><?= mcEscape($selected['department_label'] . ' · ' . $selected['hospital_name']) ?></dd></div>
            <div><dt><?= te('cases.responsible_doctor') ?></dt><dd><?= mcEscape($selected['doctor_name'] ?? t('cases.not_assigned')) ?></dd></div>
            <div><dt><?= te('cases.created') ?></dt><dd><?= mcEscape(mcDate($selected['created_at'])) ?></dd></div>
            <div><dt><?= te('cases.status_label') ?></dt><dd data-current-status>
            <?php if ($role === 'doctor'): ?>
            <details class="medical-cases__status-editor">
                <summary aria-label="<?= te('cases.edit_status') ?>">
                    <span><?= mcEscape($stateLabels[$selected['case_state']] ?? $selected['case_state']) ?></span>
                    <span class="medical-cases__edit-pencil" aria-hidden="true">&#9998;</span>
                    <span class="medical-cases__edit-label"><?= te('cases.edit') ?></span>
                </summary>
                <form method="post" data-case-update data-update-url="../backend/update_case.php" data-original-state="<?= mcEscape($selected['case_state']) ?>">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="case_id" value="<?= (int) $selected['case_id'] ?>">
                    <label class="form-label" for="update-status"><?= te('cases.update_status_label') ?></label>
                    <select class="form-control" id="update-status" name="case_state" required>
                        <?php foreach ($stateLabels as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $selected['case_state'] === $value ? 'selected' : '' ?>><?= mcEscape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn--primary" type="submit"><?= te('cases.update_status') ?></button>
                </form>
            </details>
            <?php else: ?>
                <?= mcEscape($stateLabels[$selected['case_state']] ?? $selected['case_state']) ?>
            <?php endif; ?>
            </dd></div>
            <div><dt><?= te('cases.triage') ?></dt><dd data-current-triage>
            <?php if (in_array($role, ['doctor', 'healthcare_staff'], true)): ?>
            <details class="medical-cases__triage-editor">
                <summary aria-label="<?= te('cases.edit_triage') ?>">
                    <span><?= te('tier.' . $selected['tier_color']) ?></span>
                    <span class="medical-cases__edit-pencil" aria-hidden="true">&#9998;</span>
                    <span class="medical-cases__edit-label"><?= te('cases.edit') ?></span>
                </summary>
                <form method="post" data-case-update data-update-url="../backend/update_case.php" data-original-state="<?= mcEscape($selected['case_state']) ?>">
                    <input type="hidden" name="action" value="update_triage">
                    <input type="hidden" name="case_id" value="<?= (int) $selected['case_id'] ?>">
                    <label class="form-label" for="update-triage"><?= te('cases.update_triage_label') ?></label>
                    <select class="form-control" name="tier_color" id="update-triage" required>
                        <?php foreach (['green', 'yellow', 'orange'] as $color): ?>
                            <option value="<?= $color ?>" <?= $selected['tier_color'] === $color ? 'selected' : '' ?>><?= te('tier.' . $color) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn--primary" type="submit"><?= te('cases.update_triage') ?></button>
                </form>
            </details>
            <?php else: ?>
                <?= te('tier.' . $selected['tier_color']) ?>
            <?php endif; ?>
            </dd></div>
        </dl>

        <?php if ($canViewClinicalDetails): ?>
        <h3 class="medical-cases__heading"><?= te('cases.case_description') ?></h3>
        <p class="medical-cases__description"><?= mcEscape($selected['case_description'] !== '' ? $selected['case_description'] : t('cases.no_description')) ?></p>

        <section class="medical-cases__appointments" aria-labelledby="appointment-history-title">
            <div class="medical-cases__appointments-header">
                <div>
                    <h3 class="medical-cases__heading" id="appointment-history-title"><?= te('cases.appointment_history') ?></h3>
                    <p class="card__subtitle"><?= mcEscape(tn('cases.appointment_count', count($selectedAppointments))) ?></p>
                </div>
                <button class="btn btn--outline" type="button" id="toggle-appointments" aria-controls="case-appointments" aria-expanded="false" hidden>
                    <span data-show-appointments><?= te('cases.view_appointments') ?></span>
                    <span data-hide-appointments hidden><?= te('cases.hide_appointments') ?></span>
                    <img class="medical-cases__appointment-chevron" src="../assets/icons/chevron-right.svg" width="16" height="16" alt="">
                </button>
            </div>
            <div id="case-appointments" hidden>
                <?php if ($selectedAppointments): ?>
                <ul class="medical-cases__appointment-list" role="list">
                    <?php foreach ($selectedAppointments as $appointment):
                        $date = new DateTimeImmutable($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
                        $appointmentStatus = $appointment['appointment_status'];
                    ?>
                    <li class="medical-cases__appointment">
                        <dl>
                            <div><dt><?= te('cases.appointment_id') ?></dt><dd>#<?= (int) $appointment['appointment_id'] ?></dd></div>
                            <div><dt><?= te('appointments.date') ?></dt><dd><time datetime="<?= $date->format('Y-m-d') ?>"><?= mcEscape(chnDate($date, 'medium')) ?></time></dd></div>
                            <div><dt><?= te('cases.appointment_time') ?></dt><dd><time datetime="<?= $date->format('H:i') ?>"><?= $date->format('H:i') ?></time></dd></div>
                            <div><dt><?= te('appointments.col_doctor') ?></dt><dd><?= mcEscape($appointment['doctor_name']) ?></dd></div>
                            <div><dt><?= te('appointments.department') ?></dt><dd><?= mcEscape(chnDepartmentLabel((string) $appointment['department_name']) . ' · ' . $appointment['hospital_name']) ?></dd></div>
                            <div><dt><?= te('appointments.status') ?></dt><dd><span class="badge badge--<?= $appointmentBadges[$appointmentStatus] ?? 'neutral' ?>"><?= te('appointment_status.' . $appointmentStatus) ?></span></dd></div>
                        </dl>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="medical-cases__empty"><?= te('cases.no_appointments') ?></p>
                <?php endif; ?>
            </div>
        </section>

        <section class="medical-cases__diagnoses" aria-labelledby="treatments-title">
            <div class="medical-cases__diagnosis-header">
                <h3 class="medical-cases__heading" id="treatments-title"><?= te('cases.treatments') ?></h3>
                <?php if ($canAddDiagnosis): ?>
                <button class="btn btn--primary" type="button" id="open-treatment" aria-controls="add-treatment-form" aria-expanded="false" hidden><?= te('cases.add_treatment') ?></button>
                <?php endif; ?>
            </div>
            <?php if ($canAddDiagnosis): ?>
            <form class="medical-cases__diagnosis-form" id="add-treatment-form" method="post" hidden data-treatment-url="../backend/manage_treatment.php" novalidate>
                <input type="hidden" name="case_id" value="<?= (int) $selected['case_id'] ?>">
                <div class="medical-cases__diagnosis-field">
                    <label class="form-label" for="treatment-desc"><?= te('cases.add_treatment') ?></label>
                    <input class="form-control" id="treatment-desc" name="treatment_desc" type="text" maxlength="255" placeholder="<?= te('cases.treatment_placeholder') ?>" required>
                    <p class="form-error" id="treatment-desc-error" aria-live="polite"></p>
                </div>
                <div class="medical-cases__diagnosis-actions">
                    <button class="btn btn--primary" type="submit"><?= te('cases.add_treatment') ?></button>
                    <button class="btn btn--outline" type="button" id="cancel-treatment"><?= te('cases.diagnosis_cancel') ?></button>
                </div>
            </form>
            <?php endif; ?>
            <?php if ($selectedTreatments): ?>
            <ol class="medical-cases__history" id="treatment-history">
                <?php foreach ($selectedTreatments as $tItem): ?>
                <li><p><?= mcEscape($tItem['treatment_desc']) ?> <span class="badge <?= $tItem['treatment_status'] === 'active' ? 'badge--warning' : 'badge--neutral' ?>"><?= $tItem['treatment_status'] === 'active' ? te('cases.treatment_active') : te('cases.treatment_inactive') ?></span></p><p class="card__subtitle"><?= mcEscape(mcDate($tItem['treatment_date'])) ?> · <?= mcEscape($tItem['doctor_name'] ?? t('cases.doctor_unavailable')) ?></p><?php if ($canAddDiagnosis && $tItem['treatment_status'] === 'active'): ?><button type="button" class="btn btn--outline btn--sm" data-complete-treatment data-treatment-id="<?= (int) $tItem['treatment_id'] ?>"><?= te('cases.complete_treatment') ?></button><?php endif; ?></li>
                <?php endforeach; ?>
            </ol>
            <?php else: ?>
            <p class="medical-cases__empty"><?= te('cases.no_treatments') ?></p>
            <?php endif; ?>
        </section>

        <section class="medical-cases__diagnoses" aria-labelledby="diagnosis-history-title">
            <div class="medical-cases__diagnosis-header">
                <h3 class="medical-cases__heading" id="diagnosis-history-title"><?= te('cases.diagnosis_history') ?></h3>
                <?php if ($canAddDiagnosis): ?>
                <button class="btn btn--primary" type="button" id="open-diagnosis" aria-controls="add-diagnosis" aria-expanded="false" hidden><?= te('cases.add_diagnosis') ?></button>
                <?php endif; ?>
            </div>
            <?php if ($canAddDiagnosis): ?>
            <form class="medical-cases__diagnosis-form" id="add-diagnosis" method="post" hidden novalidate
                aria-labelledby="add-diagnosis-title"
                data-diagnosis-url="../backend/add_diagnosis.php"
                data-messages="<?= chnJsMessages([
                    'required' => 'cases.diagnosis_required', 'characters' => 'cases.diagnosis_characters',
                    'descriptionLength' => 'cases.diagnosis_description_length', 'icdLength' => 'cases.diagnosis_icd_length',
                    'added' => 'cases.diagnosis_saved', 'icd' => 'cases.icd_code', 'failed' => 'cases.request_failed',
                ]) ?>">
                <h4 class="medical-cases__heading" id="add-diagnosis-title"><?= te('cases.add_diagnosis') ?></h4>
                <input type="hidden" name="case_id" value="<?= (int) $selected['case_id'] ?>">
                <dl class="medical-cases__metadata">
                    <div><dt><?= te('cases.col_case_id') ?></dt><dd>#<?= (int) $selected['case_id'] ?></dd></div>
                    <div><dt><?= te('cases.col_patient') ?></dt><dd><?= mcEscape($selected['patient_name']) ?></dd></div>
                    <div><dt><?= te('cases.responsible_doctor') ?></dt><dd id="diagnosis-doctor"><?= mcEscape($selected['doctor_name'] ?? $chnUserName) ?></dd></div>
                </dl>
                <div class="medical-cases__diagnosis-field">
                    <label class="form-label" for="diagnosis-text"><?= te('cases.diagnosis_description') ?></label>
                    <textarea class="form-control" id="diagnosis-text" name="diagnosis_text" rows="4" required aria-describedby="diagnosis-text-error"></textarea>
                    <p class="form-error" id="diagnosis-text-error" aria-live="polite"></p>
                </div>
                <div class="medical-cases__diagnosis-field">
                    <label class="form-label" for="diagnosis-icd"><?= te('cases.diagnosis_icd_optional') ?></label>
                    <input class="form-control" id="diagnosis-icd" name="icd_code" maxlength="20" aria-describedby="diagnosis-icd-hint diagnosis-icd-error">
                    <p class="form-hint" id="diagnosis-icd-hint"><?= te('cases.diagnosis_icd_length') ?></p>
                    <p class="form-error" id="diagnosis-icd-error" aria-live="polite"></p>
                </div>
                <div class="medical-cases__diagnosis-actions">
                    <button class="btn btn--primary" type="submit"><?= te('cases.add_diagnosis') ?></button>
                    <button class="btn btn--outline" type="button" id="cancel-diagnosis"><?= te('cases.diagnosis_cancel') ?></button>
                </div>
            </form>
            <p class="medical-cases__diagnosis-result" id="diagnosis-result" role="status"></p>
            <?php endif; ?>
            <ol class="medical-cases__history" id="diagnosis-history" <?= $selectedDiagnoses ? '' : 'hidden' ?>>
                <?php foreach ($selectedDiagnoses as $diagnosis): ?>
                <li>
                    <p><?= mcEscape($diagnosis['diagnosis_text']) ?></p>
                    <p class="card__subtitle"><?= mcEscape(mcDate($diagnosis['diagnosed_at'])) ?> · <?= mcEscape($diagnosis['doctor_name'] ?? t('cases.doctor_unavailable')) ?></p>
                    <?php if (!empty($diagnosis['icd_code'])): ?><p><?= te('cases.icd_code', ['code' => $diagnosis['icd_code']]) ?></p><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
            <p class="medical-cases__empty" id="diagnosis-empty" <?= $selectedDiagnoses ? 'hidden' : '' ?>><?= te('cases.no_diagnoses') ?></p>
        </section>
        <?php endif; ?>
        <?php else: ?>
            <p class="medical-cases__empty"><?= mcParam('case') !== '' ? te('cases.case_not_found') : ($role === 'healthcare_staff' ? te('cases.select_staff') : te('cases.select_clinical')) ?></p>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <noscript><p class="card__subtitle"><?= te('cases.noscript') ?></p></noscript>
</div>
</main>
</body>
</html>
