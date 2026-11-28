<?php
declare(strict_types=1);

$chnProjectRoot = dirname(__DIR__);
require_once $chnProjectRoot . '/backend/auth.php';
require_once $chnProjectRoot . '/backend/db.php';
require_once $chnProjectRoot . '/backend/validation.php';
require_once $chnProjectRoot . '/include/i18n.php';

chnStartSession();
header('Cache-Control: no-store');

$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$role = $_SESSION['role'] ?? null;
$lastActivity = $_SESSION['last_activity'] ?? null;
$now = time();
$isAuthenticated = $userId !== false
    && in_array($role, ['patient', 'doctor', 'healthcare_staff', 'admin'], true)
    && is_int($lastActivity) && $lastActivity <= $now && $lastActivity > $now - (int) (getenv('SESSION_LIFETIME') ?: 1800);

$accountUser = null;
$lastAccountActive = false;

try {
    $pdo = chnGetPdo();

    if ($isAuthenticated) {
        $stmt = $pdo->prepare(
            'SELECT u.user_id, u.role, u.name, u.surname, u.phone, u.birth_date, u.is_banned, p.full_address
             FROM `user` u
             LEFT JOIN `patient` p ON p.user_id = u.user_id
             WHERE u.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([':user_id' => (int) $userId]);
        $row = $stmt->fetch();
        if ($row === false || (int) $row['is_banned'] === 1) {
            chnDestroySession();
            $isAuthenticated = false;
        } else {
            $accountUser = $row;
            if (chnGetLastAccountUserId() !== (int) $row['user_id']) {
                chnSetLastAccountCookie((int) $row['user_id']);
            }
        }
    }

    if (!$isAuthenticated) {
        $lastAcctId = chnGetLastAccountUserId();
        if ($lastAcctId !== null) {
            $stmt = $pdo->prepare(
                'SELECT u.user_id, u.name, u.surname, u.phone, u.birth_date, u.is_banned, p.full_address
                 FROM `user` u
                 LEFT JOIN `patient` p ON p.user_id = u.user_id
                 WHERE u.user_id = :user_id AND u.is_banned = 0
                 LIMIT 1'
            );
            $stmt->execute([':user_id' => $lastAcctId]);
            $row = $stmt->fetch();
            if ($row !== false) {
                $accountUser = $row;
                $lastAccountActive = true;
            }
        }
    }
} catch (Throwable $e) {
    chnLogError('Emergency call page load error: ' . $e->getMessage());
}

$accountAddress = '';
$prefillName = '';
$prefillPhone = '';
$prefillDob = '';

if (is_array($accountUser)) {
    $accountAddress = trim((string) ($accountUser['full_address'] ?? ''));
    $prefillName = trim((string) ($accountUser['name'] ?? '') . ' ' . (string) ($accountUser['surname'] ?? ''));
    $prefillPhone = (string) ($accountUser['phone'] ?? '');
    $prefillDob = (string) ($accountUser['birth_date'] ?? '');
}

if ($isAuthenticated) {
    $chnUserRole = (string) $role;
    $chnUserName = $prefillName !== '' ? $prefillName : t('emergency_call.user_name');
    $chnPageTitle = t('emergency_call.page_title');
}
?>
<!DOCTYPE html>
<html lang="<?= e(chnLang()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= te('emergency_call.page_title') ?> | CHN</title>
    <link rel="stylesheet" href="../css/style.css?v=<?= filemtime($chnProjectRoot . '/css/style.css') ?>">
    <link rel="stylesheet" href="../css/emergency.css?v=<?= filemtime($chnProjectRoot . '/css/emergency.css') ?>">
    <?php if ($isAuthenticated): ?>
    <script src="../js/sidebar.js?v=<?= filemtime($chnProjectRoot . '/js/sidebar.js') ?>" defer></script>
    <script src="../js/topbar.js?v=<?= filemtime($chnProjectRoot . '/js/topbar.js') ?>" defer></script>
    <?php endif; ?>
    <script src="../js/emergency.js?v=<?= filemtime($chnProjectRoot . '/js/emergency.js') ?>" defer></script>
</head>
<body>
<?php if ($isAuthenticated): ?>
    <?php require $chnProjectRoot . '/include/left-sidebar.php'; ?>
    <?php require $chnProjectRoot . '/include/top-bar.php'; ?>
<?php else: ?>
    <header class="emergency__brand">
        <div class="emergency__brand-inner">
            <a href="../index.php" class="emergency__brand-link" aria-label="CHN – Cyprus Health-Care Network">
                <img src="../assets/logos/chn-logo.jpeg" width="136" height="40" alt="CHN – Cyprus Health-Care Network">
            </a>
            <?php require $chnProjectRoot . '/include/auth-language.php'; ?>
        </div>
    </header>
<?php endif; ?>

<main id="main-content" tabindex="-1" class="content emergency<?= $isAuthenticated ? '' : ' emergency--guest' ?>"
      data-authenticated="<?= $isAuthenticated ? 'true' : 'false' ?>"
      data-account-address="<?= e($accountAddress) ?>"
      data-has-last-account="<?= $lastAccountActive ? 'true' : 'false' ?>"
      data-submit-url="../backend/create_emergency.php"
      data-clear-account-url="../backend/clear_last_account.php"
      data-messages="<?= chnJsMessages([
          'noAddress' => 'emergency_call.no_address',
          'useAddress' => 'emergency_call.use_address',
          'continue' => 'emergency_call.continue',
          'waitLocating' => 'emergency_call.wait_locating',
          'usesGps' => 'emergency_call.uses_gps',
          'usesAddress' => 'emergency_call.uses_address',
          'usesBoth' => 'emergency_call.uses_both',
          'usesGpsManual' => 'emergency_call.uses_gps_manual',
          'usesManualOnly' => 'emergency_call.uses_manual_only',
          'needLocation' => 'emergency_call.need_location',
          'finding' => 'emergency_call.finding',
          'denied' => 'emergency_call.denied',
          'unavailable' => 'emergency_call.unavailable',
          'timeout' => 'emergency_call.timeout',
          'coords' => 'emergency_call.coords',
          'gpsReady' => 'emergency_call.gps_ready',
          'cancelled' => 'emergency_call.cancelled',
          'locGps' => 'emergency_call.loc_gps',
          'locAddress' => 'emergency_call.loc_address',
          'locBoth' => 'emergency_call.loc_both',
          'locManual' => 'emergency_call.loc_manual',
          'locGpsWithNote' => 'emergency_call.loc_gps_with_note',
          'locSavedAddress' => 'emergency_call.loc_saved_address',
          'manualLabelOptional' => 'emergency_call.manual_label_optional',
          'manualLabelRequired' => 'emergency_call.manual_label_required',
          'manualHintOptional' => 'emergency_call.manual_hint_optional',
          'manualHintRequired' => 'emergency_call.manual_hint_required',
          'errName' => 'emergency_call.err_name',
          'errPhone' => 'emergency_call.err_phone',
          'errDob' => 'emergency_call.err_dob',
          'errAddress' => 'emergency_call.err_address',
          'errAddressRequired' => 'emergency_call.err_address_required',
          'errMatchRequired' => 'emergency_call.err_match_required',
          'errResponsibilityRequired' => 'emergency_call.err_responsibility_required',
          'clearedLastAccount' => 'emergency_call.cleared_last_account',
          'submitting' => 'emergency_call.submitting',
          'submitFailed' => 'emergency_call.submit_failed',
          'resultRef' => 'emergency_call.result_ref',
          'notSpecified' => 'emergency_call.not_specified',
      ]) ?>">
    <div class="emergency__inner">
        <!-- High-priority direct 112 call banner -->
        <section class="card emergency__call" aria-labelledby="emergency-call-title">
            <div class="emergency__call-content">
                <div class="emergency__call-icon" aria-hidden="true">
                    <img src="../assets/icons/phone.svg" alt="" width="20" height="20">
                </div>
                <div class="emergency__call-text">
                    <div class="emergency__call-badge"><?= te('emergency_call.critical_badge') ?></div>
                    <h2 id="emergency-call-title"><?= te('emergency_call.call_title') ?></h2>
                    <p><?= str_replace('{strong}', '<strong>' . te('emergency_call.call_text_strong') . '</strong>', te('emergency_call.call_text')) ?></p>
                </div>
            </div>
            <a class="btn btn--danger emergency__call-btn" href="tel:112">
                <img src="../assets/icons/phone.svg" alt="" width="20" height="20">
                <span><?= te('emergency_call.call_btn') ?></span>
            </a>
        </section>

        <!-- Reassuring heading -->
        <header class="emergency__heading">
            <div class="emergency__badge">
                <span class="emergency__beacon" aria-hidden="true"></span>
                <span><?= te('emergency_call.portal_badge') ?></span>
            </div>
            <h1><?= te('emergency_call.heading') ?></h1>
            <?php if ($isAuthenticated): ?>
                <p class="emergency__lead"><?= te('emergency_call.lead_auth') ?></p>
            <?php else: ?>
                <p class="emergency__lead"><?= te('emergency_call.lead_guest') ?></p>
            <?php endif; ?>
        </header>

        <!-- Step-by-step workflow -->
        <section class="card emergency__workflow" aria-labelledby="emergency-workflow-title">
            <div class="emergency__stepper" aria-label="<?= te('emergency_call.progress_label') ?>">
                <div class="emergency__step emergency__step--1">
                    <span class="emergency__step-num">1</span>
                    <span class="emergency__step-name"><?= te('emergency_call.step1') ?></span>
                </div>
                <div class="emergency__step-sep" aria-hidden="true"></div>
                <div class="emergency__step emergency__step--2">
                    <span class="emergency__step-num">2</span>
                    <span class="emergency__step-name"><?= te('emergency_call.step2') ?></span>
                </div>
                <div class="emergency__step-sep" aria-hidden="true"></div>
                <div class="emergency__step emergency__step--3">
                    <span class="emergency__step-num">3</span>
                    <span class="emergency__step-name"><?= te('emergency_call.step3') ?></span>
                </div>
            </div>

            <div class="emergency__notice-box">
                <img src="../assets/icons/file-medical.svg" alt="" width="20" height="20" class="emergency__notice-icon" aria-hidden="true">
                <p class="emergency__notice"><?= te('emergency_call.notice') ?></p>
            </div>

            <p id="emergency-status" class="emergency__status" role="status" aria-atomic="true"></p>
            <noscript><p class="emergency__noscript"><?= te('emergency_call.noscript') ?></p></noscript>

            <!-- Step 1: Initial start state -->
            <div id="emergency-start" class="emergency__panel" hidden>
                <div class="emergency__hero-icon" aria-hidden="true">
                    <img src="../assets/icons/geo-alt.svg" alt="" width="36" height="36">
                </div>
                <h2 id="emergency-workflow-title" class="emergency__start-title"><?= te('emergency_call.start_title') ?></h2>
                <p class="emergency__start-desc"><?= te('emergency_call.start_desc') ?></p>
                <button class="btn btn--secondary emergency__btn--large" type="button" id="emergency-initiate">
                    <img src="../assets/icons/geo-alt.svg" alt="" width="20" height="20" aria-hidden="true">
                    <span><?= te('emergency_call.start_btn') ?></span>
                </button>
            </div>

            <!-- Step 2: Location & details review state -->
            <div id="emergency-review" class="emergency__panel" hidden>
                <div class="emergency__panel-header">
                    <h3 id="emergency-review-title" tabindex="-1"><?= te('emergency_call.review_title') ?></h3>
                    <p class="emergency__panel-subtitle"><?= te('emergency_call.review_subtitle') ?></p>
                </div>

                <dl class="emergency__location">
                    <div class="emergency__loc-card emergency__loc-card--gps">
                        <dt class="emergency__loc-dt">
                            <span class="emergency__loc-icon" aria-hidden="true">
                                <img src="../assets/icons/geo-alt.svg" alt="" width="20" height="20">
                            </span>
                            <span><?= te('emergency_call.gps_label') ?></span>
                        </dt>
                        <dd id="emergency-gps" class="emergency__loc-dd"></dd>
                    </div>

                    <div class="emergency__loc-card emergency__loc-card--address">
                        <dt class="emergency__loc-dt">
                            <span class="emergency__loc-icon" aria-hidden="true">
                                <img src="../assets/icons/house-door.svg" alt="" width="20" height="20">
                            </span>
                            <span><?= te('emergency_call.address_label') ?></span>
                        </dt>
                        <dd id="emergency-address" class="emergency__loc-dd"></dd>
                    </div>
                </dl>

                <!-- Scenario 1: Match check when BOTH account address & GPS exist -->
                <fieldset id="emergency-match-group" class="emergency__subcard" hidden>
                    <legend class="emergency__subcard-title"><?= te('emergency_call.match_question') ?></legend>
                    <div class="emergency__radio-row">
                        <label class="emergency__radio-option">
                            <input type="radio" name="locations_match" value="yes" id="emergency-match-yes">
                            <span><?= te('emergency_call.match_yes') ?></span>
                        </label>
                        <label class="emergency__radio-option">
                            <input type="radio" name="locations_match" value="no" id="emergency-match-no">
                            <span><?= te('emergency_call.match_no') ?></span>
                        </label>
                    </div>
                </fieldset>

                <!-- Scenario 2: Responsibility confirmation when account address exists & GPS is inactive -->
                <div id="emergency-responsibility-group" class="emergency__subcard emergency__subcard--warning" hidden>
                    <label class="emergency__checkbox-option" for="emergency-responsibility">
                        <input type="checkbox" id="emergency-responsibility">
                        <span><?= te('emergency_call.responsibility_label') ?></span>
                    </label>
                </div>

                <!-- Caller / Contact Information -->
                <div class="emergency__subcard" id="emergency-caller-section">
                    <div class="emergency__subcard-header">
                        <h4 class="emergency__subcard-title">
                            <?= $isAuthenticated ? te('emergency_call.caller_title_auth') : te('emergency_call.caller_title_guest') ?>
                        </h4>
                        <?php if (!$isAuthenticated && $lastAccountActive): ?>
                        <button type="button" class="btn btn--outline emergency__clear-btn" id="emergency-clear-account">
                            <?= te('emergency_call.clear_last_account') ?>
                        </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($isAuthenticated): ?>
                    <dl class="emergency__caller-summary">
                        <div>
                            <dt><?= te('emergency_call.field_name') ?></dt>
                            <dd><?= e($prefillName !== '' ? $prefillName : t('emergency_call.not_specified')) ?></dd>
                        </div>
                        <div>
                            <dt><?= te('emergency_call.field_phone') ?></dt>
                            <dd><?= e($prefillPhone !== '' ? $prefillPhone : t('emergency_call.not_specified')) ?></dd>
                        </div>
                        <div>
                            <dt><?= te('emergency_call.field_dob') ?></dt>
                            <dd><?= e($prefillDob !== '' ? $prefillDob : t('emergency_call.not_specified')) ?></dd>
                        </div>
                    </dl>
                    <?php else: ?>
                    <p class="emergency__subcard-hint">
                        <?= $lastAccountActive ? te('emergency_call.caller_prefilled_note') : te('emergency_call.caller_subtitle_guest') ?>
                    </p>
                    <div class="emergency__grid">
                        <div class="emergency__field">
                            <label for="emergency-anon-name"><?= te('emergency_call.field_name') ?></label>
                            <input type="text" id="emergency-anon-name" maxlength="100" autocomplete="name"
                                   value="<?= e($prefillName) ?>" placeholder="<?= te('emergency_call.field_name_placeholder') ?>">
                            <p class="emergency__field-error" id="emergency-anon-name-error" role="alert"></p>
                        </div>
                        <div class="emergency__field">
                            <label for="emergency-anon-phone"><?= te('emergency_call.field_phone') ?></label>
                            <input type="tel" id="emergency-anon-phone" maxlength="16" autocomplete="tel"
                                   value="<?= e($prefillPhone) ?>" placeholder="<?= te('emergency_call.field_phone_placeholder') ?>">
                            <p class="emergency__field-error" id="emergency-anon-phone-error" role="alert"></p>
                        </div>
                        <div class="emergency__field">
                            <label for="emergency-anon-dob"><?= te('emergency_call.field_dob') ?></label>
                            <input type="date" id="emergency-anon-dob" min="1900-01-01" max="<?= e(date('Y-m-d')) ?>"
                                   value="<?= e($prefillDob) ?>">
                            <p class="emergency__field-error" id="emergency-anon-dob-error" role="alert"></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Optional Emergency / Case Type -->
                <div class="emergency__subcard">
                    <div class="emergency__field">
                        <label for="emergency-type"><?= te('emergency_call.type_label') ?></label>
                        <select id="emergency-type">
                            <option value=""><?= te('emergency_call.type_placeholder') ?></option>
                            <?php foreach (CHN_EMERGENCY_TYPES as $typeKey): ?>
                            <option value="<?= e($typeKey) ?>"><?= te('emergency_call.type_' . $typeKey) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Scenarios 1B, 3, 4: Optional or Required Manual / Detailed Location at bottom -->
                <div id="emergency-manual-group" class="emergency__subcard" hidden>
                    <div class="emergency__field">
                        <label for="emergency-manual-address" id="emergency-manual-label"><?= te('emergency_call.manual_label_optional') ?></label>
                        <p class="emergency__subcard-hint" id="emergency-manual-hint"></p>
                        <textarea id="emergency-manual-address" rows="3" maxlength="255" placeholder="<?= te('emergency_call.manual_placeholder') ?>"></textarea>
                        <p class="emergency__field-error" id="emergency-manual-error" role="alert"></p>
                    </div>
                </div>

                <div class="emergency__instructions-box">
                    <p id="emergency-instructions"></p>
                </div>

                <div class="emergency__actions">
                    <button class="btn btn--secondary emergency__btn--large" type="button" id="emergency-continue" disabled><?= te('emergency_call.continue') ?></button>
                    <button class="btn btn--outline emergency__btn--large" type="button" id="emergency-cancel"><?= te('emergency_call.cancel') ?></button>
                </div>
            </div>

            <!-- Step 3: Warning confirmation state -->
            <section id="emergency-warning" class="emergency__warning" aria-labelledby="emergency-warning-title" hidden>
                <div class="emergency__warning-heading">
                    <div class="emergency__warning-icon-box" aria-hidden="true">
                        <img src="../assets/icons/exclamation-triangle.svg" alt="" width="28" height="28">
                    </div>
                    <div>
                        <h3 id="emergency-warning-title" tabindex="-1" aria-describedby="emergency-warning-copy"><?= te('emergency_call.warning_title') ?></h3>
                        <p class="emergency__warning-sub"><?= te('emergency_call.warning_sub') ?></p>
                    </div>
                </div>

                <p id="emergency-warning-copy" class="emergency__warning-text">
                    <?= te('emergency_call.warning_text') ?>
                </p>

                <div class="emergency__warning-loc-box">
                    <span class="emergency__warning-loc-label"><?= te('emergency_call.warning_loc_label') ?></span>
                    <p id="emergency-warning-location" class="emergency__warning-loc-val"></p>
                    <span class="emergency__warning-loc-label emergency__warning-type-label"><?= te('emergency_call.warning_type_label') ?></span>
                    <p id="emergency-warning-type" class="emergency__warning-loc-val"></p>
                </div>

                <p id="emergency-submit-error" class="emergency__field-error" role="alert" hidden></p>

                <div class="emergency__actions">
                    <button class="btn btn--danger emergency__btn--large" type="button" id="emergency-confirm"><?= te('emergency_call.confirm_btn') ?></button>
                    <button class="btn btn--outline emergency__btn--large" type="button" id="emergency-warning-cancel"><?= te('emergency_call.warning_cancel') ?></button>
                </div>
            </section>

            <!-- Step 4: Result state -->
            <div id="emergency-result" class="emergency__panel emergency__result-panel" hidden>
                <div class="emergency__result-icon" aria-hidden="true">
                    <img src="../assets/icons/clipboard2-pulse.svg" alt="" width="36" height="36">
                </div>
                <h3 id="emergency-result-title" tabindex="-1"><?= te('emergency_call.result_title') ?></h3>
                <p id="emergency-result-ref" class="emergency__result-ref"></p>
                <p class="emergency__result-lead"><?= te('emergency_call.result_lead') ?></p>
                <div class="emergency__result-reminder">
                    <p><?= te('emergency_call.result_reminder') ?> <strong><?= te('emergency_call.result_reminder_strong') ?></strong></p>
                    <a class="btn btn--danger emergency__btn--large" href="tel:112">
                        <img src="../assets/icons/phone.svg" alt="" width="20" height="20">
                        <span><?= te('emergency_call.result_call') ?></span>
                    </a>
                </div>
                <button class="btn btn--outline emergency__btn--large" type="button" id="emergency-restart"><?= te('emergency_call.restart') ?></button>
            </div>
        </section>
    </div>
</main>
</body>
</html>
