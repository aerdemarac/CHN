<?php
$appointmentsRoles = [
    'patient'          => 'Patient',
    'doctor'           => 'Doctor',
    'healthcare_staff' => 'Healthcare Staff',
    'admin'            => 'Admin',
];

$appointmentsRole = isset($_GET['role']) && is_string($_GET['role']) && isset($appointmentsRoles[$_GET['role']])
    ? $_GET['role']
    : 'patient';

$appointmentsToday = new DateTimeImmutable('today');
$appointmentsTodayKey = $appointmentsToday->format('Y-m-d');

$appointmentsTiers = [
    'orange' => ['label' => 'Orange', 'note' => 'Needs care'],
    'yellow' => ['label' => 'Yellow', 'note' => 'Important'],
    'green'  => ['label' => 'Green',  'note' => 'No vital risk'],
];

$appointmentsStatuses = [
    'pending'   => ['label' => 'Pending',   'badge' => 'warning'],
    'confirmed' => ['label' => 'Confirmed', 'badge' => 'success'],
    'cancelled' => ['label' => 'Cancelled', 'badge' => 'danger'],
    'completed' => ['label' => 'Completed', 'badge' => 'neutral'],
];

$appointmentsSlots = [
    '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
    '13:00', '13:30', '14:00', '14:30', '15:00', '15:30',
];

function appointmentsEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function appointmentsDate(int $days): string
{
    return (new DateTimeImmutable('today'))->modify(sprintf('%+d days', $days))->format('Y-m-d');
}

function appointmentsQuery(array $params): string
{
    return '?' . http_build_query($params);
}

function appointmentsPageLink(string $file, string $role): ?string
{
    return is_file(dirname(__DIR__) . '/' . $file) ? '../' . $file . appointmentsQuery(['role' => $role]) : null;
}

function appointmentsRelativeDay(string $date, DateTimeImmutable $today): string
{
    $days = (int) $today->diff(new DateTimeImmutable($date))->format('%r%a');

    if ($days === 0) {
        return 'Today';
    }
    if ($days === 1) {
        return 'Tomorrow';
    }
    if ($days === -1) {
        return 'Yesterday';
    }
    if ($days < 0) {
        return abs($days) . ' days ago';
    }

    return 'In ' . $days . ' days';
}

