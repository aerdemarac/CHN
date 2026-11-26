document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggles();
    initLoginForm();
    initRegisterForm();
});

function initPasswordToggles() {
    document.querySelectorAll('.auth-form__toggle').forEach((toggle) => {
        const input = document.getElementById(toggle.getAttribute('aria-controls'));
        const icon = toggle.querySelector('.icon');
        if (!input) return;

        toggle.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(show));
            toggle.setAttribute('aria-label', show
                ? (toggle.dataset.labelHide || 'Hide password')
                : (toggle.dataset.labelShow || 'Show password'));
            icon?.classList.toggle('icon--eye', !show);
            icon?.classList.toggle('icon--eye-slash', show);
        });
    });
}

function initLoginForm() {
    const form = document.getElementById('login-form');
    if (!form) return;

    const msg = getMessages(form);
    const notice = document.getElementById('login-notice');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideNotice(notice);

        const emailOk = validateField(form.email, 'email-error', (value) => {
            if (!value) return msg('email_required');
            if (!form.email.validity.valid) return msg('email_invalid');
            return '';
        });

        const passwordOk = validateField(form.password, 'password-error', (value) => {
            if (!value) return msg('password_required');
            return '';
        });

        if (!emailOk || !passwordOk) {
            (emailOk ? form.password : form.email).focus();
            return;
        }

        const result = await sendForm(form, {
            email: form.email.value.trim(),
            password: form.password.value,
        });

        if (result.ok) {
            const target = safeRedirect(result.data?.redirect);
            if (target) {
                window.location.assign(target);
                return;
            }
        }

        const type = result.error === 'ACCOUNT_NOT_VERIFIED' ? 'warning' : 'danger';
        showNotice(notice, type, msg(knownCode(result.error, ['ACCOUNT_NOT_VERIFIED', 'ACCOUNT_LOCKED', 'INVALID_CREDENTIALS', 'network_error'])));
        if (result.error === 'INVALID_CREDENTIALS' || result.error === 'ACCOUNT_LOCKED') {
            form.password.value = '';
            form.password.focus();
        }
    });
}

