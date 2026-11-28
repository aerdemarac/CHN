<?php
declare(strict_types=1);

$chnProjectRoot = dirname(__DIR__);
require_once $chnProjectRoot . '/backend/auth.php';
require_once $chnProjectRoot . '/backend/db.php';
require_once $chnProjectRoot . '/backend/validation.php';
require_once $chnProjectRoot . '/include/i18n.php';

$sessionUser = requireRole(['healthcare_staff', 'admin'], false);
$role = $sessionUser['role'];
$isLogs = $role === 'admin';

$chnUserRole = $role;
$chnUserName = trim($sessionUser['name'] . ' ' . $sessionUser['surname']);
if ($chnUserName === '') {
    $chnUserName = $isLogs ? t('emergency.admin_user') : t('emergency.staff_user');
}
$chnPageTitle = $isLogs ? t('emergency.logs_title') : t('emergency.alerts_title');

$staffHospitalId = null;
$staffHospitalName = null;
$records = [];

try {
    $pdo = chnGetPdo();

    if (!$isLogs) {
        $staffStmt = $pdo->prepare(
            'SELECT s.staff_id, s.hospital_id, h.hospital_name
             FROM `staff` s
             LEFT JOIN `hospital` h ON h.hospital_id = s.hospital_id
             WHERE s.user_id = :user_id
             LIMIT 1'
        );
        $staffStmt->execute([':user_id' => $sessionUser['user_id']]);
        $staffRow = $staffStmt->fetch();
        if ($staffRow !== false && $staffRow['hospital_id'] !== null) {
            $staffHospitalId = (int) $staffRow['hospital_id'];
            $staffHospitalName = $staffRow['hospital_name'] !== null ? (string) $staffRow['hospital_name'] : null;
        }
    }

    $sql = "SELECT e.emergency_id, e.user_id, e.hospital_id, e.ip_address,
                   e.emergency_date, e.emergency_time, e.gps_location, e.manual_address,
                   e.emergency_type, e.anon_name, e.anon_phone, e.anon_dob,
                   e.status, e.handled_by_staff_id, e.handled_at, e.created_at,
                   u.name AS user_first_name, u.surname AS user_last_name,
                   u.phone AS user_phone, u.birth_date AS user_dob,
                   u.role AS user_role, u.is_banned AS user_is_banned,
                   h.hospital_name,
                   su.name AS staff_first_name, su.surname AS staff_last_name
            FROM `emergency_log` e
            LEFT JOIN `user` u ON u.user_id = e.user_id
            LEFT JOIN `hospital` h ON h.hospital_id = e.hospital_id
            LEFT JOIN `staff` s ON s.staff_id = e.handled_by_staff_id
            LEFT JOIN `user` su ON su.user_id = s.user_id";

    $params = [];
    if (!$isLogs) {
        if ($staffHospitalId !== null) {
            $sql .= " WHERE (e.status = 'pending' AND e.hospital_id IS NULL) OR e.hospital_id = :staff_hospital_id";
            $params[':staff_hospital_id'] = $staffHospitalId;
        } else {
            $sql .= " WHERE e.status = 'pending' AND e.hospital_id IS NULL";
        }
        $sql .= " ORDER BY CASE WHEN e.status = 'pending' THEN 0 WHEN e.status = 'in_progress' THEN 1 ELSE 2 END,
                           e.created_at DESC, e.emergency_id DESC";
    } else {
        $sql .= ' ORDER BY e.created_at DESC, e.emergency_id DESC';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
} catch (Throwable $e) {
    chnLogError('Emergency audit load error: ' . $e->getMessage());
}

$labels = [
    'pending' => t('emergency.status_pending'),
    'in_progress' => t('emergency.status_in_progress'),
    'resolved' => t('emergency.status_resolved'),
    'fraud' => t('emergency.status_fraud'),
];

$typeLabels = [];
foreach (CHN_EMERGENCY_TYPES as $typeKey) {
    $typeLabels[$typeKey] = t('emergency_call.type_' . $typeKey);
}

if (!function_exists('emergencyEscape')) {
    function emergencyEscape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('emergencyValue')) {
    function emergencyValue($value): string
    {
        return $value === null || $value === '' ? t('emergency.not_available') : (string) $value;
    }
}

if (!function_exists('emergencyDate')) {
    function emergencyDate(?string $value, string $format, string $style): string
    {
        $date = $value === null || $value === '' ? false : DateTimeImmutable::createFromFormat($format, $value);
        return $date ? chnDate($date, $style) : emergencyValue($value);
    }
}

$pendingCount = count(array_filter($records, static fn(array $r): bool => $r['status'] === 'pending'));
$handledCount = count(array_filter($records, static fn(array $r): bool => $r['hospital_id'] !== null && ($staffHospitalId === null || (int) $r['hospital_id'] === $staffHospitalId)));
$fraudCount = count(array_filter($records, static fn(array $r): bool => $r['status'] === 'fraud'));
?>
<!DOCTYPE html>
<html lang="<?= emergencyEscape(chnLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CHN | <?= emergencyEscape($chnPageTitle) ?></title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime($chnProjectRoot . '/css/style.css') ?>">
    <link rel="stylesheet" href="../css/emergency-demo.css?v=<?= filemtime($chnProjectRoot . '/css/emergency-demo.css') ?>">
    <script src="../js/sidebar.js?v=<?= filemtime($chnProjectRoot . '/js/sidebar.js') ?>" defer></script>
    <script src="../js/topbar.js?v=<?= filemtime($chnProjectRoot . '/js/topbar.js') ?>" defer></script>
    <script src="../js/emergency-demo.js?v=<?= filemtime($chnProjectRoot . '/js/emergency-demo.js') ?>" defer></script>
</head>
<body>
<?php require $chnProjectRoot . '/include/left-sidebar.php'; ?>
<?php require $chnProjectRoot . '/include/top-bar.php'; ?>

<main class="content" id="main-content" tabindex="-1">
<div class="content__inner emergency-demo">

    <header class="page-header emergency-demo__header">
        <div>
            <h1 class="page-header__title"><?= emergencyEscape($chnPageTitle) ?></h1>
            <p class="page-header__subtitle">
                <?= $isLogs
                    ? te('emergency.logs_subtitle')
                    : ($staffHospitalName !== null
                        ? emergencyEscape(chnFill(t('emergency.alerts_subtitle_hospital'), ['hospital' => $staffHospitalName]))
                        : te('emergency.alerts_subtitle')) ?>
            </p>
        </div>
    </header>

    <?php if (!$isLogs && $staffHospitalId === null): ?>
    <p class="alert alert--warning" role="status"><?= te('emergency.staff_no_hospital_notice') ?></p>
    <?php endif; ?>

    <section class="emergency-demo__summary" aria-label="<?= te('emergency.overview_label') ?>">
        <div class="card">
            <p class="emergency-demo__metric-label"><?= $isLogs ? te('emergency.total_logs') : te('emergency.total_alerts') ?></p>
            <p class="emergency-demo__metric" id="metric-total"><?= count($records) ?></p>
            <p class="emergency-demo__muted"><?= te('emergency.all_reports') ?></p>
        </div>
        <div class="card">
            <p class="emergency-demo__metric-label"><?= te('emergency.pending_alerts') ?></p>
            <p class="emergency-demo__metric" id="metric-pending"><?= $pendingCount ?></p>
            <p class="emergency-demo__muted"><?= te('emergency.awaiting') ?></p>
        </div>
        <?php if ($isLogs): ?>
        <div class="card">
            <p class="emergency-demo__metric-label"><?= te('emergency.marked_fraud') ?></p>
            <p class="emergency-demo__metric" id="metric-fraud"><?= $fraudCount ?></p>
            <p class="emergency-demo__muted"><?= te('emergency.careful_review') ?></p>
        </div>
        <?php else: ?>
        <div class="card">
            <p class="emergency-demo__metric-label"><?= te('emergency.handled_by_hospital') ?></p>
            <p class="emergency-demo__metric" id="metric-handled"><?= $handledCount ?></p>
            <p class="emergency-demo__muted"><?= te('emergency.handled_by_hospital_sub') ?></p>
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

        <p class="alert emergency-demo__feedback" id="emergency-feedback" role="status" aria-live="polite" hidden></p>

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
            <?php if (!$isLogs): ?>
            <div>
                <label class="form-label" for="emergency-queue"><?= te('emergency.queue_filter') ?></label>
                <select class="form-control" id="emergency-queue">
                    <option value=""><?= te('emergency.queue_all') ?></option>
                    <option value="pending"><?= te('emergency.queue_pending') ?></option>
                    <option value="handled"><?= te('emergency.queue_handled') ?></option>
                </select>
            </div>
            <?php endif; ?>
        </div>

        <div class="table-responsive" tabindex="0" role="region" aria-label="<?= te('emergency.table_label') ?>">
            <table class="table emergency-demo__table" id="emergency-table"
                   data-manage-url="../backend/manage_emergency.php"
                   data-is-admin="<?= $isLogs ? 'true' : 'false' ?>"
                   data-status-labels="<?= emergencyEscape((string) json_encode($labels, JSON_UNESCAPED_UNICODE)) ?>"
                   data-messages="<?= chnJsMessages([
                       'cancel' => 'emergency.cancel',
                       'close' => 'emergency.close',
                       'confirmBan' => 'emergency.confirm_ban',
                       'flagTitle' => 'emergency.flag_fraud',
                       'confirmFlag' => 'emergency.confirm_flag',
                       'startTitle' => 'emergency.start_handling',
                       'confirmStart' => 'emergency.confirm_start',
                       'resolveTitle' => 'emergency.mark_resolved',
                       'confirmResolve' => 'emergency.confirm_resolve',
                       'banNoUser' => 'emergency.ban_no_user',
                       'flagNoId' => 'emergency.flag_no_id',
                       'banQuestion' => 'emergency.ban_question',
                       'flagQuestion' => 'emergency.flag_question',
                       'startQuestion' => 'emergency.start_question',
                       'resolveQuestion' => 'emergency.resolve_question',
                       'requestFailed' => 'emergency.request_failed',
                       'viewDetails' => 'emergency.view_details',
                       'hideDetails' => 'emergency.hide_details',
                       'bannedBadge' => 'emergency.user_banned_badge',
                       'fraudTitle' => 'emergency.fraud_indicator_title',
                       'fraudText' => 'emergency.fraud_indicator_text',
                       'markResolved' => 'emergency.mark_resolved',
                   ]) ?>">
                <thead>
                    <tr>
                        <th scope="col"><?= te('emergency.col_id') ?></th>
                        <th scope="col"><?= te('emergency.col_user') ?></th>
                        <th scope="col"><?= te('emergency.col_type') ?></th>
                        <?php if ($isLogs): ?><th scope="col"><?= te('emergency.col_ip') ?></th><?php endif; ?>
                        <th scope="col"><?= te('emergency.col_location') ?></th>
                        <th scope="col"><?= te('emergency.col_created') ?></th>
                        <th scope="col"><?= te('emergency.status') ?></th>
                        <th scope="col"><?= te('emergency.col_staff') ?></th>
                        <th scope="col"><?= te('emergency.col_details') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $record):
                    $emId = (int) $record['emergency_id'];
                    $isGuest = $record['user_id'] === null;
                    $regFullName = trim((string) ($record['user_first_name'] ?? '') . ' ' . (string) ($record['user_last_name'] ?? ''));
                    $callerName = $isGuest
                        ? ((string) ($record['anon_name'] ?? '') !== '' ? (string) $record['anon_name'] : t('emergency.anonymous'))
                        : ($regFullName !== '' ? $regFullName : t('emergency.user_ref', ['id' => $record['user_id']]));
                    $callerPhone = $isGuest ? ($record['anon_phone'] ?? null) : ($record['user_phone'] ?? null);
                    $callerDob = $isGuest ? ($record['anon_dob'] ?? null) : ($record['user_dob'] ?? null);
                    $typeLabel = $record['emergency_type'] !== null && isset($typeLabels[$record['emergency_type']])
                        ? $typeLabels[$record['emergency_type']]
                        : t('emergency.not_available');
                    $gpsText = trim((string) ($record['gps_location'] ?? ''));
                    $manualText = trim((string) ($record['manual_address'] ?? ''));
                    $locationSummary = $gpsText !== '' && $manualText !== ''
                        ? $gpsText . ' · ' . $manualText
                        : ($gpsText !== '' ? $gpsText : ($manualText !== '' ? $manualText : t('emergency.not_available')));
                    $staffFullName = trim((string) ($record['staff_first_name'] ?? '') . ' ' . (string) ($record['staff_last_name'] ?? ''));
                    $hospitalName = trim((string) ($record['hospital_name'] ?? ''));
                    $assignmentSummary = $record['handled_by_staff_id'] === null && $hospitalName === ''
                        ? t('emergency.unassigned')
                        : trim(($hospitalName !== '' ? $hospitalName : '') . ($staffFullName !== '' ? ($hospitalName !== '' ? ' · ' : '') . $staffFullName : ($record['handled_by_staff_id'] !== null ? ' · ' . t('emergency.staff_ref', ['id' => $record['handled_by_staff_id']]) : '')));
                    $queueGroup = ($record['status'] === 'pending' && $record['hospital_id'] === null) ? 'pending' : 'handled';
                    $userIsBanned = (int) ($record['user_is_banned'] ?? 0) === 1;
                    $canBan = $isLogs && !$isGuest && (string) ($record['user_role'] ?? '') !== 'admin' && !$userIsBanned && (int) $record['user_id'] !== $sessionUser['user_id'];
                    $colSpan = $isLogs ? '9' : '8';
                ?>
                    <tr data-emergency-id="<?= $emId ?>" data-status="<?= emergencyEscape($record['status']) ?>" data-queue="<?= emergencyEscape($queueGroup) ?>" data-user-id="<?= emergencyEscape($record['user_id'] ?? '') ?>">
                        <td><strong>#<?= $emId ?></strong></td>
                        <td>
                            <span class="emergency-demo__caller-name"><?= emergencyEscape($callerName) ?></span>
                            <?php if ($isGuest): ?>
                                <span class="emergency-demo__tag"><?= te('emergency.anonymous') ?></span>
                            <?php else: ?>
                                <span class="emergency-demo__muted-inline">#<?= emergencyEscape($record['user_id']) ?></span>
                                <?php if ($userIsBanned): ?>
                                    <span class="emergency-demo__tag emergency-demo__tag--danger"><?= te('emergency.user_banned_badge') ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><?= emergencyEscape($typeLabel) ?></td>
                        <?php if ($isLogs): ?><td><?= emergencyEscape(emergencyValue($record['ip_address'])) ?></td><?php endif; ?>
                        <td class="emergency-demo__loc-cell"><?= emergencyEscape($locationSummary) ?></td>
                        <td><?= emergencyEscape(emergencyDate($record['created_at'], 'Y-m-d H:i:s', 'medium_time')) ?></td>
                        <td><span class="emergency-demo__status emergency-demo__status--<?= emergencyEscape($record['status']) ?>" data-role="status-badge"><?= emergencyEscape($labels[$record['status']] ?? $record['status']) ?></span></td>
                        <td data-role="assignment-cell"><?= emergencyEscape($assignmentSummary) ?></td>
                        <td><button type="button" class="btn btn--outline emergency-demo__details-button" aria-expanded="false" aria-controls="emergency-detail-<?= $emId ?>"><?= te('emergency.view_details') ?></button></td>
                    </tr>
                    <tr class="emergency-demo__detail-row" id="emergency-detail-<?= $emId ?>" hidden>
                        <td colspan="<?= $colSpan ?>">
                            <div class="emergency-demo__detail">
                                <h3><?= te('emergency.detail_title', ['id' => $emId]) ?></h3>
                                <dl class="emergency-demo__detail-grid">
                                    <div><dt><?= te('emergency.reported_date') ?></dt><dd><?= emergencyEscape(emergencyDate($record['emergency_date'], '!Y-m-d', 'medium')) ?></dd></div>
                                    <div><dt><?= te('emergency.reported_time') ?></dt><dd><?= emergencyEscape(emergencyValue($record['emergency_time'] === null ? null : substr((string) $record['emergency_time'], 0, 5))) ?></dd></div>
                                    <div><dt><?= te('emergency.col_created') ?></dt><dd><?= emergencyEscape(emergencyDate($record['created_at'], 'Y-m-d H:i:s', 'medium_time')) ?></dd></div>
                                    <div><dt><?= te('emergency.handled_at') ?></dt><dd data-role="detail-handled-at"><?= emergencyEscape(emergencyDate($record['handled_at'], 'Y-m-d H:i:s', 'medium_time')) ?></dd></div>
                                    <div><dt><?= te('emergency.col_type') ?></dt><dd><?= emergencyEscape($typeLabel) ?></dd></div>
                                    <div><dt><?= te('emergency.gps_location') ?></dt><dd><?= emergencyEscape(emergencyValue($record['gps_location'])) ?></dd></div>
                                    <div><dt><?= te('emergency.manual_address') ?></dt><dd><?= emergencyEscape(emergencyValue($record['manual_address'])) ?></dd></div>
                                    <div><dt><?= te('emergency.hospital') ?></dt><dd data-role="detail-hospital"><?= emergencyEscape(emergencyValue($hospitalName)) ?></dd></div>
                                    <div><dt><?= te('emergency.col_staff') ?></dt><dd data-role="detail-staff"><?= emergencyEscape($staffFullName !== '' ? $staffFullName : ($record['handled_by_staff_id'] === null ? t('emergency.unassigned') : t('emergency.staff_ref', ['id' => $record['handled_by_staff_id']]))) ?></dd></div>
                                    <div><dt><?= te('emergency.caller_name') ?></dt><dd><?= emergencyEscape($callerName) ?></dd></div>
                                    <div><dt><?= te('emergency.caller_phone') ?></dt><dd><?= emergencyEscape(emergencyValue($callerPhone)) ?></dd></div>
                                    <div><dt><?= te('emergency.caller_dob') ?></dt><dd><?= emergencyEscape(emergencyDate($callerDob, '!Y-m-d', 'medium')) ?></dd></div>
                                    <?php if ($isLogs): ?>
                                    <div><dt><?= te('emergency.col_user') ?></dt><dd><?= $isGuest ? te('emergency.anonymous') : te('emergency.user_ref', ['id' => $record['user_id']]) ?></dd></div>
                                    <div><dt><?= te('emergency.col_ip') ?></dt><dd><?= emergencyEscape(emergencyValue($record['ip_address'])) ?></dd></div>
                                    <?php endif; ?>
                                </dl>
                                <?php if ($isLogs): ?>
                                <p data-role="fraud-note"><?= $record['status'] === 'fraud' ? '<strong>' . te('emergency.fraud_indicator_title') . '</strong> ' . te('emergency.fraud_indicator_text') : te('emergency.no_fraud') ?></p>
                                <?php endif; ?>
                                <div class="emergency-demo__actions" data-role="action-bar">
                                    <?php if (!$isLogs && $record['status'] === 'pending'): ?>
                                        <button class="btn btn--primary emergency-demo__start" type="button" data-emergency-id="<?= $emId ?>"<?= $staffHospitalId === null ? ' disabled' : '' ?>><?= te('emergency.start_handling') ?></button>
                                    <?php elseif (!$isLogs && $record['status'] === 'in_progress'): ?>
                                        <button class="btn btn--primary emergency-demo__resolve" type="button" data-emergency-id="<?= $emId ?>"><?= te('emergency.mark_resolved') ?></button>
                                    <?php endif; ?>
                                    <?php if (($isLogs && $record['status'] !== 'fraud') || (!$isLogs && in_array($record['status'], ['pending', 'in_progress'], true))): ?>
                                        <button class="btn btn--outline emergency-demo__flag" type="button" data-emergency-id="<?= $emId ?>"><?= te('emergency.flag_fraud') ?></button>
                                    <?php endif; ?>
                                    <?php if ($isLogs): ?>
                                        <button class="btn btn--danger emergency-demo__ban" type="button" data-user-id="<?= emergencyEscape($record['user_id'] ?? '') ?>"<?= $canBan ? '' : ' disabled' ?>><?= $userIsBanned ? te('emergency.user_banned_badge') : te('emergency.ban') ?></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="empty-state" id="emergency-empty" role="status"<?= count($records) === 0 ? '' : ' hidden' ?>>
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
