<?php
require_once __DIR__ . '/../include/i18n.php';

$complaintsRoles = [
    'patient'          => t('role.patient'),
    'doctor'           => t('role.doctor'),
    'healthcare_staff' => t('role.healthcare_staff'),
    'admin'            => t('role.admin'),
];

$complaintsRole = isset($_GET['role']) && is_string($_GET['role']) && isset($complaintsRoles[$_GET['role']])
    ? $_GET['role']
    : 'patient';

$complaintsToday = new DateTimeImmutable('today');

$complaintsStatuses = [
    'open'     => ['label' => t('complaints.status_open'),     'badge' => 'warning'],
    'answered' => ['label' => t('complaints.status_answered'), 'badge' => 'success'],
];

$complaintsTabs = [
    'open'     => t('complaints.tab_open'),
    'answered' => t('complaints.tab_answered'),
    'all'      => t('complaints.tab_all'),
];

$complaintsEmpty = [
    'open'     => ['title' => t('complaints.empty_open_title'),     'text' => t('complaints.empty_open_text')],
    'answered' => ['title' => t('complaints.empty_answered_title'), 'text' => t('complaints.empty_answered_text')],
    'all'      => ['title' => t('complaints.empty_all_title'),      'text' => t('complaints.empty_all_text')],
];

function complaintsEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function complaintsDate(int $days): string
{
    return (new DateTimeImmutable('today'))->modify(sprintf('%+d days', $days))->format('Y-m-d');
}

function complaintsUrl(array $state, array $changes = []): string
{
    $params = array_filter(
        array_merge($state, $changes),
        fn ($value): bool => $value !== null && $value !== ''
    );

    return '?' . http_build_query($params);
}

function complaintsRelativeDay(string $date, DateTimeImmutable $today, bool $waiting): string
{
    $days = (int) (new DateTimeImmutable($date))->diff($today)->format('%r%a');

    if ($days <= 0) {
        return t($waiting ? 'complaints.waiting_today' : 'complaints.day_today');
    }
    if ($days === 1) {
        return t($waiting ? 'complaints.waiting_yesterday' : 'complaints.day_yesterday');
    }

    return tn($waiting ? 'complaints.waiting_days' : 'complaints.days_ago', $days);
}

$complaintsNames = [
    'patient'          => 'Elif Arslan',
    'doctor'           => 'Dr. Andreas Kyrou',
    'healthcare_staff' => 'Zeynep Şahin',
    'admin'            => 'Nikos Demetriou',
];

$complaintsUsers = [
    3  => ['name' => 'Andreas', 'surname' => 'Kyrou',   'email' => 'andreas.kyrou@example.com',  'role' => 'doctor'],
    5  => ['name' => 'Selin',   'surname' => 'Aydın',   'email' => 'selin.aydin@example.com',    'role' => 'doctor'],
    10 => ['name' => 'Zeynep',  'surname' => 'Şahin',   'email' => 'zeynep.sahin@example.com',   'role' => 'healthcare_staff'],
    12 => ['name' => 'Elif',    'surname' => 'Arslan',  'email' => 'elif.arslan@example.com',    'role' => 'patient'],
    13 => ['name' => 'Maria',   'surname' => 'Kouppa',  'email' => 'maria.kouppa@example.com',   'role' => 'patient'],
    17 => ['name' => 'Can',     'surname' => 'Yılmaz',  'email' => 'can.yilmaz@example.com',     'role' => 'patient'],
    19 => ['name' => 'Hasan',   'surname' => 'Çelik',   'email' => 'hasan.celik@example.com',    'role' => 'patient'],
    20 => ['name' => 'Sophia',  'surname' => 'Ioannou', 'email' => 'sophia.ioannou@example.com', 'role' => 'patient'],
];

$complaintsFeedback = [
    282 => ['user_id' => 12, 'feedback_msg' => 'A reminder the day before my appointment would be very helpful.',                 'feedback_date' => complaintsDate(-20)],
    290 => ['user_id' => 5,  'feedback_msg' => 'Please add a way to see only the cancelled appointments.',                        'feedback_date' => complaintsDate(-14)],
    295 => ['user_id' => 20, 'feedback_msg' => 'The emergency button is hard to find on small screens.',                          'feedback_date' => complaintsDate(-9)],
    296 => ['user_id' => 10, 'feedback_msg' => 'The emergency alert list does not refresh by itself, I have to reload the page.', 'feedback_date' => complaintsDate(-8)],
    298 => ['user_id' => 17, 'feedback_msg' => 'Thank you, booking an appointment was quick and easy.',                           'feedback_date' => complaintsDate(-6)],
    300 => ['user_id' => 3,  'feedback_msg' => 'The appointment list for today should also show the phone number of the patient.', 'feedback_date' => complaintsDate(-5)],
    301 => ['user_id' => 13, 'feedback_msg' => 'I could not find where to change the language on my phone.',                      'feedback_date' => complaintsDate(-4)],
    303 => ['user_id' => 19, 'feedback_msg' => 'It would help to see the room number on the appointment page.',                   'feedback_date' => complaintsDate(-2)],
    304 => ['user_id' => 12, 'feedback_msg' => 'The appointment confirmation email arrived two hours late.',                      'feedback_date' => complaintsDate(-1)],
];

