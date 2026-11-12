<?php $chnSidebarShowTestLink = true; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | Sidebar Preview</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/sidebar-preview.css">
    <script src="js/sidebar.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/include/left-sidebar.php'; ?>
<main class="content preview" id="main-content" tabindex="-1">
    <div class="preview__inner">
        <p class="preview__eyebrow">CHN / Shared component</p>
        <h1 class="preview__title">Sidebar Component Preview</h1>
        <p class="preview__subtitle">Reusable navigation for the CHN web application. Links automatically become available once their PHP pages exist.</p>
        <div class="preview__grid">
            <section class="preview-card">
                <h2 class="preview-card__title">Navigation is ready</h2>
                <p class="preview-card__text">The current page is highlighted. Pages not yet created are clearly marked “Soon” instead of linking to missing routes.</p>
            </section>
            <section class="preview-card">
                <h2 class="preview-card__title">Responsive behavior</h2>
                <p class="preview-card__text">On phones, use the Menu button to open the navigation. Close it with the button, by tapping outside, or by pressing Escape.</p>
            </section>
        </div>
        <p class="preview__note"><strong>Component preview only.</strong> This page does not implement user authentication, medical records, a database, or role-based authorization.</p>
    </div>
</main>
</body>
</html>
