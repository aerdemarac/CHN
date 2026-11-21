<?php
declare(strict_types=1);

require_once __DIR__ . '/i18n.php';
?>
<nav class="auth__lang" aria-label="<?= te('lang.switcher') ?>">
    <?php foreach (CHN_LANGUAGES as $chnLangKey => $chnLangItem): ?>
        <a class="auth__lang-link"
           href="<?= htmlspecialchars(chnLangUrl($chnLangKey), ENT_QUOTES, 'UTF-8') ?>"
           hreflang="<?= htmlspecialchars($chnLangKey, ENT_QUOTES, 'UTF-8') ?>"
           lang="<?= htmlspecialchars($chnLangKey, ENT_QUOTES, 'UTF-8') ?>"
           aria-label="<?= htmlspecialchars($chnLangItem['label'], ENT_QUOTES, 'UTF-8') ?>"
           <?= $chnLangKey === chnLang() ? 'aria-current="true"' : '' ?>><?= htmlspecialchars($chnLangItem['code'], ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
</nav>
