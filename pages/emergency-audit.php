<?php
// Unified Dynamic Emergency Management (Staff Alerts & Admin Logs)
declare(strict_types=1);
require_once dirname(__DIR__) . '/include/i18n.php';

// Determine role dynamically from query params, session, pre-existing variable, or default
$roleParam = $_GET['role'] ?? ($_GET['view'] ?? ($_GET['tab'] ?? ($emergencyView ?? ($chnUserRole ?? null))));
$normalizedRole = strtolower(trim((string) $roleParam));

// Admin role views emergency logs; Staff (and default) views emergency alerts
$isLogs = in_array($normalizedRole, ['admin', 'logs'], true);

$chnUserRole = $isLogs ? 'admin' : 'healthcare_staff';
$chnUserName = $isLogs ? t('emergency.admin_user') : t('emergency.staff_user');
$chnPageTitle = $isLogs ? t('emergency.logs_title') : t('emergency.alerts_title');

$records = [
    ['emergency_id' => 1004, 'user_id' => null, 'ip_address' => '192.0.2.14', 'emergency_date' => '2026-10-14', 'emergency_time' => '14:20:00', 'emergency_location' => 'Oak Avenue, North District', 'status' => 'pending', 'handled_by_staff_id' => null, 'handled_at' => null, 'created_at' => '2026-10-14 14:20:00'],
    ['emergency_id' => 1003, 'user_id' => 203, 'ip_address' => '198.51.100.22', 'emergency_date' => '2026-10-13', 'emergency_time' => '09:15:00', 'emergency_location' => 'Cedar Street, West Quarter', 'status' => 'in_progress', 'handled_by_staff_id' => 12, 'handled_at' => '2026-10-13 09:19:00', 'created_at' => '2026-10-13 09:15:00'],
    ['emergency_id' => 1002, 'user_id' => 204, 'ip_address' => '203.0.113.31', 'emergency_date' => '2026-10-12', 'emergency_time' => '18:45:00', 'emergency_location' => 'Riverside Park, North District', 'status' => 'resolved', 'handled_by_staff_id' => 15, 'handled_at' => '2026-10-12 18:51:00', 'created_at' => '2026-10-12 18:45:00'],
    ['emergency_id' => 1001, 'user_id' => null, 'ip_address' => '192.0.2.45', 'emergency_date' => null, 'emergency_time' => null, 'emergency_location' => 'Central Square, West Quarter', 'status' => 'fraud', 'handled_by_staff_id' => 12, 'handled_at' => '2026-10-11 11:40:00', 'created_at' => '2026-10-11 11:32:00'],
];

$labels = [
    'pending' => t('emergency.status_pending'),
    'in_progress' => t('emergency.status_in_progress'),
    'resolved' => t('emergency.status_resolved'),
    'fraud' => t('emergency.status_fraud'),
];

