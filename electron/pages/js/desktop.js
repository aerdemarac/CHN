'use strict';

window.chnPage = {
    async start() {
        const context = await window.chnDesktop.context();
        document.documentElement.lang = context.lang;
        document.querySelectorAll('[data-i18n]').forEach((element) => {
            const value = context.strings[element.dataset.i18n];
            if (typeof value === 'string') {
                element.textContent = value;
            }
        });
        document.body.classList.remove('is-loading');
        return context;
    },

    fit() {
        return window.chnDesktop.fitContent(document.documentElement.scrollHeight);
    },

    focusFirst(element) {
        element.focus();
        window.addEventListener('focus', () => {
            if (document.activeElement === document.body) {
                element.focus();
            }
        }, { once: true });
    },

    closeOnEscape() {
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                window.chnDesktop.close();
            }
        });
    }
};
