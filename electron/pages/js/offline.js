'use strict';

(async () => {
    const context = await window.chnPage.start();
    const retryButton = document.getElementById('offline-retry');

    document.getElementById('offline-server').textContent = context.serverUrl;

    if (context.failure === null) {
        document.getElementById('offline-error-label').hidden = true;
        document.getElementById('offline-error').hidden = true;
    } else {
        document.getElementById('offline-error').textContent = `${context.failure.description} (${context.failure.code})`;
    }

    retryButton.addEventListener('click', () => {
        retryButton.disabled = true;
        retryButton.textContent = context.strings['offline.retrying'];
        window.chnDesktop.retry();
    });
    document.getElementById('offline-settings').addEventListener('click', () => window.chnDesktop.openSettings());
    document.getElementById('offline-quit').addEventListener('click', () => window.chnDesktop.quit());

    window.chnPage.focusFirst(retryButton);
})();