$complaintsResponses = [
    282 => ['response_msg' => 'Thank you for the idea. Appointment reminders are on the list for the notification settings.', 'response_date' => complaintsDate(-18)],
    290 => ['response_msg' => 'A Cancelled tab was added to the Appointments page.',                                          'response_date' => complaintsDate(-12)],
    295 => ['response_msg' => 'Thank you for reporting this. The button was moved to the top bar so it stays visible.',       'response_date' => complaintsDate(-8)],
    296 => ['response_msg' => 'Thank you. Automatic refresh is planned for the emergency alerts page.',                       'response_date' => complaintsDate(-7)],
    298 => ['response_msg' => 'Thank you for the kind words. We are glad the booking worked well for you.',                   'response_date' => complaintsDate(-5)],
];

$complaintsStatus = isset($_GET['status']) && is_string($_GET['status']) && isset($complaintsTabs[$_GET['status']])
    ? $_GET['status']
    : array_key_first($complaintsTabs);

$complaintsQuery = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';

$complaintsState = ['role' => $complaintsRole, 'status' => $complaintsStatus, 'q' => $complaintsQuery];

$complaintsRespondId = null;
if ($complaintsRole === 'admin' && isset($_GET['respond']) && is_string($_GET['respond']) && ctype_digit($_GET['respond'])
    && isset($complaintsFeedback[(int) $_GET['respond']]) && !isset($complaintsResponses[(int) $_GET['respond']])) {
    $complaintsRespondId = (int) $_GET['respond'];
}

$complaintsMatching = [];
$complaintsCounts = ['open' => 0, 'answered' => 0, 'all' => 0];

foreach ($complaintsFeedback as $complaintsId => $complaintsItem) {
    $complaintsUser = $complaintsUsers[$complaintsItem['user_id']];
    $complaintsSender = $complaintsUser['name'] . ' ' . $complaintsUser['surname'];

    if ($complaintsQuery !== ''
        && mb_stripos($complaintsItem['feedback_msg'], $complaintsQuery) === false
        && mb_stripos($complaintsSender, $complaintsQuery) === false) {
        continue;
    }

    $complaintsItemStatus = isset($complaintsResponses[$complaintsId]) ? 'answered' : 'open';
    $complaintsCounts[$complaintsItemStatus] += 1;
    $complaintsCounts['all'] += 1;
    $complaintsMatching[$complaintsId] = $complaintsItem + [
        'status'      => $complaintsItemStatus,
        'sender'      => $complaintsSender,
        'sender_role' => $complaintsUser['role'],
        'email'       => $complaintsUser['email'],
        'response'    => $complaintsResponses[$complaintsId] ?? null,
    ];
}

$complaintsRows = array_filter(
    $complaintsMatching,
    fn (array $item): bool => $complaintsStatus === 'all' || $item['status'] === $complaintsStatus
);

uasort($complaintsRows, function (array $a, array $b): int {
    if ($a['status'] !== $b['status']) {
        return $a['status'] === 'open' ? -1 : 1;
    }

    return $a['status'] === 'open'
        ? $a['feedback_date'] <=> $b['feedback_date']
        : $b['feedback_date'] <=> $a['feedback_date'];
});

