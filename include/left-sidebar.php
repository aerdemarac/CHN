
<?php
// CHN - Shared Sidebar Navigation

$sidebarPages = [
    ['title' => 'Sidebar Test', 'file' => 'sidebar-test.php'],
    ['title' => 'Dashboard', 'file' => 'dashboard.php'],
    ['title' => 'Appointments', 'file' => 'appointments.php'],
    ['title' => 'Medical Records', 'file' => 'medical-records.php'],
    ['title' => 'Medical Cases', 'file' => 'medical-cases.php'],
    ['title' => 'Feedback', 'file' => 'feedback.php'],
];

// Find the currently opened page.
$currentPage = basename(
    parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: ''
);

// Root folder of the CHN project.
$projectRoot = dirname(__DIR__);
?>

<aside class="sidebar" id="chn-sidebar" aria-label="Main navigation">

    
<div class="sidebar__brand">
    <img
        src="/CHN/assets/logos/chn-logo.jpeg"
        alt="CHN - Cyprus Healthcare Network"
        class="sidebar__logo"
    >
</div>

    
<nav class="sidebar__nav" aria-label="Primary navigation">

    <?php foreach ($sidebarPages as $page): ?>

        <?php
        $pageFile = $page['file'];

        $pageTitle = htmlspecialchars(
            $page['title'],
            ENT_QUOTES,
            'UTF-8'
        );

        $pageExists = is_file(
            $projectRoot . '/' . $pageFile
        );

        $isCurrentPage = ($currentPage === $pageFile);
        ?>

        <?php if ($pageExists): ?>

            <a
                class="sidebar__link"
                href="/CHN/<?= rawurlencode($pageFile) ?>"
                <?= $isCurrentPage ? 'aria-current="page"' : '' ?>
            >
                <?= $pageTitle ?>
            </a>

        <?php else: ?>

            <span
                class="sidebar__link sidebar__link--disabled"
                aria-label="<?= $pageTitle ?> - Under development"
            >
                <?= $pageTitle ?>
            </span>

        <?php endif; ?>

    <?php endforeach; ?>

</nav>

</aside>