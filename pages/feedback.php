<?php
require_once dirname(__DIR__) . '/include/i18n.php';
// Frontend prototype only: no database writes, session identity or live records.
require_once dirname(__DIR__) . '/include/prototype-role.php';
$preview = require dirname(__DIR__) . '/include/feedback-preview-data.php';
$chnPageTitle = t('feedback.page_title');
$chnUserName = t('feedback.preview_user');
$isAdmin = $chnPrototypeRole === 'admin';
$view = [
    'patient' => ['subtitle' => t('feedback.patient_subtitle'), 'form' => t('feedback.patient_form'), 'history' => t('feedback.patient_history')],
    'doctor' => ['subtitle' => t('feedback.doctor_subtitle'), 'form' => t('feedback.doctor_form'), 'history' => t('feedback.doctor_history')],
    'healthcare_staff' => ['subtitle' => t('feedback.staff_subtitle'), 'form' => t('feedback.staff_form'), 'history' => t('feedback.staff_history')],
    'staff' => ['subtitle' => t('feedback.staff_subtitle'), 'form' => t('feedback.staff_form'), 'history' => t('feedback.staff_history')],
    'admin' => ['subtitle' => t('feedback.admin_subtitle'), 'form' => '', 'history' => t('feedback.admin_history')],
][$chnPrototypeRole];
function fbEscape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function fbDate(string $value): string {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date ? chnDate($date, 'medium') : t('feedback.not_available');
}
$responseView = isset($_GET['view']) && is_string($_GET['view'])
    && in_array($_GET['view'], ['pending', 'answered'], true) ? $_GET['view'] : '';