function appointmentsTierBadge(string $tier, array $tiers): string
{
    if (!isset($tiers[$tier])) {
        return '';
    }

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

$appointmentsUsers = [
    'patient'          => ['name' => 'Elif Arslan',       'patient_id' => 1],
    'doctor'           => ['name' => 'Dr. Andreas Kyrou', 'doctor_id' => 11],
    'healthcare_staff' => ['name' => 'Zeynep Şahin',      'hospital_name' => 'Nicosia State Hospital'],
    'admin'            => ['name' => 'Nikos Demetriou'],
];
$appointmentsUser = $appointmentsUsers[$appointmentsRole];

$appointmentsDepartments = [
    1 => ['department_name' => 'Cardiology',        'hospital_name' => 'Nicosia State Hospital'],
    2 => ['department_name' => 'Dermatology',       'hospital_name' => 'Nicosia State Hospital'],
    3 => ['department_name' => 'Internal Medicine', 'hospital_name' => 'Nicosia State Hospital'],
    4 => ['department_name' => 'Pediatrics',        'hospital_name' => 'Nicosia State Hospital'],
    5 => ['department_name' => 'Neurology',         'hospital_name' => 'Nicosia State Hospital'],
    6 => ['department_name' => 'Cardiology',        'hospital_name' => 'Famagusta State Hospital'],
];

$appointmentsDoctors = [
    11 => ['doctor_name' => 'Dr. Andreas Kyrou',    'department_id' => 1],
    12 => ['doctor_name' => 'Dr. Eleni Markou',     'department_id' => 1],
    13 => ['doctor_name' => 'Dr. Selin Aydın',      'department_id' => 2],
    14 => ['doctor_name' => 'Dr. Can Öztürk',       'department_id' => 3],
    15 => ['doctor_name' => 'Dr. Elena Costa',      'department_id' => 4],
    16 => ['doctor_name' => 'Dr. Mert Kaya',        'department_id' => 5],
    17 => ['doctor_name' => 'Dr. Sophia Pavlidou',  'department_id' => 6],
];

$appointmentsPatients = [
    1  => ['patient_name' => 'Elif Arslan',      'birth_date' => '1991-04-12'],
    2  => ['patient_name' => 'Maria Kouppa',     'birth_date' => '1958-09-03'],
    3  => ['patient_name' => 'Mehmet Yıldız',    'birth_date' => '1966-01-21'],
    4  => ['patient_name' => 'Andreas Georgiou', 'birth_date' => '1958-06-30'],
    5  => ['patient_name' => 'Ayşe Demir',       'birth_date' => '1979-11-08'],
    6  => ['patient_name' => 'Can Yılmaz',       'birth_date' => '1995-02-17'],
    7  => ['patient_name' => 'Lefteris Pavlou',  'birth_date' => '1983-07-25'],
    8  => ['patient_name' => 'Deniz Kara',       'birth_date' => '2017-05-14'],
    9  => ['patient_name' => 'Hasan Çelik',      'birth_date' => '1972-03-09'],
    10 => ['patient_name' => 'Sophia Ioannou',   'birth_date' => '1988-12-01'],
];

$appointmentsCases = [
    1011 => ['patient_id' => 1,  'department_id' => 3, 'tier_color' => 'green',  'case_state' => 'closed', 'case_description' => 'Routine check-up, no further visits needed.'],
    1033 => ['patient_id' => 9,  'department_id' => 1, 'tier_color' => 'yellow', 'case_state' => 'closed', 'case_description' => 'Medication adjusted, condition stable.'],
    1038 => ['patient_id' => 1,  'department_id' => 2, 'tier_color' => 'yellow', 'case_state' => 'open',   'case_description' => 'Skin rash on both arms, topical treatment started.'],
    1042 => ['patient_id' => 1,  'department_id' => 1, 'tier_color' => 'orange', 'case_state' => 'open',   'case_description' => 'Blood pressure monitoring and medication review.'],
    1049 => ['patient_id' => 4,  'department_id' => 1, 'tier_color' => 'green',  'case_state' => 'open',   'case_description' => 'Post-operative check, recovering well.'],
    1051 => ['patient_id' => 3,  'department_id' => 1, 'tier_color' => 'yellow', 'case_state' => 'open',   'case_description' => 'Irregular heartbeat, 24-hour Holter test requested.'],
    1056 => ['patient_id' => 2,  'department_id' => 1, 'tier_color' => 'orange', 'case_state' => 'open',   'case_description' => 'Chest pain under observation, ECG scheduled.'],
    1060 => ['patient_id' => 5,  'department_id' => 1, 'tier_color' => 'yellow', 'case_state' => 'open',   'case_description' => 'Shortness of breath on exertion, echocardiogram planned.'],
    1063 => ['patient_id' => 6,  'department_id' => 2, 'tier_color' => 'green',  'case_state' => 'open',   'case_description' => 'Acne treatment follow-up.'],
    1065 => ['patient_id' => 7,  'department_id' => 3, 'tier_color' => 'yellow', 'case_state' => 'open',   'case_description' => 'Persistent fatigue, blood tests ordered.'],
    1067 => ['patient_id' => 8,  'department_id' => 4, 'tier_color' => 'green',  'case_state' => 'open',   'case_description' => 'Routine growth and vaccination check.'],
    1070 => ['patient_id' => 10, 'department_id' => 5, 'tier_color' => 'yellow', 'case_state' => 'open',   'case_description' => 'Recurring migraines, MRI requested.'],
    1072 => ['patient_id' => 9,  'department_id' => 6, 'tier_color' => 'green',  'case_state' => 'open',   'case_description' => 'Annual cardiology check.'],
];

$appointmentsAll = [
    ['appointment_id' => 480, 'case_id' => 1038, 'doctor_id' => 13, 'appointment_date' => appointmentsDate(-20), 'appointment_time' => '15:30:00', 'appointment_status' => 'cancelled'],
    ['appointment_id' => 489, 'case_id' => 1042, 'doctor_id' => 11, 'appointment_date' => appointmentsDate(-30), 'appointment_time' => '11:15:00', 'appointment_status' => 'completed'],
    ['appointment_id' => 497, 'case_id' => 1011, 'doctor_id' => 14, 'appointment_date' => appointmentsDate(-12), 'appointment_time' => '09:00:00', 'appointment_status' => 'completed'],
    ['appointment_id' => 501, 'case_id' => 1042, 'doctor_id' => 11, 'appointment_date' => appointmentsDate(2),   'appointment_time' => '10:30:00', 'appointment_status' => 'confirmed'],
    ['appointment_id' => 502, 'case_id' => 1038, 'doctor_id' => 13, 'appointment_date' => appointmentsDate(9),   'appointment_time' => '14:00:00', 'appointment_status' => 'pending'],
    ['appointment_id' => 585, 'case_id' => 1049, 'doctor_id' => 11, 'appointment_date' => appointmentsDate(-6),  'appointment_time' => '10:00:00', 'appointment_status' => 'cancelled'],
    ['appointment_id' => 590, 'case_id' => 1033, 'doctor_id' => 11, 'appointment_date' => appointmentsDate(-3),  'appointment_time' => '14:00:00', 'appointment_status' => 'completed'],
    ['appointment_id' => 608, 'case_id' => 1056, 'doctor_id' => 11, 'appointment_date' => appointmentsDate(0),   'appointment_time' => '09:00:00', 'appointment_status' => 'completed'],
    ['appointment_id' => 609, 'case_id' => 1051, 'doctor_id' => 11, 'appointment_date' => appointmentsDate(0),   'appointment_time' => '10:30:00', 'appointment_status' => 'confirmed'],
    ['appointment_id' => 610, 'case_id' => 1049, 'doctor_id' => 11, 'appointment_date' => appointmentsDate(0),   'appointment_time' => '13:15:00', 'appointment_status' => 'confirmed'],
    ['appointment_id' => 611, 'case_id' => 1060, 'doctor_id' => 11, 'appointment_date' => appointmentsDate(0),   'appointment_time' => '15:00:00', 'appointment_status' => 'pending'],
    ['appointment_id' => 615, 'case_id' => 1056, 'doctor_id' => 11, 'appointment_date' => appointmentsDate(5),   'appointment_time' => '09:30:00', 'appointment_status' => 'confirmed'],
    ['appointment_id' => 616, 'case_id' => 1051, 'doctor_id' => 11, 'appointment_date' => appointmentsDate(7),   'appointment_time' => '11:00:00', 'appointment_status' => 'pending'],
    ['appointment_id' => 618, 'case_id' => 1063, 'doctor_id' => 13, 'appointment_date' => appointmentsDate(-2),  'appointment_time' => '10:30:00', 'appointment_status' => 'completed'],
    ['appointment_id' => 620, 'case_id' => 1063, 'doctor_id' => 13, 'appointment_date' => appointmentsDate(0),   'appointment_time' => '09:30:00', 'appointment_status' => 'completed'],
    ['appointment_id' => 621, 'case_id' => 1065, 'doctor_id' => 14, 'appointment_date' => appointmentsDate(0),   'appointment_time' => '11:00:00', 'appointment_status' => 'confirmed'],
    ['appointment_id' => 622, 'case_id' => 1067, 'doctor_id' => 15, 'appointment_date' => appointmentsDate(0),   'appointment_time' => '14:30:00', 'appointment_status' => 'pending'],
    ['appointment_id' => 624, 'case_id' => 1065, 'doctor_id' => 14, 'appointment_date' => appointmentsDate(3),   'appointment_time' => '09:00:00', 'appointment_status' => 'confirmed'],
    ['appointment_id' => 625, 'case_id' => 1070, 'doctor_id' => 16, 'appointment_date' => appointmentsDate(4),   'appointment_time' => '13:30:00', 'appointment_status' => 'pending'],
    ['appointment_id' => 630, 'case_id' => 1072, 'doctor_id' => 17, 'appointment_date' => appointmentsDate(0),   'appointment_time' => '10:00:00', 'appointment_status' => 'confirmed'],
];

$appointmentsAll = array_map(function (array $item) use ($appointmentsCases, $appointmentsDoctors, $appointmentsDepartments, $appointmentsPatients, $appointmentsToday): array {
    $case = $appointmentsCases[$item['case_id']];
    $patient = $appointmentsPatients[$case['patient_id']];
    $department = $appointmentsDepartments[$case['department_id']];

    return $item + [
        'patient_id'       => $case['patient_id'],
        'department_id'    => $case['department_id'],
        'patient_name'     => $patient['patient_name'],
        'patient_age'      => (new DateTimeImmutable($patient['birth_date']))->diff($appointmentsToday)->y,
        'doctor_name'      => $appointmentsDoctors[$item['doctor_id']]['doctor_name'],
        'department_name'  => $department['department_name'],
        'hospital_name'    => $department['hospital_name'],
        'tier_color'       => $case['tier_color'],
        'case_description' => $case['case_description'],
    ];
}, $appointmentsAll);

$appointmentsViews = $appointmentsRole === 'patient'
    ? ['upcoming' => 'Upcoming', 'past' => 'Past']
    : ['today' => 'Today', 'upcoming' => 'Upcoming', 'past' => 'Past'];

$appointmentsView = isset($_GET['view']) && is_string($_GET['view']) && isset($appointmentsViews[$_GET['view']])
    ? $_GET['view']
    : array_key_first($appointmentsViews);

$appointmentsStaffDepartments = array_filter(
    $appointmentsDepartments,
    fn (array $item): bool => $item['hospital_name'] === ($appointmentsUsers['healthcare_staff']['hospital_name'])
);

$appointmentsFilterDepartment = null;
$appointmentsFilterStatus = null;

if ($appointmentsRole === 'healthcare_staff') {
    $appointmentsFilterDepartment = isset($_GET['department_id']) && is_string($_GET['department_id'])
        && ctype_digit($_GET['department_id']) && isset($appointmentsStaffDepartments[(int) $_GET['department_id']])
        ? (int) $_GET['department_id']
        : null;
    $appointmentsFilterStatus = isset($_GET['status']) && is_string($_GET['status']) && isset($appointmentsStatuses[$_GET['status']])
        ? $_GET['status']
        : null;
}

$appointmentsRows = array_values(array_filter($appointmentsAll, function (array $item) use ($appointmentsRole, $appointmentsUser, $appointmentsStaffDepartments, $appointmentsView, $appointmentsTodayKey, $appointmentsFilterDepartment, $appointmentsFilterStatus): bool {
    if ($appointmentsRole === 'patient' && $item['patient_id'] !== $appointmentsUser['patient_id']) {
        return false;
    }
    if ($appointmentsRole === 'doctor' && $item['doctor_id'] !== $appointmentsUser['doctor_id']) {
        return false;
    }
    if ($appointmentsRole === 'healthcare_staff' && !isset($appointmentsStaffDepartments[$item['department_id']])) {
        return false;
    }
    if ($appointmentsRole === 'admin') {
        return false;
    }
    if ($appointmentsFilterDepartment !== null && $item['department_id'] !== $appointmentsFilterDepartment) {
        return false;
    }
    if ($appointmentsFilterStatus !== null && $item['appointment_status'] !== $appointmentsFilterStatus) {
        return false;
    }

    $date = $item['appointment_date'];

    if ($appointmentsView === 'today') {
        return $date === $appointmentsTodayKey;
    }
    if ($appointmentsView === 'past') {
        return $date < $appointmentsTodayKey;
    }

    return $appointmentsRole === 'patient' ? $date >= $appointmentsTodayKey : $date > $appointmentsTodayKey;
}));

usort($appointmentsRows, function (array $a, array $b) use ($appointmentsView): int {
    $order = [$a['appointment_date'], $a['appointment_time']] <=> [$b['appointment_date'], $b['appointment_time']];

    return $appointmentsView === 'past' ? -$order : $order;
});

$appointmentsHeaders = [
    'patient'          => ['title' => 'My appointments',       'subtitle' => 'Book a new visit, or reschedule and cancel the ones you have.'],
    'doctor'           => ['title' => 'My schedule',           'subtitle' => 'Dr. Andreas Kyrou · Cardiology, Nicosia State Hospital'],
    'healthcare_staff' => ['title' => 'Hospital appointments', 'subtitle' => 'Nicosia State Hospital · all departments'],
    'admin'            => ['title' => 'Appointments',          'subtitle' => 'Appointments are managed by patients, doctors and healthcare staff.'],
];

$appointmentsEmpty = [
    'today'    => ['title' => 'No appointments today',    'text' => 'New bookings for today will appear here.'],
    'upcoming' => ['title' => 'No upcoming appointments', 'text' => 'Future bookings will appear here.'],
    'past'     => ['title' => 'No past appointments',     'text' => 'Finished and cancelled visits will appear here.'],
];

if ($appointmentsRole === 'patient') {
    $appointmentsPatientCases = array_filter(
        $appointmentsCases,
        fn (array $item): bool => $item['patient_id'] === $appointmentsUser['patient_id'] && $item['case_state'] === 'open'
    );
    $appointmentsCasesLink = appointmentsPageLink('medical-cases.php', $appointmentsRole);
}

$appointmentsFormatted = function (array $item) use ($appointmentsToday): array {
    $when = new DateTimeImmutable($item['appointment_date'] . ' ' . $item['appointment_time']);

    return [
        'iso'      => $when->format('Y-m-d\TH:i'),
        'time'     => $when->format('H:i'),
        'date'     => $when->format('D, j M'),
        'relative' => appointmentsRelativeDay($item['appointment_date'], $appointmentsToday),
    ];
};

$chnPageTitle = 'Appointments';
$chnUserName = $appointmentsUser['name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | Appointments</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/appointments.css">
    <script src="../js/sidebar.js" defer></script>
    <script src="../js/topbar.js" defer></script>
    <script src="../js/appointments.js" defer></script>
</head>
<body>
<?php
$chnUserRole = $appointmentsRole;
require __DIR__ . '/../include/left-sidebar.php';

$chnUserRole = $appointmentsRoles[$appointmentsRole];
require __DIR__ . '/../include/top-bar.php';
?>

<main class="content" id="main-content" tabindex="-1">
    <div class="content__inner appointments">
        <aside class="alert alert--info appointments-demo" aria-label="Demo notice">
            <p class="appointments-demo__text"><strong>Demo data.</strong> All names and records are fictional. Preview a role:</p>
            <nav aria-label="Preview role">
                <ul class="appointments-demo__roles">
                    <?php foreach ($appointmentsRoles as $appointmentsRoleKey => $appointmentsRoleLabel): ?>
                        <li>
                            <a class="appointments-demo__link"
                               href="<?= appointmentsEscape(appointmentsQuery(['role' => $appointmentsRoleKey])) ?>"
                               <?= $appointmentsRoleKey === $appointmentsRole ? 'aria-current="page"' : '' ?>>
                                <?= appointmentsEscape($appointmentsRoleLabel) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>

        <div class="page-header">
            <div>
                <h1 class="page-header__title"><?= appointmentsEscape($appointmentsHeaders[$appointmentsRole]['title']) ?></h1>
                <p class="page-header__subtitle"><?= appointmentsEscape($appointmentsHeaders[$appointmentsRole]['subtitle']) ?></p>
            </div>
        </div>

        <p class="alert alert--info appointments-notice" id="appointments-notice" role="status" hidden></p>

        <?php if ($appointmentsRole === 'admin'): ?>
            <section class="card" aria-labelledby="appointments-admin-title">
                <div class="empty-state">
                    <h2 class="empty-state__title" id="appointments-admin-title">Not available for admins</h2>
                    <p class="empty-state__text">Admins manage accounts and complaints. Doctors and healthcare staff manage the schedule.</p>
                    <a class="btn btn--outline" href="<?= appointmentsEscape('dashboard-test.php' . appointmentsQuery(['role' => 'admin'])) ?>">Back to dashboard</a>
                </div>
            </section>
        <?php else: ?>
            <?php if ($appointmentsRole === 'patient'): ?>
                <section class="card appointments-book" aria-labelledby="appointments-book-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="appointments-book-title" tabindex="-1">Book an appointment</h2>
                            <p class="card__subtitle" id="appointments-book-subtitle">Choose one of your open cases, a doctor, then a free time.</p>
                        </div>
                        <button type="button" class="btn btn--outline btn--sm" id="appointments-book-reset" hidden>Stop rescheduling</button>
                    </header>

                    <?php if ($appointmentsPatientCases === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title">No open cases</p>
                            <p class="empty-state__text">Appointments are booked for an open case. New cases are opened on the Medical Cases page.</p>
                            <?php if ($appointmentsCasesLink !== null): ?>
                                <a class="btn btn--outline" href="<?= appointmentsEscape($appointmentsCasesLink) ?>">Go to medical cases</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                    <form class="appointments-book__form" id="appointments-form" action="../backend/appointments.php" method="post" novalidate>
                        <input type="hidden" name="role" value="<?= appointmentsEscape($appointmentsRole) ?>">
                        <input type="hidden" name="appointment_id" id="appointments-appointment-id" value="">

                        <div class="appointments-book__grid">
                            <div class="appointments-book__column">
                                <fieldset class="appointments-step">
                                    <legend class="appointments-step__title"><span class="appointments-step__number" aria-hidden="true">1</span> Case</legend>
                                    <p class="form-hint appointments-step__hint">
                                        Appointments are booked for one of your open cases. New cases are opened on the
                                        <?php if ($appointmentsCasesLink !== null): ?>
                                            <a href="<?= appointmentsEscape($appointmentsCasesLink) ?>">Medical Cases</a> page.
                                        <?php else: ?>
                                            Medical Cases page.
                                        <?php endif; ?>
                                    </p>

                                    <div class="appointments-choices">
                                        <?php foreach ($appointmentsPatientCases as $appointmentsCaseId => $appointmentsCase): ?>
                                            <?php $appointmentsDepartment = $appointmentsDepartments[$appointmentsCase['department_id']]; ?>
                                            <label class="appointments-choice">
                                                <input type="radio" class="appointments-choice__input" name="case_id" value="<?= (int) $appointmentsCaseId ?>"
                                                       data-department="<?= (int) $appointmentsCase['department_id'] ?>"
                                                       data-label="Case #<?= (int) $appointmentsCaseId ?>">
                                                <span class="appointments-choice__body">
                                                    <span class="appointments-choice__title">
                                                        Case #<?= (int) $appointmentsCaseId ?>
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
                                    <legend class="appointments-step__title"><span class="appointments-step__number" aria-hidden="true">2</span> Doctor</legend>

                                    <p class="form-hint appointments-step__hint" id="appointments-doctor-hint">Choose a case to see the doctors of its department.</p>
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
                                    <legend class="appointments-step__title"><span class="appointments-step__number" aria-hidden="true">3</span> Date and time</legend>

                                    <div class="appointments-calendar" id="appointments-calendar"
                                         data-min="<?= appointmentsEscape(appointmentsDate(1)) ?>"
                                         data-max="<?= appointmentsEscape(appointmentsDate(60)) ?>">
                                        <div class="form-group">
                                            <label class="form-label" for="appointments-date">Date</label>
                                            <input type="date" class="form-control" id="appointments-date" name="appointment_date"
                                                   min="<?= appointmentsEscape(appointmentsDate(1)) ?>" max="<?= appointmentsEscape(appointmentsDate(60)) ?>">
                                        </div>
                                    </div>
                                    <p class="form-error" id="appointments-date-error" hidden></p>

                                    <p class="form-label appointments-slots__label" id="appointments-slots-label">Available times</p>
                                    <p class="form-hint appointments-step__hint" id="appointments-slots-hint">Choose a doctor and a date first.</p>
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
                                    <p class="appointments-summary__label">Selected</p>
                                    <p class="appointments-summary__value" id="appointments-summary">Nothing selected yet.</p>
                                    <button type="submit" class="btn btn--primary btn--block" id="appointments-submit">Confirm appointment</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="card appointments-list" aria-labelledby="appointments-list-title">
                <header class="card__header">
                    <div>
                        <h2 class="card__title" id="appointments-list-title">
                            <?= $appointmentsRole === 'patient' ? 'My visits' : 'Appointments' ?>
                        </h2>
                        <p class="card__subtitle">
                            <?= count($appointmentsRows) ?> <?= count($appointmentsRows) === 1 ? 'appointment' : 'appointments' ?>
                            · <?= appointmentsEscape($appointmentsViews[$appointmentsView]) ?>
                        </p>
                    </div>
                    <nav aria-label="Appointment period">
                        <ul class="appointments-tabs">
                            <?php foreach ($appointmentsViews as $appointmentsViewKey => $appointmentsViewLabel): ?>
                                <li>
                                    <a class="appointments-tabs__link"
                                       href="<?= appointmentsEscape(appointmentsQuery(['role' => $appointmentsRole, 'view' => $appointmentsViewKey])) ?>"
                                       <?= $appointmentsViewKey === $appointmentsView ? 'aria-current="page"' : '' ?>>
                                        <?= appointmentsEscape($appointmentsViewLabel) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                </header>

                <?php if ($appointmentsRole === 'healthcare_staff'): ?>
                    <form class="appointments-filters" method="get" action="">
                        <input type="hidden" name="role" value="<?= appointmentsEscape($appointmentsRole) ?>">
                        <input type="hidden" name="view" value="<?= appointmentsEscape($appointmentsView) ?>">
                        <div class="form-group">
                            <label class="form-label" for="appointments-filter-department">Department</label>
                            <select class="form-control appointments-select" id="appointments-filter-department" name="department_id">
                                <option value="">All departments</option>
                                <?php foreach ($appointmentsStaffDepartments as $appointmentsDepartmentId => $appointmentsDepartment): ?>
                                    <option value="<?= (int) $appointmentsDepartmentId ?>" <?= $appointmentsFilterDepartment === $appointmentsDepartmentId ? 'selected' : '' ?>>
                                        <?= appointmentsEscape($appointmentsDepartment['department_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="appointments-filter-status">Status</label>
                            <select class="form-control appointments-select" id="appointments-filter-status" name="status">
                                <option value="">All statuses</option>
                                <?php foreach ($appointmentsStatuses as $appointmentsStatusKey => $appointmentsStatus): ?>
                                    <option value="<?= appointmentsEscape($appointmentsStatusKey) ?>" <?= $appointmentsFilterStatus === $appointmentsStatusKey ? 'selected' : '' ?>>
                                        <?= appointmentsEscape($appointmentsStatus['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="appointments-filters__actions">
                            <button type="submit" class="btn btn--outline">Filter</button>
                            <?php if ($appointmentsFilterDepartment !== null || $appointmentsFilterStatus !== null): ?>
                                <a class="btn btn--outline" href="<?= appointmentsEscape(appointmentsQuery(['role' => $appointmentsRole, 'view' => $appointmentsView])) ?>">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($appointmentsRows === []): ?>
                    <div class="empty-state">
                        <p class="empty-state__title"><?= appointmentsEscape($appointmentsEmpty[$appointmentsView]['title']) ?></p>
                        <p class="empty-state__text"><?= appointmentsEscape($appointmentsEmpty[$appointmentsView]['text']) ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table appointments-table">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <?php if ($appointmentsRole === 'patient'): ?>
                                        <th scope="col">Doctor</th>
                                        <th scope="col">Department</th>
                                        <th scope="col">Case</th>
                                    <?php elseif ($appointmentsRole === 'doctor'): ?>
                                        <th scope="col">Patient</th>
                                        <th scope="col">Case</th>
                                        <th scope="col">Clinical note</th>
                                    <?php else: ?>
                                        <th scope="col">Patient</th>
                                        <th scope="col">Doctor</th>
                                        <th scope="col">Department</th>
                                    <?php endif; ?>
                                    <th scope="col">Status</th>
                                    <?php if ($appointmentsRole !== 'healthcare_staff'): ?>
                                        <th scope="col"><span class="appointments-table__actions-label">Actions</span></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointmentsRows as $appointmentsItem): ?>
                                    <?php
                                        $appointmentsWhen = $appointmentsFormatted($appointmentsItem);
                                        $appointmentsIsOpen = in_array($appointmentsItem['appointment_status'], ['pending', 'confirmed'], true)
                                            && $appointmentsItem['appointment_date'] >= $appointmentsTodayKey;
                                        $appointmentsLabel = $appointmentsWhen['date'] . ' at ' . $appointmentsWhen['time'] . ' with '
                                            . ($appointmentsRole === 'patient' ? $appointmentsItem['doctor_name'] : $appointmentsItem['patient_name']);
                                        $appointmentsCanReschedule = $appointmentsRole === 'patient' && $appointmentsIsOpen;
                                        $appointmentsCanStart = $appointmentsRole === 'doctor'
                                            && $appointmentsItem['appointment_date'] === $appointmentsTodayKey
                                            && $appointmentsItem['appointment_status'] === 'confirmed';
                                        $appointmentsCanDiagnose = $appointmentsRole === 'doctor' && $appointmentsItem['appointment_status'] === 'completed';
                                        $appointmentsHasActions = $appointmentsCanReschedule || $appointmentsCanStart || $appointmentsCanDiagnose || $appointmentsIsOpen;
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
                                                <span class="appointments-table__sub">Age <?= (int) $appointmentsItem['patient_age'] ?></span>
                                            </td>
                                            <td>
                                                <span class="appointments-table__nowrap">#<?= (int) $appointmentsItem['case_id'] ?></span>
                                                <?= appointmentsTierBadge($appointmentsItem['tier_color'], $appointmentsTiers) ?>
                                            </td>
                                            <td class="appointments-table__text"><?= appointmentsEscape($appointmentsItem['case_description']) ?></td>
                                        <?php else: ?>
                                            <td>
                                                <span class="appointments-table__name"><?= appointmentsEscape($appointmentsItem['patient_name']) ?></span>
                                                <span class="appointments-table__sub">Case #<?= (int) $appointmentsItem['case_id'] ?></span>
                                            </td>
                                            <td><span class="appointments-table__nowrap"><?= appointmentsEscape($appointmentsItem['doctor_name']) ?></span></td>
                                            <td><span class="appointments-table__nowrap"><?= appointmentsEscape($appointmentsItem['department_name']) ?></span></td>
                                        <?php endif; ?>
                                        <td data-appointment-status><?= appointmentsStatusBadge($appointmentsItem['appointment_status'], $appointmentsStatuses) ?></td>
                                        <?php if ($appointmentsRole !== 'healthcare_staff'): ?>
                                            <td>
                                                <div class="appointments-actions" data-appointment-actions>
                                                    <?php if ($appointmentsCanReschedule): ?>
                                                        <button type="button" class="btn btn--outline btn--sm"
                                                                data-appointment-reschedule="<?= (int) $appointmentsItem['appointment_id'] ?>"
                                                                data-doctor-id="<?= (int) $appointmentsItem['doctor_id'] ?>"
                                                                data-case-id="<?= (int) $appointmentsItem['case_id'] ?>">Reschedule</button>
                                                    <?php endif; ?>
                                                    <?php if ($appointmentsCanStart): ?>
                                                        <button type="button" class="btn btn--primary btn--sm"
                                                                data-appointment-start="<?= appointmentsEscape($appointmentsItem['patient_name']) ?>">Start visit</button>
                                                    <?php endif; ?>
                                                    <?php if ($appointmentsCanDiagnose): ?>
                                                        <?php $appointmentsRecordsLink = appointmentsPageLink('medical-records.php', $appointmentsRole); ?>
                                                        <?php if ($appointmentsRecordsLink !== null): ?>
                                                            <a class="btn btn--outline btn--sm" href="<?= appointmentsEscape($appointmentsRecordsLink) ?>">Add diagnosis</a>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn--outline btn--sm" disabled>Add diagnosis</button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php if ($appointmentsIsOpen): ?>
                                                        <button type="button" class="btn btn--outline btn--sm appointments-actions__cancel"
                                                                data-appointment-cancel="<?= (int) $appointmentsItem['appointment_id'] ?>"
                                                                data-appointment-label="<?= appointmentsEscape($appointmentsLabel) ?>">Cancel</button>
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
