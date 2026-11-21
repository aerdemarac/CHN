/* Frontend draft validation only. No requests, persistence or simulated submissions. */
(() => {
    'use strict';
    const form = document.getElementById('feedback-form');
    if (!form) return;
    const message = document.getElementById('feedback-message');
    const counter = document.getElementById('feedback-counter');
    const error = document.getElementById('feedback-error');
    const result = document.getElementById('feedback-result');
    const check = document.getElementById('feedback-check');
    let attempted = false;
    const messages = JSON.parse(form.dataset.messages || '{}');
    const msg = (key, params = {}) => (messages[key] || key).replace(/\{(\w+)\}/g, (m, k) => (k in params ? String(params[k]) : m));

    function validate(showError) {
        // Match native textarea maxlength (UTF-16 units), conservatively within VARCHAR(255).
        const length = message.value.length;
        const problem = !message.value.trim() ? msg('required')
            : length > 255 ? msg('tooLong') : '';
        counter.textContent = msg('counter', { count: length });
        counter.classList.toggle('is-invalid', length > 255);
        error.textContent = showError ? problem : '';
        message.setAttribute('aria-invalid', String(showError && Boolean(problem)));
        return !problem;
    }

    message.addEventListener('input', () => {
        result.textContent = '';
        validate(attempted);
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        attempted = true;
        result.textContent = '';
        if (!validate(true)) {
            message.focus();
            return;
        }
        result.textContent = msg('checked');
    });
    form.addEventListener('reset', () => {
        attempted = false;
        result.textContent = '';
        // Native reset updates field values after the event handler.
        setTimeout(() => validate(false), 0);
    });
    validate(false);
    check.disabled = false;
})();
