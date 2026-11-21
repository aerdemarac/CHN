<?php
/**
 * CHN shared top bar component.
 * Usage from a root PHP page (typically right after left-sidebar.php):
 *     require __DIR__ . '/include/top-bar.php';
 *
 * Optional overrides before require:
 *     $chnPageTitle (string) - Custom page title displayed in the breadcrumb.
 *     $chnUserName  (string) - Display name when session data is unavailable.
 *     $chnUserRole  (string) - Role label (e.g., 'Patient', 'Doctor', 'Admin').
 *
 * IMPORTANT: UI labels are for display only. The backend must enforce authentication and roles.
 */
if (!isset($chnProjectRoot)) {
    $chnProjectRoot = dirname(__DIR__);
}

require_once __DIR__ . '/i18n.php';

if (!isset($chnBaseUrl)) {
    $chnDocumentRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $chnCanonicalRoot = realpath($chnProjectRoot);

    if ($chnDocumentRoot && $chnCanonicalRoot &&
        ($chnCanonicalRoot === $chnDocumentRoot ||
         str_starts_with($chnCanonicalRoot, $chnDocumentRoot . DIRECTORY_SEPARATOR))) {
        $chnRelativeRoot = substr($chnCanonicalRoot, strlen($chnDocumentRoot));
        $chnBaseUrl = '/' . trim(str_replace(DIRECTORY_SEPARATOR, '/', $chnRelativeRoot), '/') . '/';
        $chnBaseUrl = preg_replace('~/+~', '/', $chnBaseUrl);
    } else {
        $chnBaseUrl = '/CHN/';
    }
}

if (!isset($chnCurrentPage)) {
    $chnCurrentPage = basename(parse_url($_SERVER['SCRIPT_NAME'] ?? '/', PHP_URL_PATH) ?: '');
}

$chnPageTitles = [
    'index.php'           => 'Dashboard',
    'appointments.php'    => 'Appointments',
    'medical-records.php' => 'Medical Records',
    'medical-cases.php'   => 'Medical Cases',
    'feedback.php'        => 'Feedback',
    'profile.php'         => 'Profile',
    'account-settings.php' => 'Account Settings',
    'sidebar-test.php'         => 'Sidebar Preview',
    'top-and-sidebar-test.php' => 'Top Bar & UI Preview',
];

$chnActiveTitle = (isset($chnPageTitle) && trim((string) $chnPageTitle) !== '')
    ? trim((string) $chnPageTitle)
    : ($chnPageTitles[$chnCurrentPage] ?? 'Portal');

$chnHasHomePage = is_file($chnProjectRoot . '/index.php');
$chnHasLogoutEndpoint = is_file($chnProjectRoot . '/backend/logout.php');
$chnAccountSettingsFile = is_file($chnProjectRoot . '/pages/account-settings.php')
    ? 'pages/account-settings.php' : null;

$chnSessionActive = session_status() === PHP_SESSION_ACTIVE;

