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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTopbarDropdowns);
    } else {
        initTopbarDropdowns();
    }
})();
