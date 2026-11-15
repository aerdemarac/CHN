<?php
/**
 * CHN local UI demonstration ONLY - NOT authentication or authorization.
 * Do not publish this demo endpoint in production.
 * Role is intentionally chosen using a URL query for visual testing only.
 */
$demoRoles = [
    'patient' => 'Patient',
    'doctor' => 'Doctor',
    'healthcare_staff' => 'Healthcare Staff',
    'admin' => 'Admin',
];
$selected = $_GET['demo_role'] ?? 'patient';
$selected = is_string($selected) && array_key_exists($selected, $demoRoles)
    ? $selected : 'patient';

$chnUserRole = $selected;
$chnUserName = 'Demo User';
$chnPageTitle = 'Dynamic Sidebar Demo';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | Dynamic Role Sidebar Demo</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/sidebar.js" defer></script>
    <script src="../js/topbar.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/../include/left-sidebar.php'; ?>
<?php require __DIR__ . '/../include/top-bar.php'; ?>
<main class="content" id="main-content" tabindex="-1">
    <div class="content__inner">
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Dynamic Role-Based Sidebar</h1>
                <p class="page-header__subtitle">PHP role-based navigation preview; no PHP session required for this UI test.</p>
            </div>
        </div>

        <section class="card">
            <div class="card__header">
                <div>
                    <h2 class="card__title">Choose demo role</h2>
                    <p class="card__subtitle">Change the role to preview its Sidebar items.</p>
                </div>
            </div>
            <div style="padding: 24px;">
                <form method="get">
                    <label for="demo-role" style="display:block;margin-bottom:8px;">User role</label>
                    <select id="demo-role" name="demo_role"
                            style="display:block;width:100%;max-width:340px;padding:12px 16px;border:1px solid #E2E8F0;border-radius:8px;background:#FFFFFF;color:#243447;margin-bottom:16px;">
                        <?php foreach ($demoRoles as $role => $label): ?>
                            <option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $selected === $role ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn--primary">Apply Role</button>
                </form>
                <p style="margin-top:24px;">Visible role: <strong><?= htmlspecialchars($demoRoles[$selected], ENT_QUOTES, 'UTF-8') ?></strong></p>
            </div>
        </section>
        <div class="alert alert--info" style="margin-top:24px;">
            <strong>UI preview only.</strong> Choosing a role here does not authenticate users or authorize access to protected pages.
            The real backend must derive the role from a verified identity before setting <code>$chnUserRole</code>.
            Unimplemented application pages appear as <strong>Soon</strong>.
        </div>
    </div>
</main>
</body>
</html>