$chnPageTitle = t('complaints.page_title');
$chnUserName = $complaintsNames[$complaintsRole];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(chnLang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | <?= te('complaints.page_title') ?></title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/complaints.css">
    <script src="../js/sidebar.js" defer></script>
    <script src="../js/topbar.js" defer></script>
    <script src="../js/complaints.js" defer></script>
</head>
<body>
<?php
$chnUserRole = $complaintsRole;
require __DIR__ . '/../include/left-sidebar.php';

$chnUserRole = $complaintsRoles[$complaintsRole];
require __DIR__ . '/../include/top-bar.php';
?>

<main class="content" id="main-content" tabindex="-1">
    <div class="content__inner complaints">
        <aside class="alert alert--info complaints-demo" aria-label="<?= te('complaints.demo_notice') ?>">
            <p class="complaints-demo__text"><strong><?= te('common.demo_title') ?></strong> <?= te('common.demo_text') ?></p>
            <nav aria-label="<?= te('complaints.preview_role') ?>">
                <ul class="complaints-demo__roles">
                    <?php foreach ($complaintsRoles as $complaintsRoleKey => $complaintsRoleLabel): ?>
                        <li>
                            <a class="complaints-demo__link"
                               href="<?= complaintsEscape(complaintsUrl(['role' => $complaintsRoleKey])) ?>"
                               <?= $complaintsRoleKey === $complaintsRole ? 'aria-current="page"' : '' ?>>
                                <?= complaintsEscape($complaintsRoleLabel) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>

        <div class="page-header">
            <div>
                <h1 class="page-header__title"><?= te('complaints.title') ?></h1>
                <p class="page-header__subtitle">
                    <?= te($complaintsRole === 'admin' ? 'complaints.subtitle_admin' : 'complaints.subtitle_other') ?>
                </p>
            </div>
        </div>

        <?php if ($complaintsRole !== 'admin'): ?>
            <section class="card" aria-labelledby="complaints-denied-title">
                <div class="empty-state">
                    <h2 class="empty-state__title" id="complaints-denied-title"><?= te('common.admins_only') ?></h2>
                    <p class="empty-state__text"><?= te('complaints.denied_text') ?></p>
                    <a class="btn btn--outline" href="<?= complaintsEscape('dashboard-test.php' . complaintsUrl(['role' => $complaintsRole])) ?>"><?= te('complaints.back_dashboard') ?></a>
                </div>
            </section>
        <?php else: ?>
            <p class="alert alert--info complaints-notice" id="complaints-notice" role="status" tabindex="-1" hidden
               data-messages="<?= chnJsMessages([
                   'required'      => 'complaints.error_required',
                   'tooLong'       => 'complaints.error_too_long',
                   'responseToday' => 'complaints.response_today',
                   'answered'      => 'complaints.status_answered',
                   'saved'         => 'complaints.demo_saved',
               ]) ?>"></p>

            <section class="card complaints-list" id="complaints-list" aria-labelledby="complaints-list-title">
                <header class="card__header">
                    <div>
                        <h2 class="card__title" id="complaints-list-title"><?= te('complaints.list_title') ?></h2>
                        <p class="card__subtitle">
                            <?= complaintsEscape($complaintsQuery !== ''
                                ? tn('complaints.shown_query', count($complaintsRows), ['query' => $complaintsQuery])
                                : tn('complaints.shown', count($complaintsRows))) ?>
                            <?= te($complaintsStatus === 'answered' ? 'complaints.sort_newest' : 'complaints.sort_waiting') ?>
                        </p>
                    </div>
                </header>

                <nav aria-label="<?= te('complaints.filter_status') ?>">
                    <ul class="complaints-tabs">
                        <?php foreach ($complaintsTabs as $complaintsTabKey => $complaintsTabLabel): ?>
                            <li>
                                <a class="complaints-tabs__link"
                                   href="<?= complaintsEscape(complaintsUrl($complaintsState, ['status' => $complaintsTabKey]) . '#complaints-list') ?>"
                                   <?= $complaintsTabKey === $complaintsStatus ? 'aria-current="page"' : '' ?>>
                                    <?= complaintsEscape($complaintsTabLabel) ?>
                                    <span class="complaints-tabs__count" data-complaints-count="<?= complaintsEscape($complaintsTabKey) ?>"><?= (int) $complaintsCounts[$complaintsTabKey] ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>

                <form class="complaints-search" method="get" action="<?= complaintsEscape('complaints-test.php#complaints-list') ?>" role="search">
                    <input type="hidden" name="role" value="<?= complaintsEscape($complaintsRole) ?>">
                    <input type="hidden" name="status" value="<?= complaintsEscape($complaintsStatus) ?>">
                    <label class="form-label" for="complaints-q"><?= te('complaints.search_label') ?></label>
                    <div class="complaints-search__control">
                        <input class="form-control" type="search" id="complaints-q" name="q" maxlength="100"
                               autocomplete="off" value="<?= complaintsEscape($complaintsQuery) ?>">
                        <button type="submit" class="btn btn--primary"><?= te('complaints.search') ?></button>
                        <a class="btn btn--outline" href="<?= complaintsEscape(complaintsUrl($complaintsState, ['q' => null]) . '#complaints-list') ?>"><?= te('complaints.clear') ?></a>
                    </div>
                </form>

                <?php if ($complaintsRows === []): ?>
                    <div class="empty-state">
                        <p class="empty-state__title"><?= complaintsEscape($complaintsQuery !== '' ? t('complaints.no_results_title') : $complaintsEmpty[$complaintsStatus]['title']) ?></p>
                        <p class="empty-state__text"><?= complaintsEscape($complaintsQuery !== '' ? t('complaints.no_results_text') : $complaintsEmpty[$complaintsStatus]['text']) ?></p>
                    </div>
                <?php else: ?>
                    <ol class="complaints-items">
                        <?php foreach ($complaintsRows as $complaintsId => $complaintsItem): ?>
                            <?php
                                $complaintsSent = new DateTimeImmutable($complaintsItem['feedback_date']);
                                $complaintsItemStatus = $complaintsStatuses[$complaintsItem['status']];
                            ?>
                            <li>
                                <article class="complaints-item" id="complaint-<?= (int) $complaintsId ?>"
                                         data-complaints-item data-complaints-status="<?= complaintsEscape($complaintsItem['status']) ?>"
                                         aria-labelledby="complaint-<?= (int) $complaintsId ?>-sender">
                                    <header class="complaints-item__header">
                                        <h3 class="complaints-item__sender" id="complaint-<?= (int) $complaintsId ?>-sender">
                                            <span data-complaints-sender><?= complaintsEscape($complaintsItem['sender']) ?></span>
                                            <span class="badge badge--neutral"><?= complaintsEscape($complaintsRoles[$complaintsItem['sender_role']]) ?></span>
                                        </h3>
                                        <span class="badge badge--<?= complaintsEscape($complaintsItemStatus['badge']) ?>" data-complaints-badge><?= complaintsEscape($complaintsItemStatus['label']) ?></span>
                                    </header>
                                    <p class="complaints-item__meta">
                                        <span><?= complaintsEscape($complaintsItem['email']) ?></span>
                                        <time datetime="<?= complaintsEscape($complaintsSent->format('Y-m-d')) ?>"><?= complaintsEscape(chnDate($complaintsSent, 'medium')) ?></time>
                                        <span data-complaints-wait="<?= complaintsEscape(complaintsRelativeDay($complaintsItem['feedback_date'], $complaintsToday, false)) ?>"><?= complaintsEscape(complaintsRelativeDay($complaintsItem['feedback_date'], $complaintsToday, $complaintsItem['status'] === 'open')) ?></span>
                                    </p>

                                    <p class="complaints-item__message"><?= complaintsEscape($complaintsItem['feedback_msg']) ?></p>

                                    <?php if ($complaintsItem['response'] !== null): ?>
                                        <?php $complaintsAnswered = new DateTimeImmutable($complaintsItem['response']['response_date']); ?>
                                        <div class="complaints-item__response">
                                            <p class="complaints-item__response-title">
                                                <?= te('complaints.response') ?> ·
                                                <time datetime="<?= complaintsEscape($complaintsAnswered->format('Y-m-d')) ?>"><?= complaintsEscape(chnDate($complaintsAnswered, 'medium')) ?></time>
                                            </p>
                                            <p class="complaints-item__message"><?= complaintsEscape($complaintsItem['response']['response_msg']) ?></p>
                                        </div>
                                    <?php elseif ($complaintsRespondId === $complaintsId): ?>
                                        <form class="complaints-form" method="post" novalidate data-complaints-form
                                              action="<?= complaintsEscape('../backend/complaints.php' . complaintsUrl(['role' => $complaintsRole])) ?>">
                                            <input type="hidden" name="action" value="respond">
                                            <input type="hidden" name="feedback_id" value="<?= (int) $complaintsId ?>">
                                            <div class="form-group">
                                                <label class="form-label" for="complaints-response-<?= (int) $complaintsId ?>"><?= te('complaints.your_response') ?></label>
                                                <textarea class="form-control" id="complaints-response-<?= (int) $complaintsId ?>" name="response_msg"
                                                          rows="3" maxlength="255" required
                                                          aria-describedby="complaints-response-<?= (int) $complaintsId ?>-hint complaints-response-<?= (int) $complaintsId ?>-error"></textarea>
                                                <p class="form-hint" id="complaints-response-<?= (int) $complaintsId ?>-hint">
                                                    <?= str_replace('{current}', '<span data-complaints-counter>0</span>', complaintsEscape(tn('complaints.counter', 255))) ?>
                                                    <?= te('complaints.response_hint') ?>
                                                </p>
                                                <p class="form-error" id="complaints-response-<?= (int) $complaintsId ?>-error" role="alert" hidden></p>
                                            </div>
                                            <div class="complaints-form__actions">
                                                <button type="submit" class="btn btn--primary"><?= te('complaints.send_response') ?></button>
                                                <a class="btn btn--outline" href="<?= complaintsEscape(complaintsUrl($complaintsState) . '#complaint-' . $complaintsId) ?>"><?= te('complaints.cancel') ?></a>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <a class="btn btn--outline btn--sm"
                                           href="<?= complaintsEscape(complaintsUrl($complaintsState, ['respond' => $complaintsId]) . '#complaint-' . $complaintsId) ?>"
                                           aria-label="<?= te('complaints.respond_to', ['name' => $complaintsItem['sender']]) ?>"><?= te('complaints.respond') ?></a>
                                    <?php endif; ?>
                                </article>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
