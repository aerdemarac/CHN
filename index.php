<?php
$dashboardRoles = [
    'patient' => 'Patient',
    'doctor'  => 'Doctor',
    'staff'   => 'Healthcare Staff',
    'admin'   => 'Admin',
];

$dashboardRole = isset($_GET['role']) && is_string($_GET['role']) && isset($dashboardRoles[$_GET['role']])
    ? $_GET['role']
    : 'patient';

$dashboardToday = new DateTimeImmutable('today');
$dashboardTodayKey = $dashboardToday->format('Y-m-d');

$dashboardTiers = [
    'orange' => ['label' => 'Orange', 'note' => 'Needs care',    'order' => 0],
    'yellow' => ['label' => 'Yellow', 'note' => 'Important',     'order' => 1],
    'green'  => ['label' => 'Green',  'note' => 'No vital risk', 'order' => 2],
];

$dashboardAppointmentStatuses = [
    'pending'   => ['label' => 'Pending',   'badge' => 'warning'],
    'confirmed' => ['label' => 'Confirmed', 'badge' => 'success'],
    'cancelled' => ['label' => 'Cancelled', 'badge' => 'danger'],
    'completed' => ['label' => 'Completed', 'badge' => 'neutral'],
];

$dashboardCaseStates = [
    'open'   => ['label' => 'Open',   'badge' => 'info'],
    'closed' => ['label' => 'Closed', 'badge' => 'neutral'],
];

function dashboardEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function dashboardDate(int $days): string
{
    return (new DateTimeImmutable('today'))->modify(sprintf('%+d days', $days))->format('Y-m-d');
}

function dashboardPage(?string $file): ?string
{
    return $file !== null && is_file(__DIR__ . '/' . $file) ? $file : null;
}

function dashboardButton(string $label, ?string $file, string $class): string
{
    $page = dashboardPage($file);

    if ($page === null) {
        return '<button type="button" class="' . $class . '" disabled>' . dashboardEscape($label) . '</button>';
    }

    return '<a class="' . $class . '" href="' . dashboardEscape($page) . '">' . dashboardEscape($label) . '</a>';
}

function dashboardRelativeDay(string $date, DateTimeImmutable $today): string
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
    'patient' => ['name' => 'Elif',    'surname' => 'Arslan',    'display' => 'Elif Arslan'],
    'doctor'  => ['name' => 'Andreas', 'surname' => 'Kyrou',     'display' => 'Dr. Andreas Kyrou'],
    'staff'   => ['name' => 'Zeynep',  'surname' => 'Şahin',     'display' => 'Zeynep Şahin'],
    'admin'   => ['name' => 'Nikos',   'surname' => 'Demetriou', 'display' => 'Nikos Demetriou'],
];

$dashboardUser = $dashboardUsers[$dashboardRole];
$dashboardGreeting = $dashboardRole === 'doctor'
    ? 'Dr. ' . $dashboardUser['surname']
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
} elseif ($dashboardRole === 'staff') {
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
        'patient' => 1248,
        'doctor'  => 64,
        'staff'   => 137,
        'admin'   => 4,
    ];
    $dashboardUserTotal = array_sum($dashboardUserCounts);
    $dashboardPrivilegedTotal = $dashboardUserTotal - $dashboardUserCounts['patient'];

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
        'patient' => 'Self-registered',
        'doctor'  => 'Created by admins',
        'staff'   => 'Created by admins',
        'admin'   => 'Created by admins',
    ];
}

$chnPageTitle = 'Dashboard';
$chnUserName = $dashboardUser['display'];
$chnUserRole = $dashboardRoles[$dashboardRole];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/index.css">
    <script src="js/sidebar.js" defer></script>
    <script src="js/topbar.js" defer></script>
    <script src="js/index.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/include/left-sidebar.php'; ?>
<?php require __DIR__ . '/include/top-bar.php'; ?>

