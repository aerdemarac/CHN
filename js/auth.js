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

    form.addEventListener('submit', (event) => {
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
            event.preventDefault();
            (emailOk ? form.password : form.email).focus();
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

    form.addEventListener('submit', (event) => {
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
            event.preventDefault();
            invalid[0].focus();
        } else {
            form.phone.value = normalizePhone(form.phone.value);
            form.national_id.value = form.nationality.value === 'TR'
                ? form.national_id.value.replace(/\s/g, '')
                : form.national_id.value.trim();
        }
    });
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
