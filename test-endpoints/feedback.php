<?php
// Frontend prototype only: no database writes, session identity or live records.
require_once dirname(__DIR__) . '/include/prototype-role.php';
$preview = require dirname(__DIR__) . '/include/feedback-preview-data.php';
$chnPageTitle = 'Feedback & Support';
$chnUserName = 'Preview User';
$isAdmin = $chnPrototypeRole === 'admin';
$view = [
    'patient' => ['subtitle' => 'Share your experience as a patient and review responses to your own feedback.', 'form' => 'Share your patient experience', 'history' => 'My feedback history'],
    'doctor' => ['subtitle' => 'Share feedback about your clinical workflow and review responses to your own suggestions.', 'form' => 'Share your doctor workflow feedback', 'history' => 'My doctor feedback history'],
    'healthcare_staff' => ['subtitle' => 'Share feedback about care coordination and review responses to your own suggestions.', 'form' => 'Share your staff workflow feedback', 'history' => 'My staff feedback history'],
    'staff' => ['subtitle' => 'Share feedback about care coordination and review responses to your own suggestions.', 'form' => 'Share your staff workflow feedback', 'history' => 'My staff feedback history'],
    'admin' => ['subtitle' => 'Review fictional feedback and associated responses in a read-only overview.', 'form' => '', 'history' => 'Feedback overview'],
][$chnPrototypeRole];
function fbEscape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function fbDate(string $value): string {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date ? $date->format('d M Y') : 'Not available';
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback &amp; Support | CHN</title>
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
        <div><h1 class="page-header__title">Feedback &amp; Support</h1>
        <p class="page-header__subtitle"><?= fbEscape($view['subtitle']) ?></p></div>
        <span class="badge badge--info">Frontend preview</span>
    </header>
    <div class="alert feedback__notice"><strong>Fictional demonstration data.</strong> Role selection changes this preview only and does not authenticate users. No feedback is sent or saved. Active demo role: <strong><?= fbEscape($chnPrototypeRoles[$chnPrototypeRole]) ?></strong>.</div>
    <form method="get" action="<?= fbEscape($feedbackUrl) ?>" class="feedback__toolbar" aria-label="Choose prototype role">
        <div><label class="form-label" for="feedback-role">Demo role</label>
        <select class="form-control" name="role" id="feedback-role"><?php foreach ($chnPrototypeRoles as $role => $label): ?><option value="<?= $role ?>" <?= $role === $chnPrototypeRole ? 'selected' : '' ?>><?= fbEscape($label) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn--outline" type="submit">Preview role</button>
    </form>
    <?php if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'): ?>
    <div class="alert alert--warning feedback__notice" role="status">Nothing was submitted or saved. Feedback submission is not connected in this frontend preview.</div>
    <?php endif; ?>
    <?php if (!$isAdmin): ?>
    <section class="card" aria-labelledby="feedback-form-title">
        <header class="card__header"><div><h2 class="card__title" id="feedback-form-title"><?= fbEscape($view['form']) ?></h2>
        <p class="card__subtitle">Check a draft before submission becomes available.</p></div></header>
        <form id="feedback-form" method="post" action="<?= fbEscape($feedbackUrl) ?>" novalidate aria-describedby="feedback-preview-help">
            <p class="feedback__help" id="feedback-preview-help">This form checks your draft only. It will not send it, save it, or add it to the history below.</p>
            <div class="form-group">
                <label class="form-label" for="feedback-message">Your feedback <span class="feedback__muted">(required)</span></label>
                <textarea class="form-control" id="feedback-message" name="feedback_msg" rows="4" maxlength="255" required aria-describedby="feedback-message-help feedback-counter feedback-error" placeholder="Tell us what worked well or what could be clearer."></textarea>
                <div class="feedback__input-meta"><p class="form-hint" id="feedback-message-help">Maximum 255 characters. Please leave out personal or medical details.</p>
                <span class="feedback__counter" id="feedback-counter">0 / 255 characters</span></div>
                <p class="form-error" id="feedback-error" aria-live="polite"></p>
            </div>
            <div class="feedback__form-actions"><button class="btn btn--primary" id="feedback-check" type="submit" disabled>Check feedback (preview)</button><button class="btn btn--outline" type="reset">Clear draft</button></div>
            <p class="feedback__result" id="feedback-result" role="status" aria-live="polite"></p>
            <noscript><p class="form-hint">JavaScript is needed to check your draft. Submission is unavailable; nothing will be sent. You can still read the demonstration history below.</p></noscript>
        </form>
    </section>
    <?php else: ?>
    <div class="alert feedback__notice"><strong>Read-only admin preview.</strong> This fictional overview does not grant management permissions. Replying, editing and moderation are not enabled.</div>
    <?php endif; ?>
    <section class="card feedback__history" aria-labelledby="feedback-history-title">
        <header class="card__header"><div><h2 class="card__title" id="feedback-history-title"><?= fbEscape($view['history']) ?></h2>
        <p class="card__subtitle"><?= $isAdmin ? 'Fictional entries across demo identities.' : 'Only entries belonging to your current demonstration identity.' ?> Answered means an associated response exists.</p></div></header>
        <form class="feedback__toolbar" method="get" action="<?= fbEscape($feedbackUrl) ?>" aria-label="Filter feedback history">
            <input type="hidden" name="role" value="<?= $chnPrototypeRole ?>">
            <div><label class="form-label" for="feedback-view">Response</label><select class="form-control" id="feedback-view" name="view"><option value="">All feedback</option><option value="pending" <?= $responseView === 'pending' ? 'selected' : '' ?>>Pending response</option><option value="answered" <?= $responseView === 'answered' ? 'selected' : '' ?>>Answered</option></select></div>
            <button class="btn btn--outline" type="submit">Apply filter</button>
            <?php if ($responseView !== ''): ?><a class="btn btn--outline" href="<?= fbEscape($feedbackUrl) ?>#feedback-history-title">Reset filter</a><?php endif; ?>
        </form>
        <p class="card__subtitle feedback__count"><?= count($history) ?> of <?= count($ownFeedback) ?> demonstration entries</p>
        <?php if ($history): ?>
        <ol class="feedback__entries">
            <?php foreach ($history as $entry): ?>
            <?php $response = $responses[$entry['feedback_id']] ?? null; ?>
            <li><article class="feedback__entry" aria-labelledby="feedback-entry-<?= $entry['feedback_id'] ?>">
                <header class="feedback__entry-header">
                    <div><h3 class="feedback__entry-title" id="feedback-entry-<?= $entry['feedback_id'] ?>">DEMO-F-<?= $entry['feedback_id'] ?></h3>
                    <p class="card__subtitle">Feedback date: <time datetime="<?= fbEscape($entry['feedback_date']) ?>"><?= fbEscape(fbDate($entry['feedback_date'])) ?></time><?php if ($isAdmin): ?> · Demo user DEMO-U-<?= $entry['user_id'] ?><?php endif; ?></p></div>
                    <span class="badge <?= $response ? 'badge--success' : 'badge--warning' ?>"><?= $response ? 'Answered' : 'Pending response' ?></span>
                </header>
                <p class="feedback__message"><?= fbEscape($entry['feedback_msg']) ?></p>
                <div class="feedback__response">
                    <?php if ($response): ?>
                    <h4 class="feedback__response-title">Response</h4>
                    <p class="card__subtitle">Response date: <time datetime="<?= fbEscape($response['response_date']) ?>"><?= fbEscape(fbDate($response['response_date'])) ?></time></p>
                    <p class="feedback__message"><?= fbEscape($response['response_msg']) ?></p>
                    <?php else: ?><p class="feedback__pending">No response is available for this demonstration entry yet.</p><?php endif; ?>
                </div>
            </article></li>
            <?php endforeach; ?>
        </ol>
        <?php else: ?>
        <div class="empty-state">
            <h3 class="empty-state__title"><?= $ownFeedback ? 'No feedback matches this filter' : 'No feedback history yet' ?></h3>
            <p class="empty-state__text"><?= $ownFeedback ? 'Choose another response filter to see demonstration entries.' : 'There are no entries for this demonstration identity. Checking a draft does not create a history entry.' ?></p>
            <?php if ($responseView !== ''): ?><a class="btn btn--outline" href="<?= fbEscape($feedbackUrl) ?>#feedback-history-title">Show all feedback</a><?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
</main>
</body>
</html>
