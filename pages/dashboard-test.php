<?php
require_once __DIR__ . '/../include/i18n.php';

$dashboardRoles = [
    'patient'          => t('role.patient'),
    'doctor'           => t('role.doctor'),
    'healthcare_staff' => t('role.healthcare_staff'),
    'admin'            => t('role.admin'),
];

$dashboardRole =isset($_GET['role']) && is_string($_GET['role']) && isset($dashboardRoles[$_GET['role']])
    ? $_GET['role']
    : 'patient';

$dashboardToday = new DateTimeImmutable('today');
$dashboardTodayKey = $dashboardToday->format('Y-m-d');

$dashboardTiers = [
    'orange' => ['label' => t('tier.orange'), 'note' => t('tier.orange_note'), 'order' => 0],
    'yellow' => ['label' => t('tier.yellow'), 'note' => t('tier.yellow_note'), 'order' => 1],
    'green'  => ['label' => t('tier.green'),  'note' => t('tier.green_note'),  'order' => 2],
];

$dashboardAppointmentStatuses = [
    'pending'   => ['label' => t('appointment_status.pending'),   'badge' => 'warning'],
    'confirmed' => ['label' => t('appointment_status.confirmed'), 'badge' => 'success'],
    'cancelled' => ['label' => t('appointment_status.cancelled'), 'badge' => 'danger'],
    'completed' => ['label' => t('appointment_status.completed'), 'badge' => 'neutral'],
];

$dashboardCaseStates = [
    'open'   => ['label' => t('case_state.open'),   'badge' => 'info'],
    'closed' => ['label' => t('case_state.closed'), 'badge' => 'neutral'],
];

function dashboardEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function dashboardNumber(int $value): string
{
    return number_format($value, 0, '', t('dashboard.thousands_separator'));
}

