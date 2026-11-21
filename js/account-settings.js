/* Frontend validation only. No network or account persistence.
 * Connect authenticated updates only after server authorization, CSRF and validation
 * exist. Keep validation and persistence outcomes separate; never mark this as saved.
 */
(() => {
    'use strict';
    const form = document.getElementById('account-form');
    if (!form) return;
    const result = document.getElementById('account-result');
    const unsaved = document.getElementById('account-unsaved');
    const messages = JSON.parse(form.dataset.messages || '{}');
    const msg = (key, params = {}) => (messages[key] || key).replace(/\{(\w+)\}/g, (m, k) => (k in params ? String(params[k]) : m));
    // Presentation only; the server must authorize fields against a verified session.
    const fields = [...form.querySelectorAll('input, select')].filter(field => !field.readOnly && !field.disabled);
    const snapshot = () => JSON.stringify(fields.map(field => [field.name, field.type === 'checkbox' ? field.checked : field.value]));
    const initial = snapshot();
    let dirty = false;
    const track = () => {
        dirty = snapshot() !== initial;
        unsaved.textContent = dirty ? msg('unsaved') : msg('noUnsaved');
    };
    const validate = (field) => {
        const error = document.getElementById(`${field.id}-error`);
        if (!error) return true;
        let message = '';
        const value = field.value.trim();
        if (field.required && !value) message = msg('required');
        else if (field.maxLength > 0 && field.value.length > field.maxLength) message = msg('maxChars', { max: field.maxLength });
        else if (field.name === 'email' && (field.validity.typeMismatch || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value))) message = msg('email');
        else if (field.name === 'phone' && value && !/^\+[1-9][0-9]{6,14}$/.test(value)) message = msg('phone');
        else if (field.name === 'nationality' && value && !/^[A-Za-z]{2}$/.test(value)) message = msg('nationality');
        else if (field.name === 'birth_date') {
            const today = new Date();
            const localDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            if (field.validity.badInput || (value && (Number.isNaN(Date.parse(value)) || value > localDate))) message = msg('birthDate');
        }
        error.textContent = message;
        field.setAttribute('aria-invalid', String(Boolean(message)));
        return !message;
    };
    fields.forEach(field => {
        field.addEventListener('blur', () => validate(field));
        field.addEventListener('input', () => {
            track(); result.textContent = '';
            if (field.getAttribute('aria-invalid') === 'true') validate(field);
        });
        field.addEventListener('change', track);
    });
    const reviewAccount = event => {
        event.preventDefault();
        const invalid = fields.filter(field => !validate(field));
        result.dataset.error = String(invalid.length > 0);
        result.textContent = invalid.length ? msg('review') : msg('validated');
        if (invalid.length) invalid[0].focus();
    };
    form.addEventListener('submit', reviewAccount);
    document.getElementById('account-save').addEventListener('click', reviewAccount);
    form.addEventListener('reset', () => {
        fields.forEach(field => {
            field.removeAttribute('aria-invalid');
            const error = document.getElementById(`${field.id}-error`);
            if (error) error.textContent = '';
        });
        result.dataset.error = 'false';
        result.textContent = msg('cancelled');
        queueMicrotask(track);
    });
    window.addEventListener('beforeunload', event => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
    const dialog = document.getElementById('password-dialog');
    if (!dialog) return; // Non-admin views display managed security information only.
    const passwordForm = document.getElementById('password-form');
    const password = document.getElementById('password-new');
    const confirmation = document.getElementById('password-confirm');
    const passwordResult = document.getElementById('password-result');
    const clearPasswords = () => {
        passwordForm.reset();
        [password, confirmation].forEach(field => {
            field.value = '';
            field.removeAttribute('aria-invalid');
            document.getElementById(`${field.id}-error`).textContent = '';
        });
        passwordResult.textContent = '';
    };
    document.getElementById('password-open').addEventListener('click', () => {
        clearPasswords();
        dialog.showModal();
        password.focus();
    });
    const closePassword = () => {
        clearPasswords();
        dialog.close();
        document.getElementById('password-open').focus();
    };
    document.getElementById('password-cancel').addEventListener('click', closePassword);
    dialog.addEventListener('close', () => {
        clearPasswords();
        document.getElementById('password-open').focus();
    });
    // Clear sensitive inputs on Escape immediately and before a page can enter bfcache.
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        closePassword();
    });
    window.addEventListener('pagehide', clearPasswords);
    const reviewPassword = event => {
        event.preventDefault();
        const errors = [password.value.length < 12 ? msg('passwordShort') : '',
            !confirmation.value || confirmation.value !== password.value ? msg('passwordMismatch') : ''];
        [password, confirmation].forEach((field, index) => {
            field.setAttribute('aria-invalid', String(Boolean(errors[index])));
            document.getElementById(`${field.id}-error`).textContent = errors[index];
        });
        const invalid = [password, confirmation].find((field, index) => errors[index]);
        passwordResult.textContent = '';
        if (invalid) { invalid.focus(); return; }
        clearPasswords();
        passwordResult.textContent = msg('passwordPassed');
    };
    passwordForm.addEventListener('submit', reviewPassword);
    document.getElementById('password-check').addEventListener('click', reviewPassword);
})();
