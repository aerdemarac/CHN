/* CHN shared, dependency-free top bar interactions. */
(() => {
    'use strict';

    function initTopbarDropdowns() {
        const dropdowns = [...document.querySelectorAll('.topbar [data-dropdown]')];
        if (dropdowns.length === 0) return;

        const closeAll = (except = null) => {
            dropdowns.forEach((dropdown) => {
                if (dropdown === except) return;
                const toggle = dropdown.querySelector('[data-dropdown-toggle]');
                const menu = dropdown.querySelector('[data-dropdown-menu]');
                if (menu) menu.hidden = true;
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            });
        };

        dropdowns.forEach((dropdown) => {
            const toggle = dropdown.querySelector('[data-dropdown-toggle]');
            const menu = dropdown.querySelector('[data-dropdown-menu]');
            if (!toggle || !menu) return;

            toggle.addEventListener('click', (event) => {
                event.stopPropagation();
                const willOpen = menu.hidden;
                closeAll(willOpen ? dropdown : null);
                menu.hidden = !willOpen;
                toggle.setAttribute('aria-expanded', String(willOpen));

                if (willOpen) {
                    const target = menu.querySelector('.is-active, a[href], button:not(:disabled)');
                    target?.focus();
                }
            });
        });

        document.addEventListener('click', (event) => {
            const clickedInside = dropdowns.some((dropdown) => dropdown.contains(event.target));
            if (!clickedInside) closeAll();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            const openDropdown = dropdowns.find((dropdown) => {
                const menu = dropdown.querySelector('[data-dropdown-menu]');
                return menu && !menu.hidden;
            });
            if (!openDropdown) return;

            event.preventDefault();
            closeAll();
            openDropdown.querySelector('[data-dropdown-toggle]')?.focus();
        });

        initLanguageSelector(closeAll);
    }

    function initLanguageSelector(closeAll) {
        const dropdown = document.getElementById('chn-lang-dropdown');
        const toggle = document.getElementById('chn-lang-toggle');
        const menu = document.getElementById('chn-lang-menu');
        if (!dropdown || !toggle || !menu) return;

        const currentLabel = toggle.querySelector('[data-lang-current]');
        const options = [...menu.querySelectorAll('.topbar__lang-option')];

        const applyLanguage = (lang) => {
            const match = options.find((opt) => opt.dataset.lang === lang);
            if (!match) return;

            options.forEach((opt) => {
                const active = opt === match;
                opt.classList.toggle('is-active', active);
                opt.setAttribute('aria-checked', String(active));
            });

            if (currentLabel && match.dataset.langCode) {
                currentLabel.textContent = match.dataset.langCode;
            }
            document.documentElement.lang = lang;
        };

        try {
            const params = new URLSearchParams(window.location.search);
            const saved = localStorage.getItem('chn_lang');
            if (!params.has('lang') && saved) {
                applyLanguage(saved);
            }
        } catch {
            // Ignore storage access errors in restricted contexts.
        }

        options.forEach((option) => {
            option.addEventListener('click', () => {
                const lang = option.dataset.lang;
                if (lang) {
                    applyLanguage(lang);
                    try {
                        localStorage.setItem('chn_lang', lang);
                    } catch {
                        // Ignore storage errors.
                    }
                    document.dispatchEvent(new CustomEvent('chn:languagechange', { detail: { lang } }));
                }
                closeAll();
                toggle.focus();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTopbarDropdowns);
    } else {
        initTopbarDropdowns();
    }
})();
