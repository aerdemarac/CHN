<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/i18n.php';

// Read the future shared login session without creating a guest session.
if (session_status() === PHP_SESSION_NONE && isset($_COOKIE[session_name()])) {
    session_start(['read_and_close' => true, 'use_strict_mode' => true]);
}
header('Cache-Control: no-store');

$userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$role = $_SESSION['user_role'] ?? null;
$lastActivity = $_SESSION['last_activity'] ?? null;
$now = time();
$isAuthenticated = $userId !== false
    && in_array($role, ['patient', 'doctor', 'healthcare_staff', 'admin'], true)
    && is_int($lastActivity) && $lastActivity <= $now && $lastActivity > $now - 1800;

// The shared backend must set this only for a verified, complete address and after
// explicit permission to cache it on this device. The scope is an opaque account key.
$addressCache = $isAuthenticated ? ($_SESSION['emergency_address_cache'] ?? null) : null;
$cacheScope = is_array($addressCache) ? ($addressCache['scope'] ?? null) : null;
$addressDigest = is_array($addressCache) ? ($addressCache['sha256'] ?? null) : null;
$hasAddressCache = is_string($cacheScope) && preg_match('/\A[a-f0-9]{32}\z/', $cacheScope)
    && is_string($addressDigest) && preg_match('/\A[a-f0-9]{64}\z/', $addressDigest);

if ($isAuthenticated) {
    $chnUserRole = $role;
    $chnUserName = t('emergency_call.user_name');
    $chnPageTitle = t('emergency_call.page_title');
}
?>
<!DOCTYPE html>
<html lang="<?= chnLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= te('emergency_call.page_title') ?> | CHN</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/emergency.css">
    <?php if ($isAuthenticated): ?>
    <script src="../js/sidebar.js" defer></script>
    <script src="../js/topbar.js" defer></script>
    <?php endif; ?>
    <script src="../js/emergency.js" defer></script>
</head>
<body>
<?php if ($isAuthenticated): ?>
    <?php require dirname(__DIR__) . '/include/left-sidebar.php'; ?>
    <?php require dirname(__DIR__) . '/include/top-bar.php'; ?>
<?php else: ?>
    <header class="emergency__brand">
        <div class="emergency__brand-inner">
            <img src="../assets/logos/chn-logo.png" width="136" height="40" alt="CHN – Cyprus Health-Care Network">
            <?php require dirname(__DIR__) . '/include/auth-language.php'; ?>
        </div>
    </header>
<?php endif; ?>

<main id="main-content" tabindex="-1" class="content emergency<?= $isAuthenticated ? '' : ' emergency--guest' ?>"
      data-authenticated="<?= $isAuthenticated ? 'true' : 'false' ?>"
      data-messages="<?= chnJsMessages(['noAddress' => 'emergency_call.no_address', 'addressLocked' => 'emergency_call.address_locked', 'useAddress' => 'emergency_call.use_address', 'continue' => 'emergency_call.continue', 'waitLocating' => 'emergency_call.wait_locating', 'usesGps' => 'emergency_call.uses_gps', 'usesAddress' => 'emergency_call.uses_address', 'needLocation' => 'emergency_call.need_location', 'finding' => 'emergency_call.finding', 'denied' => 'emergency_call.denied', 'unavailable' => 'emergency_call.unavailable', 'timeout' => 'emergency_call.timeout', 'coords' => 'emergency_call.coords', 'gpsReady' => 'emergency_call.gps_ready', 'unlocked' => 'emergency_call.unlocked', 'saved' => 'emergency_call.saved', 'unlockFailed' => 'emergency_call.unlock_failed', 'saveFailed' => 'emergency_call.save_failed', 'cancelled' => 'emergency_call.cancelled', 'locGps' => 'emergency_call.loc_gps', 'locAddress' => 'emergency_call.loc_address']) ?>"
      <?php if ($hasAddressCache): ?>
      data-address-scope="<?= $cacheScope ?>" data-address-digest="<?= $addressDigest ?>"
      <?php endif; ?>>
    <div class="emergency__inner">
        <!-- High-priority direct 112 call banner (tight & stacked) -->
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

        <!-- Step-by-step stress-free workflow -->
        <section class="card emergency__workflow" aria-labelledby="emergency-workflow-title">
            <!-- 3-step visual tracker -->
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

            <!-- Preview notice banner -->
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

            <!-- Step 2: Location review state -->
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

                <details id="emergency-unlock" class="emergency__details-card" hidden>
                    <summary class="emergency__summary">
                        <span><?= te('emergency_call.unlock_summary') ?></span>
                    </summary>
                    <div class="emergency__details-body">
                        <p><?= te('emergency_call.unlock_text') ?></p>
                        <form id="emergency-unlock-form" class="emergency__form">
                            <label for="emergency-unlock-passphrase"><?= te('emergency_call.unlock_label') ?></label>
                            <div class="emergency__form-row">
                                <input id="emergency-unlock-passphrase" type="password" autocomplete="off" required maxlength="128" placeholder="<?= te('emergency_call.unlock_placeholder') ?>">
                                <button class="btn btn--outline" type="submit"><?= te('emergency_call.unlock_btn') ?></button>
                            </div>
                        </form>
                    </div>
                </details>

                <details id="emergency-enable" class="emergency__details-card" hidden>
                    <summary class="emergency__summary">
                        <span><?= te('emergency_call.enable_summary') ?></span>
                    </summary>
                    <div class="emergency__details-body">
                        <p><?= te('emergency_call.enable_text') ?></p>
                        <form id="emergency-enable-form" class="emergency__form">
                            <label for="emergency-enable-passphrase"><?= te('emergency_call.enable_label') ?></label>
                            <div class="emergency__form-row">
                                <input id="emergency-enable-passphrase" type="password" autocomplete="new-password" required minlength="12" maxlength="128" placeholder="<?= te('emergency_call.enable_placeholder') ?>">
                                <button class="btn btn--outline" type="submit"><?= te('emergency_call.enable_btn') ?></button>
                            </div>
                        </form>
                    </div>
                </details>

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
                </div>

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