<main class="content" id="main-content" tabindex="-1">
    <div class="content__inner dashboard">
        <aside class="alert alert--info dashboard-demo" aria-label="Demo notice">
            <p class="dashboard-demo__text"><strong>Demo data.</strong> All names and records are fictional. Preview a role:</p>
            <nav aria-label="Preview role">
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
                <h1 class="page-header__title">Welcome back, <?= dashboardEscape($dashboardGreeting) ?></h1>
                <p class="page-header__subtitle">
                    <?= dashboardEscape($dashboardToday->format('l, j F Y')) ?>
                    <?php if ($dashboardRole === 'patient'): ?>
                        · Here is an overview of your appointments and medical cases.
                    <?php elseif ($dashboardRole === 'doctor'): ?>
                        · <?= dashboardEscape($dashboardDepartment) ?>
                        · You have <?= count($dashboardTodayAppointments) ?> appointments today.
                    <?php elseif ($dashboardRole === 'staff'): ?>
                        · Emergency alerts and today's schedule across all departments.
                    <?php elseif ($dashboardRole === 'admin'): ?>
                        · System overview.
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($dashboardRole === 'patient'): ?>
                <div class="page-header__actions">
                    <?= dashboardButton('Book appointment', 'appointments.php', 'btn btn--primary') ?>
                </div>
            <?php elseif ($dashboardRole === 'doctor'): ?>
                <div class="page-header__actions">
                    <?= dashboardButton('Add diagnosis', 'medical-records.php', 'btn btn--primary') ?>
                </div>
            <?php elseif ($dashboardRole === 'admin'): ?>
                <div class="page-header__actions">
                    <?= dashboardButton('Create privileged account', null, 'btn btn--primary') ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($dashboardRole === 'patient'): ?>
            <section class="grid grid--3" aria-label="Summary">
                <div class="stat-card">
                    <div class="stat-card__icon">
                        <span class="icon icon--calendar" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label">Upcoming appointments</p>
                        <p class="stat-card__value"><?= count($dashboardUpcoming) ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--warning">
                        <span class="icon icon--cases" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label">Open cases</p>
                        <p class="stat-card__value"><?= $dashboardOpenCases ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--success">
                        <span class="icon icon--records" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label">Completed visits</p>
                        <p class="stat-card__value"><?= $dashboardCompletedVisits ?></p>
                    </div>
                </div>
            </section>

            <div class="grid grid--2 dashboard-section">
                <section class="card" aria-labelledby="dashboard-appointments-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-appointments-title">Upcoming appointments</h2>
                            <p class="card__subtitle">Confirmed and pending visits.</p>
                        </div>
                        <?= dashboardButton('View all', 'appointments.php', 'btn btn--outline btn--sm') ?>
                    </header>

                    <?php if ($dashboardUpcoming === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title">No upcoming appointments</p>
                            <p class="empty-state__text">Book a visit when you need one.</p>
                        </div>
                    <?php else: ?>
                        <ul class="dashboard-list">
                            <?php foreach ($dashboardUpcoming as $dashboardItem): ?>
                                <?php $dashboardWhen = new DateTimeImmutable($dashboardItem['appointment_date'] . ' ' . $dashboardItem['appointment_time']); ?>
                                <li class="dashboard-item dashboard-item--appointment">
                                    <time class="dashboard-date" datetime="<?= dashboardEscape($dashboardWhen->format('Y-m-d\TH:i')) ?>">
                                        <span class="dashboard-date__month"><?= dashboardEscape($dashboardWhen->format('M')) ?></span>
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
                            <h2 class="card__title" id="dashboard-cases-title">My medical cases</h2>
                            <p class="card__subtitle">The tier shows how urgent a case is.</p>
                        </div>
                        <?= dashboardButton('Medical records', 'medical-records.php', 'btn btn--outline btn--sm') ?>
                    </header>

                    <?php if ($dashboardCases === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title">No medical cases yet</p>
                            <p class="empty-state__text">Cases appear here after your first visit.</p>
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
        <?php elseif ($dashboardRole === 'doctor'): ?>
            <section class="grid grid--3" aria-label="Summary">
                <div class="stat-card">
                    <div class="stat-card__icon">
                        <span class="icon icon--calendar" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label">Appointments today</p>
                        <p class="stat-card__value"><?= count($dashboardTodayAppointments) ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--primary">
                        <span class="icon icon--cases" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label">Open cases in <?= dashboardEscape($dashboardDepartment) ?></p>
                        <p class="stat-card__value"><?= $dashboardOpenCases ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--warning">
                        <span class="icon icon--records" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label">Orange tier · Needs care</p>
                        <p class="stat-card__value"><?= $dashboardNeedsCare ?></p>
                    </div>
                </div>
            </section>

            <div class="dashboard-split dashboard-section">
                <section class="card" aria-labelledby="dashboard-today-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-today-title">Today's appointments</h2>
                            <p class="card__subtitle"><?= dashboardEscape($dashboardToday->format('l, j F')) ?></p>
                        </div>
                        <?= dashboardButton('View all', 'appointments.php', 'btn btn--outline btn--sm') ?>
                    </header>

                    <?php if ($dashboardTodayAppointments === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title">No appointments today</p>
                            <p class="empty-state__text">New bookings will appear here.</p>
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
                                        <p class="dashboard-item__title"><?= dashboardEscape($dashboardCase['patient_name'] ?? 'Unknown patient') ?></p>
                                        <div class="dashboard-item__meta dashboard-item__meta--row">
                                            <span>Case #<?= (int) $dashboardItem['case_id'] ?></span>
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
                            <h2 class="card__title" id="dashboard-department-title">Open cases</h2>
                            <p class="card__subtitle"><?= dashboardEscape($dashboardDepartment) ?> · most urgent on top.</p>
                        </div>
                        <div class="dashboard-filters">
                            <input type="search" class="form-control dashboard-filters__search" id="dashboard-case-search"
                                   placeholder="Search patient or case" aria-label="Search patient or case" autocomplete="off">
                            <select class="form-control dashboard-filters__select" id="dashboard-case-tier" aria-label="Filter by tier">
                                <option value="">All tiers</option>
                                <?php foreach ($dashboardTiers as $dashboardTierKey => $dashboardTierItem): ?>
                                    <option value="<?= dashboardEscape($dashboardTierKey) ?>"><?= dashboardEscape($dashboardTierItem['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </header>

                    <?php if ($dashboardOpenCaseRows === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title">No open cases in your department</p>
                            <p class="empty-state__text">New cases will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive dashboard-table">
                            <table class="table" id="dashboard-cases-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Patient</th>
                                        <th scope="col">Tier</th>
                                        <th scope="col">Latest treatment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dashboardOpenCaseRows as $dashboardItem): ?>
                                        <tr data-tier="<?= dashboardEscape($dashboardItem['tier_color']) ?>">
                                            <td>
                                                <span class="dashboard-table__name"><?= dashboardEscape($dashboardItem['patient_name']) ?></span>
                                                <span class="dashboard-table__sub">Case #<?= (int) $dashboardItem['case_id'] ?></span>
                                            </td>
                                            <td><?= dashboardTierBadge($dashboardItem['tier_color'], $dashboardTiers) ?></td>
                                            <td class="dashboard-table__text"><?= dashboardEscape($dashboardItem['treatment_desc']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="dashboard-filters__empty" id="dashboard-cases-empty" role="status" hidden>No cases match your search.</p>
                    <?php endif; ?>

                </section>
            </div>
        <?php elseif ($dashboardRole === 'staff'): ?>
            <section class="grid grid--3" aria-label="Summary">
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--danger">
                        <span class="icon icon--alert" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label">Emergency alerts today</p>
                        <p class="stat-card__value"><?= $dashboardEmergenciesToday ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon">
                        <span class="icon icon--calendar" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label">Appointments today</p>
                        <p class="stat-card__value"><?= count($dashboardAppointments) ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--warning">
                        <span class="icon icon--cases" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label">Orange tier · Needs care</p>
                        <p class="stat-card__value"><?= (int) $dashboardTierCounts['orange'] ?></p>
                    </div>
                </div>
            </section>

            <div class="grid grid--2 dashboard-section">
                <section class="card" aria-labelledby="dashboard-emergency-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-emergency-title">Emergency alerts</h2>
                            <p class="card__subtitle">Sent with the emergency button, newest first.</p>
                        </div>
                    </header>

                    <?php if ($dashboardEmergencies === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title">No emergency alerts</p>
                            <p class="empty-state__text">New alerts will appear here right away.</p>
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
                            <h2 class="card__title" id="dashboard-tiers-title">Open cases by tier</h2>
                            <p class="card__subtitle">All departments · <?= (int) $dashboardTierTotal ?> open cases.</p>
                        </div>
                        <?= dashboardButton('Medical cases', 'medical-cases.php', 'btn btn--outline btn--sm') ?>
                    </header>

                    <ul class="dashboard-list">
                        <?php foreach ($dashboardTiers as $dashboardTierKey => $dashboardTierItem): ?>
                            <?php $dashboardTierCount = (int) ($dashboardTierCounts[$dashboardTierKey] ?? 0); ?>
                            <li class="dashboard-item dashboard-tier">
                                <?= dashboardTierBadge($dashboardTierKey, $dashboardTiers) ?>
                                <span class="dashboard-count"><?= $dashboardTierCount ?></span>
                                <progress class="dashboard-bar dashboard-bar--<?= dashboardEscape($dashboardTierKey) ?>"
                                          max="<?= max(1, (int) $dashboardTierTotal) ?>" value="<?= $dashboardTierCount ?>"
                                          aria-label="<?= dashboardEscape($dashboardTierItem['label']) ?> tier: <?= $dashboardTierCount ?> of <?= (int) $dashboardTierTotal ?> open cases"></progress>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                </section>
            </div>

            <section class="card dashboard-section" aria-labelledby="dashboard-schedule-title">
                <header class="card__header">
                    <div>
                        <h2 class="card__title" id="dashboard-schedule-title">Today's appointments</h2>
                        <p class="card__subtitle">All departments · <?= dashboardEscape($dashboardToday->format('l, j F')) ?></p>
                    </div>
                    <?= dashboardButton('View all', 'appointments.php', 'btn btn--outline btn--sm') ?>
                </header>

                <?php if ($dashboardAppointments === []): ?>
                    <div class="empty-state">
                        <p class="empty-state__title">No appointments today</p>
                        <p class="empty-state__text">New bookings will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive dashboard-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">Time</th>
                                    <th scope="col">Patient</th>
                                    <th scope="col">Doctor</th>
                                    <th scope="col">Department</th>
                                    <th scope="col">Status</th>
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
            <section class="grid grid--3" aria-label="Summary">
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--primary">
                        <span class="icon icon--people" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label">Registered users</p>
                        <p class="stat-card__value"><?= number_format($dashboardUserTotal) ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--warning">
                        <span class="icon icon--feedback" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label">Open complaints</p>
                        <p class="stat-card__value"><?= $dashboardOpenComplaints ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon">
                        <span class="icon icon--badge" aria-hidden="true"></span>
                    </div>
                    <div>
                        <p class="stat-card__label">Privileged accounts</p>
                        <p class="stat-card__value"><?= number_format($dashboardPrivilegedTotal) ?></p>
                    </div>
                </div>
            </section>

            <div class="dashboard-split dashboard-section">
                <section class="card" aria-labelledby="dashboard-users-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-users-title">Users by role</h2>
                            <p class="card__subtitle">Doctors, staff and admins can only be added by an admin.</p>
                        </div>
                    </header>

                    <ul class="dashboard-list">
                        <?php foreach ($dashboardRoles as $dashboardRoleKey => $dashboardRoleLabel): ?>
                            <li class="dashboard-item">
                                <div class="dashboard-item__body">
                                    <p class="dashboard-item__title"><?= dashboardEscape($dashboardRoleLabel) ?></p>
                                    <p class="dashboard-item__meta"><?= dashboardEscape($dashboardUserRoleNotes[$dashboardRoleKey]) ?></p>
                                </div>
                                <span class="dashboard-count"><?= number_format((int) ($dashboardUserCounts[$dashboardRoleKey] ?? 0)) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <section class="card" aria-labelledby="dashboard-feedback-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-feedback-title">Complaints and feedback</h2>
                            <p class="card__subtitle">Latest messages, open complaints first.</p>
                        </div>
                        <?= dashboardButton('All feedback', 'feedback.php', 'btn btn--outline btn--sm') ?>
                    </header>

                    <?php if ($dashboardFeedback === []): ?>
                        <div class="empty-state">
                            <p class="empty-state__title">No feedback yet</p>
                            <p class="empty-state__text">Messages from users will appear here.</p>
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
                                            <span class="badge badge--warning">Open</span>
                                            <?= dashboardButton('Respond', 'feedback.php', 'btn btn--outline btn--sm') ?>
                                        <?php else: ?>
                                            <span class="badge badge--success">Answered</span>
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
