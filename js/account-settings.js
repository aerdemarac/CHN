/* Frontend draft only. No network, cookies, storage or account mutations. */
(() => {
    'use strict';
    const form = document.getElementById('account-form');
    if (!form) return;
    const result = document.getElementById('account-result');
    const unsaved = document.getElementById('account-unsaved');
    const fields = [...form.querySelectorAll('input, select')];
    const snapshot = () => JSON.stringify(fields.map(field => [field.name, field.type === 'checkbox' ? field.checked : field.value]));
    const initial = snapshot();
    let dirty = false;
    const track = () => {
        dirty = snapshot() !== initial;
        unsaved.textContent = dirty ? 'Unsaved draft changes — nothing has been sent or saved.' : 'No draft changes.';
    };
    const validate = (field) => {
        const error = document.getElementById(`${field.id}-error`);
        if (!error) return true;
        let message = '';
        const value = field.value.trim();
        if (field.required && !value) message = 'This field is required.';
        else if (field.maxLength > 0 && field.value.length > field.maxLength) message = `Use at most ${field.maxLength} characters.`;
        else if (field.name === 'email' && (field.validity.typeMismatch || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value))) message = 'Enter a valid email address.';
        else if (field.name === 'phone' && value && !/^\+[1-9][0-9]{6,14}$/.test(value)) message = 'Use + followed by 7–15 digits, without spaces.';
        else if (field.name === 'nationality' && value && !/^[A-Za-z]{2}$/.test(value)) message = 'Enter a two-letter country code.';
        else if (field.name === 'birth_date') {
            const today = new Date();
            const localDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            if (field.validity.badInput || (value && (Number.isNaN(Date.parse(value)) || value > localDate))) message = 'Enter a valid date that is not in the future.';
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
    form.addEventListener('submit', event => {
        event.preventDefault();
        const invalid = fields.filter(field => !validate(field));
        result.dataset.error = String(invalid.length > 0);
        result.textContent = invalid.length ? 'Review the highlighted fields.' : 'Draft checked. Nothing was sent or saved; account updates are not connected.';
        if (invalid.length) invalid[0].focus();
    });
    form.addEventListener('reset', () => {
        fields.forEach(field => {
            field.removeAttribute('aria-invalid');
            const error = document.getElementById(`${field.id}-error`);
            if (error) error.textContent = '';
        });
        result.dataset.error = 'false';
        result.textContent = 'Draft changes discarded. Fictional account details restored.';
        queueMicrotask(track);
    });
    window.addEventListener('beforeunload', event => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
    document.getElementById('account-check').disabled = false;
})();
