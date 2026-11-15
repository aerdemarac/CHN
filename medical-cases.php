<?php
// Frontend preview only: no medical database access or authorization claims.
$preview = require __DIR__ . '/include/medical-cases-preview-data.php';
$cases = $preview['cases'];
$diagnoses = $preview['diagnoses'];
$chnPageTitle = 'Medical Cases';
$chnUserName = 'Preview User';
require_once __DIR__ . '/include/prototype-role.php';
$view = [
    'patient' => ['title' => 'My Medical Cases', 'subtitle' => 'Review your demonstration cases and diagnosis history.', 'overview' => 'My cases', 'action' => 'View my case'],
    'doctor' => ['title' => 'Medical Cases — Clinical Review', 'subtitle' => 'Review assigned demonstration patients, case details and diagnosis history.', 'overview' => 'Assigned patient cases', 'action' => 'View clinical details'],
    'staff' => ['title' => 'Medical Cases — Care Coordination', 'subtitle' => 'Review demonstration case routing within an illustrative department scope.', 'overview' => 'Department case routing', 'action' => 'View routing details'],
    'admin' => ['title' => 'Medical Cases — Admin', 'subtitle' => 'Clinical case access has not been defined for the admin role.', 'overview' => '', 'action' => ''],
][$chnPrototypeRole];
$clinicalView = in_array($chnPrototypeRole, ['patient', 'doctor'], true);
// Illustrative fixture scopes only. Real authorization must be enforced by the backend.
$cases = array_values(array_filter($cases, static function (array $case) use ($chnPrototypeRole, $preview): bool {
    switch ($chnPrototypeRole) {
        case 'patient': return $case['patient_id'] === $preview['context']['patient_id'];
        case 'doctor': return $case['doctor_id'] === $preview['context']['doctor_id'];
        case 'staff': return $case['department_id'] === $preview['context']['staff_department_id'];
        default: return false;
    }
}));
function mcEscape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function mcParam(string $key): string {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
}
function mcDate(?string $value): string {
    if (!$value) return 'Not available';
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    return $date ? $date->format('d M Y, H:i') : 'Not available';
}
$query = mcParam('q');
$status = in_array(mcParam('status'), ['open', 'closed'], true) ? mcParam('status') : '';
$department = mcParam('department');
$departments = array_column($cases, 'department_name', 'department_id');
if (!isset($departments[$department])) $department = '';
$filtered = array_values(array_filter($cases, static function (array $case) use ($query, $status, $department, $clinicalView, $chnPrototypeRole): bool {
    $search = implode(' ', ['DEMO-' . $case['case_id'], $case['department_name'], $case['doctor_name'] ?? '', $clinicalView ? ($case['case_description'] ?? '') : '', $chnPrototypeRole === 'doctor' ? $case['patient_name'] : '', $chnPrototypeRole !== 'patient' ? 'DEMO-P-' . $case['patient_id'] : '']);
    return ($status === '' || $case['case_state'] === $status)
        && ($department === '' || (string) $case['department_id'] === $department)
        && ($query === '' || stripos($search, $query) !== false);
}));
$selected = null;
foreach ($filtered as $case) {
    if ((string) $case['case_id'] === mcParam('case')) $selected = $case;
}
$selectedDiagnoses = $selected && $clinicalView ? array_values(array_filter($diagnoses, static fn(array $diagnosis): bool => (string) $diagnosis['case_id'] === (string) $selected['case_id'])) : [];
usort($selectedDiagnoses, static fn(array $a, array $b): int => strcmp($b['diagnosed_at'], $a['diagnosed_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= mcEscape($view['title']) ?> | CHN</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/medical-cases.css">
    <script src="js/sidebar.js" defer></script>
    <script src="js/topbar.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/include/left-sidebar.php'; ?>
<?php require __DIR__ . '/include/top-bar.php'; ?>
<main class="content medical-cases" id="main-content" tabindex="-1">
<div class="content__inner">
    <header class="page-header">
        <div><h1 class="page-header__title"><?= mcEscape($view['title']) ?></h1>
        <p class="page-header__subtitle"><?= mcEscape($view['subtitle']) ?></p></div>
        <span class="badge badge--info">Frontend preview</span>
    </header>
    <div class="alert medical-cases__notice"><strong>Fictional demonstration data.</strong> These are not live medical records. The URL role changes this frontend preview only; it does not authenticate users or grant access. Active demo role: <strong><?= mcEscape($chnPrototypeRoles[$chnPrototypeRole]) ?></strong>.</div>
    <form method="get" action="<?= mcEscape(chnPrototypeUrl('medical-cases.php', $chnPrototypeRole)) ?>" class="medical-cases__role-picker" aria-label="Choose prototype role">
        <div><label class="form-label" for="preview-role">Demo role</label><select class="form-control" id="preview-role" name="role"><?php foreach ($chnPrototypeRoles as $role => $label): ?><option value="<?= $role ?>" <?= $role === $chnPrototypeRole ? 'selected' : '' ?>><?= mcEscape($label) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn--outline" type="submit">Preview role</button>
    </form>
    <?php if ($chnPrototypeRole === 'admin'): ?>
    <section class="card"><h2 class="card__title">Clinical access not defined</h2><p>No case records or clinical actions are shown in the admin prototype. Admin clinical permissions require an explicit project decision.</p></section>
    <?php else: ?>
    <div class="grid grid--2 medical-cases__summary" aria-label="Demonstration case totals">
        <?php foreach (['open' => 'Active Cases', 'closed' => 'Closed Cases'] as $state => $label): ?>
        <div class="stat-card"><div><p class="stat-card__label"><?= $label ?></p>
        <p class="stat-card__value"><?= count(array_filter($cases, static fn(array $case): bool => $case['case_state'] === $state)) ?></p>
        <p class="card__subtitle">Current demo scope · <?= $state ?></p></div></div>
        <?php endforeach; ?>
    </div>
    <section class="card" aria-labelledby="cases-title">
        <header class="card__header"><div><h2 class="card__title" id="cases-title"><?= mcEscape($view['overview']) ?></h2>
        <p class="card__subtitle"><?= $clinicalView ? 'Select a case to view its details and diagnoses.' : 'Operational preview only. Clinical descriptions and diagnoses are not shown for staff.' ?></p></div></header>
        <form method="get" action="<?= mcEscape(chnPrototypeUrl('medical-cases.php', $chnPrototypeRole)) ?>" class="medical-cases__filters" role="search" aria-label="Filter cases">
            <input type="hidden" name="role" value="<?= mcEscape($chnPrototypeRole) ?>">
            <div><label class="form-label" for="case-search">Search cases</label><input class="form-control" type="search" name="q" id="case-search" value="<?= mcEscape($query) ?>" placeholder="<?= $clinicalView ? 'Case ID, doctor or description' : 'Case ID, patient reference or doctor' ?>"></div>
            <div><label class="form-label" for="case-status">Case status</label><select class="form-control" name="status" id="case-status"><option value="">All statuses</option><?php foreach (['open' => 'Active (open)', 'closed' => 'Closed'] as $value => $label): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="case-department">Department</label><select class="form-control" name="department" id="case-department"><option value="">All departments</option><?php foreach ($departments as $id => $label): ?><option value="<?= $id ?>" <?= $department === (string) $id ? 'selected' : '' ?>><?= mcEscape($label) ?></option><?php endforeach; ?></select></div>
            <div class="medical-cases__filter-actions"><button class="btn btn--primary" type="submit">Apply filters</button><a class="btn btn--outline" href="<?= mcEscape(chnPrototypeUrl('medical-cases.php', $chnPrototypeRole)) ?>">Reset</a></div>
        </form>
        <p class="card__subtitle medical-cases__count"><?= count($filtered) ?> of <?= count($cases) ?> demonstration cases</p>
        <?php if ($filtered): ?>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Cases table, scroll horizontally for more columns">
        <table class="table"><caption class="medical-cases__caption">Demonstration cases. Case status and medical triage are separate indicators.</caption>
        <thead><tr><th scope="col">Case ID</th><?php if ($chnPrototypeRole !== 'patient'): ?><th scope="col"><?= $chnPrototypeRole === 'doctor' ? 'Demo patient' : 'Demo patient reference' ?></th><?php endif; ?><th scope="col">Department</th><th scope="col">Responsible doctor</th><th scope="col">Created</th><th scope="col">Case status</th><th scope="col">Medical triage</th><th scope="col">Details</th></tr></thead>
        <tbody><?php foreach ($filtered as $case): ?>
        <tr class="<?= $selected && $selected['case_id'] === $case['case_id'] ? 'medical-cases__selected' : '' ?>">
            <th scope="row">DEMO-<?= $case['case_id'] ?></th>
            <?php if ($chnPrototypeRole !== 'patient'): ?><td><?= $chnPrototypeRole === 'doctor' ? mcEscape($case['patient_name']) . '<br>' : '' ?>DEMO-P-<?= $case['patient_id'] ?></td><?php endif; ?>
            <td><?= mcEscape($case['department_name']) ?></td><td><?= mcEscape($case['doctor_name'] ?? 'Not assigned') ?></td><td><?= mcEscape(mcDate($case['created_at'])) ?></td>
            <td><span class="badge <?= $case['case_state'] === 'open' ? 'badge--info' : 'badge--neutral' ?>"><?= $case['case_state'] === 'open' ? 'Active (open)' : 'Closed' ?></span></td>
            <td><span class="badge medical-cases__triage medical-cases__triage--<?= mcEscape($case['tier_color']) ?>"><?= ucfirst($case['tier_color']) ?></span></td>
            <td><a class="btn btn--outline btn--sm" href="<?= mcEscape(chnPrototypeUrl('medical-cases.php#case-details', $chnPrototypeRole, ['q' => $query, 'status' => $status, 'department' => $department, 'case' => $case['case_id']])) ?>" <?= $selected && $selected['case_id'] === $case['case_id'] ? 'aria-current="true"' : '' ?> aria-label="<?= mcEscape($view['action'] . ' DEMO-' . $case['case_id']) ?>"><?= mcEscape($view['action']) ?></a></td>
        </tr><?php endforeach; ?></tbody></table></div>
        <?php else: ?><div class="medical-cases__empty"><h3>No matching cases</h3><p>Try another case ID or reset the filters.</p><a class="btn btn--outline" href="<?= mcEscape(chnPrototypeUrl('medical-cases.php', $chnPrototypeRole)) ?>">Reset filters</a></div><?php endif; ?>
    </section>
    <section class="card medical-cases__details" id="case-details" aria-labelledby="case-details-title" tabindex="-1">
        <header class="card__header"><h2 class="card__title" id="case-details-title"><?= $selected ? 'Case DEMO-' . $selected['case_id'] : 'Case details' ?></h2><?php if ($selected): ?><span class="badge badge--info">Demonstration case</span><?php endif; ?></header>
        <?php if ($selected): ?>
        <dl class="medical-cases__metadata">
            <?php if ($chnPrototypeRole !== 'patient'): ?><div><dt>Demo patient<?= $chnPrototypeRole === 'staff' ? ' reference' : '' ?></dt><dd><?= $chnPrototypeRole === 'doctor' ? mcEscape($selected['patient_name']) . ' · ' : '' ?>DEMO-P-<?= $selected['patient_id'] ?></dd></div><?php endif; ?>
            <div><dt>Department</dt><dd><?= mcEscape($selected['department_name']) ?></dd></div>
            <div><dt>Responsible doctor</dt><dd><?= mcEscape($selected['doctor_name'] ?? 'Not assigned') ?></dd></div>
            <div><dt>Created</dt><dd><?= mcEscape(mcDate($selected['created_at'])) ?></dd></div>
            <div><dt>Case status</dt><dd><?= $selected['case_state'] === 'open' ? 'Active (open)' : 'Closed' ?></dd></div>
            <div><dt>Medical triage</dt><dd><?= ucfirst($selected['tier_color']) ?></dd></div>
        </dl>
        <?php if ($clinicalView): ?>
        <h3 class="medical-cases__heading">Case description</h3><p class="medical-cases__description"><?= mcEscape($selected['case_description'] ?? 'No description available.') ?></p>
        <div class="medical-cases__diagnoses"><h3 class="medical-cases__heading">Diagnosis history</h3>
        <?php if ($selectedDiagnoses): ?><ol class="medical-cases__history">
            <?php foreach ($selectedDiagnoses as $diagnosis): ?><li><p><?= mcEscape($diagnosis['diagnosis_text']) ?></p><p class="card__subtitle"><?= mcEscape(mcDate($diagnosis['diagnosed_at'])) ?> · <?= mcEscape($diagnosis['doctor_name'] ?? 'Doctor not available') ?></p><?php if (!empty($diagnosis['icd_code'])): ?><p>ICD code: <?= mcEscape($diagnosis['icd_code']) ?></p><?php endif; ?></li><?php endforeach; ?>
        </ol><?php else: ?><p class="medical-cases__empty">No diagnoses available for this demonstration case. No diagnosis records have been fabricated.</p><?php endif; ?></div>
        <?php else: ?><p class="medical-cases__empty">Staff preview is limited to case routing. Clinical descriptions and diagnosis history are not included.</p><?php endif; ?>
        <?php else: ?><p class="medical-cases__empty"><?= mcParam('case') !== '' ? 'The requested case is not in the current results. Select a visible case or reset the filters.' : ($clinicalView ? 'Select a case from the overview to see its description and diagnosis history.' : 'Select a case from the overview to see its routing details.') ?></p><?php endif; ?>
    </section>
    <?php endif; ?>
    <noscript><p class="card__subtitle">Search, filters and case selection work without JavaScript. Shared navigation menus require JavaScript.</p></noscript>
</div>
</main>
</body>
</html>
