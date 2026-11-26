'use strict';

(async () => {
    const context = await window.chnPage.start();
    const support = document.getElementById('about-support');
    const closeButton = document.getElementById('about-close');

    document.getElementById('about-name').textContent = context.productName;
    document.getElementById('about-version').textContent = context.version;
    document.getElementById('about-server').textContent = context.serverUrl;
    document.getElementById('about-runtime').textContent = `Electron ${context.electronVersion} · Chromium ${context.chromeVersion}`;
    support.textContent = context.supportEmail;
    support.href = `mailto:${context.supportEmail}`;

    closeButton.addEventListener('click', () => window.chnDesktop.close());
    window.chnPage.closeOnEscape();
    await window.chnPage.fit();
    window.chnPage.focusFirst(closeButton);
})();
