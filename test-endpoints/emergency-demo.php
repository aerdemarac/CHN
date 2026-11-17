<?php
// Shared presentation for the two emergency prototypes. These records exist only in this file.
if (!isset($emergencyView) || !in_array($emergencyView, ['alerts', 'logs'], true)) {
    http_response_code(404);
    exit;
}
$isLogs = $emergencyView === 'logs';
$chnUserRole = $isLogs ? 'admin' : 'healthcare_staff';
$chnUserName = $isLogs ? 'Administrator' : 'Healthcare Staff';
$chnPageTitle = $isLogs ? 'Emergency Logs' : 'Emergency Alerts';
$records = [
    ['emergency_id'=>1004, 'user_id'=>null, 'ip_address'=>'192.0.2.14', 'emergency_date'=>'2026-10-14', 'emergency_time'=>'14:20:00', 'emergency_location'=>'Oak Avenue, North District', 'status'=>'pending', 'handled_by_staff_id'=>null, 'handled_at'=>null, 'created_at'=>'2026-10-14 14:20:00'],
    ['emergency_id'=>1003, 'user_id'=>203, 'ip_address'=>'198.51.100.22', 'emergency_date'=>'2026-10-13', 'emergency_time'=>'09:15:00', 'emergency_location'=>'Cedar Street, West Quarter', 'status'=>'in_progress', 'handled_by_staff_id'=>12, 'handled_at'=>'2026-10-13 09:19:00', 'created_at'=>'2026-10-13 09:15:00'],
    ['emergency_id'=>1002, 'user_id'=>204, 'ip_address'=>'203.0.113.31', 'emergency_date'=>'2026-10-12', 'emergency_time'=>'18:45:00', 'emergency_location'=>'Riverside Park, North District', 'status'=>'resolved', 'handled_by_staff_id'=>15, 'handled_at'=>'2026-10-12 18:51:00', 'created_at'=>'2026-10-12 18:45:00'],
    ['emergency_id'=>1001, 'user_id'=>null, 'ip_address'=>'192.0.2.45', 'emergency_date'=>null, 'emergency_time'=>null, 'emergency_location'=>'Central Square, West Quarter', 'status'=>'fraud', 'handled_by_staff_id'=>12, 'handled_at'=>'2026-10-11 11:40:00', 'created_at'=>'2026-10-11 11:32:00'],
];
$labels = ['pending'=>'Pending', 'in_progress'=>'In Progress', 'resolved'=>'Resolved', 'fraud'=>'Fraud'];
function emergencyEscape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function emergencyValue($value): string { return $value === null || $value === '' ? 'Not available' : (string) $value; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CHN | <?= emergencyEscape($chnPageTitle) ?></title>
    <link rel="stylesheet" href="../css/style.css">
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
    <header class="page-header emergency-demo__header"><div>
        <h1 class="page-header__title"><?= emergencyEscape($chnPageTitle) ?></h1>
        <p class="page-header__subtitle"><?= $isLogs ? 'Review the history of emergency reports and their handling.' : 'Review incoming emergency reports and their handling status.' ?></p>
    </div></header>
    <section class="emergency-demo__summary" aria-label="Emergency overview">
        <div class="card"><p class="emergency-demo__metric-label">Total <?= $isLogs ? 'logs' : 'alerts' ?></p><p class="emergency-demo__metric"><?= count($records) ?></p><p class="emergency-demo__muted">All recorded reports</p></div>
        <div class="card"><p class="emergency-demo__metric-label">Pending alerts</p><p class="emergency-demo__metric"><?= count(array_filter($records, static fn($record) => $record['status'] === 'pending')) ?></p><p class="emergency-demo__muted">Awaiting handling</p></div>
        <?php if ($isLogs): ?><div class="card"><p class="emergency-demo__metric-label">Marked fraud</p><p class="emergency-demo__metric"><?= count(array_filter($records, static fn($record) => $record['status'] === 'fraud')) ?></p><p class="emergency-demo__muted">Requires careful review</p></div><?php endif; ?>
    </section>
    <section class="card emergency-demo__listing" aria-labelledby="emergency-list-title">
        <header class="card__header"><div><h2 class="card__title" id="emergency-list-title"><?= $isLogs ? 'Report history' : 'Alert overview' ?></h2><p class="card__subtitle">Search records or filter by status.</p></div></header>
        <div class="emergency-demo__filters" role="search" aria-label="Filter emergency records">
            <div><label class="form-label" for="emergency-search">Search</label><input class="form-control" type="search" id="emergency-search" placeholder="Search records"></div>
            <div><label class="form-label" for="emergency-status">Status</label><select class="form-control" id="emergency-status"><option value="">All statuses</option><?php foreach ($labels as $value => $label): ?><option value="<?= emergencyEscape($value) ?>"><?= emergencyEscape($label) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Emergency records, scroll horizontally for more columns">
        <table class="table emergency-demo__table" id="emergency-table"><thead><tr><th scope="col">Emergency ID</th><th scope="col">User</th><?php if ($isLogs): ?><th scope="col">IP address</th><?php endif; ?><th scope="col">Reported location</th><th scope="col">Created at</th><th scope="col">Status</th><th scope="col">Handling staff</th><th scope="col">Details</th></tr></thead><tbody>
        <?php foreach ($records as $record): ?>
            <tr data-status="<?= emergencyEscape($record['status']) ?>"><td><strong>#<?= emergencyEscape($record['emergency_id']) ?></strong></td><td><?= $record['user_id'] === null ? 'Anonymous' : 'User #' . emergencyEscape($record['user_id']) ?></td><?php if ($isLogs): ?><td><?= emergencyEscape(emergencyValue($record['ip_address'])) ?></td><?php endif; ?><td><?= emergencyEscape(emergencyValue($record['emergency_location'])) ?></td><td><?= emergencyEscape(emergencyValue($record['created_at'])) ?></td><td><span class="emergency-demo__status emergency-demo__status--<?= emergencyEscape($record['status']) ?>"><?= emergencyEscape($labels[$record['status']]) ?></span></td><td><?= $record['handled_by_staff_id'] === null ? 'Unassigned' : 'Staff #' . emergencyEscape($record['handled_by_staff_id']) ?></td><td><button type="button" class="btn btn--outline emergency-demo__details-button" aria-expanded="false" aria-controls="emergency-detail-<?= emergencyEscape($record['emergency_id']) ?>">View details</button></td></tr>
            <tr class="emergency-demo__detail-row" id="emergency-detail-<?= emergencyEscape($record['emergency_id']) ?>" hidden><td colspan="<?= $isLogs ? '8' : '7' ?>"><div class="emergency-demo__detail"><h3>Emergency #<?= emergencyEscape($record['emergency_id']) ?> details</h3><dl class="emergency-demo__detail-grid"><div><dt>Reported date</dt><dd><?= emergencyEscape(emergencyValue($record['emergency_date'])) ?></dd></div><div><dt>Reported time</dt><dd><?= emergencyEscape(emergencyValue($record['emergency_time'])) ?></dd></div><div><dt>Created at</dt><dd><?= emergencyEscape(emergencyValue($record['created_at'])) ?></dd></div><div><dt>Handled at</dt><dd><?= emergencyEscape(emergencyValue($record['handled_at'])) ?></dd></div><div><dt>Reported location</dt><dd><?= emergencyEscape(emergencyValue($record['emergency_location'])) ?></dd></div><div><dt>Handling staff</dt><dd><?= $record['handled_by_staff_id'] === null ? 'Unassigned' : 'Staff #' . emergencyEscape($record['handled_by_staff_id']) ?></dd></div><?php if ($isLogs): ?><div><dt>User</dt><dd><?= $record['user_id'] === null ? 'Anonymous' : 'User #' . emergencyEscape($record['user_id']) ?></dd></div><div><dt>IP address</dt><dd><?= emergencyEscape(emergencyValue($record['ip_address'])) ?></dd></div><?php endif; ?></dl>
            <?php if ($isLogs): ?><p><?= $record['status'] === 'fraud' ? '<strong>Fraud indicator:</strong> This record is marked fraud. A status alone does not establish abuse.' : 'No fraud status is recorded.' ?></p><?php endif; ?>
            <div class="emergency-demo__actions">
                <?php if (!$isLogs && $record['status'] === 'pending'): ?><button class="btn btn--primary" type="button" disabled>Start handling</button><?php elseif (!$isLogs && $record['status'] === 'in_progress'): ?><button class="btn btn--primary" type="button" disabled>Mark resolved</button><?php endif; ?>
                <?php if ($isLogs || in_array($record['status'], ['pending', 'in_progress'], true)): ?><button class="btn btn--outline emergency-demo__flag" type="button" data-emergency-id="<?= emergencyEscape($record['emergency_id']) ?>">Flag as fraud</button><?php endif; ?>
                <?php if ($isLogs): ?><button class="btn btn--danger emergency-demo__ban" type="button" data-user-id="<?= emergencyEscape($record['user_id'] ?? '') ?>">Ban</button><?php endif; ?>
            </div>
            </div></td></tr>
        <?php endforeach; ?></tbody></table></div>
        <div class="empty-state" id="emergency-empty" role="status" hidden><p class="empty-state__title">No records found</p><p class="empty-state__text">Try another search or status filter.</p></div>
    </section>
</div></main>
<dialog class="emergency-demo__dialog" id="emergency-action-dialog" aria-labelledby="emergency-action-title" aria-describedby="emergency-action-description">
    <h2 id="emergency-action-title"></h2>
    <p id="emergency-action-description"></p>
    <p id="emergency-action-result" role="status" aria-live="polite" hidden></p>
    <div class="emergency-demo__actions">
        <button type="button" class="btn btn--outline" id="emergency-action-cancel">Cancel</button>
        <button type="button" class="btn btn--primary" id="emergency-action-confirm">Confirm</button>
    </div>
</dialog>
</body></html>
