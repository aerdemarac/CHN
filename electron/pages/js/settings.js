'use strict';

(async () => {
    const LOCAL_HOSTS = ['localhost', '127.0.0.1', '[::1]'];
    const URL_CHARACTERS = /^[A-Za-z0-9\-._~:/?#[\]@!$&'()*+,;=%]*$/;

    const context = await window.chnPage.start();
    const form = document.getElementById('settings-form');
    const input = document.getElementById('settings-url');
    const error = document.getElementById('settings-url-error');
    const saveButton = document.getElementById('settings-save');
    const restoreButton = document.getElementById('settings-restore');
    const hintId = 'settings-url-hint';

    function validate(value) {
        const trimmed = value.trim();
        if (trimmed === '') {
            return 'required';
        }
        if (!URL_CHARACTERS.test(trimmed)) {
            return 'characters';
        }
        let url;
        try {
            url = new URL(trimmed);
        } catch {
            return 'invalid';
        }
        if (url.username !== '' || url.password !== '' || url.hostname === '') {
            return 'invalid';
        }
        const isLocalHttp = url.protocol === 'http:' && LOCAL_HOSTS.includes(url.hostname);
        if (url.protocol !== 'https:' && !isLocalHttp) {
            return 'protocol';
        }
        return null;
    }

    function showError(code) {
        if (code === null) {
            error.hidden = true;
            error.textContent = '';
            input.removeAttribute('aria-invalid');
            input.setAttribute('aria-describedby', hintId);
        } else {
            error.textContent = context.strings[`settings.error_${code}`] ?? context.strings['settings.error_invalid'];
            error.hidden = false;
            input.setAttribute('aria-invalid', 'true');
            input.setAttribute('aria-describedby', `${hintId} ${error.id}`);
        }
        window.chnPage.fit();
    }

    input.value = context.serverUrl;
    document.getElementById('settings-default').textContent = context.defaultServerUrl;

    if (context.serverLocked) {
        document.getElementById('settings-locked').hidden = false;
        input.disabled = true;
        saveButton.disabled = true;
        restoreButton.disabled = true;
    }

    input.addEventListener('input', () => {
        const code = validate(input.value);
        if (code === 'characters' || input.getAttribute('aria-invalid') === 'true') {
            showError(code);
        }
    });

    restoreButton.addEventListener('click', () => {
        input.value = context.defaultServerUrl;
        showError(null);
        input.focus();
    });

    document.getElementById('settings-cancel').addEventListener('click', () => window.chnDesktop.close());

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const code = validate(input.value);
        if (code !== null) {
            showError(code);
            input.focus();
            return;
        }
        saveButton.disabled = true;
        const result = await window.chnDesktop.saveServerUrl(input.value.trim());
        if (!result.ok) {
            saveButton.disabled = false;
            showError(result.error);
            input.focus();
        }
    });

    window.chnPage.closeOnEscape();
    await window.chnPage.fit();
    if (input.disabled) {
        window.chnPage.focusFirst(document.getElementById('settings-cancel'));
    } else {
        window.chnPage.focusFirst(input);
        input.select();
    }
})();
