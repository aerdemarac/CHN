<?php
require_once dirname(__DIR__) . '/include/i18n.php';
// Visual demo only: fixtures and URL view selection, no persistence or backend calls.
$preview = require dirname(__DIR__) . '/include/medical-cases-preview-data.php';
$cases = $preview['cases'];
$diagnoses = $preview['diagnoses'];
$chnPageTitle = t('cases.page_title');
$chnUserName = t('cases.preview_user');
require_once dirname(__DIR__) . '/include/prototype-role.php';
$casePrefix = 'DEMO-';
$options = $preview['options'];
if ($chnPrototypeRole === 'doctor') {
    $options['departments'] = array_values(array_filter($options['departments'], static fn(array $department): bool => $department['department_id'] === $preview['context']['doctor_department_id']));
}
$view = [
    'patient' => ['title' => t('cases.patient_title'), 'subtitle' => t('cases.patient_subtitle'), 'overview' => t('cases.patient_overview'), 'action' => t('cases.patient_action'), 'action_aria' => 'cases.patient_action_aria'],
    'doctor' => ['title' => t('cases.doctor_title'), 'subtitle' => t('cases.doctor_subtitle'), 'overview' => t('cases.doctor_overview'), 'action' => t('cases.doctor_action'), 'action_aria' => 'cases.doctor_action_aria'],
    'healthcare_staff' => ['title' => t('cases.staff_title'), 'subtitle' => t('cases.staff_subtitle'), 'overview' => t('cases.staff_overview'), 'action' => t('cases.staff_action'), 'action_aria' => 'cases.staff_action_aria'],
    'admin' => ['title' => t('cases.admin_title'), 'subtitle' => t('cases.admin_subtitle'), 'overview' => '', 'action' => '', 'action_aria' => ''],
][$chnPrototypeRole];
$canViewClinicalDetails = in_array($chnPrototypeRole, ['patient', 'doctor', 'healthcare_staff'], true);
$canSearchClinicalText = in_array($chnPrototypeRole, ['patient', 'doctor'], true);
// Illustrative fixture scopes only. Real authorization must be enforced by the backend.
$cases = array_values(array_filter($cases, static function (array $case) use ($chnPrototypeRole, $preview): bool {
    switch ($chnPrototypeRole) {
        case 'patient': return $case['patient_id'] === $preview['context']['patient_id'];
        case 'doctor': return $case['doctor_id'] === $preview['context']['doctor_id'];
        case 'healthcare_staff': return $case['department_id'] === $preview['context']['staff_department_id'];
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
    if (!$value) return t('cases.not_available');
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    return $date ? chnDate($date, 'medium_time') : t('cases.not_available');
}
$stateLabels = ['open' => t('cases.state_open'), 'closed' => t('cases.state_closed')];
$query = mcParam('q');
$status = in_array(mcParam('status'), ['open', 'closed'], true) ? mcParam('status') : '';
$department = mcParam('department');
$departments = array_column($cases, 'department_name', 'department_id');
if (!isset($departments[$department])) $department = '';
$filtered = array_values(array_filter($cases, static function (array $case) use ($query, $status, $department, $canSearchClinicalText, $chnPrototypeRole, $casePrefix): bool {
    $search = implode(' ', [$casePrefix . $case['case_id'], $case['department_name'], $case['doctor_name'] ?? '', $canSearchClinicalText ? (($case['case_title'] ?? '') . ' ' . ($case['case_description'] ?? '')) : '', $chnPrototypeRole === 'doctor' ? $case['patient_name'] : '', $chnPrototypeRole !== 'patient' ? 'DEMO-P-' . $case['patient_id'] : '']);
    return ($status === '' || $case['case_state'] === $status)
        && ($department === '' || (string) $case['department_id'] === $department)
        && ($query === '' || stripos($search, $query) !== false);
}));
$selected = null;
foreach ($filtered as $case) {
    if ((string) $case['case_id'] === mcParam('case')) $selected = $case;
}
$canAddDiagnosis = $chnPrototypeRole === 'doctor' && $selected && $selected['case_state'] === 'open';
$selectedDiagnoses = $selected && $canViewClinicalDetails ? array_values(array_filter($diagnoses, static fn(array $diagnosis): bool => (string) $diagnosis['case_id'] === (string) $selected['case_id'])) : [];
usort($selectedDiagnoses, static fn(array $a, array $b): int => strcmp($b['diagnosed_at'], $a['diagnosed_at']));
$appointmentBadges = ['pending' => 'warning', 'confirmed' => 'success', 'completed' => 'neutral'];
$selectedAppointments = $selected && $canViewClinicalDetails ? array_values(array_filter(
    $preview['appointments'],
    static fn(array $appointment): bool => $appointment['case_id'] === $selected['case_id']
        && isset($appointmentBadges[$appointment['appointment_status']])
)) : [];
usort($selectedAppointments, static fn(array $a, array $b): int =>
    [$b['appointment_date'], $b['appointment_time'], $b['appointment_id']]
    <=> [$a['appointment_date'], $a['appointment_time'], $a['appointment_id']]
);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(chnLang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= mcEscape($view['title']) ?> | CHN</title>

    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="../css/medical-cases.css?v=<?= filemtime(dirname(__DIR__) . '/css/medical-cases.css') ?>">

    <script src="../js/sidebar.js" defer></script>
    <script src="../js/topbar.js?v=<?= filemtime(__DIR__ . '/../js/topbar.js') ?>" defer></script>
    <script src="../js/medical-cases.js?v=<?= filemtime(dirname(__DIR__) . '/js/medical-cases.js') ?>" defer></script>
</head>
<body>
<?php require dirname(__DIR__) . '/include/left-sidebar.php'; ?>
<?php require dirname(__DIR__) . '/include/top-bar.php'; ?>
<main class="content medical-cases" id="main-content" tabindex="-1">
<div class="content__inner">
    <header class="page-header">
        <div><h1 class="page-header__title"><?= mcEscape($view['title']) ?></h1>
        <p class="page-header__subtitle"><?= mcEscape($view['subtitle']) ?></p></div>
        <div class="page-header__actions"><span class="badge badge--info"><?= te('cases.demo_badge') ?></span>
        <?php if (in_array($chnPrototypeRole, ['patient', 'doctor'], true)): ?><a class="btn btn--primary" href="#create-case"><?= te('cases.create_case') ?></a><?php endif; ?></div>
    </header>
    <div class="alert medical-cases__notice"><strong><?= te('cases.notice_title') ?></strong> <?= te('cases.notice_text') ?> <?= strtr(te('cases.active_role'), ['{role}' => '<strong>' . mcEscape($chnPrototypeRoles[$chnPrototypeRole]) . '</strong>']) ?></div>
    <?php if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'): ?><div class="alert alert--info medical-cases__notice" role="status"><?= te('cases.post_notice') ?></div><?php endif; ?>
    <p class="alert alert--info medical-cases__notice" id="case-demo-result" role="status" hidden data-messages="<?= chnJsMessages(['selectDoctor' => 'cases.select_doctor', 'doctorRequired' => 'cases.doctor_select_required', 'noDoctors' => 'cases.no_doctors', 'formInvalid' => 'cases.form_invalid', 'formChecked' => 'cases.form_checked', 'open' => 'cases.state_open', 'closed' => 'cases.state_closed', 'green' => 'tier.green', 'yellow' => 'tier.yellow', 'orange' => 'tier.orange', 'updated' => 'cases.demo_updated']) ?>"></p>
    <form method="get" action="<?= mcEscape(chnPrototypeUrl('medical-cases.php', $chnPrototypeRole)) ?>" class="medical-cases__role-picker" aria-label="<?= te('cases.role_picker') ?>">
        <div><label class="form-label" for="preview-role"><?= te('cases.demo_role') ?></label><select class="form-control" id="preview-role" name="role"><?php foreach ($chnPrototypeRoles as $role => $label): ?><option value="<?= $role ?>" <?= $role === $chnPrototypeRole ? 'selected' : '' ?>><?= mcEscape($label) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn--outline" type="submit"><?= te('cases.preview_role') ?></button>
    </form>
    <?php if (in_array($chnPrototypeRole, ['patient', 'doctor'], true)): ?>
    <section class="card medical-cases__creation" id="create-case">
        <header class="card__header"><div><h2 class="card__title"><?= te('cases.create_case') ?></h2><p class="card__subtitle"><?= te('cases.create_subtitle') ?></p></div></header>
        <form method="post" action="<?= mcEscape(chnPrototypeUrl('medical-cases.php#create-case', $chnPrototypeRole)) ?>" data-case-demo>
            <input type="hidden" name="action" value="<?= $chnPrototypeRole === 'doctor' ? 'create_doctor' : 'patient_create' ?>">
            <div class="medical-cases__create-grid">
            <?php if ($chnPrototypeRole === 'doctor'): ?>
            <div><label class="form-label" for="create-national_id"><?= te('cases.national_id') ?></label><input class="form-control" id="create-national_id" name="national_id" required minlength="3" maxlength="50" pattern="[A-Za-z0-9-]{3,50}" autocomplete="off"><p class="form-hint"><?= te('cases.national_id_hint') ?></p></div>
            <?php endif; ?>
            <div><label class="form-label" for="create-department_id"><?= te('cases.department') ?></label>
            <select class="form-control" id="create-department_id" name="department_id" required><option value=""><?= te('cases.select_department') ?></option><?php foreach ($options['departments'] as $option): ?><option value="<?= (int) $option['department_id'] ?>"<?= $chnPrototypeRole === 'doctor' ? ' selected' : '' ?>><?= mcEscape($option['department_name']) ?></option><?php endforeach; ?></select></div>
            <?php if ($chnPrototypeRole === 'patient'): ?>
            <div><label class="form-label" for="create-doctor_id"><?= te('cases.preferred_doctor') ?></label>
            <select class="form-control" id="create-doctor_id" name="doctor_id" required><option value=""><?= te('cases.select_doctor') ?></option><?php foreach ($options['doctors'] as $option): ?><option data-department="<?= (int) $option['department_id'] ?>" value="<?= (int) $option['doctor_id'] ?>"><?= mcEscape($option['doctor_name']) ?></option><?php endforeach; ?></select><p id="doctor-availability" class="form-hint"><?= te('cases.doctor_hint') ?></p></div>
            <?php else: ?>
            <div><p class="form-label"><?= te('cases.responsible_doctor') ?></p><p class="form-hint"><?= te('cases.auto_assigned') ?></p></div>
            <?php endif; ?>
            <div class="medical-cases__create-description"><label class="form-label" for="create-case_title"><?= te('cases.case_title') ?></label><input class="form-control" id="create-case_title" name="case_title" required maxlength="160"><p class="form-hint"><?= te('cases.case_title_hint') ?></p></div>
            <div class="medical-cases__create-description"><label class="form-label" for="create-case_description"><?= te('cases.case_description') ?></label><textarea class="form-control" id="create-case_description" name="case_description" required rows="6"></textarea><p class="form-hint"><?= te('cases.case_description_hint') ?></p></div>
            </div><button class="btn btn--primary" type="submit" data-case-submit-button disabled><?= te('cases.save') ?></button>
        </form>
    </section>
    <?php endif; ?>
    <?php if ($chnPrototypeRole === 'admin'): ?>
    <section class="card"><h2 class="card__title"><?= te('cases.admin_access_title') ?></h2><p><?= te('cases.admin_access_text') ?></p></section>
    <?php else: ?>
    <div class="grid grid--2 medical-cases__summary" aria-label="<?= te('cases.summary_label') ?>">
        <?php foreach (['open' => t('cases.active_cases'), 'closed' => t('cases.closed_cases')] as $state => $label): ?>
        <div class="stat-card"><div><p class="stat-card__label"><?= mcEscape($label) ?></p>
        <p class="stat-card__value" data-case-count="<?= $state ?>" data-initial-count="<?= count(array_filter($cases, static fn(array $case): bool => $case['case_state'] === $state)) ?>"><?= count(array_filter($cases, static fn(array $case): bool => $case['case_state'] === $state)) ?></p>
        <p class="card__subtitle"><?= te('cases.scope_' . $state) ?></p></div></div>
        <?php endforeach; ?>
    </div>
    <section class="card" aria-labelledby="cases-title">
        <header class="card__header"><div><h2 class="card__title" id="cases-title"><?= mcEscape($view['overview']) ?></h2>
        <p class="card__subtitle"><?= $chnPrototypeRole === 'healthcare_staff' ? te('cases.overview_staff') : te('cases.overview_clinical') ?></p></div></header>
        <form method="get" action="<?= mcEscape(chnPrototypeUrl('medical-cases.php', $chnPrototypeRole)) ?>" class="medical-cases__filters" role="search" aria-label="<?= te('cases.filter_label') ?>">
            <input type="hidden" name="role" value="<?= mcEscape($chnPrototypeRole) ?>">
            <div><label class="form-label" for="case-search"><?= te('cases.search_label') ?></label><input class="form-control" type="search" name="q" id="case-search" value="<?= mcEscape($query) ?>" placeholder="<?= $canSearchClinicalText ? te('cases.search_placeholder_clinical') : te('cases.search_placeholder_staff') ?>"></div>
            <div><label class="form-label" for="case-status"><?= te('cases.status_label') ?></label><select class="form-control" name="status" id="case-status"><option value=""><?= te('cases.all_statuses') ?></option><?php foreach ($stateLabels as $value => $label): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= mcEscape($label) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="case-department"><?= te('cases.department') ?></label><select class="form-control" name="department" id="case-department"><option value=""><?= te('cases.all_departments') ?></option><?php foreach ($departments as $id => $label): ?><option value="<?= $id ?>" <?= $department === (string) $id ? 'selected' : '' ?>><?= mcEscape($label) ?></option><?php endforeach; ?></select></div>
            <div class="medical-cases__filter-actions"><button class="btn btn--primary" type="submit"><?= te('cases.apply_filters') ?></button><a class="btn btn--outline" href="<?= mcEscape(chnPrototypeUrl('medical-cases.php', $chnPrototypeRole)) ?>"><?= te('cases.reset') ?></a></div>
        </form>
        <p class="card__subtitle medical-cases__count"><?= mcEscape(tn('cases.count', count($cases), ['shown' => count($filtered)])) ?></p>
        <?php if ($filtered): ?>
        <div class="table-responsive" tabindex="0" role="region" aria-label="<?= te('cases.table_label') ?>">
        <table class="table"><caption class="medical-cases__caption"><?= te('cases.caption') ?></caption>
        <thead><tr><th scope="col"><?= te('cases.col_case_id') ?></th><?php if ($chnPrototypeRole !== 'patient'): ?><th scope="col"><?= $chnPrototypeRole === 'doctor' ? te('cases.col_patient') : te('cases.col_patient_ref') ?></th><?php endif; ?><th scope="col"><?= te('cases.department') ?></th><th scope="col"><?= te('cases.responsible_doctor') ?></th><th scope="col"><?= te('cases.created') ?></th><th scope="col"><?= te('cases.status_label') ?></th><th scope="col"><?= te('cases.triage') ?></th><th scope="col"><?= te('cases.details') ?></th></tr></thead>
        <tbody><?php foreach ($filtered as $case): ?>
        <tr data-case-id="<?= (int) $case['case_id'] ?>" class="<?= $selected && $selected['case_id'] === $case['case_id'] ? 'medical-cases__selected' : '' ?>">
            <th scope="row"><?= mcEscape($casePrefix) ?><?= $case['case_id'] ?></th>
            <?php if ($chnPrototypeRole !== 'patient'): ?><td><?= $chnPrototypeRole === 'doctor' ? mcEscape($case['patient_name']) . '<br>' : '' ?>DEMO-P-<?= $case['patient_id'] ?></td><?php endif; ?>
            <td><?= mcEscape($case['department_name']) ?></td><td><?= mcEscape($case['doctor_name'] ?? t('cases.not_assigned')) ?></td><td><?= mcEscape(mcDate($case['created_at'])) ?></td>
            <td><span data-case-state class="badge <?= $case['case_state'] === 'open' ? 'badge--info' : 'badge--neutral' ?>"><?= mcEscape($stateLabels[$case['case_state']] ?? $case['case_state']) ?></span></td>
            <td><span data-case-triage class="badge medical-cases__triage medical-cases__triage--<?= mcEscape($case['tier_color']) ?>"><?= te('tier.' . $case['tier_color']) ?></span></td>
            <td><a class="btn btn--outline btn--sm" href="<?= mcEscape(chnPrototypeUrl('medical-cases.php#case-details', $chnPrototypeRole, ['q' => $query, 'status' => $status, 'department' => $department, 'case' => $case['case_id']])) ?>" <?= $selected && $selected['case_id'] === $case['case_id'] ? 'aria-current="true"' : '' ?> aria-label="<?= te($view['action_aria'], ['id' => $casePrefix . $case['case_id']]) ?>"><?= mcEscape($view['action']) ?></a></td>
        </tr><?php endforeach; ?></tbody></table></div>
        <?php else: ?><div class="medical-cases__empty"><h3><?= te('cases.no_match_title') ?></h3><p><?= te('cases.no_match_text') ?></p><a class="btn btn--outline" href="<?= mcEscape(chnPrototypeUrl('medical-cases.php', $chnPrototypeRole)) ?>"><?= te('cases.reset_filters') ?></a></div><?php endif; ?>
    </section>
    <section class="card medical-cases__details" id="case-details" aria-labelledby="case-details-title" tabindex="-1">
        <header class="card__header"><h2 class="card__title" id="case-details-title"><?= $selected ? te('cases.case_heading', ['id' => $casePrefix . $selected['case_id']]) : te('cases.case_details') ?></h2><?php if ($selected): ?><span class="badge badge--info"><?= te('cases.demo_case_badge') ?></span><?php endif; ?></header>
        <?php if ($selected): ?>
        <dl class="medical-cases__metadata">
            <?php if ($chnPrototypeRole !== 'patient'): ?><div><dt><?= $chnPrototypeRole === 'healthcare_staff' ? te('cases.col_patient_ref') : te('cases.col_patient') ?></dt><dd><?= $chnPrototypeRole === 'doctor' ? mcEscape($selected['patient_name']) . ' · ' : '' ?>DEMO-P-<?= $selected['patient_id'] ?></dd></div><?php endif; ?>
            <div><dt><?= te('cases.department') ?></dt><dd><?= mcEscape($selected['department_name']) ?></dd></div>
            <div><dt><?= te('cases.responsible_doctor') ?></dt><dd><?= mcEscape($selected['doctor_name'] ?? t('cases.not_assigned')) ?></dd></div>
            <div><dt><?= te('cases.created') ?></dt><dd><?= mcEscape(mcDate($selected['created_at'])) ?></dd></div>
            <div><dt><?= te('cases.status_label') ?></dt><dd data-current-status>
            <?php if (in_array($chnPrototypeRole, ['patient','doctor'], true)): ?>
            <details class="medical-cases__status-editor">
                <summary aria-label="<?= te('cases.edit_status') ?>"><span><?= mcEscape($stateLabels[$selected['case_state']] ?? $selected['case_state']) ?></span><span class="medical-cases__edit-pencil" aria-hidden="true">&#9998;</span><span class="medical-cases__edit-label"><?= te('cases.edit') ?></span></summary>
        <form method="post" action="<?= mcEscape(chnPrototypeUrl('medical-cases.php#case-details', $chnPrototypeRole, ['case' => $selected['case_id']])) ?>" data-case-demo data-original-state="<?= mcEscape($selected['case_state']) ?>">
            <input type="hidden" name="action" value="<?= $chnPrototypeRole === 'doctor' ? 'update_doctor_status' : 'update_status' ?>"><input type="hidden" name="case_id" value="<?= (int) $selected['case_id'] ?>">
            <label class="form-label" for="update-status"><?= te('cases.update_status_label') ?></label><select class="form-control" id="update-status" name="case_state" required><?php foreach ($stateLabels as $value => $label): ?><option value="<?= $value ?>" <?= $selected['case_state'] === $value ? 'selected' : '' ?>><?= mcEscape($label) ?></option><?php endforeach; ?></select>
            <button class="btn btn--primary" data-case-submit-button disabled><?= te('cases.update_status') ?></button>
        </form>
            </details>
            <?php else: ?><?= mcEscape($stateLabels[$selected['case_state']] ?? $selected['case_state']) ?><?php endif; ?>
            </dd></div>
            <div><dt><?= te('cases.triage') ?></dt><dd data-current-triage>
            <?php if (in_array($chnPrototypeRole, ['doctor', 'healthcare_staff'], true)): ?>
            <details class="medical-cases__triage-editor">
                <summary aria-label="<?= te('cases.edit_triage') ?>"><span><?= te('tier.' . $selected['tier_color']) ?></span><span class="medical-cases__edit-pencil" aria-hidden="true">&#9998;</span><span class="medical-cases__edit-label"><?= te('cases.edit') ?></span></summary>
        <form method="post" action="<?= mcEscape(chnPrototypeUrl('medical-cases.php#case-details', $chnPrototypeRole, ['case' => $selected['case_id']])) ?>" data-case-demo data-original-state="<?= mcEscape($selected['case_state']) ?>">
        <input type="hidden" name="action" value="update_triage"><input type="hidden" name="case_id" value="<?= (int) $selected['case_id'] ?>">
        <label class="form-label" for="update-triage"><?= te('cases.update_triage_label') ?></label><select class="form-control" name="tier_color" id="update-triage" required><?php foreach (['green','yellow','orange'] as $color): ?><option value="<?= $color ?>" <?= $selected['tier_color'] === $color ? 'selected' : '' ?>><?= te('tier.' . $color) ?></option><?php endforeach; ?></select><button class="btn btn--primary" data-case-submit-button disabled><?= te('cases.update_triage') ?></button></form>
            </details>
            <?php else: ?><?= te('tier.' . $selected['tier_color']) ?><?php endif; ?>
            </dd></div>
        </dl>
        <?php if ($canViewClinicalDetails): ?>
        <h3 class="medical-cases__heading"><?= te('cases.case_title') ?></h3><p><?= mcEscape($selected['case_title'] ?? t('cases.no_title')) ?></p><h3 class="medical-cases__heading"><?= te('cases.case_description') ?></h3><p class="medical-cases__description"><?= mcEscape($selected['case_description'] ?? t('cases.no_description')) ?></p>
        <section class="medical-cases__appointments" aria-labelledby="appointment-history-title">
            <div class="medical-cases__appointments-header">
                <div>
                    <h3 class="medical-cases__heading" id="appointment-history-title"><?= te('cases.appointment_history') ?></h3>
                    <p class="card__subtitle"><?= mcEscape(tn('cases.appointment_count', count($selectedAppointments))) ?></p>
                </div>
                <button class="btn btn--outline" type="button" id="toggle-appointments" aria-controls="case-appointments" aria-expanded="false" hidden>
                    <span data-show-appointments><?= te('cases.view_appointments') ?></span>
                    <span data-hide-appointments hidden><?= te('cases.hide_appointments') ?></span>
                    <img class="medical-cases__appointment-chevron" src="../assets/icons/chevron-right.svg" width="16" height="16" alt="">
                </button>
            </div>
            <div id="case-appointments" hidden>
                <?php if ($selectedAppointments): ?>
                <ul class="medical-cases__appointment-list" role="list">
                    <?php foreach ($selectedAppointments as $appointment):
                        $date = new DateTimeImmutable($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
                        $appointmentStatus = $appointment['appointment_status'];
                    ?>
                    <li class="medical-cases__appointment">
                        <dl>
                            <div><dt><?= te('cases.appointment_id') ?></dt><dd>#<?= mcEscape($appointment['appointment_id']) ?></dd></div>
                            <div><dt><?= te('appointments.date') ?></dt><dd><time datetime="<?= $date->format('Y-m-d') ?>"><?= mcEscape(chnDate($date, 'medium')) ?></time></dd></div>
                            <div><dt><?= te('cases.appointment_time') ?></dt><dd><time datetime="<?= $date->format('H:i') ?>"><?= $date->format('H:i') ?></time></dd></div>
                            <div><dt><?= te('appointments.col_doctor') ?></dt><dd><?= mcEscape($appointment['doctor_name']) ?></dd></div>
                            <div><dt><?= te('appointments.department') ?></dt><dd><?= mcEscape($appointment['department_name']) ?></dd></div>
                            <div><dt><?= te('appointments.status') ?></dt><dd><span class="badge badge--<?= $appointmentBadges[$appointmentStatus] ?>"><?= te('appointment_status.' . $appointmentStatus) ?></span></dd></div>
                        </dl>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="medical-cases__empty"><?= te('cases.no_appointments') ?></p>
                <?php endif; ?>
            </div>
        </section>
        <section class="medical-cases__diagnoses" aria-labelledby="diagnosis-history-title">
            <div class="medical-cases__diagnosis-header">
                <h3 class="medical-cases__heading" id="diagnosis-history-title"><?= te('cases.diagnosis_history') ?></h3>
                <?php if ($canAddDiagnosis): ?>
                <button class="btn btn--primary" type="button" id="open-diagnosis" aria-controls="add-diagnosis" aria-expanded="false" hidden><?= te('cases.add_diagnosis') ?></button>
                <?php endif; ?>
            </div>
            <?php if ($canAddDiagnosis): ?>
            <form class="medical-cases__diagnosis-form" id="add-diagnosis" method="post" hidden novalidate
                aria-labelledby="add-diagnosis-title"
                data-messages="<?= chnJsMessages([
                    'required' => 'cases.diagnosis_required', 'characters' => 'cases.diagnosis_characters',
                    'descriptionLength' => 'cases.diagnosis_description_length', 'icdLength' => 'cases.diagnosis_icd_length',
                    'preview' => 'cases.diagnosis_preview_entry', 'added' => 'cases.diagnosis_added', 'icd' => 'cases.icd_code',
                ]) ?>">
                <h4 class="medical-cases__heading" id="add-diagnosis-title"><?= te('cases.add_diagnosis') ?></h4>
                <input type="hidden" name="case_id" value="<?= (int) $selected['case_id'] ?>">
                <dl class="medical-cases__metadata">
                    <div><dt><?= te('cases.col_case_id') ?></dt><dd><?= mcEscape($casePrefix . $selected['case_id']) ?></dd></div>
                    <div><dt><?= te('cases.col_patient') ?></dt><dd><?= mcEscape($selected['patient_name']) ?></dd></div>
                    <div><dt><?= te('cases.responsible_doctor') ?></dt><dd id="diagnosis-doctor"><?= mcEscape($selected['doctor_name']) ?></dd></div>
                </dl>
                <div class="medical-cases__diagnosis-field">
                    <label class="form-label" for="diagnosis-text"><?= te('cases.diagnosis_description') ?></label>
                    <textarea class="form-control" id="diagnosis-text" name="diagnosis_text" rows="4" required aria-describedby="diagnosis-text-error"></textarea>
                    <p class="form-error" id="diagnosis-text-error" aria-live="polite"></p>
                </div>
                <div class="medical-cases__diagnosis-field">
                    <label class="form-label" for="diagnosis-icd"><?= te('cases.diagnosis_icd_optional') ?></label>
                    <input class="form-control" id="diagnosis-icd" name="icd_code" aria-describedby="diagnosis-icd-hint diagnosis-icd-error">
                    <p class="form-hint" id="diagnosis-icd-hint"><?= te('cases.diagnosis_icd_length') ?></p>
                    <p class="form-error" id="diagnosis-icd-error" aria-live="polite"></p>
                </div>
                <div class="medical-cases__diagnosis-actions">
                    <button class="btn btn--primary" type="submit" disabled><?= te('cases.diagnosis_add_preview') ?></button>
                    <button class="btn btn--outline" type="button" id="cancel-diagnosis"><?= te('cases.diagnosis_cancel') ?></button>
                </div>
            </form>
            <p class="medical-cases__diagnosis-result" id="diagnosis-result" role="status"></p>
            <?php endif; ?>
            <ol class="medical-cases__history" id="diagnosis-history" <?= $selectedDiagnoses ? '' : 'hidden' ?>>
                <?php foreach ($selectedDiagnoses as $diagnosis): ?><li><p><?= mcEscape($diagnosis['diagnosis_text']) ?></p><p class="card__subtitle"><?= mcEscape(mcDate($diagnosis['diagnosed_at'])) ?> · <?= mcEscape($diagnosis['doctor_name'] ?? t('cases.doctor_unavailable')) ?></p><?php if (!empty($diagnosis['icd_code'])): ?><p><?= te('cases.icd_code', ['code' => $diagnosis['icd_code']]) ?></p><?php endif; ?></li><?php endforeach; ?>
            </ol>
            <p class="medical-cases__empty" id="diagnosis-empty" <?= $selectedDiagnoses ? 'hidden' : '' ?>><?= te('cases.no_diagnoses') ?></p>
        </section>
        <?php endif; ?>
        <?php else: ?><p class="medical-cases__empty"><?= mcParam('case') !== '' ? te('cases.case_not_found') : ($chnPrototypeRole === 'healthcare_staff' ? te('cases.select_staff') : te('cases.select_clinical')) ?></p><?php endif; ?>
    </section>
    <?php endif; ?>
    <noscript><p class="card__subtitle"><?= te('cases.noscript') ?></p></noscript>
</div>
</main>
</body>
</html>
