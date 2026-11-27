<?php
declare(strict_types=1);

$chnProjectRoot = dirname(__DIR__);
require_once $chnProjectRoot . '/backend/auth.php';
require_once $chnProjectRoot . '/backend/db.php';
require_once $chnProjectRoot . '/include/i18n.php';
require_once $chnProjectRoot . '/include/departments.php';

$sessionUser = requireRole(['patient', 'doctor', 'healthcare_staff', 'admin'], false);
$appointmentsRole = $sessionUser['role'];
$userId = $sessionUser['user_id'];

$appointmentsToday = new DateTimeImmutable('today');
$appointmentsTodayKey = $appointmentsToday->format('Y-m-d');

$appointmentsTiers = [
    'orange' => ['label' => t('tier.orange'), 'note' => t('tier.orange_note')],
    'yellow' => ['label' => t('tier.yellow'), 'note' => t('tier.yellow_note')],
    'green'  => ['label' => t('tier.green'),  'note' => t('tier.green_note')],
];

$appointmentsStatuses = [
    'confirmed' => ['label' => t('appointment_status.confirmed'), 'badge' => 'success'],
    'cancelled' => ['label' => t('appointment_status.cancelled'), 'badge' => 'danger'],
    'completed' => ['label' => t('appointment_status.completed'), 'badge' => 'neutral'],
];

$appointmentsSlots = [
    '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
    '13:00', '13:30', '14:00', '14:30', '15:00', '15:30',
];

function appointmentsEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function appointmentsDate(int $days): string
{
    return (new DateTimeImmutable('today'))->modify(sprintf('%+d days', $days))->format('Y-m-d');
}

function appointmentsQuery(array $params): string
{
    $clean = array_filter($params, static fn ($v): bool => $v !== null && $v !== '');
    return $clean === [] ? 'appointments.php' : 'appointments.php?' . http_build_query($clean);
}

function appointmentsDateParam(string $name): ?string
{
    if (!isset($_GET[$name]) || !is_string($_GET[$name])) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $_GET[$name]);
    return $date !== false && $date->format('Y-m-d') === $_GET[$name] ? $_GET[$name] : null;
}

function appointmentsRelativeDay(string $date, DateTimeImmutable $today): string
{
    $days = (int) $today->diff(new DateTimeImmutable($date))->format('%r%a');
    if ($days === 0) return t('appointments.relative_today');
    if ($days === 1) return t('appointments.relative_tomorrow');
    if ($days === -1) return t('appointments.relative_yesterday');
    if ($days < 0) return tn('appointments.relative_days_ago', abs($days));
    return tn('appointments.relative_in_days', $days);
}

function appointmentsTierBadge(string $tier, array $tiers): string
{
    if (!isset($tiers[$tier])) return '';
    return '<span class="tier-badge tier-badge--' . appointmentsEscape($tier) . '">'
        . appointmentsEscape($tiers[$tier]['label'] . ' · ' . $tiers[$tier]['note'])
        . '</span>';
}

function appointmentsStatusBadge(string $value, array $statuses): string
{
    $status = $statuses[$value] ?? ['label' => $value, 'badge' => 'neutral'];
    return '<span class="badge badge--' . appointmentsEscape($status['badge']) . '">'
        . appointmentsEscape($status['label'])
        . '</span>';
}