if (!function_exists('emergencyEscape')) {
    function emergencyEscape($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('emergencyValue')) {
    function emergencyValue($value): string {
        return $value === null || $value === '' ? t('emergency.not_available') : (string) $value;
    }
}

if (!function_exists('emergencyDate')) {
    function emergencyDate(?string $value, string $format, string $style): string {
        $date = $value === null || $value === '' ? false : DateTimeImmutable::createFromFormat($format, $value);
        return $date ? chnDate($date, $style) : emergencyValue($value);
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(chnLang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CHN | <?= emergencyEscape($chnPageTitle) ?></title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/emergency-demo.css">
    <script src="../js/sidebar.js" defer></script>
    <script src="../js/topbar.js" defer></script>
    <script src="../js/emergency-demo.js" defer></script>
</head>
<body>
<?php require dirname(__DIR__) . '/include/left-sidebar.php'; ?>
<?php require dirname(__DIR__) . '/include/top-bar.php'; ?>

<main class="content" id="main-content" tabindex="-1">
<div class="content__inner emergency-demo">

    <header class="page-header emergency-demo__header">
        <div>
            <h1 class="page-header__title"><?= emergencyEscape($chnPageTitle) ?></h1>
            <p class="page-header__subtitle">
                <?= $isLogs
                    ? te('emergency.logs_subtitle')
                    : te('emergency.alerts_subtitle') ?>
            </p>
        </div>
    </header>

    <section class="emergency-demo__summary" aria-label="<?= te('emergency.overview_label') ?>">
        <div class="card">
            <p class="emergency-demo__metric-label"><?= $isLogs ? te('emergency.total_logs') : te('emergency.total_alerts') ?></p>
            <p class="emergency-demo__metric"><?= count($records) ?></p>
            <p class="emergency-demo__muted"><?= te('emergency.all_reports') ?></p>
        </div>
        <div class="card">
            <p class="emergency-demo__metric-label"><?= te('emergency.pending_alerts') ?></p>
            <p class="emergency-demo__metric"><?= count(array_filter($records, static fn($record) => $record['status'] === 'pending')) ?></p>
            <p class="emergency-demo__muted"><?= te('emergency.awaiting') ?></p>
        </div>
        <?php if ($isLogs): ?>
        <div class="card">
            <p class="emergency-demo__metric-label"><?= te('emergency.marked_fraud') ?></p>
            <p class="emergency-demo__metric"><?= count(array_filter($records, static fn($record) => $record['status'] === 'fraud')) ?></p>
            <p class="emergency-demo__muted"><?= te('emergency.careful_review') ?></p>
        </div>
        <?php endif; ?>
    </section>

    <section class="card emergency-demo__listing" aria-labelledby="emergency-list-title">
        <header class="card__header">
            <div>
                <h2 class="card__title" id="emergency-list-title"><?= $isLogs ? te('emergency.report_history') : te('emergency.alert_overview') ?></h2>
                <p class="card__subtitle"><?= te('emergency.list_subtitle') ?></p>
            </div>
        </header>

        <div class="emergency-demo__filters" role="search" aria-label="<?= te('emergency.filter_label') ?>">
            <div>
                <label class="form-label" for="emergency-search"><?= te('emergency.search') ?></label>
                <input class="form-control" type="search" id="emergency-search" placeholder="<?= te('emergency.search_placeholder') ?>">
            </div>
            <div>
                <label class="form-label" for="emergency-status"><?= te('emergency.status') ?></label>
                <select class="form-control" id="emergency-status">
                    <option value=""><?= te('emergency.all_statuses') ?></option>
                    <?php foreach ($labels as $value => $label): ?>
                        <option value="<?= emergencyEscape($value) ?>"><?= emergencyEscape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="table-responsive" tabindex="0" role="region" aria-label="<?= te('emergency.table_label') ?>">
            <table class="table emergency-demo__table" id="emergency-table" data-messages="<?= chnJsMessages(['cancel' => 'emergency.cancel', 'close' => 'emergency.close', 'confirmBan' => 'emergency.confirm_ban', 'flagTitle' => 'emergency.flag_fraud', 'confirmFlag' => 'emergency.confirm_flag', 'banNoUser' => 'emergency.ban_no_user', 'flagNoId' => 'emergency.flag_no_id', 'banQuestion' => 'emergency.ban_question', 'flagQuestion' => 'emergency.flag_question', 'notConnected' => 'emergency.not_connected', 'viewDetails' => 'emergency.view_details', 'hideDetails' => 'emergency.hide_details']) ?>">
                <thead>
                    <tr>
                        <th scope="col"><?= te('emergency.col_id') ?></th>
                        <th scope="col"><?= te('emergency.col_user') ?></th>
                        <?php if ($isLogs): ?><th scope="col"><?= te('emergency.col_ip') ?></th><?php endif; ?>
                        <th scope="col"><?= te('emergency.col_location') ?></th>
                        <th scope="col"><?= te('emergency.col_created') ?></th>
                        <th scope="col"><?= te('emergency.status') ?></th>
                        <th scope="col"><?= te('emergency.col_staff') ?></th>
                        <th scope="col"><?= te('emergency.col_details') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $record): ?>
                    <tr data-status="<?= emergencyEscape($record['status']) ?>">
                        <td><strong>#<?= emergencyEscape($record['emergency_id']) ?></strong></td>
                        <td><?= $record['user_id'] === null ? te('emergency.anonymous') : te('emergency.user_ref', ['id' => $record['user_id']]) ?></td>
                        <?php if ($isLogs): ?><td><?= emergencyEscape(emergencyValue($record['ip_address'])) ?></td><?php endif; ?>
                        <td><?= emergencyEscape(emergencyValue($record['emergency_location'])) ?></td>
                        <td><?= emergencyEscape(emergencyDate($record['created_at'], 'Y-m-d H:i:s', 'medium_time')) ?></td>
                        <td><span class="emergency-demo__status emergency-demo__status--<?= emergencyEscape($record['status']) ?>"><?= emergencyEscape($labels[$record['status']]) ?></span></td>
                        <td><?= $record['handled_by_staff_id'] === null ? te('emergency.unassigned') : te('emergency.staff_ref', ['id' => $record['handled_by_staff_id']]) ?></td>
                        <td><button type="button" class="btn btn--outline emergency-demo__details-button" aria-expanded="false" aria-controls="emergency-detail-<?= emergencyEscape($record['emergency_id']) ?>"><?= te('emergency.view_details') ?></button></td>
                    </tr>
                    <tr class="emergency-demo__detail-row" id="emergency-detail-<?= emergencyEscape($record['emergency_id']) ?>" hidden>
                        <td colspan="<?= $isLogs ? '8' : '7' ?>">
                            <div class="emergency-demo__detail">
                                <h3><?= te('emergency.detail_title', ['id' => $record['emergency_id']]) ?></h3>
                                <dl class="emergency-demo__detail-grid">
                                    <div><dt><?= te('emergency.reported_date') ?></dt><dd><?= emergencyEscape(emergencyDate($record['emergency_date'], '!Y-m-d', 'medium')) ?></dd></div>
                                    <div><dt><?= te('emergency.reported_time') ?></dt><dd><?= emergencyEscape(emergencyValue($record['emergency_time'] === null ? null : substr((string) $record['emergency_time'], 0, 5))) ?></dd></div>
                                    <div><dt><?= te('emergency.col_created') ?></dt><dd><?= emergencyEscape(emergencyDate($record['created_at'], 'Y-m-d H:i:s', 'medium_time')) ?></dd></div>
                                    <div><dt><?= te('emergency.handled_at') ?></dt><dd><?= emergencyEscape(emergencyDate($record['handled_at'], 'Y-m-d H:i:s', 'medium_time')) ?></dd></div>
                                    <div><dt><?= te('emergency.col_location') ?></dt><dd><?= emergencyEscape(emergencyValue($record['emergency_location'])) ?></dd></div>
                                    <div><dt><?= te('emergency.col_staff') ?></dt><dd><?= $record['handled_by_staff_id'] === null ? te('emergency.unassigned') : te('emergency.staff_ref', ['id' => $record['handled_by_staff_id']]) ?></dd></div>
                                    <?php if ($isLogs): ?>
                                    <div><dt><?= te('emergency.col_user') ?></dt><dd><?= $record['user_id'] === null ? te('emergency.anonymous') : te('emergency.user_ref', ['id' => $record['user_id']]) ?></dd></div>
                                    <div><dt><?= te('emergency.col_ip') ?></dt><dd><?= emergencyEscape(emergencyValue($record['ip_address'])) ?></dd></div>
                                    <?php endif; ?>
                                </dl>
                                <?php if ($isLogs): ?>
                                <p><?= $record['status'] === 'fraud' ? '<strong>' . te('emergency.fraud_indicator_title') . '</strong> ' . te('emergency.fraud_indicator_text') : te('emergency.no_fraud') ?></p>
                                <?php endif; ?>
                                <div class="emergency-demo__actions">
                                    <?php if (!$isLogs && $record['status'] === 'pending'): ?>
                                        <button class="btn btn--primary" type="button" disabled><?= te('emergency.start_handling') ?></button>
                                    <?php elseif (!$isLogs && $record['status'] === 'in_progress'): ?>
                                        <button class="btn btn--primary" type="button" disabled><?= te('emergency.mark_resolved') ?></button>
                                    <?php endif; ?>
                                    <?php if ($isLogs || in_array($record['status'], ['pending', 'in_progress'], true)): ?>
                                        <button class="btn btn--outline emergency-demo__flag" type="button" data-emergency-id="<?= emergencyEscape($record['emergency_id']) ?>"><?= te('emergency.flag_fraud') ?></button>
                                    <?php endif; ?>
                                    <?php if ($isLogs): ?>
                                        <button class="btn btn--danger emergency-demo__ban" type="button" data-user-id="<?= emergencyEscape($record['user_id'] ?? '') ?>"><?= te('emergency.ban') ?></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="empty-state" id="emergency-empty" role="status" hidden>
            <p class="empty-state__title"><?= te('emergency.no_records') ?></p>
            <p class="empty-state__text"><?= te('emergency.no_records_text') ?></p>
        </div>
    </section>
</div>
</main>

<dialog class="emergency-demo__dialog" id="emergency-action-dialog" aria-labelledby="emergency-action-title" aria-describedby="emergency-action-description">
    <h2 id="emergency-action-title"></h2>
    <p id="emergency-action-description"></p>
    <p id="emergency-action-result" role="status" aria-live="polite" hidden></p>
    <div class="emergency-demo__actions">
        <button type="button" class="btn btn--outline" id="emergency-action-cancel"><?= te('emergency.cancel') ?></button>
        <button type="button" class="btn btn--primary" id="emergency-action-confirm"><?= te('emergency.confirm') ?></button>
    </div>
</dialog>
</body>
</html>
