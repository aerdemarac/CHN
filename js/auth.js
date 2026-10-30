document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggles();
    initLoginForm();
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

function validateField(input, errorId, getError) {
    const message = getError(input.value.trim());
    const error = document.getElementById(errorId);

    error.textContent = message;
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
    input.setAttribute('aria-describedby', errorId);

    return message === '';
}
