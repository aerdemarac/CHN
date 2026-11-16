<?php
/** Frontend preview only. No authentication, account reads, POST processing or persistence. */
$accountRoles = ['patient' => 'Patient', 'doctor' => 'Doctor', 'healthcare_staff' => 'Healthcare Staff', 'admin' => 'Admin'];
$accountRole = isset($_GET['role']) && is_string($_GET['role']) && isset($accountRoles[$_GET['role']]) ? $_GET['role'] : 'patient';
$chnPageTitle = 'Account Settings';
$chnUserName = 'Alex Demo';
$chnUserRole = $accountRole; // Fictional UI scope only; never permissions.
$account = ['name' => 'Alex', 'surname' => 'Demo', 'email' => 'alex.demo@example.invalid', 'phone' => '+905551234567', 'birth_date' => '1994-06-15', 'gender' => 'not_specified', 'nationality' => 'CY'];
function accountEscape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | CHN</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/account-settings.css">
    <script src="js/sidebar.js" defer></script>
    <script src="js/topbar.js" defer></script>
    <script src="js/account-settings.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/include/left-sidebar.php'; ?>
<?php require __DIR__ . '/include/top-bar.php'; ?>
<main class="content account-settings" id="main-content" tabindex="-1">
<div class="content__inner">
    <header class="page-header"><div><h1 class="page-header__title">Account Settings</h1><p class="page-header__subtitle">Manage your personal details and communication preferences.</p></div><span class="badge badge--info">Frontend preview</span></header>
    <div class="alert account-settings__notice"><strong>Fictional account preview.</strong> Draft changes stay on this page and disappear on reload. Nothing is sent or saved. Use fictional information only.</div>
    <?php if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'): ?><p class="alert account-settings__notice" role="status">No account changes were processed or saved.</p><?php endif; ?>
    <form method="get" action="account-settings.php" class="account-settings__role-picker" aria-label="Choose preview role">
        <div><label class="form-label" for="account-role">Preview role</label><select class="form-control" id="account-role" name="role"><?php foreach ($accountRoles as $role => $label): ?><option value="<?= $role ?>" <?= $role === $accountRole ? 'selected' : '' ?>><?= accountEscape($label) ?></option><?php endforeach; ?></select></div>
        <button class="btn btn--outline" type="submit">Preview role</button>
    </form>
    <form id="account-form" method="post" action="account-settings.php?role=<?= accountEscape($accountRole) ?>" novalidate aria-describedby="account-draft-help">
        <section class="card account-settings__section" aria-labelledby="personal-title">
            <header class="card__header"><div><h2 class="card__title" id="personal-title">Personal information</h2><p class="card__subtitle">Keep your contact details clear and up to date. Required fields are marked *</p></div></header>
            <p class="account-settings__identity">Account role: <strong><?= accountEscape($accountRoles[$accountRole]) ?></strong><span>Managed by your care network</span></p>
            <div class="account-settings__grid">
                <?php foreach (['name' => ['First name', 'text', 'given-name'], 'surname' => ['Last name', 'text', 'family-name'], 'email' => ['Email address', 'email', 'email'], 'phone' => ['Phone number', 'tel', 'tel'], 'birth_date' => ['Date of birth', 'date', 'bday'], 'nationality' => ['Nationality', 'text', 'country']] as $field => $config): ?>
                <div class="account-settings__field"><label class="form-label" for="account-<?= $field ?>"><?= $config[0] ?><?= in_array($field, ['name', 'surname', 'email'], true) ? ' *' : ' (optional)' ?></label>
                    <input class="form-control" id="account-<?= $field ?>" name="<?= $field ?>" type="<?= $config[1] ?>" autocomplete="<?= $config[2] ?>" value="<?= accountEscape($account[$field]) ?>" <?= in_array($field, ['name', 'surname', 'email'], true) ? 'required' : '' ?> <?= $field === 'phone' ? 'maxlength="16" inputmode="tel"' : ($field === 'nationality' ? 'maxlength="2" pattern="[A-Za-z]{2}"' : (in_array($field, ['name', 'surname', 'email'], true) ? 'maxlength="255"' : '')) ?> aria-describedby="account-<?= $field ?>-help account-<?= $field ?>-error">
                    <p class="form-hint" id="account-<?= $field ?>-help"><?php if ($field === 'phone'): ?>International format: + followed by 7–15 digits.<?php elseif ($field === 'nationality'): ?>Two-letter country code, for example CY or TR.<?php elseif ($field === 'birth_date'): ?>Enter a valid date that is not in the future.<?php elseif ($field === 'email'): ?>Use a fictional email address in this preview.<?php else: ?>As you would like it to appear on your account.<?php endif; ?></p>
                    <p class="form-error" id="account-<?= $field ?>-error"></p>
                </div>
                <?php endforeach; ?>
                <div class="account-settings__field"><label class="form-label" for="account-gender">Gender (optional)</label><select class="form-control" id="account-gender" name="gender"><option value="not_specified" selected>Prefer not to say</option><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option></select></div>
            </div>
        </section>
        <section class="card account-settings__section" aria-labelledby="preferences-title">
            <header class="card__header"><div><h2 class="card__title" id="preferences-title">Preferences</h2><p class="card__subtitle">Choose how you would like to hear from CHN.</p></div></header>
            <div class="account-settings__grid">
                <div class="account-settings__field"><label class="form-label" for="account-language">Preferred language</label><select class="form-control" id="account-language" name="preferred_language"><?php foreach ($chnLanguages as $code => $language): ?><option value="<?= accountEscape($code) ?>" <?= $code === 'en' ? 'selected' : '' ?>><?= accountEscape($language['label']) ?></option><?php endforeach; ?></select><p class="form-hint">Draft preference only; this preview does not translate the interface.</p></div>
                <fieldset class="account-settings__notifications"><legend class="form-label">Notification preferences</legend><label><input type="checkbox" name="email_notifications" value="1" checked> Email reminders</label><label><input type="checkbox" name="sms_notifications" value="1"> SMS reminders</label><p class="form-hint">Illustrative preferences only. No messages are sent.</p></fieldset>
            </div>
        </section>
        <div class="card account-settings__actions">
            <p class="form-hint" id="account-draft-help">Account updates require a connected, authenticated backend. You can check or reset this draft.</p>
            <div class="account-settings__buttons"><button class="btn btn--primary" id="account-check" type="submit" disabled>Check draft</button><button class="btn btn--outline" type="reset">Cancel changes</button><button class="btn btn--outline" type="button" disabled aria-describedby="account-draft-help">Save changes unavailable</button></div>
            <p class="account-settings__result" id="account-result" role="status" aria-live="polite"></p>
            <p class="form-hint" id="account-unsaved" aria-live="polite">No draft changes.</p>
            <noscript><p class="form-hint">JavaScript is needed to validate drafts. Saving is unavailable; no account changes will be submitted.</p></noscript>
        </div>
    </form>
    <section class="card account-settings__section" aria-labelledby="security-title">
        <header class="card__header"><div><h2 class="card__title" id="security-title">Security</h2><p class="card__subtitle">Keep access to your account secure.</p></div></header>
        <p id="password-help">Password changes will be available once the secure account service is connected. This preview does not request or store passwords.</p>
        <button class="btn btn--outline" type="button" disabled aria-describedby="password-help">Change password unavailable</button>
    </section>
</div>
</main>
</body>
</html>
