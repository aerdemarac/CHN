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
            toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            icon?.classList.toggle('icon--eye', !show);
            icon?.classList.toggle('icon--eye-slash', show);
        });
    });
}

function initLoginForm() {
    const form = document.getElementById('login-form');
    if (!form) return;

    form.addEventListener('submit', (event) => {
        const emailOk = validateField(form.email, 'email-error', (value) => {
            if (!value) return 'Please enter your email address.';
            if (!form.email.validity.valid) return 'Please enter a valid email address.';
            return '';
        });

        const passwordOk = validateField(form.password, 'password-error', (value) => {
            if (!value) return 'Please enter your password.';
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

    const getNameError = (value, label) => {
        if (!value) return `Please enter your ${label}.`;
        if (!namePattern.test(value)) return `Please enter a valid ${label}.`;
        return '';
    };

    form.addEventListener('submit', (event) => {
        const password = form.password.value;

        const checks = [
            [form.first_name, 'first-name-error', (value) => getNameError(value, 'first name')],
            [form.last_name, 'last-name-error', (value) => getNameError(value, 'last name')],
            [form.birth_date, 'birth-date-error', (value) => {
                if (form.birth_date.validity.badInput) return 'Please enter a valid date of birth.';
                if (!value) return 'Please enter your date of birth.';
                if (!form.birth_date.validity.valid) return 'Please enter a valid date of birth.';
                return '';
            }],
            [form.nationality, 'nationality-error', (value) => {
                if (!value) return 'Please select your nationality.';
                return '';
            }],
            [form.national_id, 'national-id-error', (value) => {
                if (!value) return 'Please enter your national ID or passport number.';
                if (form.nationality.value === 'TR') {
                    if (!isValidTcKimlikNo(value.replace(/\s/g, ''))) return 'Please enter a valid T.C. Kimlik No.';
                    return '';
                }
                const length = value.replace(/[\s-]/g, '').length;
                if (!documentPattern.test(value) || length < 5 || length > 20) {
                    return 'Please enter a valid national ID or passport number.';
                }
                return '';
            }],
            [form.email, 'email-error', (value) => {
                if (!value) return 'Please enter your email address.';
                if (!form.email.validity.valid) return 'Please enter a valid email address.';
                return '';
            }],
            [form.phone, 'phone-error', (value) => {
                if (!value) return 'Please enter your phone number.';
                const digits = value.replace(/\D/g, '').length;
                if (!phonePattern.test(value) || digits < 7 || digits > 15) return 'Please enter a valid phone number.';
                return '';
            }],
            [form.password, 'password-error', () => {
                if (!password) return 'Please enter a password.';
                if (password.length < 8 || !/\p{L}/u.test(password) || !/\d/.test(password)) {
                    return 'Password does not meet the requirements.';
                }
                return '';
            }],
            [form.password_confirm, 'password-confirm-error', () => {
                if (!form.password_confirm.value) return 'Please confirm your password.';
                if (form.password_confirm.value !== password) return 'Passwords do not match.';
                return '';
            }],
        ];

        const invalid = checks
            .filter(([input, errorId, getError]) => !validateField(input, errorId, getError))
            .map(([input]) => input);

        if (invalid.length > 0) {
            event.preventDefault();
            invalid[0].focus();
        }
    });
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
