/* Progressive enhancement only; validation, review and persistence are enforced in PHP/MySQL. */
(() => {
    'use strict';
    document.querySelectorAll('form[data-case-submit]').forEach((form) => {
        const save = form.querySelector('[data-case-submit-button]');
        if (!save) return;
        const originalLabel = save.textContent;
        const unavailable = save.disabled;
        form.addEventListener('submit', (event) => {
            if (unavailable) { event.preventDefault(); return; }
            if (!form.checkValidity()) return;
            save.disabled = true;
            save.textContent = save.dataset.busyLabel || 'Submitting…';
        });
        window.addEventListener('pageshow', () => {
            save.disabled = unavailable;
            save.textContent = originalLabel;
        });
    });
})();
