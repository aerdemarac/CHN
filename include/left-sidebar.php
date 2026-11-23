<?php
/**
 * CHN shared sidebar component.
 * Usage from a root PHP page: require __DIR__ . '/include/left-sidebar.php';
 * Optional demo-only link: $chnSidebarShowTestLink = true; (before require)
 * Live pages set $chnUserRole from a verified backend value.
 * Fictional prototypes opt into include/prototype-role.php before this file.
 * IMPORTANT: Hiding links is NOT authorization. The backend must enforce roles.
 */
$chnProjectRoot = dirname(__DIR__);
require_once __DIR__ . '/i18n.php';
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
    $chnPreviewFile = is_file($chnProjectRoot . '/pages/top-and-sidebar-test.php')
        ? 'pages/top-and-sidebar-test.php'
        : (is_file($chnProjectRoot . '/sidebar-test.php') ? 'sidebar-test.php' : 'pages/top-and-sidebar-test.php');
    array_unshift($chnSidebarItems,
        ['label' => 'UI Preview', 'file' => $chnPreviewFile, 'icon' => 'preview']);
}

$chnSidebarGroups = [];
foreach ($chnSidebarItems as $chnItem) {
    $chnSidebarGroups[$chnItem['group'] ?? 'overview'][] = $chnItem;
}
?>
<a class="chn-skip-link" href="#main-content"><?= te('sidebar.skip') ?></a>

<button type="button" class="sidebar__toggle" id="sidebar-menu-toggle"
        aria-controls="chn-sidebar" aria-expanded="false"
        aria-label="<?= te('sidebar.open') ?>"
        data-label-open="<?= te('sidebar.open') ?>" data-label-close="<?= te('sidebar.close') ?>"
        data-text-open="<?= te('sidebar.menu') ?>" data-text-close="<?= te('sidebar.close_short') ?>">
    <span class="sidebar__toggle-icon sidebar__toggle-icon--menu" aria-hidden="true"></span>
    <span class="sidebar__toggle-label"><?= te('sidebar.menu') ?></span>
</button>

<div class="sidebar__backdrop" id="chn-sidebar-backdrop" aria-hidden="true"></div>

<aside class="sidebar" id="chn-sidebar" aria-label="<?= te('sidebar.main_nav') ?>">
    <div class="sidebar__brand">
        <img src="<?= htmlspecialchars($chnBaseUrl . 'assets/logos/chn-logo.png', ENT_QUOTES, 'UTF-8') ?>"
             alt="CHN – Cyprus Health-Care Network" class="sidebar__logo" width="186" height="55">
    </div>

    <nav class="sidebar__nav" aria-label="<?= te('sidebar.primary_nav') ?>">
        <?php foreach ($chnSidebarGroups as $chnGroup => $chnGroupItems): ?>
        <div class="sidebar__group" role="group" aria-labelledby="sidebar-group-<?= htmlspecialchars($chnGroup, ENT_QUOTES, 'UTF-8') ?>">
        <p class="sidebar__section-title" id="sidebar-group-<?= htmlspecialchars($chnGroup, ENT_QUOTES, 'UTF-8') ?>"><?= te('sidebar.group_' . $chnGroup) ?></p>
        <?php foreach ($chnGroupItems as $chnItem): ?>
            <?php
                $chnFile = $chnItem['file'];
                if (!is_file($chnProjectRoot . '/' . $chnFile) && isset($chnItem['demo'])
                    && is_file($chnProjectRoot . '/' . $chnItem['demo'])) {
                    $chnFile = $chnItem['demo'];
                }
                $chnLabelText = isset($chnItem['key']) ? t('nav.' . $chnItem['key']) : $chnItem['label'];
                $chnLabel = htmlspecialchars($chnLabelText, ENT_QUOTES, 'UTF-8');
                $chnIcon = htmlspecialchars($chnItem['icon'], ENT_QUOTES, 'UTF-8');
                $chnExists = is_file($chnProjectRoot . '/' . $chnFile);
                $chnIsCurrent = $chnCurrentPage === basename($chnFile);
                $chnHref = $chnBaseUrl . implode('/', array_map('rawurlencode', explode('/', $chnFile)))
                    . (in_array($chnUserRole ?? null, ['patient', 'doctor', 'healthcare_staff', 'admin'], true)
                        ? '?' . http_build_query(['role' => $chnUserRole]) : '');
            ?>
            <?php if ($chnExists): ?>
                <a class="sidebar__link<?= $chnIsCurrent ? ' sidebar__link--active' : '' ?>"
                   href="<?= htmlspecialchars($chnHref, ENT_QUOTES, 'UTF-8') ?>"
                   <?= $chnIsCurrent ? 'aria-current="page"' : '' ?>>
                    <span class="sidebar__icon-box" aria-hidden="true"><span class="sidebar__icon sidebar__icon--<?= $chnIcon ?>"></span></span>
                    <span class="sidebar__link-text"><?= $chnLabel ?></span>
                </a>
            <?php else: ?>
                <span class="sidebar__link sidebar__link--disabled"
                      aria-label="<?= te('common.under_development', ['label' => $chnLabelText]) ?>">
                    <span class="sidebar__icon-box" aria-hidden="true"><span class="sidebar__icon sidebar__icon--<?= $chnIcon ?>"></span></span>
                    <span class="sidebar__link-text"><?= $chnLabel ?></span>
                    <span class="sidebar__status" aria-hidden="true"><?= te('common.soon') ?></span>
                </span>
            <?php endif; ?>
        <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar__footer"><?= te('sidebar.footer') ?></div>
</aside>

<!-- Molehi-AI Chatbot (Gemini + LocalStorage) Component -->
<link rel="stylesheet" href="<?= htmlspecialchars($chnBaseUrl . 'css/chatwidget.css', ENT_QUOTES, 'UTF-8') ?>">
<script src="<?= htmlspecialchars($chnBaseUrl . 'js/chatwidget.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>