function dashboardLowerFirst(string $value): string
{
    return mb_strtolower(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($value, 1, null, 'UTF-8');
}

function dashboardDate(int $days): string
{
    return (new DateTimeImmutable('today'))->modify(sprintf('%+d days', $days))->format('Y-m-d');
}

function dashboardPage(?string $file, string $role): ?string
{
    if ($file === null) {
        return null;
    }

    $query = '?' . http_build_query(['role' => $role]);

    if (is_file(dirname(__DIR__) . '/' . $file)) {
        return '../' . $file . $query;
    }

    if (is_file(__DIR__ . '/' . basename($file))) {
        return basename($file) . $query;
    }

    $demo = pathinfo($file, PATHINFO_FILENAME) . '-test.php';

    return is_file(__DIR__ . '/' . $demo) ? $demo . $query : null;
}

function dashboardButton(string $label, ?string $file, string $class, string $role): string
{
    $page = dashboardPage($file, $role);

    if ($page === null) {
        return '<button type="button" class="' . $class . '" disabled>' . dashboardEscape($label) . '</button>';
    }

    return '<a class="' . $class . '" href="' . dashboardEscape($page) . '">' . dashboardEscape($label) . '</a>';
}

function dashboardRelativeDay(string $date, DateTimeImmutable $today): string
{
    $days = (int) $today->diff(new DateTimeImmutable($date))->format('%r%a');

    if ($days === 0) {
        return t('dashboard.today');
    }
    if ($days === 1) {
        return t('dashboard.tomorrow');
    }
    if ($days === -1) {
        return t('dashboard.yesterday');
    }
    if ($days < 0) {
        return tn('dashboard.days_ago', abs($days));
    }

    return tn('dashboard.in_days', $days);
}

function dashboardTierBadge(string $tier, array $tiers): string
{
    if (!isset($tiers[$tier])) {
        return '';
    }

    return '<span class="tier-badge tier-badge--' . dashboardEscape($tier) . '">'
        . dashboardEscape($tiers[$tier]['label'] . ' · ' . $tiers[$tier]['note'])
        . '</span>';
}

function dashboardStatusBadge(string $value, array $statuses): string
{
    $status = $statuses[$value] ?? ['label' => $value, 'badge' => 'neutral'];

    return '<span class="badge badge--' . dashboardEscape($status['badge']) . '">'
        . dashboardEscape($status['label'])
        . '</span>';
}

function dashboardSortCases(array $cases, array $tiers): array
{
    usort($cases, function (array $a, array $b) use ($tiers): int {
        return [
            $a['case_state'] === 'open' ? 0 : 1,
            $tiers[$a['tier_color']]['order'] ?? 9,
        ] <=> [
            $b['case_state'] === 'open' ? 0 : 1,
            $tiers[$b['tier_color']]['order'] ?? 9,
        ];
    });

    return $cases;
}

$dashboardUsers = [
    'patient'          => ['name' => 'Elif',    'surname' => 'Arslan',    'display' => 'Elif Arslan'],
    'doctor'           => ['name' => 'Andreas', 'surname' => 'Kyrou',     'display' => 'Dr. Andreas Kyrou'],
    'healthcare_staff' => ['name' => 'Zeynep',  'surname' => 'Şahin',     'display' => 'Zeynep Şahin'],
    'admin'            => ['name' => 'Nikos',   'surname' => 'Demetriou', 'display' => 'Nikos Demetriou'],
];

$dashboardUser = $dashboardUsers[$dashboardRole];
$dashboardGreeting = $dashboardRole === 'doctor'
    ? t('dashboard.doctor_name', ['surname' => $dashboardUser['surname']])
    : $dashboardUser['name'];

if ($dashboardRole === 'patient') {
    $dashboardAppointments = [
        [
            'appointment_id'     => 501,
            'case_id'            => 1042,
            'appointment_date'   => dashboardDate(2),
            'appointment_time'   => '10:30:00',
            'appointment_status' => 'confirmed',
            'doctor_name'        => 'Dr. Andreas Kyrou',
            'department_name'    => 'Cardiology',
        ],
        [
            'appointment_id'     => 502,
            'case_id'            => 1038,
            'appointment_date'   => dashboardDate(9),
            'appointment_time'   => '14:00:00',
            'appointment_status' => 'pending',
            'doctor_name'        => 'Dr. Selin Aydın',
            'department_name'    => 'Dermatology',
        ],
        [
            'appointment_id'     => 497,
            'case_id'            => 1011,
            'appointment_date'   => dashboardDate(-12),
            'appointment_time'   => '09:00:00',
            'appointment_status' => 'completed',
            'doctor_name'        => 'Dr. Can Öztürk',
            'department_name'    => 'Internal Medicine',
        ],
        [
            'appointment_id'     => 489,
            'case_id'            => 1042,
            'appointment_date'   => dashboardDate(-30),
            'appointment_time'   => '11:15:00',
            'appointment_status' => 'completed',
            'doctor_name'        => 'Dr. Andreas Kyrou',
            'department_name'    => 'Cardiology',
        ],
    ];

    $dashboardCases = dashboardSortCases([
        [
            'case_id'         => 1011,
            'department_name' => 'Internal Medicine',
            'tier_color'      => 'green',
            'case_state'      => 'closed',
            'treatment_desc'  => 'Routine check-up completed, no further visits needed.',
        ],
        [
            'case_id'         => 1042,
            'department_name' => 'Cardiology',
            'tier_color'      => 'orange',
            'case_state'      => 'open',
            'treatment_desc'  => 'Blood pressure monitoring and medication review.',
        ],
        [
            'case_id'         => 1038,
            'department_name' => 'Dermatology',
            'tier_color'      => 'yellow',
            'case_state'      => 'open',
            'treatment_desc'  => 'Topical treatment, follow-up in four weeks.',
        ],
    ], $dashboardTiers);

    $dashboardUpcoming = array_values(array_filter(
        $dashboardAppointments,
        fn (array $item): bool => $item['appointment_date'] >= $dashboardTodayKey
            && in_array($item['appointment_status'], ['pending', 'confirmed'], true)
    ));
    usort(
        $dashboardUpcoming,
        fn (array $a, array $b): int => [$a['appointment_date'], $a['appointment_time']]
            <=> [$b['appointment_date'], $b['appointment_time']]
    );

    $dashboardOpenCases = count(array_filter($dashboardCases, fn (array $item): bool => $item['case_state'] === 'open'));
    $dashboardCompletedVisits = count(array_filter(
        $dashboardAppointments,
        fn (array $item): bool => $item['appointment_status'] === 'completed'
    ));

    $dashboardFeedback = [
        [
            'feedback_id'   => 282,
            'feedback_msg'  => 'A reminder the day before my appointment would be very helpful.',
            'feedback_date' => dashboardDate(-20),
            'response_date' => dashboardDate(-18),
        ],
        [
            'feedback_id'   => 304,
            'feedback_msg'  => 'The appointment confirmation email arrived two hours late.',
            'feedback_date' => dashboardDate(-1),
            'response_date' => null,
        ],
        [
            'feedback_id'   => 261,
            'feedback_msg'  => 'Booking my first appointment was quick and easy, thank you.',
            'feedback_date' => dashboardDate(-41),
            'response_date' => dashboardDate(-40),
        ],
    ];
    usort(
        $dashboardFeedback,
        fn (array $a, array $b): int => $b['feedback_date'] <=> $a['feedback_date']
    );
    $dashboardFeedback = array_slice($dashboardFeedback, 0, 2);
} elseif ($dashboardRole === 'doctor') {
    $dashboardDepartment = 'Cardiology';

    $dashboardCases = dashboardSortCases([
        [
            'case_id'        => 1027,
            'patient_name'   => 'Sophia Ioannou',
            'tier_color'     => 'green',
            'case_state'     => 'closed',
            'treatment_desc' => 'Routine screening completed, no findings.',
        ],
        [
            'case_id'        => 1033,
            'patient_name'   => 'Hasan Çelik',
            'tier_color'     => 'yellow',
            'case_state'     => 'closed',
            'treatment_desc' => 'Medication adjusted, condition stable.',
        ],
        [
            'case_id'        => 1042,
            'patient_name'   => 'Elif Arslan',
            'tier_color'     => 'orange',
            'case_state'     => 'open',
            'treatment_desc' => 'Blood pressure monitoring and medication review.',
        ],
        [
            'case_id'        => 1049,
            'patient_name'   => 'Andreas Georgiou',
            'tier_color'     => 'green',
            'case_state'     => 'open',
            'treatment_desc' => 'Post-operative check, recovering well.',
        ],
        [
            'case_id'        => 1051,
            'patient_name'   => 'Mehmet Yıldız',
            'tier_color'     => 'yellow',
            'case_state'     => 'open',
            'treatment_desc' => 'Irregular heartbeat, 24-hour Holter test requested.',
        ],
        [
            'case_id'        => 1056,
            'patient_name'   => 'Maria Kouppa',
            'tier_color'     => 'orange',
            'case_state'     => 'open',
            'treatment_desc' => 'Chest pain under observation, ECG scheduled.',
        ],
        [
            'case_id'        => 1060,
            'patient_name'   => 'Ayşe Demir',
            'tier_color'     => 'yellow',
            'case_state'     => 'open',
            'treatment_desc' => 'Shortness of breath on exertion, echocardiogram planned.',
        ],
    ], $dashboardTiers);

    $dashboardCasesById = array_column($dashboardCases, null, 'case_id');
    $dashboardOpenCaseRows = array_values(array_filter(
        $dashboardCases,
        fn (array $item): bool => $item['case_state'] === 'open'
    ));

    $dashboardAppointments = [
        [
            'appointment_id'     => 611,
            'case_id'            => 1060,
            'appointment_date'   => $dashboardTodayKey,
            'appointment_time'   => '15:00:00',
            'appointment_status' => 'pending',
        ],
        [
            'appointment_id'     => 608,
            'case_id'            => 1056,
            'appointment_date'   => $dashboardTodayKey,
            'appointment_time'   => '09:00:00',
            'appointment_status' => 'completed',
        ],
        [
            'appointment_id'     => 609,
            'case_id'            => 1051,
            'appointment_date'   => $dashboardTodayKey,
            'appointment_time'   => '10:30:00',
            'appointment_status' => 'confirmed',
        ],
        [
            'appointment_id'     => 610,
            'case_id'            => 1049,
            'appointment_date'   => $dashboardTodayKey,
            'appointment_time'   => '13:15:00',
            'appointment_status' => 'confirmed',
        ],
        [
            'appointment_id'     => 612,
            'case_id'            => 1042,
            'appointment_date'   => dashboardDate(2),
            'appointment_time'   => '10:30:00',
            'appointment_status' => 'confirmed',
        ],
    ];

    $dashboardTodayAppointments = array_values(array_filter(
        $dashboardAppointments,
        fn (array $item): bool => $item['appointment_date'] === $dashboardTodayKey
            && $item['appointment_status'] !== 'cancelled'
    ));
    usort(
        $dashboardTodayAppointments,
        fn (array $a, array $b): int => $a['appointment_time'] <=> $b['appointment_time']
    );

    $dashboardOpenCases = count($dashboardOpenCaseRows);
    $dashboardNeedsCare = count(array_filter(
        $dashboardOpenCaseRows,
        fn (array $item): bool => $item['tier_color'] === 'orange'
    ));
} elseif ($dashboardRole === 'healthcare_staff') {
    $dashboardEmergencies = [
        [
            'emergency_id'       => 87,
            'patient_name'       => 'Eleni Christou',
            'emergency_date'     => $dashboardTodayKey,
            'emergency_time'     => '08:15:00',
            'emergency_location' => 'Famagusta, Salamis Road',
        ],
        [
            'emergency_id'       => 88,
            'patient_name'       => 'Kemal Aksoy',
            'emergency_date'     => $dashboardTodayKey,
            'emergency_time'     => '11:42:00',
            'emergency_location' => 'Kyrenia, harbour area',
        ],
        [
            'emergency_id'       => 85,
            'patient_name'       => 'Murat Eren',
            'emergency_date'     => dashboardDate(-1),
            'emergency_time'     => '22:05:00',
            'emergency_location' => 'Nicosia, Dereboyu Avenue',
        ],
    ];
    usort(
        $dashboardEmergencies,
        fn (array $a, array $b): int => [$b['emergency_date'], $b['emergency_time']]
            <=> [$a['emergency_date'], $a['emergency_time']]
    );

    $dashboardEmergenciesToday = count(array_filter(
        $dashboardEmergencies,
        fn (array $item): bool => $item['emergency_date'] === $dashboardTodayKey
    ));

    $dashboardTierCounts = [
        'orange' => 6,
        'yellow' => 11,
        'green'  => 19,
    ];
    $dashboardTierTotal = array_sum($dashboardTierCounts);

    $dashboardAppointments = [
        ['appointment_time' => '09:00:00', 'patient_name' => 'Maria Kouppa',     'doctor_name' => 'Dr. Andreas Kyrou', 'department_name' => 'Cardiology',        'appointment_status' => 'completed'],
        ['appointment_time' => '09:30:00', 'patient_name' => 'Can Yılmaz',       'doctor_name' => 'Dr. Selin Aydın',   'department_name' => 'Dermatology',       'appointment_status' => 'completed'],
        ['appointment_time' => '10:30:00', 'patient_name' => 'Mehmet Yıldız',    'doctor_name' => 'Dr. Andreas Kyrou', 'department_name' => 'Cardiology',        'appointment_status' => 'confirmed'],
        ['appointment_time' => '11:00:00', 'patient_name' => 'Lefteris Pavlou',  'doctor_name' => 'Dr. Can Öztürk',    'department_name' => 'Internal Medicine', 'appointment_status' => 'confirmed'],
        ['appointment_time' => '13:15:00', 'patient_name' => 'Andreas Georgiou', 'doctor_name' => 'Dr. Andreas Kyrou', 'department_name' => 'Cardiology',        'appointment_status' => 'confirmed'],
        ['appointment_time' => '14:30:00', 'patient_name' => 'Deniz Kara',       'doctor_name' => 'Dr. Elena Costa',   'department_name' => 'Pediatrics',        'appointment_status' => 'pending'],
        ['appointment_time' => '15:00:00', 'patient_name' => 'Ayşe Demir',       'doctor_name' => 'Dr. Andreas Kyrou', 'department_name' => 'Cardiology',        'appointment_status' => 'pending'],
    ];
} elseif ($dashboardRole === 'admin') {
    $dashboardUserCounts = [
        'patient'          => 1248,
        'doctor'           => 64,
        'healthcare_staff' => 137,
        'admin'            => 4,
    ];
    $dashboardUserTotal = array_sum($dashboardUserCounts);
    $dashboardEstablishmentStaffTotal = $dashboardUserTotal - $dashboardUserCounts['patient'];

    $dashboardFeedback = [
        [
            'feedback_id'   => 298,
            'user_name'     => 'Can Yılmaz',
            'feedback_msg'  => 'Thank you, booking an appointment was quick and easy.',
            'feedback_date' => dashboardDate(-6),
            'response_date' => dashboardDate(-5),
        ],
        [
            'feedback_id'   => 304,
            'user_name'     => 'Elif Arslan',
            'feedback_msg'  => 'The appointment confirmation email arrived two hours late.',
            'feedback_date' => dashboardDate(-1),
            'response_date' => null,
        ],
        [
            'feedback_id'   => 301,
            'user_name'     => 'Maria Kouppa',
            'feedback_msg'  => 'I could not find where to change the language on my phone.',
            'feedback_date' => dashboardDate(-4),
            'response_date' => null,
        ],
        [
            'feedback_id'   => 303,
            'user_name'     => 'Hasan Çelik',
            'feedback_msg'  => 'It would help to see the room number on the appointment page.',
            'feedback_date' => dashboardDate(-2),
            'response_date' => null,
        ],
        [
            'feedback_id'   => 295,
            'user_name'     => 'Sophia Ioannou',
            'feedback_msg'  => 'The emergency button is hard to find on small screens.',
            'feedback_date' => dashboardDate(-9),
            'response_date' => dashboardDate(-8),
        ],
    ];
    usort(
        $dashboardFeedback,
        fn (array $a, array $b): int => [$a['response_date'] === null ? 0 : 1, $b['feedback_date']]
            <=> [$b['response_date'] === null ? 0 : 1, $a['feedback_date']]
    );

    $dashboardOpenComplaints = count(array_filter(
        $dashboardFeedback,
        fn (array $item): bool => $item['response_date'] === null
    ));
    $dashboardFeedback = array_slice($dashboardFeedback, 0, 4);

    $dashboardUserRoleNotes = [
        'patient'          => t('dashboard.self_registered'),
        'doctor'           => t('dashboard.created_by_admins'),
        'healthcare_staff' => t('dashboard.created_by_admins'),
        'admin'            => t('dashboard.created_by_admins'),
    ];
}

$chnPageTitle = t('nav.dashboard');
$chnUserName = $dashboardUser['display'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(chnLang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | <?= te('nav.dashboard') ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <script src="../js/sidebar.js" defer></script>
    <script src="../js/topbar.js" defer></script>
    <script src="../js/dashboard.js" defer></script>
</head>
<body>
<?php
$chnUserRole = $dashboardRole;
require __DIR__ . '/../include/left-sidebar.php';

$chnUserRole = $dashboardRoles[$dashboardRole];
require __DIR__ . '/../include/top-bar.php';
?>

<main class="content" id="main-content" tabindex="-1">
    <div class="content__inner dashboard">
        <aside class="alert alert--info dashboard-demo" aria-label="<?= te('dashboard.demo_notice') ?>">
            <p class="dashboard-demo__text"><strong><?= te('common.demo_title') ?></strong> <?= te('common.demo_text') ?></p>
            <nav aria-label="<?= te('dashboard.preview_role') ?>">
                <ul class="dashboard-demo__roles">
                    <?php foreach ($dashboardRoles as $dashboardRoleKey => $dashboardRoleLabel): ?>
                        <li>
                            <a class="dashboard-demo__link"
                               href="?role=<?= dashboardEscape($dashboardRoleKey) ?>"
                               <?= $dashboardRoleKey === $dashboardRole ? 'aria-current="page"' : '' ?>>
                                <?= dashboardEscape($dashboardRoleLabel) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>

        <div class="page-header">
            <div>
                <h1 class="page-header__title"><?= te('dashboard.welcome', ['name' => $dashboardGreeting]) ?></h1>
                <p class="page-header__subtitle">
                    <?= dashboardEscape(chnDate($dashboardToday, 'full')) ?>
                    <?php if ($dashboardRole === 'patient'): ?>
                        · <?= te('dashboard.subtitle_patient') ?>
                    <?php elseif ($dashboardRole === 'doctor'): ?>
                        · <?= dashboardEscape($dashboardDepartment) ?>
                        · <?= dashboardEscape(tn('dashboard.subtitle_doctor', count($dashboardTodayAppointments))) ?>
                    <?php elseif ($dashboardRole === 'healthcare_staff'): ?>
                        · <?= te('dashboard.subtitle_staff') ?>
                    <?php elseif ($dashboardRole === 'admin'): ?>
                        · <?= te('dashboard.subtitle_admin') ?>
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($dashboardRole === 'patient'): ?>
                <div class="page-header__actions">
                    <?= dashboardButton(t('dashboard.book_appointment'), 'appointments.php', 'btn btn--primary', $dashboardRole) ?>
                </div>
            <?php elseif ($dashboardRole === 'doctor'): ?>
                <div class="page-header__actions">
                    <?= dashboardButton(t('dashboard.add_diagnosis'), 'medical-records.php', 'btn btn--primary', $dashboardRole) ?>
                </div>
            <?php elseif ($dashboardRole === 'admin'): ?>
                <div class="page-header__actions">
                    <?= dashboardButton(t('dashboard.create_staff_account'), null, 'btn btn--primary', $dashboardRole) ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($dashboardRole === 'patient'): ?>
            <section class="grid grid--3" aria-label="<?= te('dashboard.summary') ?>">
                <div class="stat-card">
                    <div class="stat-card__icon">
                        <span class="icon icon--calendar" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label"><?= te('dashboard.upcoming_appointments') ?></p>
                        <p class="stat-card__value"><?= count($dashboardUpcoming) ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--warning">
                        <span class="icon icon--cases" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label"><?= te('dashboard.open_cases') ?></p>
                        <p class="stat-card__value"><?= $dashboardOpenCases ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--success">
                        <span class="icon icon--records" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label"><?= te('dashboard.completed_visits') ?></p>
                        <p class="stat-card__value"><?= $dashboardCompletedVisits ?></p>
                    </div>
                </div>
            </section>

            <div class="grid grid--2 dashboard-section">
                <section class="card" aria-labelledby="dashboard-appointments-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-appointments-title"><?= te('dashboard.upcoming_appointments') ?></h2>
                            <p class="card__subtitle"><?= te('dashboard.upcoming_subtitle') ?></p>
                        </div>
                        <?= dashboardButton(t('dashboard.view_all'), 'appointments.php', 'btn btn--outline btn--sm', $dashboardRole) ?>
                    </header>

                    <?php if ($dashboardUpcoming === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title"><?= te('dashboard.no_upcoming_title') ?></p>
                            <p class="empty-state__text"><?= te('dashboard.no_upcoming_text') ?></p>
                        </div>
                    <?php else: ?>
                        <ul class="dashboard-list">
                            <?php foreach ($dashboardUpcoming as $dashboardItem): ?>
                                <?php $dashboardWhen = new DateTimeImmutable($dashboardItem['appointment_date'] . ' ' . $dashboardItem['appointment_time']); ?>
                                <li class="dashboard-item dashboard-item--appointment">
                                    <time class="dashboard-date" datetime="<?= dashboardEscape($dashboardWhen->format('Y-m-d\TH:i')) ?>">
                                        <span class="dashboard-date__month"><?= dashboardEscape(chnDate($dashboardWhen, 'month_short')) ?></span>
                                        <span class="dashboard-date__day"><?= dashboardEscape($dashboardWhen->format('j')) ?></span>
                                    </time>
                                    <div class="dashboard-item__body">
                                        <p class="dashboard-item__title"><?= dashboardEscape($dashboardItem['doctor_name']) ?></p>
                                        <p class="dashboard-item__meta">
                                            <?= dashboardEscape($dashboardItem['department_name']) ?>
                                            · <?= dashboardEscape($dashboardWhen->format('H:i')) ?>
                                            · <?= dashboardEscape(dashboardRelativeDay($dashboardItem['appointment_date'], $dashboardToday)) ?>
                                        </p>
                                    </div>
                                    <?= dashboardStatusBadge($dashboardItem['appointment_status'], $dashboardAppointmentStatuses) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                </section>

                <section class="card" aria-labelledby="dashboard-cases-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-cases-title"><?= te('dashboard.my_cases') ?></h2>
                            <p class="card__subtitle"><?= te('dashboard.my_cases_subtitle') ?></p>
                        </div>
                        <?= dashboardButton(t('dashboard.medical_records'), 'medical-records.php', 'btn btn--outline btn--sm', $dashboardRole) ?>
                    </header>

                    <?php if ($dashboardCases === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title"><?= te('dashboard.no_cases_title') ?></p>
                            <p class="empty-state__text"><?= te('dashboard.no_cases_text') ?></p>
                        </div>
                    <?php else: ?>
                        <ul class="dashboard-list">
                            <?php foreach ($dashboardCases as $dashboardItem): ?>
                                <li class="dashboard-item dashboard-item--wrap">
                                    <div class="dashboard-item__body">
                                        <p class="dashboard-item__title">
                                            <?= dashboardEscape($dashboardItem['department_name']) ?>
                                            <span class="dashboard-item__id">#<?= (int) $dashboardItem['case_id'] ?></span>
                                        </p>
                                        <p class="dashboard-item__meta"><?= dashboardEscape($dashboardItem['treatment_desc']) ?></p>
                                    </div>
                                    <div class="dashboard-item__tags">
                                        <?= dashboardTierBadge($dashboardItem['tier_color'], $dashboardTiers) ?>
                                        <?= dashboardStatusBadge($dashboardItem['case_state'], $dashboardCaseStates) ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                </section>
            </div>

            <section class="card dashboard-section" aria-labelledby="dashboard-feedback-title">
                <header class="card__header">
                    <div>
                        <h2 class="card__title" id="dashboard-feedback-title"><?= te('dashboard.my_feedback') ?></h2>
                        <p class="card__subtitle"><?= te('dashboard.my_feedback_subtitle') ?></p>
                    </div>
                    <?= dashboardButton(t('dashboard.view_all'), 'feedback.php', 'btn btn--outline btn--sm', $dashboardRole) ?>
                </header>

                <?php if ($dashboardFeedback === []): ?>
                    <div class="empty-state">
                        <p class="empty-state__title"><?= te('dashboard.no_feedback_sent_title') ?></p>
                        <p class="empty-state__text"><?= te('dashboard.no_feedback_sent_text') ?></p>
                    </div>
                <?php else: ?>
                    <ul class="dashboard-list">
                        <?php foreach ($dashboardFeedback as $dashboardItem): ?>
                            <li class="dashboard-item dashboard-item--wrap">
                                <div class="dashboard-item__body">
                                    <p class="dashboard-item__title">
                                        <?= dashboardEscape($dashboardItem['feedback_msg']) ?>
                                    </p>
                                    <p class="dashboard-item__meta">
                                        #<?= (int) $dashboardItem['feedback_id'] ?>
                                        · <?= te('dashboard.sent_when', ['when' => dashboardLowerFirst(dashboardRelativeDay($dashboardItem['feedback_date'], $dashboardToday))]) ?>
                                        <?php if ($dashboardItem['response_date'] !== null): ?>
                                            · <?= te('dashboard.answered_when', ['when' => dashboardLowerFirst(dashboardRelativeDay($dashboardItem['response_date'], $dashboardToday))]) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="dashboard-item__tags">
                                    <?php if ($dashboardItem['response_date'] === null): ?>
                                        <span class="badge badge--warning"><?= te('dashboard.feedback_open') ?></span>
                                    <?php else: ?>
                                        <span class="badge badge--success"><?= te('dashboard.feedback_answered') ?></span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

            </section>
        <?php elseif ($dashboardRole === 'doctor'): ?>
            <section class="grid grid--3" aria-label="<?= te('dashboard.summary') ?>">
                <div class="stat-card">
                    <div class="stat-card__icon">
                        <span class="icon icon--calendar" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label"><?= te('dashboard.appointments_today') ?></p>
                        <p class="stat-card__value"><?= count($dashboardTodayAppointments) ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--primary">
                        <span class="icon icon--cases" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label"><?= te('dashboard.open_cases_in', ['department' => $dashboardDepartment]) ?></p>
                        <p class="stat-card__value"><?= $dashboardOpenCases ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--warning">
                        <span class="icon icon--records" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label"><?= te('dashboard.orange_needs_care') ?></p>
                        <p class="stat-card__value"><?= $dashboardNeedsCare ?></p>
                    </div>
                </div>
            </section>

            <div class="dashboard-split dashboard-section">
                <section class="card" aria-labelledby="dashboard-today-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-today-title"><?= te('dashboard.todays_appointments') ?></h2>
                            <p class="card__subtitle">
                                <?= dashboardEscape(chnDate($dashboardToday, 'weekday_day_month')) ?>
                                · <?= dashboardEscape(tn('dashboard.appointments_total', count($dashboardTodayAppointments))) ?>
                            </p>
                        </div>
                        <?= dashboardButton(t('dashboard.view_all'), 'appointments.php', 'btn btn--outline btn--sm', $dashboardRole) ?>
                    </header>

                    <?php if ($dashboardTodayAppointments === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title"><?= te('dashboard.no_appointments_today_title') ?></p>
                            <p class="empty-state__text"><?= te('dashboard.no_appointments_today_text') ?></p>
                        </div>
                    <?php else: ?>
                        <ul class="dashboard-list">
                            <?php foreach ($dashboardTodayAppointments as $dashboardItem): ?>
                                <?php
                                    $dashboardWhen = new DateTimeImmutable($dashboardItem['appointment_date'] . ' ' . $dashboardItem['appointment_time']);
                                    $dashboardCase = $dashboardCasesById[$dashboardItem['case_id']] ?? null;
                                ?>
                                <li class="dashboard-item dashboard-item--appointment">
                                    <time class="dashboard-date dashboard-date--time" datetime="<?= dashboardEscape($dashboardWhen->format('Y-m-d\TH:i')) ?>">
                                        <?= dashboardEscape($dashboardWhen->format('H:i')) ?>
                                    </time>
                                    <div class="dashboard-item__body">
                                        <p class="dashboard-item__title"><?= dashboardEscape($dashboardCase['patient_name'] ?? t('dashboard.unknown_patient')) ?></p>
                                        <div class="dashboard-item__meta dashboard-item__meta--row">
                                            <span><?= te('dashboard.case_number', ['id' => (int) $dashboardItem['case_id']]) ?></span>
                                            <?php if ($dashboardCase !== null): ?>
                                                <?= dashboardTierBadge($dashboardCase['tier_color'], $dashboardTiers) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?= dashboardStatusBadge($dashboardItem['appointment_status'], $dashboardAppointmentStatuses) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                </section>

                <section class="card" aria-labelledby="dashboard-department-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-department-title"><?= te('dashboard.open_cases') ?></h2>
                            <p class="card__subtitle"><?= te('dashboard.department_cases_subtitle', ['department' => $dashboardDepartment]) ?></p>
                        </div>
                        <div class="dashboard-filters">
                            <input type="search" class="form-control dashboard-filters__search" id="dashboard-case-search"
                                   placeholder="<?= te('dashboard.search_cases') ?>" aria-label="<?= te('dashboard.search_cases') ?>" autocomplete="off">
                            <select class="form-control dashboard-filters__select" id="dashboard-case-tier" aria-label="<?= te('dashboard.filter_tier') ?>">
                                <option value=""><?= te('dashboard.all_tiers') ?></option>
                                <?php foreach ($dashboardTiers as $dashboardTierKey => $dashboardTierItem): ?>
                                    <option value="<?= dashboardEscape($dashboardTierKey) ?>"><?= dashboardEscape($dashboardTierItem['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </header>

                    <?php if ($dashboardOpenCaseRows === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title"><?= te('dashboard.no_open_cases_title') ?></p>
                            <p class="empty-state__text"><?= te('dashboard.no_open_cases_text') ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive dashboard-table">
                            <table class="table" id="dashboard-cases-table">
                                <thead>
                                    <tr>
                                        <th scope="col"><?= te('dashboard.col_patient') ?></th>
                                        <th scope="col"><?= te('dashboard.col_tier') ?></th>
                                        <th scope="col"><?= te('dashboard.col_latest_treatment') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dashboardOpenCaseRows as $dashboardItem): ?>
                                        <tr data-tier="<?= dashboardEscape($dashboardItem['tier_color']) ?>">
                                            <td>
                                                <span class="dashboard-table__name"><?= dashboardEscape($dashboardItem['patient_name']) ?></span>
                                                <span class="dashboard-table__sub"><?= te('dashboard.case_number', ['id' => (int) $dashboardItem['case_id']]) ?></span>
                                            </td>
                                            <td><?= dashboardTierBadge($dashboardItem['tier_color'], $dashboardTiers) ?></td>
                                            <td class="dashboard-table__text"><?= dashboardEscape($dashboardItem['treatment_desc']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="dashboard-filters__empty" id="dashboard-cases-empty" role="status" hidden><?= te('dashboard.no_cases_match') ?></p>
                    <?php endif; ?>

                </section>
            </div>
        <?php elseif ($dashboardRole === 'healthcare_staff'): ?>
            <section class="grid grid--3" aria-label="<?= te('dashboard.summary') ?>">
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--danger">
                        <span class="icon icon--alert" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label"><?= te('dashboard.emergency_alerts_today') ?></p>
                        <p class="stat-card__value"><?= $dashboardEmergenciesToday ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon">
                        <span class="icon icon--calendar" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label"><?= te('dashboard.appointments_today') ?></p>
                        <p class="stat-card__value"><?= count($dashboardAppointments) ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--warning">
                        <span class="icon icon--cases" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label"><?= te('dashboard.orange_needs_care') ?></p>
                        <p class="stat-card__value"><?= (int) $dashboardTierCounts['orange'] ?></p>
                    </div>
                </div>
            </section>

            <div class="grid grid--2 dashboard-section">
                <section class="card" aria-labelledby="dashboard-emergency-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-emergency-title"><?= te('dashboard.emergency_alerts') ?></h2>
                            <p class="card__subtitle"><?= te('dashboard.emergency_subtitle') ?></p>
                        </div>
                    </header>

                    <?php if ($dashboardEmergencies === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title"><?= te('dashboard.no_emergencies_title') ?></p>
                            <p class="empty-state__text"><?= te('dashboard.no_emergencies_text') ?></p>
                        </div>
                    <?php else: ?>
                        <ul class="dashboard-list">
                            <?php foreach ($dashboardEmergencies as $dashboardItem): ?>
                                <?php $dashboardWhen = new DateTimeImmutable($dashboardItem['emergency_date'] . ' ' . $dashboardItem['emergency_time']); ?>
                                <li class="dashboard-item dashboard-item--appointment">
                                    <time class="dashboard-date dashboard-date--time dashboard-date--danger" datetime="<?= dashboardEscape($dashboardWhen->format('Y-m-d\TH:i')) ?>">
                                        <?= dashboardEscape($dashboardWhen->format('H:i')) ?>
                                    </time>
                                    <div class="dashboard-item__body">
                                        <p class="dashboard-item__title"><?= dashboardEscape($dashboardItem['patient_name']) ?></p>
                                        <p class="dashboard-item__meta">
                                            <?= dashboardEscape($dashboardItem['emergency_location']) ?>
                                            · <?= dashboardEscape(dashboardRelativeDay($dashboardItem['emergency_date'], $dashboardToday)) ?>
                                        </p>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="card" aria-labelledby="dashboard-tiers-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-tiers-title"><?= te('dashboard.cases_by_tier') ?></h2>
                            <p class="card__subtitle"><?= dashboardEscape(tn('dashboard.cases_by_tier_subtitle', (int) $dashboardTierTotal)) ?></p>
                        </div>
                        <?= dashboardButton(t('dashboard.medical_cases'), 'medical-cases.php', 'btn btn--outline btn--sm', $dashboardRole) ?>
                    </header>

                    <ul class="dashboard-list">
                        <?php foreach ($dashboardTiers as $dashboardTierKey => $dashboardTierItem): ?>
                            <?php $dashboardTierCount = (int) ($dashboardTierCounts[$dashboardTierKey] ?? 0); ?>
                            <li class="dashboard-item dashboard-tier">
                                <?= dashboardTierBadge($dashboardTierKey, $dashboardTiers) ?>
                                <span class="dashboard-count"><?= $dashboardTierCount ?></span>
                                <progress class="dashboard-bar dashboard-bar--<?= dashboardEscape($dashboardTierKey) ?>"
                                          max="<?= max(1, (int) $dashboardTierTotal) ?>" value="<?= $dashboardTierCount ?>"
                                          aria-label="<?= dashboardEscape(tn('dashboard.tier_progress', (int) $dashboardTierTotal, ['tier' => $dashboardTierItem['label'], 'value' => $dashboardTierCount])) ?>"></progress>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                </section>
            </div>

            <section class="card dashboard-section" aria-labelledby="dashboard-schedule-title">
                <header class="card__header">
                    <div>
                        <h2 class="card__title" id="dashboard-schedule-title"><?= te('dashboard.todays_appointments') ?></h2>
                        <p class="card__subtitle"><?= te('dashboard.schedule_subtitle', ['date' => chnDate($dashboardToday, 'weekday_day_month')]) ?></p>
                    </div>
                    <?= dashboardButton(t('dashboard.view_all'), 'appointments.php', 'btn btn--outline btn--sm', $dashboardRole) ?>
                </header>

                <?php if ($dashboardAppointments === []): ?>
                    <div class="empty-state">
                        <p class="empty-state__title"><?= te('dashboard.no_appointments_today_title') ?></p>
                        <p class="empty-state__text"><?= te('dashboard.no_appointments_today_text') ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive dashboard-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col"><?= te('dashboard.col_time') ?></th>
                                    <th scope="col"><?= te('dashboard.col_patient') ?></th>
                                    <th scope="col"><?= te('dashboard.col_doctor') ?></th>
                                    <th scope="col"><?= te('dashboard.col_department') ?></th>
                                    <th scope="col"><?= te('dashboard.col_status') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboardAppointments as $dashboardItem): ?>
                                    <tr>
                                        <td class="dashboard-table__time"><?= dashboardEscape(substr($dashboardItem['appointment_time'], 0, 5)) ?></td>
                                        <td class="dashboard-table__strong"><?= dashboardEscape($dashboardItem['patient_name']) ?></td>
                                        <td><?= dashboardEscape($dashboardItem['doctor_name']) ?></td>
                                        <td><?= dashboardEscape($dashboardItem['department_name']) ?></td>
                                        <td><?= dashboardStatusBadge($dashboardItem['appointment_status'], $dashboardAppointmentStatuses) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            </section>
        <?php elseif ($dashboardRole === 'admin'): ?>
            <section class="grid grid--3" aria-label="<?= te('dashboard.summary') ?>">
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--primary">
                        <span class="icon icon--people" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label"><?= te('dashboard.registered_users') ?></p>
                        <p class="stat-card__value"><?= dashboardEscape(dashboardNumber($dashboardUserTotal)) ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--warning">
                        <span class="icon icon--feedback" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label"><?= te('dashboard.open_complaints') ?></p>
                        <p class="stat-card__value"><?= $dashboardOpenComplaints ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon">
                        <span class="icon icon--badge" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label"><?= te('dashboard.establishment_staff') ?></p>
                        <p class="stat-card__value"><?= dashboardEscape(dashboardNumber($dashboardEstablishmentStaffTotal)) ?></p>
                    </div>
                </div>
            </section>

            <div class="dashboard-split dashboard-section">
                <section class="card" aria-labelledby="dashboard-users-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-users-title"><?= te('dashboard.users_by_role') ?></h2>
                            <p class="card__subtitle"><?= te('dashboard.users_by_role_subtitle') ?></p>
                        </div>
                    </header>

                    <ul class="dashboard-list">
                        <?php foreach ($dashboardRoles as $dashboardRoleKey => $dashboardRoleLabel): ?>
                            <li class="dashboard-item">
                                <div class="dashboard-item__body">
                                    <p class="dashboard-item__title"><?= dashboardEscape($dashboardRoleLabel) ?></p>
                                    <p class="dashboard-item__meta"><?= dashboardEscape($dashboardUserRoleNotes[$dashboardRoleKey]) ?></p>
                                </div>
                                <span class="dashboard-count"><?= dashboardEscape(dashboardNumber((int) ($dashboardUserCounts[$dashboardRoleKey] ?? 0))) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="card" aria-labelledby="dashboard-feedback-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-feedback-title"><?= te('dashboard.complaints_feedback') ?></h2>
                            <p class="card__subtitle"><?= te('dashboard.complaints_subtitle') ?></p>
                        </div>
                        <?= dashboardButton(t('dashboard.all_feedback'), 'feedback.php', 'btn btn--outline btn--sm', $dashboardRole) ?>
                    </header>

                    <?php if ($dashboardFeedback === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title"><?= te('dashboard.no_feedback_title') ?></p>
                            <p class="empty-state__text"><?= te('dashboard.no_feedback_text') ?></p>
                        </div>
                    <?php else: ?>
                        <ul class="dashboard-list">
                            <?php foreach ($dashboardFeedback as $dashboardItem): ?>
                                <?php $dashboardIsOpen = $dashboardItem['response_date'] === null; ?>
                                <li class="dashboard-item dashboard-item--wrap">
                                    <div class="dashboard-item__body">
                                        <p class="dashboard-item__title">
                                            <?= dashboardEscape($dashboardItem['user_name']) ?>
                                            <span class="dashboard-item__id">#<?= (int) $dashboardItem['feedback_id'] ?> · <?= dashboardEscape(dashboardRelativeDay($dashboardItem['feedback_date'], $dashboardToday)) ?></span>
                                        </p>
                                        <p class="dashboard-item__meta"><?= dashboardEscape($dashboardItem['feedback_msg']) ?></p>
                                    </div>
                                    <div class="dashboard-item__tags">
                                        <?php if ($dashboardIsOpen): ?>
                                            <span class="badge badge--warning"><?= te('dashboard.feedback_open') ?></span>
                                            <?= dashboardButton(t('dashboard.respond'), 'feedback.php', 'btn btn--outline btn--sm', $dashboardRole) ?>
                                        <?php else: ?>
                                            <span class="badge badge--success"><?= te('dashboard.feedback_answered') ?></span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                </section>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