function appointmentsInitials(string $name): string
{
    $parts = preg_split('/\s+/u', trim(preg_replace('/^Dr\.\s*/u', '', $name)));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

$scope = ['is_island_wide' => true, 'hospital_id' => null, 'department_id' => null, 'hospital_name' => null, 'department_name' => null];
$appointmentsDepartments = [];
$appointmentsDoctors = [];
$appointmentsPatientCases = [];
$appointmentsAll = [];

try {
    $pdo = chnGetPdo();
    $scope = chnGetUserHospitalScope($pdo, $sessionUser);

    foreach ($pdo->query(
        'SELECT dep.department_id, dep.department_name, dep.hospital_id, h.hospital_name
         FROM `department` dep
         INNER JOIN `hospital` h ON h.hospital_id = dep.hospital_id
         ORDER BY h.hospital_name ASC, dep.department_name ASC'
    )->fetchAll() as $row) {
        $appointmentsDepartments[(int) $row['department_id']] = [
            'department_id' => (int) $row['department_id'],
            'department_name' => chnDepartmentLabel((string) $row['department_name']),
            'hospital_id' => (int) $row['hospital_id'],
            'hospital_name' => (string) $row['hospital_name'],
        ];
    }

    foreach ($pdo->query(
        'SELECT d.doctor_id, d.department_id, u.name, u.surname
         FROM `doctor` d
         INNER JOIN `user` u ON u.user_id = d.user_id
         ORDER BY u.name ASC, u.surname ASC'
    )->fetchAll() as $row) {
        $appointmentsDoctors[(int) $row['doctor_id']] = [
            'doctor_id' => (int) $row['doctor_id'],
            'doctor_name' => 'Dr. ' . trim((string) $row['name'] . ' ' . (string) $row['surname']),
            'department_id' => (int) $row['department_id'],
        ];
    }

    if ($appointmentsRole === 'patient') {
        $caseStmt = $pdo->prepare(
            "SELECT c.case_id, c.department_id, c.tier_color, c.case_description
             FROM `case` c
             INNER JOIN `patient` p ON p.patient_id = c.patient_id
             WHERE p.user_id = :user_id AND c.case_state = 'open'
             ORDER BY c.created_at DESC, c.case_id DESC"
        );
        $caseStmt->execute([':user_id' => $userId]);
        foreach ($caseStmt->fetchAll() as $row) {
            $appointmentsPatientCases[(int) $row['case_id']] = [
                'case_id' => (int) $row['case_id'],
                'department_id' => (int) $row['department_id'],
                'tier_color' => (string) $row['tier_color'],
                'case_description' => (string) ($row['case_description'] ?? ''),
            ];
        }
    }

    if ($appointmentsRole !== 'admin') {
        $apptSql = "SELECT a.appointment_id, a.case_id, a.doctor_id, a.patient_id, a.department_id,
                           a.appointment_date, a.appointment_time, a.appointment_status,
                           c.tier_color, c.case_description,
                           CONCAT(pu.name, ' ', pu.surname) AS patient_name, pu.birth_date,
                           CONCAT('Dr. ', du.name, ' ', du.surname) AS doctor_name,
                           dep.department_name, h.hospital_name
                    FROM `appointment` a
                    INNER JOIN `case` c ON c.case_id = a.case_id
                    INNER JOIN `patient` p ON p.patient_id = a.patient_id
                    INNER JOIN `user` pu ON pu.user_id = p.user_id
                    INNER JOIN `doctor` d ON d.doctor_id = a.doctor_id
                    INNER JOIN `user` du ON du.user_id = d.user_id
                    INNER JOIN `department` dep ON dep.department_id = a.department_id
                    INNER JOIN `hospital` h ON h.hospital_id = dep.hospital_id";

        $apptParams = [];
        if ($appointmentsRole === 'patient') {
            $apptSql .= ' WHERE p.user_id = :user_id';
            $apptParams[':user_id'] = $userId;
        } elseif ($appointmentsRole === 'doctor') {
            $apptSql .= ' WHERE d.user_id = :user_id';
            $apptParams[':user_id'] = $userId;
        } elseif ($appointmentsRole === 'healthcare_staff') {
            if ($scope['hospital_id'] !== null) {
                $apptSql .= ' WHERE dep.hospital_id = :hospital_id';
                $apptParams[':hospital_id'] = $scope['hospital_id'];
            } else {
                $apptSql .= ' WHERE 1 = 0';
            }
        }

        $apptStmt = $pdo->prepare($apptSql);
        $apptStmt->execute($apptParams);
        foreach ($apptStmt->fetchAll() as $row) {
            $birth = $row['birth_date'] ? DateTimeImmutable::createFromFormat('Y-m-d', (string) $row['birth_date']) : false;
            $appointmentsAll[] = [
                'appointment_id' => (int) $row['appointment_id'],
                'case_id' => (int) $row['case_id'],
                'doctor_id' => (int) $row['doctor_id'],
                'patient_id' => (int) $row['patient_id'],
                'department_id' => (int) $row['department_id'],
                'appointment_date' => (string) $row['appointment_date'],
                'appointment_time' => (string) $row['appointment_time'],
                'appointment_status' => (string) $row['appointment_status'],
                'patient_name' => (string) $row['patient_name'],
                'patient_age' => $birth ? (int) $birth->diff($appointmentsToday)->y : null,
                'doctor_name' => (string) $row['doctor_name'],
                'department_name' => chnDepartmentLabel((string) $row['department_name']),
                'hospital_name' => (string) $row['hospital_name'],
                'tier_color' => (string) $row['tier_color'],
                'case_description' => (string) ($row['case_description'] ?? ''),
            ];
        }
    }
} catch (Throwable $e) {
    chnLogError('Appointments page load error: ' . $e->getMessage());
}

$appointmentsViews = [
    'today'     => t('appointments.view_today'),
    'upcoming'  => t('appointments.view_upcoming'),
    'past'      => t('appointments.view_past'),
    'cancelled' => t('appointments.view_cancelled'),
];

$appointmentsView = isset($_GET['view']) && is_string($_GET['view']) && isset($appointmentsViews[$_GET['view']])
    ? $_GET['view']
    : array_key_first($appointmentsViews);

$appointmentsStaffDepartments = array_filter(
    $appointmentsDepartments,
    static fn (array $item): bool => $scope['hospital_id'] !== null && $item['hospital_id'] === $scope['hospital_id']
);

$appointmentsFilterDepartment = null;
$appointmentsFilterStatus = null;
$appointmentsFilterPatient = '';
$appointmentsStatusOptions = array_diff_key($appointmentsStatuses, ['cancelled' => true]);
$appointmentsHasStatusFilter = $appointmentsRole === 'healthcare_staff' && $appointmentsView !== 'cancelled';
$appointmentsFilterFrom = null;
$appointmentsFilterTo = null;

if ($appointmentsRole === 'healthcare_staff') {
    $appointmentsFilterDepartment = isset($_GET['department_id']) && is_string($_GET['department_id'])
        && ctype_digit($_GET['department_id']) && isset($appointmentsStaffDepartments[(int) $_GET['department_id']])
        ? (int) $_GET['department_id']
        : null;
}

if ($appointmentsHasStatusFilter) {
    $appointmentsFilterStatus = isset($_GET['status']) && is_string($_GET['status']) && isset($appointmentsStatusOptions[$_GET['status']])
        ? $_GET['status']
        : null;
}

if ($appointmentsRole === 'doctor' || $appointmentsRole === 'healthcare_staff') {
    $appointmentsFilterPatient = isset($_GET['patient_name']) && is_string($_GET['patient_name'])
        ? mb_substr(trim(preg_replace('/\s+/u', ' ', $_GET['patient_name']) ?? ''), 0, 100)
        : '';
}

$appointmentsHasDateFilter = $appointmentsRole !== 'admin' && $appointmentsView !== 'today';
$appointmentsDateMin = $appointmentsView === 'upcoming' ? appointmentsDate(1) : null;
$appointmentsDateMax = $appointmentsView === 'past' ? appointmentsDate(-1) : null;

if ($appointmentsHasDateFilter) {
    $appointmentsFilterFrom = appointmentsDateParam('date_from');
    $appointmentsFilterTo = appointmentsDateParam('date_to');
    if ($appointmentsFilterFrom !== null && $appointmentsFilterTo !== null && $appointmentsFilterFrom > $appointmentsFilterTo) {
        [$appointmentsFilterFrom, $appointmentsFilterTo] = [$appointmentsFilterTo, $appointmentsFilterFrom];
    }
}

$appointmentsKeepFilters = array_filter([
    'patient_name'  => $appointmentsFilterPatient,
    'department_id' => $appointmentsFilterDepartment,
    'status'        => $appointmentsFilterStatus,
], static fn ($value): bool => $value !== null && $value !== '');

$appointmentsIsFiltered = $appointmentsKeepFilters !== [] || $appointmentsFilterFrom !== null || $appointmentsFilterTo !== null;

$appointmentsRows = array_values(array_filter($appointmentsAll, static function (array $item) use ($appointmentsView, $appointmentsTodayKey, $appointmentsFilterDepartment, $appointmentsFilterStatus, $appointmentsFilterPatient, $appointmentsFilterFrom, $appointmentsFilterTo): bool {
    if ($appointmentsFilterDepartment !== null && $item['department_id'] !== $appointmentsFilterDepartment) return false;
    if ($appointmentsFilterStatus !== null && $item['appointment_status'] !== $appointmentsFilterStatus) return false;
    if ($appointmentsFilterPatient !== '' && mb_stripos($item['patient_name'], $appointmentsFilterPatient) === false) return false;

    $date = $item['appointment_date'];
    if ($appointmentsFilterFrom !== null && $date < $appointmentsFilterFrom) return false;
    if ($appointmentsFilterTo !== null && $date > $appointmentsFilterTo) return false;

    if ($appointmentsView === 'cancelled') return $item['appointment_status'] === 'cancelled';
    if ($item['appointment_status'] === 'cancelled') return false;
    if ($appointmentsView === 'today') return $date === $appointmentsTodayKey;
    if ($appointmentsView === 'past') return $date < $appointmentsTodayKey;
    return $date > $appointmentsTodayKey;
}));

usort($appointmentsRows, static function (array $a, array $b) use ($appointmentsView): int {
    $order = [$a['appointment_date'], $a['appointment_time']] <=> [$b['appointment_date'], $b['appointment_time']];
    return $appointmentsView === 'past' || $appointmentsView === 'cancelled' ? -$order : $order;
});

$doctorSubtitleParts = array_filter([
    'Dr. ' . trim($sessionUser['name'] . ' ' . $sessionUser['surname']),
    !empty($scope['department_name']) ? chnDepartmentLabel((string) $scope['department_name']) : null,
    $scope['hospital_name'] ?? null,
]);

$appointmentsHeaders = [
    'patient'          => ['title' => t('appointments.header_patient_title'), 'subtitle' => t('appointments.header_patient_subtitle')],
    'doctor'           => ['title' => t('appointments.header_doctor_title'),  'subtitle' => implode(' · ', $doctorSubtitleParts)],
    'healthcare_staff' => ['title' => t('appointments.header_staff_title'),   'subtitle' => t('appointments.header_staff_subtitle', ['hospital' => $scope['hospital_name'] ?? ''])],
    'admin'            => ['title' => t('nav.appointments'),                  'subtitle' => t('appointments.header_admin_subtitle')],
];

$appointmentsEmpty = [];
foreach (array_keys($appointmentsViews) as $appointmentsViewKey) {
    $appointmentsEmpty[$appointmentsViewKey] = [
        'title' => t('appointments.empty_' . $appointmentsViewKey . '_title'),
        'text'  => t('appointments.empty_' . $appointmentsViewKey . '_text'),
    ];
}

$appointmentsFormatted = static function (array $item) use ($appointmentsToday): array {
    $when = new DateTimeImmutable($item['appointment_date'] . ' ' . $item['appointment_time']);
    return [
        'iso'      => $when->format('Y-m-d\TH:i'),
        'time'     => $when->format('H:i'),
        'date'     => chnDate($when, 'short_weekday'),
        'relative' => appointmentsRelativeDay($item['appointment_date'], $appointmentsToday),
    ];
};

$appointmentsCasesHint = '<a href="medical-cases.php">' . appointmentsEscape(t('nav.cases')) . '</a>';

$chnPageTitle = t('nav.appointments');
$chnUserName = trim($sessionUser['name'] . ' ' . $sessionUser['surname']);
$chnUserRole = $appointmentsRole;
?>
<!DOCTYPE html>
<html lang="<?= appointmentsEscape(chnLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | <?= te('nav.appointments') ?></title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime($chnProjectRoot . '/css/style.css') ?>">
    <link rel="stylesheet" href="../css/appointments.css?v=<?= filemtime($chnProjectRoot . '/css/appointments.css') ?>">
    <script src="../js/sidebar.js?v=<?= filemtime($chnProjectRoot . '/js/sidebar.js') ?>" defer></script>
    <script src="../js/topbar.js?v=<?= filemtime($chnProjectRoot . '/js/topbar.js') ?>" defer></script>
    <script src="../js/appointments.js?v=<?= filemtime($chnProjectRoot . '/js/appointments.js') ?>" defer></script>
</head>
<body>
<?php
require $chnProjectRoot . '/include/left-sidebar.php';
require $chnProjectRoot . '/include/top-bar.php';
?>

<main class="content" id="main-content" tabindex="-1">
    <div class="content__inner appointments">
        <div class="page-header">
            <div>
                <h1 class="page-header__title"><?= appointmentsEscape($appointmentsHeaders[$appointmentsRole]['title']) ?></h1>
                <p class="page-header__subtitle"><?= appointmentsEscape($appointmentsHeaders[$appointmentsRole]['subtitle']) ?></p>
            </div>
        </div>

        <p class="alert alert--info appointments-notice" id="appointments-notice" role="status" hidden
           data-messages="<?= chnJsMessages([
               'doctorHint'         => 'appointments.doctor_hint',
               'doctorsFound'       => 'appointments.doctors_found',
               'nothingSelected'    => 'appointments.nothing_selected',
               'prevMonth'          => 'appointments.prev_month',
               'nextMonth'          => 'appointments.next_month',
               'rescheduleTitle'    => 'appointments.reschedule_title',
               'rescheduleSubtitle' => 'appointments.reschedule_subtitle',
               'rescheduleSubmit'   => 'appointments.reschedule_submit',
               'errorCase'          => 'appointments.error_case',
               'errorDoctor'        => 'appointments.error_doctor',
               'errorCaseFirst'     => 'appointments.error_case_first',
               'errorDate'          => 'appointments.error_date',
               'errorTime'          => 'appointments.error_time',
               'noticeReschedule'   => 'appointments.notice_reschedule',
               'noticeBook'         => 'appointments.notice_book',
               'noticeCancel'       => 'appointments.notice_cancel',
               'noticeStart'        => 'appointments.notice_start',
               'requestFailed'      => 'appointments.request_failed',
               'cancelled'          => 'appointment_status.cancelled',
           ]) ?>"></p>

        <?php if ($appointmentsRole === 'admin'): ?>
            <section class="card" aria-labelledby="appointments-admin-title">
                <div class="empty-state">
                    <h2 class="empty-state__title" id="appointments-admin-title"><?= te('appointments.admin_title') ?></h2>
                    <p class="empty-state__text"><?= te('appointments.admin_text') ?></p>
                    <a class="btn btn--outline" href="dashboard-test.php"><?= te('appointments.back_dashboard') ?></a>
                </div>
            </section>
        <?php else: ?>
            <?php if ($appointmentsRole === 'patient'): ?>
                <section class="card appointments-book" aria-labelledby="appointments-book-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="appointments-book-title" tabindex="-1"><?= te('appointments.book_title') ?></h2>
                            <p class="card__subtitle" id="appointments-book-subtitle"><?= te('appointments.book_subtitle') ?></p>
                        </div>
                        <button type="button" class="btn btn--outline btn--sm" id="appointments-book-reset" hidden><?= te('appointments.stop_reschedule') ?></button>
                    </header>

                    <?php if ($appointmentsPatientCases === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title"><?= te('appointments.no_cases_title') ?></p>
                            <p class="empty-state__text"><?= te('appointments.no_cases_text', ['page' => t('nav.cases')]) ?></p>
                            <a class="btn btn--outline" href="medical-cases.php"><?= te('appointments.go_cases') ?></a>
                        </div>
                    <?php else: ?>
                    <form class="appointments-book__form" id="appointments-form"
                          data-book-url="../backend/book_appointment.php"
                          data-slots-url="../backend/get_appointment_slots.php"
                          method="post" novalidate>
                        <input type="hidden" name="appointment_id" id="appointments-appointment-id" value="">

                        <div class="appointments-book__grid">
                            <div class="appointments-book__column">
                                <fieldset class="appointments-step">
                                    <legend class="appointments-step__title"><span class="appointments-step__number" aria-hidden="true">1</span> <?= te('appointments.step_case') ?></legend>
                                    <p class="form-hint appointments-step__hint">
                                        <?= strtr(appointmentsEscape(t('appointments.case_hint')), ['{page}' => $appointmentsCasesHint]) ?>
                                    </p>

                                    <div class="appointments-choices">
                                        <?php foreach ($appointmentsPatientCases as $appointmentsCaseId => $appointmentsCase): ?>
                                            <?php $appointmentsDepartment = $appointmentsDepartments[$appointmentsCase['department_id']]; ?>
                                            <label class="appointments-choice">
                                                <input type="radio" class="appointments-choice__input" name="case_id" value="<?= (int) $appointmentsCaseId ?>"
                                                       data-department="<?= (int) $appointmentsCase['department_id'] ?>"
                                                       data-label="<?= te('appointments.case_label', ['id' => (int) $appointmentsCaseId]) ?>">
                                                <span class="appointments-choice__body">
                                                    <span class="appointments-choice__title">
                                                        <?= te('appointments.case_label', ['id' => (int) $appointmentsCaseId]) ?>
                                                        <?= appointmentsTierBadge($appointmentsCase['tier_color'], $appointmentsTiers) ?>
                                                    </span>
                                                    <span class="appointments-choice__meta"><?= appointmentsEscape($appointmentsDepartment['department_name'] . ' · ' . $appointmentsDepartment['hospital_name']) ?></span>
                                                    <span class="appointments-choice__meta"><?= appointmentsEscape($appointmentsCase['case_description']) ?></span>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="form-error" id="appointments-case-error" hidden></p>
                                </fieldset>

                                <fieldset class="appointments-step">
                                    <legend class="appointments-step__title"><span class="appointments-step__number" aria-hidden="true">2</span> <?= te('appointments.step_doctor') ?></legend>

                                    <p class="form-hint appointments-step__hint" id="appointments-doctor-hint"><?= te('appointments.doctor_hint') ?></p>
                                    <div class="appointments-choices" id="appointments-doctors">
                                        <?php foreach ($appointmentsDoctors as $appointmentsDoctorId => $appointmentsDoctor): ?>
                                            <?php $appointmentsDepartment = $appointmentsDepartments[$appointmentsDoctor['department_id']]; ?>
                                            <label class="appointments-choice" data-department="<?= (int) $appointmentsDoctor['department_id'] ?>" hidden>
                                                <input type="radio" class="appointments-choice__input" name="doctor_id" value="<?= (int) $appointmentsDoctorId ?>"
                                                       data-label="<?= appointmentsEscape($appointmentsDoctor['doctor_name'] . ' · ' . $appointmentsDepartment['department_name']) ?>">
                                                <span class="appointments-choice__avatar" aria-hidden="true"><?= appointmentsEscape(appointmentsInitials($appointmentsDoctor['doctor_name'])) ?></span>
                                                <span class="appointments-choice__body">
                                                    <span class="appointments-choice__title"><?= appointmentsEscape($appointmentsDoctor['doctor_name']) ?></span>
                                                    <span class="appointments-choice__meta"><?= appointmentsEscape($appointmentsDepartment['department_name'] . ' · ' . $appointmentsDepartment['hospital_name']) ?></span>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="form-error" id="appointments-doctor-error" hidden></p>
                                </fieldset>
                            </div>

                            <div class="appointments-book__column">
                                <fieldset class="appointments-step">
                                    <legend class="appointments-step__title"><span class="appointments-step__number" aria-hidden="true">3</span> <?= te('appointments.step_datetime') ?></legend>

                                    <div class="appointments-calendar" id="appointments-calendar"
                                         data-min="<?= appointmentsEscape(appointmentsDate(1)) ?>"
                                         data-max="<?= appointmentsEscape(appointmentsDate(60)) ?>">
                                        <div class="form-group">
                                            <label class="form-label" for="appointments-date"><?= te('appointments.date') ?></label>
                                            <input type="date" class="form-control" id="appointments-date" name="appointment_date"
                                                   min="<?= appointmentsEscape(appointmentsDate(1)) ?>" max="<?= appointmentsEscape(appointmentsDate(60)) ?>">
                                        </div>
                                    </div>
                                    <p class="form-error" id="appointments-date-error" hidden></p>

                                    <p class="form-label appointments-slots__label" id="appointments-slots-label"><?= te('appointments.available_times') ?></p>
                                    <p class="form-hint appointments-step__hint" id="appointments-slots-hint"><?= te('appointments.slots_hint') ?></p>
                                    <div class="appointments-slots" role="radiogroup" aria-labelledby="appointments-slots-label">
                                        <?php foreach ($appointmentsSlots as $appointmentsSlot): ?>
                                            <label class="appointments-slot">
                                                <input type="radio" class="appointments-slot__input" name="appointment_time" value="<?= appointmentsEscape($appointmentsSlot) ?>">
                                                <span class="appointments-slot__label"><?= appointmentsEscape($appointmentsSlot) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="form-error" id="appointments-time-error" hidden></p>
                                </fieldset>

                                <div class="appointments-summary">
                                    <p class="appointments-summary__label"><?= te('appointments.selected') ?></p>
                                    <p class="appointments-summary__value" id="appointments-summary"><?= te('appointments.nothing_selected') ?></p>
                                    <button type="submit" class="btn btn--primary btn--block" id="appointments-submit"><?= te('appointments.confirm') ?></button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="card appointments-list" id="appointments-list" aria-labelledby="appointments-list-title">
                <header class="card__header">
                    <div>
                        <h2 class="card__title" id="appointments-list-title">
                            <?= $appointmentsRole === 'patient' ? te('appointments.list_title_patient') : te('nav.appointments') ?>
                        </h2>
                        <p class="card__subtitle">
                            <?= appointmentsEscape(tn('appointments.count', count($appointmentsRows))) ?>
                            · <?= appointmentsEscape($appointmentsViews[$appointmentsView]) ?>
                            <?= $appointmentsIsFiltered ? '· ' . te('appointments.filtered') : '' ?>
                        </p>
                    </div>
                    <nav aria-label="<?= te('appointments.period_nav') ?>">
                        <ul class="appointments-tabs">
                            <?php foreach ($appointmentsViews as $appointmentsViewKey => $appointmentsViewLabel): ?>
                                <li>
                                    <a class="appointments-tabs__link"
                                       href="<?= appointmentsEscape(appointmentsQuery(['view' => $appointmentsViewKey] + $appointmentsKeepFilters) . '#appointments-list') ?>"
                                       <?= $appointmentsViewKey === $appointmentsView ? 'aria-current="page"' : '' ?>>
                                        <?= appointmentsEscape($appointmentsViewLabel) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                </header>

                <?php if ($appointmentsRole !== 'patient' || $appointmentsHasDateFilter): ?>
                    <form class="appointments-filters" method="get" action="appointments.php#appointments-list" aria-label="<?= te('appointments.filter_label') ?>">
                        <input type="hidden" name="view" value="<?= appointmentsEscape($appointmentsView) ?>">
                        <?php if ($appointmentsRole !== 'patient'): ?>
                        <div class="form-group">
                            <label class="form-label" for="appointments-filter-patient"><?= te('appointments.patient_name') ?></label>
                            <input type="search" class="form-control" id="appointments-filter-patient" name="patient_name"
                                   value="<?= appointmentsEscape($appointmentsFilterPatient) ?>" maxlength="100"
                                   autocomplete="off" placeholder="<?= te('appointments.search_name') ?>">
                        </div>
                        <?php endif; ?>
                        <?php if ($appointmentsRole === 'healthcare_staff'): ?>
                        <div class="form-group">
                            <label class="form-label" for="appointments-filter-department"><?= te('appointments.department') ?></label>
                            <select class="form-control appointments-select" id="appointments-filter-department" name="department_id">
                                <option value=""><?= te('appointments.all_departments') ?></option>
                                <?php foreach ($appointmentsStaffDepartments as $appointmentsDepartmentId => $appointmentsDepartment): ?>
                                    <option value="<?= (int) $appointmentsDepartmentId ?>" <?= $appointmentsFilterDepartment === $appointmentsDepartmentId ? 'selected' : '' ?>>
                                        <?= appointmentsEscape($appointmentsDepartment['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <?php if ($appointmentsHasStatusFilter): ?>
                        <div class="form-group">
                            <label class="form-label" for="appointments-filter-status"><?= te('appointments.status') ?></label>
                            <select class="form-control appointments-select" id="appointments-filter-status" name="status">
                                <option value=""><?= te('appointments.all_statuses') ?></option>
                                <?php foreach ($appointmentsStatusOptions as $appointmentsStatusKey => $appointmentsStatus): ?>
                                    <option value="<?= appointmentsEscape($appointmentsStatusKey) ?>" <?= $appointmentsFilterStatus === $appointmentsStatusKey ? 'selected' : '' ?>>
                                        <?= appointmentsEscape($appointmentsStatus['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <?php if ($appointmentsHasDateFilter): ?>
                        <div class="form-group">
                            <label class="form-label" for="appointments-filter-from"><?= te('appointments.date_from') ?></label>
                            <input type="date" class="form-control" id="appointments-filter-from" name="date_from"
                                   value="<?= appointmentsEscape($appointmentsFilterFrom ?? '') ?>"
                                   <?= $appointmentsDateMin !== null ? 'min="' . appointmentsEscape($appointmentsDateMin) . '"' : '' ?>
                                   <?= $appointmentsDateMax !== null ? 'max="' . appointmentsEscape($appointmentsDateMax) . '"' : '' ?>>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="appointments-filter-to"><?= te('appointments.date_to') ?></label>
                            <input type="date" class="form-control" id="appointments-filter-to" name="date_to"
                                   value="<?= appointmentsEscape($appointmentsFilterTo ?? '') ?>"
                                   <?= $appointmentsDateMin !== null ? 'min="' . appointmentsEscape($appointmentsDateMin) . '"' : '' ?>
                                   <?= $appointmentsDateMax !== null ? 'max="' . appointmentsEscape($appointmentsDateMax) . '"' : '' ?>>
                        </div>
                        <?php endif; ?>
                        <div class="appointments-filters__actions">
                            <button type="submit" class="btn btn--outline"><?= te('appointments.filter') ?></button>
                            <?php if ($appointmentsIsFiltered): ?>
                                <a class="btn btn--outline" href="<?= appointmentsEscape(appointmentsQuery(['view' => $appointmentsView]) . '#appointments-list') ?>"><?= te('appointments.clear') ?></a>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($appointmentsRows === []): ?>
                    <div class="empty-state">
                        <?php if ($appointmentsIsFiltered): ?>
                            <p class="empty-state__title"><?= te('appointments.no_match_title') ?></p>
                            <p class="empty-state__text"><?= te('appointments.no_match_text') ?></p>
                        <?php else: ?>
                            <p class="empty-state__title"><?= appointmentsEscape($appointmentsEmpty[$appointmentsView]['title']) ?></p>
                            <p class="empty-state__text"><?= appointmentsEscape($appointmentsEmpty[$appointmentsView]['text']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table appointments-table">
                            <thead>
                                <tr>
                                    <th scope="col"><?= te('appointments.date') ?></th>
                                    <?php if ($appointmentsRole === 'patient'): ?>
                                        <th scope="col"><?= te('appointments.col_doctor') ?></th>
                                        <th scope="col"><?= te('appointments.department') ?></th>
                                        <th scope="col"><?= te('appointments.step_case') ?></th>
                                    <?php elseif ($appointmentsRole === 'doctor'): ?>
                                        <th scope="col"><?= te('appointments.col_patient') ?></th>
                                        <th scope="col"><?= te('appointments.step_case') ?></th>
                                        <th scope="col"><?= te('appointments.col_note') ?></th>
                                    <?php else: ?>
                                        <th scope="col"><?= te('appointments.col_patient') ?></th>
                                        <th scope="col"><?= te('appointments.col_doctor') ?></th>
                                        <th scope="col"><?= te('appointments.department') ?></th>
                                    <?php endif; ?>
                                    <th scope="col"><?= te('appointments.status') ?></th>
                                    <?php if ($appointmentsRole !== 'healthcare_staff'): ?>
                                        <th scope="col"><span class="appointments-table__actions-label"><?= te('appointments.col_actions') ?></span></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointmentsRows as $appointmentsItem): ?>
                                    <?php
                                        $appointmentsWhen = $appointmentsFormatted($appointmentsItem);
                                        $appointmentsIsOpen = $appointmentsItem['appointment_status'] === 'confirmed'
                                            && $appointmentsItem['appointment_date'] >= $appointmentsTodayKey;
                                        $appointmentsConfirm = t('appointments.cancel_confirm', [
                                            'date' => $appointmentsWhen['date'],
                                            'time' => $appointmentsWhen['time'],
                                            'name' => $appointmentsRole === 'patient' ? $appointmentsItem['doctor_name'] : $appointmentsItem['patient_name'],
                                        ]);
                                        $appointmentsCanReschedule = $appointmentsRole === 'patient' && $appointmentsIsOpen;
                                        $appointmentsCanComplete = $appointmentsRole === 'doctor'
                                            && $appointmentsItem['appointment_date'] <= $appointmentsTodayKey
                                            && $appointmentsItem['appointment_status'] === 'confirmed';
                                        $appointmentsCanDiagnose = $appointmentsRole === 'doctor' && $appointmentsItem['appointment_status'] === 'completed';
                                        $appointmentsHasActions = $appointmentsCanReschedule || $appointmentsCanComplete || $appointmentsCanDiagnose || $appointmentsIsOpen;
                                    ?>
                                    <tr data-appointment-row="<?= (int) $appointmentsItem['appointment_id'] ?>">
                                        <td>
                                            <time class="appointments-table__strong" datetime="<?= appointmentsEscape($appointmentsWhen['iso']) ?>">
                                                <?= appointmentsEscape($appointmentsWhen['time']) ?>
                                            </time>
                                            <span class="appointments-table__sub">
                                                <?= appointmentsEscape($appointmentsWhen['date']) ?> · <?= appointmentsEscape($appointmentsWhen['relative']) ?>
                                            </span>
                                        </td>
                                        <?php if ($appointmentsRole === 'patient'): ?>
                                            <td><span class="appointments-table__name"><?= appointmentsEscape($appointmentsItem['doctor_name']) ?></span></td>
                                            <td>
                                                <span class="appointments-table__nowrap"><?= appointmentsEscape($appointmentsItem['department_name']) ?></span>
                                                <span class="appointments-table__sub"><?= appointmentsEscape($appointmentsItem['hospital_name']) ?></span>
                                            </td>
                                            <td>
                                                <span class="appointments-table__nowrap">#<?= (int) $appointmentsItem['case_id'] ?></span>
                                                <?= appointmentsTierBadge($appointmentsItem['tier_color'], $appointmentsTiers) ?>
                                            </td>
                                        <?php elseif ($appointmentsRole === 'doctor'): ?>
                                            <td>
                                                <span class="appointments-table__name"><?= appointmentsEscape($appointmentsItem['patient_name']) ?></span>
                                                <?php if ($appointmentsItem['patient_age'] !== null): ?>
                                                    <span class="appointments-table__sub"><?= te('appointments.age', ['age' => $appointmentsItem['patient_age']]) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="appointments-table__nowrap">#<?= (int) $appointmentsItem['case_id'] ?></span>
                                                <?= appointmentsTierBadge($appointmentsItem['tier_color'], $appointmentsTiers) ?>
                                            </td>
                                            <td class="appointments-table__text"><?= appointmentsEscape($appointmentsItem['case_description']) ?></td>
                                        <?php else: ?>
                                            <td>
                                                <span class="appointments-table__name"><?= appointmentsEscape($appointmentsItem['patient_name']) ?></span>
                                                <span class="appointments-table__sub"><?= te('appointments.case_label', ['id' => (int) $appointmentsItem['case_id']]) ?></span>
                                            </td>
                                            <td><span class="appointments-table__nowrap"><?= appointmentsEscape($appointmentsItem['doctor_name']) ?></span></td>
                                            <td>
                                                <span class="appointments-table__nowrap"><?= appointmentsEscape($appointmentsItem['department_name']) ?></span>
                                                <span class="appointments-table__sub"><?= appointmentsEscape($appointmentsItem['hospital_name']) ?></span>
                                            </td>
                                        <?php endif; ?>
                                        <td data-appointment-status><?= appointmentsStatusBadge($appointmentsItem['appointment_status'], $appointmentsStatuses) ?></td>
                                        <?php if ($appointmentsRole !== 'healthcare_staff'): ?>
                                            <td>
                                                <div class="appointments-actions" data-appointment-actions>
                                                    <?php if ($appointmentsCanReschedule): ?>
                                                        <button type="button" class="btn btn--outline btn--sm"
                                                                data-appointment-reschedule="<?= (int) $appointmentsItem['appointment_id'] ?>"
                                                                data-doctor-id="<?= (int) $appointmentsItem['doctor_id'] ?>"
                                                                data-case-id="<?= (int) $appointmentsItem['case_id'] ?>"><?= te('appointments.reschedule') ?></button>
                                                    <?php endif; ?>
                                                    <?php if ($appointmentsCanComplete): ?>
                                                        <button type="button" class="btn btn--primary btn--sm"
                                                                data-appointment-complete="<?= (int) $appointmentsItem['appointment_id'] ?>"><?= te('appointments.complete_visit') ?></button>
                                                    <?php endif; ?>
                                                    <?php if ($appointmentsCanDiagnose): ?>
                                                        <a class="btn btn--outline btn--sm" href="<?= appointmentsEscape('medical-cases.php?case=' . (int) $appointmentsItem['case_id'] . '#add-diagnosis') ?>"><?= te('appointments.add_diagnosis') ?></a>
                                                    <?php endif; ?>
                                                    <?php if ($appointmentsIsOpen): ?>
                                                        <button type="button" class="btn btn--outline btn--sm appointments-actions__cancel"
                                                                data-appointment-cancel="<?= (int) $appointmentsItem['appointment_id'] ?>"
                                                                data-appointment-confirm="<?= appointmentsEscape($appointmentsConfirm) ?>"><?= te('appointments.cancel') ?></button>
                                                    <?php endif; ?>
                                                    <?php if (!$appointmentsHasActions): ?>
                                                        <span class="appointments-actions__none">—</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
