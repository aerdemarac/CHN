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

    function initLogoutButton() {
        const button = document.querySelector('.topbar [data-logout-button]');
        if (!button) return;

        button.addEventListener('click', async () => {
            if (button.disabled) return;
            const logoutUrl = button.dataset.logoutUrl || '';
            if (!logoutUrl) return;

            button.disabled = true;
            button.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(logoutUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({}),
                });

                let body = null;
                try {
                    body = await response.json();
                } catch {
                    body = null;
                }

                if (response.ok && body && body.ok === true) {
                    const target = safeRedirect(body.data?.redirect) || safeRedirect(button.dataset.loginUrl);
                    if (target) {
                        window.location.assign(target);
                        return;
                    }
                }
            } catch {
                // Handled in finally by re-enabling the button.
            }

            button.disabled = false;
            button.removeAttribute('aria-busy');
        });
    }

    function initTopbar() {
        initTopbarDropdowns();
        initLogoutButton();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTopbar);
    } else {
        initTopbar();
    }
})();
