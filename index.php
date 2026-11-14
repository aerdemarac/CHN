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

$dashboardTiers = [
    'orange' => ['label' => 'Orange', 'note' => 'Needs care'],
    'yellow' => ['label' => 'Yellow', 'note' => 'Important'],
    'green'  => ['label' => 'Green',  'note' => 'No vital risk'],
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

$dashboardUsers = [
    'patient' => ['name' => 'Elif',    'surname' => 'Arslan',    'display' => 'Elif Arslan'],
    'doctor'  => ['name' => 'Andreas', 'surname' => 'Kyrou',     'display' => 'Dr. Andreas Kyrou'],
    'staff'   => ['name' => 'Zeynep',  'surname' => 'Şahin',     'display' => 'Zeynep Şahin'],
    'admin'   => ['name' => 'Nikos',   'surname' => 'Demetriou', 'display' => 'Nikos Demetriou'],
];

$dashboardUser = $dashboardUsers[$dashboardRole];

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

    $dashboardCases = [
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
    ];

    $dashboardTodayKey = $dashboardToday->format('Y-m-d');

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

    usort(
        $dashboardCases,
        fn (array $a, array $b): int => ($a['case_state'] === 'open' ? 0 : 1) <=> ($b['case_state'] === 'open' ? 0 : 1)
    );

    $dashboardOpenCases = count(array_filter($dashboardCases, fn (array $item): bool => $item['case_state'] === 'open'));
    $dashboardCompletedVisits = count(array_filter(
        $dashboardAppointments,
        fn (array $item): bool => $item['appointment_status'] === 'completed'
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
                <h1 class="page-header__title">Welcome back, <?= dashboardEscape($dashboardUser['name']) ?></h1>
                <p class="page-header__subtitle">
                    <?= dashboardEscape($dashboardToday->format('l, j F Y')) ?>
                    <?php if ($dashboardRole === 'patient'): ?>
                        · Here is an overview of your appointments and medical cases.
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($dashboardRole === 'patient'): ?>
                <div class="page-header__actions">
                    <?= dashboardButton('Book appointment', 'appointments.php', 'btn btn--primary') ?>
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
                                <?php
                                    $dashboardWhen = new DateTimeImmutable($dashboardItem['appointment_date'] . ' ' . $dashboardItem['appointment_time']);
                                    $dashboardStatus = $dashboardAppointmentStatuses[$dashboardItem['appointment_status']]
                                        ?? ['label' => $dashboardItem['appointment_status'], 'badge' => 'neutral'];
                                ?>
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
                                    <span class="badge badge--<?= dashboardEscape($dashboardStatus['badge']) ?>"><?= dashboardEscape($dashboardStatus['label']) ?></span>
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
                                <?php
                                    $dashboardTier = $dashboardTiers[$dashboardItem['tier_color']] ?? null;
                                    $dashboardState = $dashboardCaseStates[$dashboardItem['case_state']]
                                        ?? ['label' => $dashboardItem['case_state'], 'badge' => 'neutral'];
                                ?>
                                <li class="dashboard-item dashboard-item--wrap">
                                    <div class="dashboard-item__body">
                                        <p class="dashboard-item__title">
                                            <?= dashboardEscape($dashboardItem['department_name']) ?>
                                            <span class="dashboard-item__id">#<?= (int) $dashboardItem['case_id'] ?></span>
                                        </p>
                                        <p class="dashboard-item__meta"><?= dashboardEscape($dashboardItem['treatment_desc']) ?></p>
                                    </div>
                                    <div class="dashboard-item__tags">
                                        <?php if ($dashboardTier !== null): ?>
                                            <span class="tier-badge tier-badge--<?= dashboardEscape($dashboardItem['tier_color']) ?>">
                                                <?= dashboardEscape($dashboardTier['label']) ?> · <?= dashboardEscape($dashboardTier['note']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="badge badge--<?= dashboardEscape($dashboardState['badge']) ?>"><?= dashboardEscape($dashboardState['label']) ?></span>
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
        <?php else: ?>
            <section class="card">
                <div class="empty-state">
                    <h2 class="empty-state__title"><?= dashboardEscape($dashboardRoles[$dashboardRole]) ?> dashboard</h2>
                    <p class="empty-state__text">This view is being built. The patient view is ready to preview.</p>
                    <a class="btn btn--outline btn--sm" href="?role=patient">Open patient view</a>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
