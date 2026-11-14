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
                <section class="card dashboard-card" aria-labelledby="dashboard-appointments-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-appointments-title">Upcoming appointments</h2>
                            <p class="card__subtitle">Confirmed and pending visits.</p>
                        </div>
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

                    <footer class="card__footer">
                        <?= dashboardButton('All appointments', 'appointments.php', 'btn btn--outline btn--sm') ?>
                    </footer>
                </section>

                <section class="card dashboard-card" aria-labelledby="dashboard-cases-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-cases-title">My medical cases</h2>
                            <p class="card__subtitle">The tier shows how urgent a case is.</p>
                        </div>
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

                    <footer class="card__footer">
                        <?= dashboardButton('Medical records', 'medical-records.php', 'btn btn--outline btn--sm') ?>
                    </footer>
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
                <section class="card dashboard-card" aria-labelledby="dashboard-today-title">
                    <header class="card__header">
                        <div>
                            <h2 class="card__title" id="dashboard-today-title">Today's appointments</h2>
                            <p class="card__subtitle"><?= dashboardEscape($dashboardToday->format('l, j F')) ?></p>
                        </div>
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
                                        <p class="dashboard-item__meta">Case #<?= (int) $dashboardItem['case_id'] ?></p>
                                        <?php if ($dashboardCase !== null): ?>
                                            <div class="dashboard-item__tags dashboard-item__tags--below">
                                                <?= dashboardTierBadge($dashboardCase['tier_color'], $dashboardTiers) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?= dashboardStatusBadge($dashboardItem['appointment_status'], $dashboardAppointmentStatuses) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <footer class="card__footer">
                        <?= dashboardButton('All appointments', 'appointments.php', 'btn btn--outline btn--sm') ?>
                    </footer>
                </section>

                <section class="card dashboard-card" aria-labelledby="dashboard-department-title">
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

                    <footer class="card__footer">
                        <?= dashboardButton('Medical records', 'medical-records.php', 'btn btn--outline btn--sm') ?>
                    </footer>
                </section>
            </div>
        <?php else: ?>
            <section class="card">
                <div class="empty-state">
                    <h2 class="empty-state__title"><?= dashboardEscape($dashboardRoles[$dashboardRole]) ?> dashboard</h2>
                    <p class="empty-state__text">This view is being built. The patient and doctor views are ready to preview.</p>
                    <a class="btn btn--outline btn--sm" href="?role=doctor">Open doctor view</a>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