$chnResolvedName = (isset($chnUserName) && trim((string) $chnUserName) !== '')
    ? trim((string) $chnUserName)
    : ($chnSessionActive
        ? ($_SESSION['user_name'] ?? $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Patient User')
        : 'Patient User');

$chnResolvedRole = (isset($chnUserRole) && trim((string) $chnUserRole) !== '')
    ? trim((string) $chnUserRole)
    : ($chnSessionActive
        ? ($_SESSION['user_role'] ?? $_SESSION['role'] ?? 'Patient')
        : 'Patient');

$chnAccountLinkRole = str_replace([' ', '-'], '_', strtolower(trim((string) $chnResolvedRole)));
foreach (['patient', 'doctor', 'healthcare_staff', 'admin'] as $chnRoleKey) {
    if ($chnAccountLinkRole === $chnRoleKey || (string) $chnResolvedRole === t('role.' . $chnRoleKey)) {
        $chnAccountLinkRole = $chnRoleKey;
        $chnResolvedRole = t('role.' . $chnRoleKey);
        break;
    }
}

$chnNameParts = preg_split('/\s+/u', trim((string) $chnResolvedName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$chnInitials = '';
foreach ($chnNameParts as $chnPart) {
    if (mb_strlen($chnInitials, 'UTF-8') >= 2) {
        break;
    }
    if (preg_match('/\p{L}/u', $chnPart, $chnLetter)) {
        $chnInitials .= mb_strtoupper($chnLetter[0], 'UTF-8');
    }
}
if ($chnInitials === '') {
    $chnInitials = 'CH';
}

$chnEscapedTitle = htmlspecialchars($chnActiveTitle, ENT_QUOTES, 'UTF-8');
$chnEscapedName = htmlspecialchars((string) $chnResolvedName, ENT_QUOTES, 'UTF-8');
$chnEscapedRole = htmlspecialchars((string) $chnResolvedRole, ENT_QUOTES, 'UTF-8');
$chnEscapedInitials = htmlspecialchars($chnInitials, ENT_QUOTES, 'UTF-8');

// Optional notifications from a trusted backend (no database required for UI preview).
// Example array item: ['title' => 'Appointment update', 'message' => '...', 'time' => 'Today', 'read' => false]
// Do not place patient-sensitive details in notification previews.
$chnNotifications = is_array($chnNotifications ?? null)
    ? array_values(array_filter($chnNotifications, 'is_array'))
    : [];
$chnUnreadNotifications = count(array_filter(
    $chnNotifications,
    static fn(array $notice): bool => empty($notice['read'])
));

$chnLanguages = CHN_LANGUAGES;
$chnActiveLang = chnLang();
?>
<style id="chn-topbar-mobile-single-row-v7">
/* Only mobile: desktop Cyprus Healthcare Network heading remains visible.
   Component-local fallback also works if style.css is cached by the browser. */
@media screen and (max-width: 767px) {
    .topbar > .topbar__brand {
        display: none !important;
    }
    .topbar {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        flex-wrap: nowrap !important;
        min-height: 68px !important;
        padding: 12px 106px 12px 8px !important;
    }
    .topbar > .topbar__actions {
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        flex-wrap: nowrap !important;
        flex: 0 0 auto !important;
        width: max-content !important;
        max-width: none !important;
        gap: 4px !important;
        margin-left: 0 !important;
    }
    .topbar__user, .topbar__user:hover {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
    }
    .topbar__user .topbar__caret,
    .topbar__user .topbar__user-info {
        display: none !important;
    }
}
</style>
<header class="topbar" aria-label="<?= te('topbar.label') ?>">
    <div class="topbar__brand">Cyprus Healthcare Network</div>
    <div class="topbar__actions">
        <a class="btn btn--danger btn--sm topbar__emergency" href="<?= htmlspecialchars($chnBaseUrl . 'pages/emergency-call.php?' . http_build_query(['lang' => $chnActiveLang]), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= te('topbar.emergency_call') ?>">
            <span class="icon icon--phone" aria-hidden="true"></span>
            <span class="topbar__emergency-label"><?= te('common.emergency') ?></span>
            <span class="topbar__emergency-number">112</span>
        </a>

        <div class="topbar__dropdown topbar__notification-dropdown" id="chn-notification-dropdown" data-dropdown>
            <button type="button" class="btn btn--outline btn--sm topbar__notification-toggle"
                    id="chn-notification-toggle" data-dropdown-toggle
                    aria-expanded="false"
                    aria-controls="chn-notification-menu" aria-label="<?= htmlspecialchars($chnUnreadNotifications ? tn('topbar.notifications_unread', $chnUnreadNotifications) : t('topbar.notifications'), ENT_QUOTES, 'UTF-8') ?>">

                <img class="topbar__bell-image"
                     src="<?= htmlspecialchars($chnBaseUrl . 'assets/icons/bell.svg', ENT_QUOTES, 'UTF-8') ?>"
                     alt="" width="20" height="20" aria-hidden="true">
                <?php if ($chnUnreadNotifications > 0): ?>
                    <span class="topbar__notification-count" aria-hidden="true"><?= $chnUnreadNotifications > 99 ? '99+' : $chnUnreadNotifications ?></span>
                <?php endif; ?>
            </button>
            <div class="topbar__menu topbar__menu--notifications" id="chn-notification-menu"
                 data-dropdown-menu role="region" aria-label="<?= te('topbar.notifications') ?>" hidden>
                <div class="topbar__notifications-title"><?= te('topbar.notifications') ?></div>
                <?php if ($chnNotifications === []): ?>
                    <p class="topbar__notifications-empty"><?= te('topbar.notifications_empty') ?></p>
                <?php else: ?>
                    <div class="topbar__notification-list">
                        <?php foreach ($chnNotifications as $chnNotification): ?>
                            <div class="topbar__notification-item<?= empty($chnNotification['read']) ? ' topbar__notification-item--unread' : '' ?>">
                                <div class="topbar__notification-heading">
                                    <strong><?= htmlspecialchars((string)($chnNotification['title'] ?? t('topbar.notification')), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if (empty($chnNotification['read'])): ?>
                                        <span class="topbar__notification-dot" aria-label="<?= te('topbar.unread') ?>"></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($chnNotification['message'])): ?>
                                    <p><?= htmlspecialchars((string)$chnNotification['message'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <?php if (!empty($chnNotification['time'])): ?>
                                    <small><?= htmlspecialchars((string)$chnNotification['time'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="topbar__dropdown" id="chn-lang-dropdown" data-dropdown>
            <button type="button" class="btn btn--outline btn--sm topbar__lang-toggle"
                    id="chn-lang-toggle" data-dropdown-toggle
                    aria-haspopup="true" aria-expanded="false"
                    aria-controls="chn-lang-menu" aria-label="<?= te('lang.change') ?>">
                <span data-lang-current><?= htmlspecialchars($chnLanguages[$chnActiveLang]['code'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="topbar__caret" aria-hidden="true"></span>
            </button>
            <div class="topbar__menu" id="chn-lang-menu" data-dropdown-menu role="menu" hidden>
                <?php foreach ($chnLanguages as $chnLangKey => $chnLangItem): ?>
                    <?php $chnIsSelected = $chnLangKey === $chnActiveLang; ?>
                    <a class="topbar__menu-item topbar__lang-option<?= $chnIsSelected ? ' is-active' : '' ?>"
                       role="menuitemradio"
                       aria-checked="<?= $chnIsSelected ? 'true' : 'false' ?>"
                       href="<?= htmlspecialchars(chnLangUrl($chnLangKey), ENT_QUOTES, 'UTF-8') ?>"
                       hreflang="<?= htmlspecialchars($chnLangKey, ENT_QUOTES, 'UTF-8') ?>"
                       lang="<?= htmlspecialchars($chnLangKey, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="topbar__lang-option-code"><?= htmlspecialchars($chnLangItem['code'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span><?= htmlspecialchars($chnLangItem['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="topbar__dropdown" id="chn-user-dropdown" data-dropdown>
            <button type="button" class="topbar__user"
                    id="chn-user-toggle" data-dropdown-toggle
                    aria-haspopup="true" aria-expanded="false"
                    aria-controls="chn-user-menu"
                    aria-label="<?= te('topbar.account_menu', ['name' => (string) $chnResolvedName]) ?>">
                <span class="topbar__avatar" aria-hidden="true"><?= $chnEscapedInitials ?></span>
                <span class="topbar__user-info">
                    <span class="topbar__user-name"><?= $chnEscapedName ?></span>
                    <span class="topbar__user-role"><?= $chnEscapedRole ?></span>
                </span>
                <span class="topbar__caret" aria-hidden="true"></span>
            </button>
            <div class="topbar__menu topbar__menu--user" id="chn-user-menu" data-dropdown-menu role="menu" hidden>
                <?php if ($chnAccountSettingsFile !== null): ?>
                    <a class="topbar__menu-item" role="menuitem"
                       href="<?= htmlspecialchars($chnBaseUrl . implode('/', array_map('rawurlencode', explode('/', $chnAccountSettingsFile)))
                           . (in_array($chnAccountLinkRole, ['patient', 'doctor', 'healthcare_staff', 'admin'], true)
                               ? '?role=' . rawurlencode($chnAccountLinkRole) : ''), ENT_QUOTES, 'UTF-8') ?>">
                        <span><?= te('topbar.account_settings') ?></span>
                    </a>
                <?php else: ?>
                    <span class="topbar__menu-item topbar__menu-item--disabled" role="menuitem"
                          aria-disabled="true" aria-label="<?= te('common.under_development', ['label' => t('topbar.account_settings')]) ?>">
                        <span><?= te('topbar.account_settings') ?></span>
                        <span class="topbar__menu-status" aria-hidden="true"><?= te('common.soon') ?></span>
                    </span>
                <?php endif; ?>

                <div class="topbar__menu-divider" role="separator"></div>

                <?php if ($chnHasLogoutEndpoint): ?>
                    <a class="topbar__menu-item topbar__menu-item--danger" role="menuitem"
                       href="<?= htmlspecialchars(isset($chnPrototypeRole)
                           ? chnPrototypeUrl($chnBaseUrl . 'backend/logout.php', $chnPrototypeRole)
                           : $chnBaseUrl . 'backend/logout.php', ENT_QUOTES, 'UTF-8') ?>">
                        <span><?= te('topbar.log_out') ?></span>
                    </a>
                <?php else: ?>
                    <button type="button" class="topbar__menu-item topbar__menu-item--danger"
                            role="menuitem" disabled aria-label="<?= te('common.under_development', ['label' => t('topbar.log_out')]) ?>">
                        <span><?= te('topbar.log_out') ?></span>
                        <span class="topbar__menu-status" aria-hidden="true"><?= te('common.soon') ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
