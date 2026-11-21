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
        <img src="../assets/logos/chn-logo.png" width="136" height="40" alt="CHN – Cyprus Health-Care Network">
    </header>
<?php endif; ?>

<main id="main-content" tabindex="-1" class="content emergency<?= $isAuthenticated ? '' : ' emergency--guest' ?>"
      data-authenticated="<?= $isAuthenticated ? 'true' : 'false' ?>"
      <?php if ($hasAddressCache): ?>
      data-address-scope="<?= $cacheScope ?>" data-address-digest="<?= $addressDigest ?>"
      <?php endif; ?>>
    <div class="emergency__inner">
        <header class="emergency__heading">
            <h1>Emergency Request</h1>
            <?php if ($isAuthenticated): ?>
                <p>You’re signed in to CHN. Review the location you would share with your request.</p>
            <?php else: ?>
                <p>You can start a request without signing in.</p>
            <?php endif; ?>
        </header>

        <section class="card emergency__call" aria-labelledby="emergency-call-title">
            <div>
                <h2 id="emergency-call-title">Need immediate help?</h2>
                <p>Call 112 to reach emergency services directly.</p>
            </div>
            <a class="btn btn--danger" href="tel:112">
                <img src="../assets/icons/phone.svg" alt="" width="20" height="20">
                Call 112
            </a>
        </section>

        <section class="card emergency__workflow" aria-labelledby="emergency-workflow-title">
            <h2 id="emergency-workflow-title">Request preview</h2>
            <p class="emergency__notice">This preview cannot send an emergency request.</p>
            <p id="emergency-status" role="status" aria-atomic="true"></p>
            <noscript><p>Enable JavaScript to review your location and use the request preview.</p></noscript>

            <div id="emergency-start" hidden>
                <p>Start by allowing your browser to find your current location.</p>
                <button class="btn btn--secondary" type="button" id="emergency-initiate">Start request</button>
            </div>

            <div id="emergency-review" hidden>
                <h3 id="emergency-review-title" tabindex="-1">Review your location</h3>
                <dl class="emergency__location">
                    <div>
                        <dt>Current GPS Location</dt>
                        <dd id="emergency-gps"></dd>
                    </div>
                    <div>
                        <dt>Saved Full Address</dt>
                        <dd id="emergency-address"></dd>
                    </div>
                </dl>
                <details id="emergency-unlock" hidden>
                    <summary>Unlock a saved address</summary>
                    <p>Use the passphrase you chose when you enabled access after signing out.</p>
                    <form id="emergency-unlock-form">
                        <label for="emergency-unlock-passphrase">Address passphrase</label>
                        <input id="emergency-unlock-passphrase" type="password" autocomplete="off" required maxlength="128">
                        <button class="btn btn--outline" type="submit">Unlock address</button>
                    </form>
                </details>
                <details id="emergency-enable" hidden>
                    <summary>Make this address available after signing out</summary>
                    <p>This saves an encrypted copy in this browser and replaces any previous copy. Anyone with the passphrase can unlock it. Choose a separate passphrase and enable this only on a device you trust.</p>
                    <form id="emergency-enable-form">
                        <label for="emergency-enable-passphrase">New address passphrase (at least 12 characters)</label>
                        <input id="emergency-enable-passphrase" type="password" autocomplete="new-password" required minlength="12" maxlength="128">
                        <button class="btn btn--outline" type="submit">Save encrypted address</button>
                    </form>
                </details>
                <p id="emergency-instructions"></p>
                <div class="emergency__actions">
                    <button class="btn btn--secondary" type="button" id="emergency-continue" disabled>Continue to confirmation</button>
                    <button class="btn btn--outline" type="button" id="emergency-cancel">Cancel</button>
                </div>
            </div>

            <section id="emergency-warning" class="emergency__warning" aria-labelledby="emergency-warning-title" hidden>
                <div class="emergency__warning-heading">
                    <img src="../assets/icons/exclamation-triangle.svg" alt="" width="32" height="32">
                    <h3 id="emergency-warning-title" tabindex="-1" aria-describedby="emergency-warning-copy">Are You Sure?</h3>
                </div>
                <p id="emergency-warning-copy">Intentionally false emergency reports may be flagged, reviewed by administrators, and result in account restrictions or bans.</p>
                <p id="emergency-warning-location"></p>
                <div class="emergency__actions">
                    <button class="btn btn--secondary" type="button" id="emergency-confirm">Confirm request preview</button>
                    <button class="btn btn--outline" type="button" id="emergency-warning-cancel">Cancel request</button>
                </div>
            </section>

            <div id="emergency-result" hidden>
                <h3 id="emergency-result-title" tabindex="-1">Preview confirmed</h3>
                <p>No emergency request was sent. Call 112 if you need immediate help.</p>
                <button class="btn btn--outline" type="button" id="emergency-restart">Start a new preview</button>
            </div>
        </section>
    </div>
</main>
</body>
</html>
