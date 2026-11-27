/* Authenticated account settings interactions (fetch + JSON). */
(() => {
    'use strict';
    const form = document.getElementById('account-form');
    if (!form) return;

    const result = document.getElementById('account-result');
    const unsaved = document.getElementById('account-unsaved');
    const saveButton = document.getElementById('account-save');
    const messages = JSON.parse(form.dataset.messages || '{}');
    const msg = (key, params = {}) => (messages[key] || key).replace(/\{(\w+)\}/g, (m, k) => (k in params ? String(params[k]) : m));

    const namePattern = /^[A-Za-zçÇğĞıİöÖşŞüÜ][A-Za-zçÇğĞıİöÖşŞüÜ '.-]*$/;
    const addressPattern = /^[A-Za-zçÇğĞıİöÖşŞüÜ0-9\s,.\-\/:#'()]+$/;
    const passwordHasLetter = /[A-Za-zçÇğĞıİöÖşŞüÜ]/;
    const passwordHasDigit = /\d/;

    const fields = [...form.querySelectorAll('input, select')].filter(field => !field.readOnly && !field.disabled);
    const snapshot = () => JSON.stringify(fields.map(field => [field.name, field.type === 'checkbox' ? field.checked : field.value]));
    let initial = snapshot();
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

        if (field.required && !value) {
            message = msg('required');
        } else if (field.maxLength > 0 && field.value.length > field.maxLength) {
            message = msg('maxChars', { max: field.maxLength });
        } else if ((field.name === 'name' || field.name === 'surname') && value && !namePattern.test(value)) {
            message = msg('invalidChars');
        } else if (field.name === 'email' && (field.validity.typeMismatch || !/^[A-Za-z0-9._%+\-@]+$/.test(value) || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value))) {
            message = msg('email');
        } else if (field.name === 'phone' && value && !/^\+?[0-9\s()-]+$/.test(value)) {
            message = msg('phone');
        } else if (field.name === 'phone' && value) {
            const hasPlus = value.startsWith('+');
            const digits = value.replace(/\D/g, '');
            const normalizedLen = (hasPlus ? 1 : 0) + digits.length;
            if (digits.length < 7 || normalizedLen > 16) {
                message = msg('phone');
            }
        } else if (field.name === 'nationality' && value && !/^[A-Za-z]{2}$/.test(value)) {
            message = msg('nationality');
        } else if (field.name === 'full_address' && value && !addressPattern.test(value)) {
            message = msg('addressInvalid');
        } else if (field.name === 'birth_date') {
            const today = new Date();
            const localDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            if (field.validity.badInput || (value && (Number.isNaN(Date.parse(value)) || value > localDate || value < '1900-01-01'))) {
                message = msg('birthDate');
            }
        }

        error.textContent = message;
        field.setAttribute('aria-invalid', String(Boolean(message)));
        return !message;
    };

    fields.forEach(field => {
        field.addEventListener('blur', () => validate(field));
        field.addEventListener('input', () => {
            track();
            result.textContent = '';
            validate(field);
        });
        field.addEventListener('paste', () => {
            queueMicrotask(() => {
                track();
                validate(field);
            });
        });
        field.addEventListener('change', track);
    });

    const submitAccount = async (event) => {
        event.preventDefault();
        if (saveButton.disabled) return;

        const invalid = fields.filter(field => !validate(field));
        if (invalid.length > 0) {
            result.dataset.error = 'true';
            result.textContent = msg('review');
            invalid[0].focus();
            return;
        }

        const updateUrl = form.dataset.updateUrl || form.getAttribute('action');
        if (!updateUrl) return;

        const payload = {};
        fields.forEach(field => {
            payload[field.name] = field.value.trim();
        });

        saveButton.disabled = true;
        saveButton.setAttribute('aria-busy', 'true');
        result.textContent = '';

        try {
            const response = await fetch(updateUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            let body = null;
            try {
                body = await response.json();
            } catch {
                body = null;
            }

            if (response.ok && body && body.ok === true) {
                const phoneField = form.querySelector('#account-phone');
                if (phoneField && typeof body.data?.phone === 'string') {
                    phoneField.value = body.data.phone;
                }
                initial = snapshot();
                track();
                result.dataset.error = 'false';
                result.textContent = msg('validated');
                if (body.data?.language_changed) {
                    window.location.reload();
                }
                return;
            }

            result.dataset.error = 'true';
            if (body?.error === 'EMAIL_EXISTS') {
                result.textContent = msg('emailExists');
            } else if (typeof body?.message === 'string' && body.message !== '') {
                result.textContent = body.message;
            } else {
                result.textContent = msg('saveError');
            }
        } catch {
            result.dataset.error = 'true';
            result.textContent = msg('saveError');
        } finally {
            saveButton.disabled = false;
            saveButton.removeAttribute('aria-busy');
        }
    };

    form.addEventListener('submit', submitAccount);
    saveButton.addEventListener('click', submitAccount);

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
    if (!dialog) return;

    const passwordForm = document.getElementById('password-form');
    const password = document.getElementById('password-new');
    const confirmation = document.getElementById('password-confirm');
    const passwordResult = document.getElementById('password-result');
    const passwordSaveBtn = document.getElementById('password-check');

    const clearPasswords = () => {
        passwordForm.reset();
        [password, confirmation].forEach(field => {
            field.value = '';
            field.removeAttribute('aria-invalid');
            document.getElementById(`${field.id}-error`).textContent = '';
        });
        passwordResult.dataset.error = 'false';
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
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        closePassword();
    });
    window.addEventListener('pagehide', clearPasswords);

    const submitPassword = async (event) => {
        event.preventDefault();
        if (passwordSaveBtn.disabled) return;

        const val = password.value;
        const validPw = val.length >= 12 && val.length <= 255 && passwordHasLetter.test(val) && passwordHasDigit.test(val);
        const errors = [
            validPw ? '' : msg('passwordShort'),
            !confirmation.value || confirmation.value !== val ? msg('passwordMismatch') : '',
        ];

        [password, confirmation].forEach((field, index) => {
            field.setAttribute('aria-invalid', String(Boolean(errors[index])));
            document.getElementById(`${field.id}-error`).textContent = errors[index];
        });

        const invalid = [password, confirmation].find((field, index) => errors[index]);
        passwordResult.textContent = '';
        if (invalid) {
            invalid.focus();
            return;
        }

        const passwordUrl = passwordForm.dataset.passwordUrl || passwordForm.getAttribute('action');
        if (!passwordUrl) return;

        passwordSaveBtn.disabled = true;
        passwordSaveBtn.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(passwordUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    new_password: val,
                    password_confirm: confirmation.value,
                }),
            });

            let body = null;
            try {
                body = await response.json();
            } catch {
                body = null;
            }

            if (response.ok && body && body.ok === true) {
                clearPasswords();
                passwordResult.dataset.error = 'false';
                passwordResult.textContent = msg('passwordPassed');
                return;
            }

            passwordResult.dataset.error = 'true';
            passwordResult.textContent = (typeof body?.message === 'string' && body.message !== '')
                ? body.message
                : msg('passwordError');
        } catch {
            passwordResult.dataset.error = 'true';
            passwordResult.textContent = msg('passwordError');
        } finally {
            passwordSaveBtn.disabled = false;
            passwordSaveBtn.removeAttribute('aria-busy');
        }
    };

    passwordForm.addEventListener('submit', submitPassword);
    passwordSaveBtn.addEventListener('click', submitPassword);
})();
