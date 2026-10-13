export function initializeBackupLoading() {
    const labels = {
        create: ['Creating backup…', 'Please keep this page open until the backup finishes.'],
        verify: ['Checking backup integrity…', 'Please keep this page open while the backup is checked.'],
        restore: ['Restoring your backup…', 'A safety backup is created first. Please keep this page open until restoration finishes.'],
    };
    document.querySelectorAll('[data-backup-loading]').forEach(form => {
        const action = form.dataset.backupLoading;
        if (!labels[action]) return;
        const dialog = form.closest('dialog');
        let timer;
        let panel;
        let states;
        const reset = () => {
            clearInterval(timer);
            panel?.remove();
            form.removeAttribute('aria-busy');
            delete form.dataset.submitting;
            states?.forEach(({button, disabled, html}) => {button.disabled = disabled; button.innerHTML = html;});
        };
        dialog?.addEventListener('cancel', event => {
            if (form.dataset.submitting === 'true') event.preventDefault();
        });
        form.addEventListener('submit', event => {
            if (form.dataset.submitting === 'true') {event.preventDefault(); return;}
            if (event.defaultPrevented) return;
            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            states = [...form.querySelectorAll('button')].map(button => ({button, disabled: button.disabled, html: button.innerHTML}));
            states.forEach(({button}) => {button.disabled = true;});
            const submit = form.querySelector('button[type="submit"]');
            submit.textContent = labels[action][0];
            panel = document.createElement('div');
            panel.className = `maps-backup-loading maps-backup-loading-${action}`;
            panel.setAttribute('role', 'status');
            panel.setAttribute('aria-live', 'polite');
            const spinner = document.createElement('span');
            spinner.className = 'maps-backup-spinner';
            spinner.setAttribute('aria-hidden', 'true');
            const copy = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = labels[action][0];
            const detail = document.createElement('p');
            detail.textContent = labels[action][1];
            const elapsed = document.createElement('p');
            elapsed.setAttribute('aria-hidden', 'true');
            copy.append(title, detail, elapsed);
            panel.append(spinner, copy);
            form.insertBefore(panel, submit.parentElement);
            const start = Date.now();
            const tick = () => {elapsed.textContent = `Elapsed: ${Math.floor((Date.now()-start)/1000)} seconds`;};
            tick();
            timer = window.setInterval(tick, 1000);
            panel.scrollIntoView({block: 'nearest'});
        });
        // Browser Back may restore a page with disabled controls from its page cache.
        window.addEventListener('pageshow', event => {if (event.persisted) reset();});
    });
}