function initRegisterForm() {
    const form = document.getElementById('register-form');
    if (!form) return;

    const msg = getMessages(form);
    const namePattern = /^\p{L}[\p{L}\p{M}\s'.-]*$/u;
    const phonePattern = /^\+?[0-9\s()-]+$/;
    const documentPattern = /^[A-Za-z0-9\s-]+$/;

    const updateNationalIdKeyboard = () => {
        if (form.nationality.value === 'TR') {
            form.national_id.setAttribute('inputmode', 'numeric');
        } else {
            form.national_id.removeAttribute('inputmode');
        }
    };

    form.nationality.addEventListener('change', updateNationalIdKeyboard);
    updateNationalIdKeyboard();

    form.phone.addEventListener('paste', (event) => {
        const pasted = (event.clipboardData || window.clipboardData)?.getData('text');
        if (pasted) {
            event.preventDefault();
            const normalized = normalizePhone(pasted).slice(0, 16);
            form.phone.value = normalized;
            form.phone.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    const getNameError = (value, field) => {
        if (!value) return msg(`${field}_required`);
        if (!namePattern.test(value)) return msg(`${field}_invalid`);
        return '';
    };

    const notice = document.getElementById('register-notice');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        hideNotice(notice);
        const password = form.password.value;

        const checks = [
            [form.first_name, 'first-name-error', (value) => getNameError(value, 'first_name')],
            [form.last_name, 'last-name-error', (value) => getNameError(value, 'last_name')],
            [form.birth_date, 'birth-date-error', (value) => {
                if (form.birth_date.validity.badInput) return msg('birth_date_invalid');
                if (!value) return msg('birth_date_required');
                if (!form.birth_date.validity.valid) return msg('birth_date_invalid');
                return '';
            }],
            [form.gender, 'gender-error', (value) => {
                if (value !== 'female' && value !== 'male') return msg('gender_required');
                return '';
            }],
            [form.nationality, 'nationality-error', (value) => {
                if (!value) return msg('nationality_required');
                return '';
            }],
            [form.national_id, 'national-id-error', (value) => {
                if (!value) return msg('national_id_required');
                if (form.nationality.value === 'TR') {
                    if (!isValidTcKimlikNo(value.replace(/\s/g, ''))) return msg('tc_invalid');
                    return '';
                }
                const clean = value.replace(/[\s-]/g, '');
                if (!documentPattern.test(value) || clean.length < 5 || clean.length > 50 || value.length > 50) {
                    return msg('national_id_invalid');
                }
                return '';
            }],
            [form.email, 'email-error', (value) => {
                if (!value) return msg('email_required');
                if (!form.email.validity.valid) return msg('email_invalid');
                return '';
            }],
            [form.phone, 'phone-error', (value) => {
                if (!value) return msg('phone_required');
                const normalized = normalizePhone(value);
                const digits = normalized.replace(/\D/g, '');
                if (!phonePattern.test(value) || digits.length < 7 || normalized.length > 16) {
                    return msg('phone_invalid');
                }
                return '';
            }],
            [form.password, 'password-error', () => {
                if (!password) return msg('password_required');
                if (password.length < 8 || !/\p{L}/u.test(password) || !/\d/.test(password)) {
                    return msg('password_weak');
                }
                return '';
            }],
            [form.password_confirm, 'password-confirm-error', () => {
                if (!form.password_confirm.value) return msg('password_confirm_required');
                if (form.password_confirm.value !== password) return msg('password_mismatch');
                return '';
            }],
        ];

        const invalid = checks
            .filter(([input, errorId, getError]) => !validateField(input, errorId, getError))
            .map(([input]) => input);

        if (invalid.length > 0) {
            invalid[0].focus();
            return;
        }

        form.phone.value = normalizePhone(form.phone.value);
        form.national_id.value = form.nationality.value === 'TR'
            ? form.national_id.value.replace(/\s/g, '')
            : form.national_id.value.trim();

        const result = await sendForm(form, {
            first_name: form.first_name.value.trim(),
            last_name: form.last_name.value.trim(),
            birth_date: form.birth_date.value,
            gender: form.gender.value,
            nationality: form.nationality.value,
            national_id: form.national_id.value,
            email: form.email.value.trim(),
            phone: form.phone.value,
            password: form.password.value,
            password_confirm: form.password_confirm.value,
        });

        if (result.ok) {
            showRegisterSuccess(form);
            return;
        }

        showNotice(notice, 'danger', msg(knownCode(result.error, ['network_error'])));
    });
}

function showRegisterSuccess(form) {
    const success = document.getElementById('register-success');
    const footer = document.getElementById('register-footer');

    form.reset();
    form.hidden = true;
    if (footer) footer.hidden = true;
    if (!success) return;

    success.hidden = false;
    document.getElementById('register-success-title')?.focus();
}

async function sendForm(form, payload) {
    const button = form.querySelector('button[type="submit"]');
    const endpoint = form.dataset.endpoint || form.getAttribute('action');

    form.setAttribute('aria-busy', 'true');
    if (button) button.disabled = true;

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        });

        let body = null;
        try {
            body = await response.json();
        } catch {
            body = null;
        }

        if (!body || typeof body !== 'object') {
            return { ok: false, error: 'server_error' };
        }
        if (body.ok === true && response.ok) {
            return { ok: true, data: body.data || {} };
        }
        return { ok: false, error: typeof body.error === 'string' ? body.error : 'server_error' };
    } catch {
        return { ok: false, error: 'network_error' };
    } finally {
        form.removeAttribute('aria-busy');
        if (button) button.disabled = false;
    }
}

function knownCode(code, allowed) {
    return allowed.includes(code) ? code : 'server_error';
}

function safeRedirect(value) {
    if (typeof value !== 'string' || value === '') return '';
    try {
        const url = new URL(value, window.location.href);
        if (url.origin !== window.location.origin) return '';
        if (url.protocol !== 'http:' && url.protocol !== 'https:') return '';
        return url.href;
    } catch {
        return '';
    }
}

function showNotice(notice, type, text) {
    if (!notice) return;
    notice.className = `auth-notice auth-notice--${type}`;
    notice.hidden = false;
    notice.textContent = text;
}

function hideNotice(notice) {
    if (!notice) return;
    notice.hidden = true;
    notice.textContent = '';
}

function getMessages(form) {
    let messages = {};
    try {
        messages = JSON.parse(form.dataset.messages || '{}');
    } catch {
        messages = {};
    }
    return (key) => messages[key] || key;
}

function normalizePhone(value) {
    const trimmed = (value || '').trim();
    if (!trimmed) return '';
    const hasPlus = trimmed.startsWith('+');
    const digits = trimmed.replace(/\D/g, '');
    return (hasPlus ? '+' : '') + digits;
}

function isValidTcKimlikNo(value) {
    if (!/^[1-9][0-9]{10}$/.test(value)) return false;

    const d = Array.from(value, Number);
    const odd = d[0] + d[2] + d[4] + d[6] + d[8];
    const even = d[1] + d[3] + d[5] + d[7];
    const tenth = (((odd * 7 - even) % 10) + 10) % 10;
    const eleventh = d.slice(0, 10).reduce((sum, digit) => sum + digit, 0) % 10;

    return d[9] === tenth && d[10] === eleventh;
}

function validateField(input, errorId, getError) {
    const message = getError(input.value.trim());
    const error = document.getElementById(errorId);

    error.textContent = message;
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
    if (!input.hasAttribute('aria-describedby')) {
        input.setAttribute('aria-describedby', errorId);
    }

    return message === '';
}
