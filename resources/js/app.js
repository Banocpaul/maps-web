document.addEventListener("DOMContentLoaded", () => {
    initializeSidebar();
    initializeDismissibleAlerts();
});

/**
 * Controls the mobile application sidebar.
 */
function initializeSidebar() {
    const sidebar = document.getElementById("application-sidebar");
    const overlay = document.getElementById("sidebar-overlay");
    const openButton = document.getElementById("sidebar-open-button");
    const closeButton = document.getElementById("sidebar-close-button");

    if (!sidebar || !overlay) {
        return;
    }

    const openSidebar = () => {
        sidebar.classList.remove("-translate-x-full");
        overlay.classList.remove("hidden");
        document.body.classList.add("overflow-hidden");

        openButton?.setAttribute("aria-expanded", "true");
        overlay.setAttribute("aria-hidden", "false");
    };

    const closeSidebar = () => {
        sidebar.classList.add("-translate-x-full");
        overlay.classList.add("hidden");
        document.body.classList.remove("overflow-hidden");

        openButton?.setAttribute("aria-expanded", "false");
        overlay.setAttribute("aria-hidden", "true");
    };

    openButton?.addEventListener("click", openSidebar);
    closeButton?.addEventListener("click", closeSidebar);
    overlay.addEventListener("click", closeSidebar);

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeSidebar();
        }
    });

    window.addEventListener("resize", () => {
        if (window.innerWidth >= 1024) {
            overlay.classList.add("hidden");
            document.body.classList.remove("overflow-hidden");
            overlay.setAttribute("aria-hidden", "true");
        }
    });
}

/**
 * Enables reusable dismiss buttons for alerts.
 *
 * Usage:
 * <button data-dismiss-alert>...</button>
 */
function initializeDismissibleAlerts() {
    document.querySelectorAll("[data-dismiss-alert]").forEach((button) => {
        button.addEventListener("click", () => {
            const alert = button.closest('[role="alert"]');

            if (!alert) {
                return;
            }

            alert.remove();
        });
    });
}