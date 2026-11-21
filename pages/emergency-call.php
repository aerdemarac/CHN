<?php
declare(strict_types=1);

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
    $chnUserName = 'CHN account';
    $chnPageTitle = 'Emergency Request';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Emergency Request | CHN</title>
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
            <a class="emergency__brand-call" href="tel:112" aria-label="Direct Emergency Call 112">
                <img src="../assets/icons/phone.svg" alt="" width="16" height="16">
                <span>Direct Dial: <strong>112</strong></span>
            </a>
        </div>
    </header>
<?php endif; ?>

<main id="main-content" tabindex="-1" class="content emergency<?= $isAuthenticated ? '' : ' emergency--guest' ?>"
      data-authenticated="<?= $isAuthenticated ? 'true' : 'false' ?>"
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
                    <div class="emergency__call-badge">CRITICAL LIFE-SAFETY</div>
                    <h2 id="emergency-call-title">Need Immediate Life-Saving Help?</h2>
                    <p>For life-threatening injury, cardiac arrest, or sudden collapse, <strong>call 112 directly now</strong>.</p>
                </div>
            </div>
            <a class="btn btn--danger emergency__call-btn" href="tel:112">
                <img src="../assets/icons/phone.svg" alt="" width="20" height="20">
                <span>CALL 112 NOW</span>
            </a>
        </section>

        <!-- Reassuring heading -->
        <header class="emergency__heading">
            <div class="emergency__badge">
                <span class="emergency__beacon" aria-hidden="true"></span>
                <span>Emergency Response Portal</span>
            </div>
            <h1>Emergency Assistance</h1>
            <?php if ($isAuthenticated): ?>
                <p class="emergency__lead">You are signed in to CHN. Review and verify your location before dispatching assistance.</p>
            <?php else: ?>
                <p class="emergency__lead">No sign-in required. Quickly share your location to get emergency response coordinates.</p>
            <?php endif; ?>
        </header>

        <!-- Step-by-step stress-free workflow -->
        <section class="card emergency__workflow" aria-labelledby="emergency-workflow-title">
            <!-- 3-step visual tracker -->
            <div class="emergency__stepper" aria-label="Emergency request progress">
                <div class="emergency__step emergency__step--1">
                    <span class="emergency__step-num">1</span>
                    <span class="emergency__step-name">Location Discovery</span>
                </div>
                <div class="emergency__step-sep" aria-hidden="true"></div>
                <div class="emergency__step emergency__step--2">
                    <span class="emergency__step-num">2</span>
                    <span class="emergency__step-name">Review &amp; Verify</span>
                </div>
                <div class="emergency__step-sep" aria-hidden="true"></div>
                <div class="emergency__step emergency__step--3">
                    <span class="emergency__step-num">3</span>
                    <span class="emergency__step-name">Confirmation</span>
                </div>
            </div>

            <!-- Preview notice banner -->
            <div class="emergency__notice-box">
                <img src="../assets/icons/file-medical.svg" alt="" width="20" height="20" class="emergency__notice-icon" aria-hidden="true">
                <p class="emergency__notice">Request Preview Mode: Demonstrates location resolution without dispatching real emergency crews.</p>
            </div>

            <p id="emergency-status" class="emergency__status" role="status" aria-atomic="true"></p>
            <noscript><p class="emergency__noscript">Please enable JavaScript in your browser to detect your location and use the emergency preview.</p></noscript>

            <!-- Step 1: Initial start state -->
            <div id="emergency-start" class="emergency__panel" hidden>
                <div class="emergency__hero-icon" aria-hidden="true">
                    <img src="../assets/icons/geo-alt.svg" alt="" width="36" height="36">
                </div>
                <h2 id="emergency-workflow-title" class="emergency__start-title">Acquire Your Emergency Location</h2>
                <p class="emergency__start-desc">Allow your browser to detect your current GPS coordinates. Accurate positioning ensures emergency services can find you immediately.</p>
                <button class="btn btn--secondary emergency__btn--large" type="button" id="emergency-initiate">
                    <img src="../assets/icons/geo-alt.svg" alt="" width="20" height="20" aria-hidden="true">
                    <span>Start Location Detection</span>
                </button>
            </div>

            <!-- Step 2: Location review state -->
            <div id="emergency-review" class="emergency__panel" hidden>
                <div class="emergency__panel-header">
                    <h3 id="emergency-review-title" tabindex="-1">Review Your Location</h3>
                    <p class="emergency__panel-subtitle">Please verify your detected coordinates or saved address below.</p>
                </div>

                <dl class="emergency__location">
                    <div class="emergency__loc-card emergency__loc-card--gps">
                        <dt class="emergency__loc-dt">
                            <span class="emergency__loc-icon" aria-hidden="true">
                                <img src="../assets/icons/geo-alt.svg" alt="" width="20" height="20">
                            </span>
                            <span>Current GPS Location</span>
                        </dt>
                        <dd id="emergency-gps" class="emergency__loc-dd"></dd>
                    </div>

                    <div class="emergency__loc-card emergency__loc-card--address">
                        <dt class="emergency__loc-dt">
                            <span class="emergency__loc-icon" aria-hidden="true">
                                <img src="../assets/icons/house-door.svg" alt="" width="20" height="20">
                            </span>
                            <span>Last Active Account's Address</span>
                        </dt>
                        <dd id="emergency-address" class="emergency__loc-dd"></dd>
                    </div>
                </dl>

                <details id="emergency-unlock" class="emergency__details-card" hidden>
                    <summary class="emergency__summary">
                        <span>Unlock saved address with passphrase</span>
                    </summary>
                    <div class="emergency__details-body">
                        <p>Use the passphrase you chose when you saved an encrypted copy on this device.</p>
                        <form id="emergency-unlock-form" class="emergency__form">
                            <label for="emergency-unlock-passphrase">Address passphrase</label>
                            <div class="emergency__form-row">
                                <input id="emergency-unlock-passphrase" type="password" autocomplete="off" required maxlength="128" placeholder="Enter your passphrase">
                                <button class="btn btn--outline" type="submit">Unlock Address</button>
                            </div>
                        </form>
                    </div>
                </details>

                <details id="emergency-enable" class="emergency__details-card" hidden>
                    <summary class="emergency__summary">
                        <span>Make this address available after signing out</span>
                    </summary>
                    <div class="emergency__details-body">
                        <p>Saves an encrypted copy in this browser so you can access it during future emergencies without signing in. Choose a separate passphrase of at least 12 characters.</p>
                        <form id="emergency-enable-form" class="emergency__form">
                            <label for="emergency-enable-passphrase">New address passphrase (at least 12 characters)</label>
                            <div class="emergency__form-row">
                                <input id="emergency-enable-passphrase" type="password" autocomplete="new-password" required minlength="12" maxlength="128" placeholder="Create a secure passphrase">
                                <button class="btn btn--outline" type="submit">Save Encrypted Address</button>
                            </div>
                        </form>
                    </div>
                </details>

                <div class="emergency__instructions-box">
                    <p id="emergency-instructions"></p>
                </div>

                <div class="emergency__actions">
                    <button class="btn btn--secondary emergency__btn--large" type="button" id="emergency-continue" disabled>Continue to Confirmation</button>
                    <button class="btn btn--outline emergency__btn--large" type="button" id="emergency-cancel">Cancel</button>
                </div>
            </div>

            <!-- Step 3: Warning confirmation state -->
            <section id="emergency-warning" class="emergency__warning" aria-labelledby="emergency-warning-title" hidden>
                <div class="emergency__warning-heading">
                    <div class="emergency__warning-icon-box" aria-hidden="true">
                        <img src="../assets/icons/exclamation-triangle.svg" alt="" width="28" height="28">
                    </div>
                    <div>
                        <h3 id="emergency-warning-title" tabindex="-1" aria-describedby="emergency-warning-copy">Confirm Emergency Request</h3>
                        <p class="emergency__warning-sub">Verify carefully before confirming</p>
                    </div>
                </div>

                <p id="emergency-warning-copy" class="emergency__warning-text">
                    Intentionally false emergency reports may be flagged, reviewed by administrators, and result in account restrictions, severe fines, or legal prosecution.
                </p>

                <div class="emergency__warning-loc-box">
                    <span class="emergency__warning-loc-label">Location For Dispatch:</span>
                    <p id="emergency-warning-location" class="emergency__warning-loc-val"></p>
                </div>

                <div class="emergency__actions">
                    <button class="btn btn--danger emergency__btn--large" type="button" id="emergency-confirm">Confirm Request Preview</button>
                    <button class="btn btn--outline emergency__btn--large" type="button" id="emergency-warning-cancel">Cancel Request</button>
                </div>
            </section>

            <!-- Step 4: Result state -->
            <div id="emergency-result" class="emergency__panel emergency__result-panel" hidden>
                <div class="emergency__result-icon" aria-hidden="true">
                    <img src="../assets/icons/clipboard2-pulse.svg" alt="" width="36" height="36">
                </div>
                <h3 id="emergency-result-title" tabindex="-1">Preview Confirmed</h3>
                <p class="emergency__result-lead">This completed the emergency request workflow demonstration.</p>
                <div class="emergency__result-reminder">
                    <p>No emergency request was transmitted to live first responders. <strong>If you need real emergency assistance, please call 112 directly.</strong></p>
                    <a class="btn btn--danger emergency__btn--large" href="tel:112">
                        <img src="../assets/icons/phone.svg" alt="" width="20" height="20">
                        <span>Call 112 Now</span>
                    </a>
                </div>
                <button class="btn btn--outline emergency__btn--large" type="button" id="emergency-restart">Start a New Preview</button>
            </div>
        </section>
    </div>
</main>
</body>
</html>
