export function initializeProfileDialog() {
    const dialog = document.querySelector('[data-profile-dialog]');
    if (!dialog) return;
    const content = dialog.querySelector('[data-profile-content]');
    let opener;
    let previousOverflow;
    let controller;

    const open = async (button) => {
        if (dialog.open) return;
        opener = button;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        dialog.showModal();
        content.textContent = 'Loading profile…';
        controller = new AbortController();
        try {
            const response = await fetch(dialog.dataset.profileUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store', signal: controller.signal,
            });
            if (!response.ok || response.redirected) throw new Error('Profile unavailable');
            content.innerHTML = await response.text();
        } catch (error) {
            if (error.name !== 'AbortError') {
                content.textContent = 'Unable to load your profile. Refresh the page and sign in again if needed.';
            }
        }
    };

    document.querySelectorAll('[data-profile-open]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            open(button);
        });
    });
    dialog.querySelector('[data-profile-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        const bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
    });
    dialog.addEventListener('close', () => {
        controller?.abort();
        document.body.style.overflow = previousOverflow;
        opener?.focus();
    });
    if (dialog.dataset.profileReopen === 'true') open(document.querySelector('[data-profile-open]'));
}
