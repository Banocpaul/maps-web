import { initializeRecipientSearch } from "./sms-recipient-search.js";

document.addEventListener("DOMContentLoaded", () => {
    initializeSidebar();
    initializeDismissibleAlerts();
    initializeRecipientSearch();
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
 * Enables dismiss buttons and optional timers for alerts and panels.
 *
 * Usage:
 * <button data-dismiss-alert>...</button>
 */
function initializeDismissibleAlerts() {
    const timers = new WeakMap();

    document.querySelectorAll("[data-auto-dismiss]").forEach((panel) => {
        const delay = Number(panel.dataset.autoDismiss);
        if (Number.isFinite(delay) && delay > 0) {
            timers.set(panel, window.setTimeout(() => panel.remove(), delay));
        }
    });

    document.querySelectorAll("[data-dismiss-alert]").forEach((button) => {
        button.addEventListener("click", () => {
            const alert = button.closest('[data-dismissible-panel], [role="alert"]');

            if (!alert) {
                return;
            }

            window.clearTimeout(timers.get(alert));
            alert.remove();
        });
    });
}