// Illustrative demo ownership only; URL roles never authorize access to live data.
$ownFeedback = array_values(array_filter($preview['feedback'], static function (array $entry) use ($isAdmin, $preview, $chnPrototypeRole): bool {
    return $isAdmin || $entry['user_id'] === $preview['user_ids'][$chnPrototypeRole];
}));
$responses = array_column($preview['responses'], null, 'feedback_id');
$history = array_values(array_filter($ownFeedback, static function (array $entry) use ($responseView, $responses): bool {
    $answered = isset($responses[$entry['feedback_id']]);
    return $responseView === '' || ($responseView === 'answered' ? $answered : !$answered);
}));
usort($history, static fn(array $a, array $b): int => strcmp($b['feedback_date'], $a['feedback_date']) ?: $b['feedback_id'] <=> $a['feedback_id']);
$feedbackUrl = chnPrototypeUrl('feedback.php', $chnPrototypeRole);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(chnLang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('feedback.page_title') ?> | CHN</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/feedback.css">
    <script src="../js/sidebar.js" defer></script>
    <script src="../js/topbar.js" defer></script>
    <script src="../js/feedback.js" defer></script>
</head>
<body>
<?php require dirname(__DIR__) . '/include/left-sidebar.php'; ?>
<?php require dirname(__DIR__) . '/include/top-bar.php'; ?>
<main class="content feedback" id="main-content" tabindex="-1">
<div class="content__inner">
    <header class="page-header">
        <div><h1 class="page-header__title"><?= te('feedback.page_title') ?></h1>
        <p class="page-header__subtitle"><?= fbEscape($view['subtitle']) ?></p></div>
        <span class="badge badge--info"><?= te('feedback.preview_badge') ?></span>
    </header>
    <div class="alert feedback__notice"><strong><?= te('feedback.notice_title') ?></strong> <?= te('feedback.notice_text') ?> <?= strtr(te('feedback.active_role'), ['{role}' => '<strong>' . fbEscape($chnPrototypeRoles[$chnPrototypeRole]) . '</strong>']) ?></div>
    <form method="get" action="<?= fbEscape($feedbackUrl) ?>" class="feedback__toolbar" aria-label="<?= te('feedback.role_picker') ?>">
        <div><label class="form-label" for="feedback-role"><?= te('feedback.demo_role') ?></label>
        <select class="form-control" name="role" id="feedback-role"><?php foreach ($chnPrototypeRoles as $role => $label): ?><option value="<?= $role ?>" <?= $role === $chnPrototypeRole ? 'selected' : '' ?>><?= fbEscape($label) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn--outline" type="submit"><?= te('feedback.preview_role') ?></button>
    </form>
    <?php if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'): ?>
    <div class="alert alert--warning feedback__notice" role="status"><?= te('feedback.post_notice') ?></div>
    <?php endif; ?>
    <?php if (!$isAdmin): ?>
    <section class="card" aria-labelledby="feedback-form-title">
        <header class="card__header"><div><h2 class="card__title" id="feedback-form-title"><?= fbEscape($view['form']) ?></h2>
        <p class="card__subtitle"><?= te('feedback.form_subtitle') ?></p></div></header>
        <form id="feedback-form" method="post" action="<?= fbEscape($feedbackUrl) ?>" novalidate aria-describedby="feedback-preview-help" data-messages="<?= chnJsMessages(['required' => 'feedback.message_required', 'tooLong' => 'feedback.message_too_long', 'counter' => 'feedback.counter', 'checked' => 'feedback.draft_checked']) ?>">
            <p class="feedback__help" id="feedback-preview-help"><?= te('feedback.form_help') ?></p>
            <div class="form-group">
                <label class="form-label" for="feedback-message"><?= te('feedback.message_label') ?> <span class="feedback__muted"><?= te('feedback.required') ?></span></label>
                <textarea class="form-control" id="feedback-message" name="feedback_msg" rows="4" maxlength="255" required aria-describedby="feedback-message-help feedback-counter feedback-error" placeholder="<?= te('feedback.message_placeholder') ?>"></textarea>
                <div class="feedback__input-meta"><p class="form-hint" id="feedback-message-help"><?= te('feedback.message_hint') ?></p>
                <span class="feedback__counter" id="feedback-counter"><?= te('feedback.counter', ['count' => 0]) ?></span></div>
                <p class="form-error" id="feedback-error" aria-live="polite"></p>
            </div>
            <div class="feedback__form-actions"><button class="btn btn--primary" id="feedback-check" type="submit" disabled><?= te('feedback.check') ?></button><button class="btn btn--outline" type="reset"><?= te('feedback.clear') ?></button></div>
            <p class="feedback__result" id="feedback-result" role="status" aria-live="polite"></p>
            <noscript><p class="form-hint"><?= te('feedback.noscript') ?></p></noscript>
        </form>
    </section>
    <?php else: ?>
    <div class="alert feedback__notice"><strong><?= te('feedback.admin_notice_title') ?></strong> <?= te('feedback.admin_notice_text') ?></div>
    <?php endif; ?>
    <section class="card feedback__history" aria-labelledby="feedback-history-title">
        <header class="card__header"><div><h2 class="card__title" id="feedback-history-title"><?= fbEscape($view['history']) ?></h2>
        <p class="card__subtitle"><?= $isAdmin ? te('feedback.history_admin_subtitle') : te('feedback.history_own_subtitle') ?></p></div></header>
        <form class="feedback__toolbar" method="get" action="<?= fbEscape($feedbackUrl) ?>" aria-label="<?= te('feedback.filter_label') ?>">
            <input type="hidden" name="role" value="<?= $chnPrototypeRole ?>">
            <div><label class="form-label" for="feedback-view"><?= te('feedback.filter_response') ?></label><select class="form-control" id="feedback-view" name="view"><option value=""><?= te('feedback.filter_all') ?></option><option value="pending" <?= $responseView === 'pending' ? 'selected' : '' ?>><?= te('feedback.status_pending') ?></option><option value="answered" <?= $responseView === 'answered' ? 'selected' : '' ?>><?= te('feedback.status_answered') ?></option></select></div>
            <button class="btn btn--outline" type="submit"><?= te('feedback.apply_filter') ?></button>
            <?php if ($responseView !== ''): ?><a class="btn btn--outline" href="<?= fbEscape($feedbackUrl) ?>#feedback-history-title"><?= te('feedback.reset_filter') ?></a><?php endif; ?>
        </form>
        <p class="card__subtitle feedback__count"><?= fbEscape(tn('feedback.count', count($ownFeedback), ['shown' => count($history)])) ?></p>
        <?php if ($history): ?>
        <ol class="feedback__entries">
            <?php foreach ($history as $entry): ?>
            <?php $response = $responses[$entry['feedback_id']] ?? null; ?>
            <li><article class="feedback__entry" aria-labelledby="feedback-entry-<?= $entry['feedback_id'] ?>">
                <header class="feedback__entry-header">
                    <div><h3 class="feedback__entry-title" id="feedback-entry-<?= $entry['feedback_id'] ?>">DEMO-F-<?= $entry['feedback_id'] ?></h3>
                    <p class="card__subtitle"><?= te('feedback.feedback_date') ?> <time datetime="<?= fbEscape($entry['feedback_date']) ?>"><?= fbEscape(fbDate($entry['feedback_date'])) ?></time><?php if ($isAdmin): ?> · <?= te('feedback.demo_user', ['id' => 'DEMO-U-' . $entry['user_id']]) ?><?php endif; ?></p></div>
                    <span class="badge <?= $response ? 'badge--success' : 'badge--warning' ?>"><?= $response ? te('feedback.status_answered') : te('feedback.status_pending') ?></span>
                </header>
                <p class="feedback__message"><?= fbEscape($entry['feedback_msg']) ?></p>
                <div class="feedback__response">
                    <?php if ($response): ?>
                    <h4 class="feedback__response-title"><?= te('feedback.response_title') ?></h4>
                    <p class="card__subtitle"><?= te('feedback.response_date') ?> <time datetime="<?= fbEscape($response['response_date']) ?>"><?= fbEscape(fbDate($response['response_date'])) ?></time></p>
                    <p class="feedback__message"><?= fbEscape($response['response_msg']) ?></p>
                    <?php else: ?><p class="feedback__pending"><?= te('feedback.no_response') ?></p><?php endif; ?>
                </div>
            </article></li>
            <?php endforeach; ?>
        </ol>
        <?php else: ?>
        <div class="empty-state">
            <h3 class="empty-state__title"><?= $ownFeedback ? te('feedback.empty_filtered_title') : te('feedback.empty_title') ?></h3>
            <p class="empty-state__text"><?= $ownFeedback ? te('feedback.empty_filtered_text') : te('feedback.empty_text') ?></p>
            <?php if ($responseView !== ''): ?><a class="btn btn--outline" href="<?= fbEscape($feedbackUrl) ?>#feedback-history-title"><?= te('feedback.show_all') ?></a><?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
</main>
</body>
</html>
