<?php
$chnSidebarShowTestLink = true;
$chnPageTitle = 'Top Bar & UI Preview';
$chnUserName = 'Ahmet Yılmaz';
$chnUserRole = 'Patient';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHN | Top Bar &amp; UI Preview</title>
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
                <h1 class="page-header__title">Dashboard &amp; Layout Preview</h1>
                <p class="page-header__subtitle">Shared Top Bar, Left Sidebar, and core UI components powered by css/style.css.</p>
            </div>
            <div class="page-header__actions">
                <button type="button" class="btn btn--outline btn--sm">Secondary Action</button>
                <button type="button" class="btn btn--primary btn--sm">Primary Action</button>
            </div>
        </div>

        <div class="grid grid--3">
            <div class="stat-card">
                <div class="stat-card__icon">
                    <span class="icon" style="--icon: url('../assets/icons/calendar3.svg')" aria-hidden="true"></span>
                </div>
                <div>
                    <p class="stat-card__label">Upcoming Appointments</p>
                    <p class="stat-card__value">2</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--success">
                    <span class="icon" style="--icon: url('../assets/icons/file-medical.svg')" aria-hidden="true"></span>
                </div>
                <div>
                    <p class="stat-card__label">Ready Test Results</p>
                    <p class="stat-card__value">5</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-card__icon stat-card__icon--warning">
                    <span class="icon" style="--icon: url('../assets/icons/clipboard2-pulse.svg')" aria-hidden="true"></span>
                </div>
                <div>
                    <p class="stat-card__label">Active Medical Cases</p>
                    <p class="stat-card__value">1</p>
                </div>
            </div>
        </div>

        <section class="card" style="margin-top: 24px;">
            <header class="card__header">
                <div>
                    <h2 class="card__title">Sample Appointments Table</h2>
                    <p class="card__subtitle">Preview of shared .card, .table, and .badge styles.</p>
                </div>
                <span class="badge badge--info">Preview Data</span>
            </header>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Department</th>
                            <th>Doctor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>15 Nov 2026, 10:30</td>
                            <td>Cardiology</td>
                            <td>Dr. Mehmet Kaya</td>
                            <td><span class="badge badge--success">Confirmed</span></td>
                        </tr>
                        <tr>
                            <td>18 Nov 2026, 14:00</td>
                            <td>Internal Medicine</td>
                            <td>Dr. Ayşe Demir</td>
                            <td><span class="badge badge--warning">Pending</span></td>
                        </tr>
                        <tr>
                            <td>02 Nov 2026, 09:00</td>
                            <td>Orthopedics</td>
                            <td>Dr. Can Öztürk</td>
                            <td><span class="badge badge--danger">Cancelled</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="alert alert--info" style="margin-top: 24px;">
            <strong>Component preview only.</strong> Include <code>include/left-sidebar.php</code> and <code>include/top-bar.php</code> on any root PHP page to reuse this full shell.
        </div>
    </div>
</main>
</body>
</html>
