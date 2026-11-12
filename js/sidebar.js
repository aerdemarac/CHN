/* CHN shared, dependency-free sidebar interactions. */
(() => {
    'use strict';

    function initSidebar() {
        const sidebar = document.getElementById('chn-sidebar');
        const toggle = document.getElementById('sidebar-menu-toggle');
        const backdrop = document.getElementById('chn-sidebar-backdrop');
        if (!sidebar || !toggle || !backdrop) return;

        const mobile = window.matchMedia('(max-width: 767px)');
        const icon = toggle.querySelector('.sidebar__toggle-icon');
        const label = toggle.querySelector('.sidebar__toggle-label');
        const isOpen = () => mobile.matches && sidebar.classList.contains('is-open');

        function update(open, focusTarget = false) {
            const actualOpen = mobile.matches && open;
            sidebar.classList.toggle('is-open', actualOpen);
            backdrop.classList.toggle('is-open', actualOpen);
            document.body.classList.toggle('chn-sidebar-open', actualOpen);
            toggle.setAttribute('aria-expanded', String(actualOpen));
            toggle.setAttribute('aria-label', actualOpen ? 'Close navigation menu' : 'Open navigation menu');
            if (label) label.textContent = actualOpen ? 'Close' : 'Menu';
            if (icon) {
                icon.classList.toggle('sidebar__toggle-icon--menu', !actualOpen);
                icon.classList.toggle('sidebar__toggle-icon--close', actualOpen);
            }
            // Off-canvas navigation must not be keyboard-focusable when closed.
            sidebar.inert = mobile.matches && !actualOpen;
            if (mobile.matches && !actualOpen) sidebar.setAttribute('aria-hidden', 'true');
            else sidebar.removeAttribute('aria-hidden');

            if (focusTarget) {
                if (actualOpen) sidebar.querySelector('a.sidebar__link[href]')?.focus();
                else toggle.focus();
            }
        }

        update(false);
        toggle.addEventListener('click', () => update(!isOpen(), true));
        backdrop.addEventListener('click', () => update(false, true));
        sidebar.addEventListener('click', (event) => {
            if (mobile.matches && event.target.closest('a.sidebar__link[href]')) update(false);
        });
        document.addEventListener('keydown', (event) => {
            if (!isOpen()) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                update(false, true);
                return;
            }
            if (event.key !== 'Tab') return;
            const links = [...sidebar.querySelectorAll('a.sidebar__link[href]')];
            const items = [toggle, ...links];
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
        if (typeof mobile.addEventListener === 'function') {
            mobile.addEventListener('change', () => update(false));
        } else {
            mobile.addListener(() => update(false));
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initSidebar);
    else initSidebar();
})();
