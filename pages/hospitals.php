<?php
declare(strict_types=1);

$chnProjectRoot = dirname(__DIR__);
require_once $chnProjectRoot . '/backend/auth.php';
require_once $chnProjectRoot . '/backend/db.php';
require_once $chnProjectRoot . '/include/i18n.php';

$sessionUser = requireRole(['admin'], false);

function hospitalsEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$hospitals = [];
try {
    $pdo = chnGetPdo();
    $hospRows = $pdo->query(
        'SELECT h.hospital_id, h.hospital_name, h.hospital_type, h.city, h.address, h.phone,
                (SELECT COUNT(*) FROM `staff` s WHERE s.hospital_id = h.hospital_id) AS staff_count
         FROM `hospital` h
         ORDER BY h.hospital_name ASC'
    )->fetchAll();

    foreach ($hospRows as $row) {
        $hid = (int) $row['hospital_id'];
        $hospitals[$hid] = [
            'hospital_id' => $hid,
            'hospital_name' => (string) $row['hospital_name'],
            'hospital_type' => (string) $row['hospital_type'],
            'city' => (string) ($row['city'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'staff_count' => (int) $row['staff_count'],
            'departments' => [],
        ];
    }

    $deptRows = $pdo->query(
        'SELECT d.department_id, d.department_name, d.hospital_id,
                (SELECT COUNT(*) FROM `doctor` doc WHERE doc.department_id = d.department_id) AS doctor_count,
                (SELECT COUNT(*) FROM `case` c WHERE c.department_id = d.department_id) AS case_count
         FROM `department` d
         ORDER BY d.department_name ASC'
    )->fetchAll();

    foreach ($deptRows as $row) {
        $hid = (int) $row['hospital_id'];
        if (!isset($hospitals[$hid])) {
            continue;
        }
        $hospitals[$hid]['departments'][] = [
            'department_id' => (int) $row['department_id'],
            'department_name' => (string) $row['department_name'],
            'doctor_count' => (int) $row['doctor_count'],
            'case_count' => (int) $row['case_count'],
        ];
    }
} catch (Throwable $e) {
    chnLogError('Hospitals page load error: ' . $e->getMessage());
}

$chnPageTitle = t('hospitals.page_title');
$chnUserName = trim($sessionUser['name'] . ' ' . $sessionUser['surname']);
$chnUserRole = 'admin';
?>
<!DOCTYPE html>
<html lang="<?= hospitalsEscape(chnLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | <?= te('hospitals.page_title') ?></title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime($chnProjectRoot . '/css/style.css') ?>">
    <link rel="stylesheet" href="../css/hospitals.css?v=<?= filemtime($chnProjectRoot . '/css/hospitals.css') ?>">
    <script src="../js/sidebar.js?v=<?= filemtime($chnProjectRoot . '/js/sidebar.js') ?>" defer></script>
    <script src="../js/topbar.js?v=<?= filemtime($chnProjectRoot . '/js/topbar.js') ?>" defer></script>
    <script src="../js/hospitals.js?v=<?= filemtime($chnProjectRoot . '/js/hospitals.js') ?>" defer></script>
</head>
<body>
<?php
require $chnProjectRoot . '/include/left-sidebar.php';
require $chnProjectRoot . '/include/top-bar.php';
?>

<main class="content" id="main-content" tabindex="-1">
    <div class="content__inner hospitals">
        <header class="page-header">
            <div>
                <h1 class="page-header__title"><?= te('hospitals.title') ?></h1>
                <p class="page-header__subtitle"><?= te('hospitals.subtitle') ?></p>
            </div>
        </header>

        <p class="alert alert--info hospitals-notice" id="hospitals-notice" role="status" tabindex="-1" hidden
           data-messages="<?= chnJsMessages([
               'hospital_created' => 'hospitals.hospital_created',
               'hospital_updated' => 'hospitals.hospital_updated',
               'department_created' => 'hospitals.department_created',
               'department_deleted' => 'hospitals.department_deleted',
               'delete_department_confirm' => 'hospitals.delete_department_confirm',
               'required' => 'hospitals.field_required',
               'invalid_chars' => 'hospitals.invalid_chars',
               'phone_invalid' => 'hospitals.phone_invalid',
               'request_failed' => 'hospitals.request_failed',
               'add_title' => 'hospitals.add_hospital',
               'edit_title' => 'hospitals.edit_hospital',
               'save_add' => 'hospitals.save_hospital',
               'save_edit' => 'hospitals.update_hospital',
           ]) ?>"></p>

        <div class="hospitals-forms">
            <section class="card" aria-labelledby="hospital-form-title">
                <header class="card__header">
                    <div>
                        <h2 class="card__title" id="hospital-form-title"><?= te('hospitals.add_hospital') ?></h2>
                        <p class="card__subtitle"><?= te('hospitals.public_only_note') ?></p>
                    </div>
                </header>
                <form id="hospital-form" method="post" novalidate
                      data-create-url="../backend/create_hospital.php"
                      data-update-url="../backend/update_hospital.php"
                      data-mode="create">
                    <input type="hidden" name="hospital_id" id="hospital-id" value="">
                    <div class="hospitals-form__grid">
                        <div class="form-group">
                            <label class="form-label" for="hospital-name"><?= te('hospitals.name') ?> *</label>
                            <input class="form-control" id="hospital-name" name="hospital_name" type="text" maxlength="200" required>
                            <p class="form-error" id="hospital-name-error" hidden></p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="hospital-city"><?= te('hospitals.city') ?> *</label>
                            <input class="form-control" id="hospital-city" name="city" type="text" maxlength="100" required>
                            <p class="form-error" id="hospital-city-error" hidden></p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="hospital-phone"><?= te('hospitals.phone') ?></label>
                            <input class="form-control" id="hospital-phone" name="phone" type="tel" maxlength="16" inputmode="tel">
                            <p class="form-error" id="hospital-phone-error" hidden></p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="hospital-address"><?= te('hospitals.address') ?></label>
                            <input class="form-control" id="hospital-address" name="address" type="text" maxlength="255">
                            <p class="form-error" id="hospital-address-error" hidden></p>
                        </div>
                    </div>
                    <div class="card__footer">
                        <button type="button" class="btn btn--outline" id="hospital-cancel" hidden><?= te('hospitals.cancel') ?></button>
                        <button type="submit" class="btn btn--primary" id="hospital-submit"><?= te('hospitals.save_hospital') ?></button>
                    </div>
                </form>
            </section>

            <section class="card" aria-labelledby="department-form-title">
                <header class="card__header">
                    <div>
                        <h2 class="card__title" id="department-form-title"><?= te('hospitals.add_department') ?></h2>
                        <p class="card__subtitle"><?= te('hospitals.department_subtitle') ?></p>
                    </div>
                </header>
                <form id="department-form" method="post" novalidate data-create-url="../backend/create_department.php">
                    <div class="hospitals-form__grid">
                        <div class="form-group">
                            <label class="form-label" for="dept-hospital-id"><?= te('hospitals.hospital') ?> *</label>
                            <select class="form-control" id="dept-hospital-id" name="hospital_id" required>
                                <option value=""><?= te('hospitals.select_hospital') ?></option>
                                <?php foreach ($hospitals as $h): ?>
                                    <option value="<?= (int) $h['hospital_id'] ?>"><?= hospitalsEscape($h['hospital_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="form-error" id="dept-hospital-id-error" hidden></p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="dept-name"><?= te('hospitals.department_name') ?> *</label>
                            <input class="form-control" id="dept-name" name="department_name" type="text" maxlength="255" required>
                            <p class="form-error" id="dept-name-error" hidden></p>
                        </div>
                    </div>
                    <div class="card__footer">
                        <button type="submit" class="btn btn--primary" id="department-submit"><?= te('hospitals.save_department') ?></button>
                    </div>
                </form>
            </section>
        </div>

        <section class="hospitals-directory" aria-labelledby="hospitals-list-title">
            <h2 class="hospitals-directory__title" id="hospitals-list-title"><?= te('hospitals.directory_title') ?> (<?= count($hospitals) ?>)</h2>
            <div class="hospitals-cards">
                <?php foreach ($hospitals as $h): ?>
                    <?php
                    $editJson = json_encode([
                        'hospital_id' => $h['hospital_id'],
                        'hospital_name' => $h['hospital_name'],
                        'city' => $h['city'],
                        'phone' => $h['phone'],
                        'address' => $h['address'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    ?>
                    <article class="card hospital-card">
                        <header class="card__header">
                            <div>
                                <h3 class="card__title"><?= hospitalsEscape($h['hospital_name']) ?></h3>
                                <p class="card__subtitle">
                                    <?= hospitalsEscape($h['city'] !== '' ? $h['city'] : 'Cyprus') ?>
                                    <?php if ($h['phone'] !== ''): ?> · <?= hospitalsEscape($h['phone']) ?><?php endif; ?>
                                </p>
                            </div>
                            <div class="hospital-card__header-actions">
                                <span class="badge badge--info"><?= te('hospitals.public_badge') ?></span>
                                <button type="button" class="btn btn--outline btn--sm" data-hospital-edit data-hospital="<?= hospitalsEscape((string) $editJson) ?>">
                                    <?= te('hospitals.edit') ?>
                                </button>
                            </div>
                        </header>
                        <?php if ($h['address'] !== ''): ?>
                            <p class="hospital-card__address"><?= hospitalsEscape($h['address']) ?></p>
                        <?php endif; ?>
                        <p class="hospital-card__meta">
                            <?= te('hospitals.staff_count', ['count' => $h['staff_count']]) ?> ·
                            <?= te('hospitals.dept_count', ['count' => count($h['departments'])]) ?>
                        </p>
                        <?php if ($h['departments'] === []): ?>
                            <p class="hospital-card__empty"><?= te('hospitals.no_departments') ?></p>
                        <?php else: ?>
                            <ul class="hospital-card__depts">
                                <?php foreach ($h['departments'] as $dept): ?>
                                    <li class="hospital-card__dept">
                                        <div>
                                            <strong><?= hospitalsEscape($dept['department_name']) ?></strong>
                                            <span class="hospital-card__dept-stats">
                                                <?= te('hospitals.doctor_count', ['count' => $dept['doctor_count']]) ?> ·
                                                <?= te('hospitals.case_count', ['count' => $dept['case_count']]) ?>
                                            </span>
                                        </div>
                                        <button type="button" class="btn btn--outline btn--sm hospital-card__dept-delete"
                                                data-department-delete
                                                data-department-id="<?= (int) $dept['department_id'] ?>"
                                                data-department-name="<?= hospitalsEscape($dept['department_name']) ?>">
                                            <?= te('hospitals.delete') ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</main>
</body>
</html>
