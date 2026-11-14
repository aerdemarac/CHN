<?php
/**
 * CHN shared sidebar component.
 * Usage from a root PHP page: require __DIR__ . '/include/left-sidebar.php';
 * Optional demo-only link: $chnSidebarShowTestLink = true; (before require)
 * Set $chnUserRole using a verified backend value before requiring this file.
 * IMPORTANT: Hiding links is NOT authorization. The backend must enforce roles.
 */
$chnProjectRoot = dirname(__DIR__);
$chnDocumentRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$chnCanonicalRoot = realpath($chnProjectRoot);

// Derive the app URL from XAMPP document root rather than assuming /CHN/.
if ($chnDocumentRoot && $chnCanonicalRoot &&
    ($chnCanonicalRoot === $chnDocumentRoot ||
     str_starts_with($chnCanonicalRoot, $chnDocumentRoot . DIRECTORY_SEPARATOR))) {
    $chnRelativeRoot = substr($chnCanonicalRoot, strlen($chnDocumentRoot));
    $chnBaseUrl = '/' . trim(str_replace(DIRECTORY_SEPARATOR, '/', $chnRelativeRoot), '/') . '/';
    $chnBaseUrl = preg_replace('~/+~', '/', $chnBaseUrl);
} else {
    $chnBaseUrl = '/CHN/';
}

$chnCurrentPage = basename(parse_url($_SERVER['SCRIPT_NAME'] ?? '/', PHP_URL_PATH) ?: '');

// ROLE-BASED CHANGE: Keep menu definitions in one manager file.
require_once __DIR__ . '/sidebar-menu-manager.php';
$chnSidebarItems = chnSidebarItemsForRole((string)($chnUserRole ?? 'guest'));

// Only the local preview page opts into this extra menu item.
if (!empty($chnSidebarShowTestLink)) {
    $chnPreviewFile = is_file($chnProjectRoot . '/test-endpoints/top-and-sidebar-test.php')
        ? 'test-endpoints/top-and-sidebar-test.php'
        : (is_file($chnProjectRoot . '/sidebar-test.php') ? 'sidebar-test.php' : 'test-endpoints/top-and-sidebar-test.php');
    array_unshift($chnSidebarItems,
        ['label' => 'UI Preview', 'file' => $chnPreviewFile, 'icon' => 'preview']);
}
?>
<a class="chn-skip-link" href="#main-content">Skip to main content</a>

<button type="button" class="sidebar__toggle" id="sidebar-menu-toggle"
        aria-controls="chn-sidebar" aria-expanded="false"
        aria-label="Open navigation menu">
    <span class="sidebar__toggle-icon sidebar__toggle-icon--menu" aria-hidden="true"></span>
    <span class="sidebar__toggle-label">Menu</span>
</button>

<div class="sidebar__backdrop" id="chn-sidebar-backdrop" aria-hidden="true"></div>

<aside class="sidebar" id="chn-sidebar" aria-label="Main navigation">
    <div class="sidebar__brand">
        <img src="<?= htmlspecialchars($chnBaseUrl . 'assets/logos/chn-logo.png', ENT_QUOTES, 'UTF-8') ?>"
             alt="CHN – Cyprus Health-Care Network" class="sidebar__logo" width="186" height="55">
    </div>

    <p class="sidebar__section-title">Navigation</p>
    <nav class="sidebar__nav" aria-label="Primary navigation">
        <?php foreach ($chnSidebarItems as $chnItem): ?>
            <?php
                $chnFile = $chnItem['file'];
                $chnLabel = htmlspecialchars($chnItem['label'], ENT_QUOTES, 'UTF-8');
                $chnIcon = htmlspecialchars($chnItem['icon'], ENT_QUOTES, 'UTF-8');
                $chnExists = is_file($chnProjectRoot . '/' . $chnFile);
                $chnIsCurrent = $chnCurrentPage === basename($chnFile);
                $chnHref = $chnBaseUrl . implode('/', array_map('rawurlencode', explode('/', $chnFile)));
            ?>
            <?php if ($chnExists): ?>
                <a class="sidebar__link<?= $chnIsCurrent ? ' sidebar__link--active' : '' ?>"
                   href="<?= htmlspecialchars($chnHref, ENT_QUOTES, 'UTF-8') ?>"
                   <?= $chnIsCurrent ? 'aria-current="page"' : '' ?>>
                    <span class="sidebar__icon sidebar__icon--<?= $chnIcon ?>" aria-hidden="true"></span>
                    <span class="sidebar__link-text"><?= $chnLabel ?></span>
                </a>
            <?php else: ?>
                <span class="sidebar__link sidebar__link--disabled"
                      aria-label="<?= $chnLabel ?> — Under development">
                    <span class="sidebar__icon sidebar__icon--<?= $chnIcon ?>" aria-hidden="true"></span>
                    <span class="sidebar__link-text"><?= $chnLabel ?></span>
                    <span class="sidebar__status" aria-hidden="true">Soon</span>
                </span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar__footer">CHN · Academic prototype</div>
</aside>
