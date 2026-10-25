
/* CHN - Shared Sidebar Interactions */

(() => {
    "use strict";

    const sidebar = document.getElementById("chn-sidebar");
    const toggle = document.getElementById("sidebar-menu-toggle");

    if (!sidebar || !toggle) {
        return;
    }

    const mobile = window.matchMedia("(max-width: 767px)");

    function setMenuState(open) {
        const isOpen = mobile.matches && open;

        sidebar.classList.toggle("is-open", isOpen);

        toggle.setAttribute("aria-expanded", String(isOpen));

        toggle.setAttribute(
            "aria-label",
            isOpen ? "Close navigation menu" : "Open navigation menu"
        );

        // Prevent keyboard access to the hidden mobile sidebar.
        sidebar.inert = mobile.matches && !isOpen;
    }

    // Start with the mobile menu closed.
    setMenuState(false);

    // Open or close when the menu button is clicked.
    toggle.addEventListener("click", () => {
        const isOpen = sidebar.classList.contains("is-open");
        setMenuState(!isOpen);
    });

    // Close the menu when Escape is pressed.
    document.addEventListener("keydown", (event) => {
        if (
            event.key === "Escape" &&
            sidebar.classList.contains("is-open")
        ) {
            setMenuState(false);
            toggle.focus();
        }
    });

    // Close the menu when clicking outside of it.
    document.addEventListener("click", (event) => {
        if (
            mobile.matches &&
            sidebar.classList.contains("is-open") &&
            !sidebar.contains(event.target) &&
            !toggle.contains(event.target)
        ) {
            setMenuState(false);
        }
    });

    // Reset the menu when switching screen sizes.
    mobile.addEventListener("change", () => {
        setMenuState(false);
    });
})();