export function initializeAddressSearch() {
    const root = document.querySelector('[data-address-search]');
    const input = document.getElementById('location');
    if (!root || !input) return;
    const button = root.querySelector('[data-address-search-button]');
    const status = root.querySelector('[data-address-search-status]');
    const results = root.querySelector('[data-address-search-results]');
    let version = 0;
    let controller;
    const invalidate = () => {
        version++;
        controller?.abort();
        results.replaceChildren();
        results.hidden = true;
        button.disabled = false;
    };
    input.addEventListener('input', invalidate);
    document.getElementById('barangay_id')?.addEventListener('change', invalidate);
    input.form?.addEventListener('reset', invalidate);
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); button.click(); }
        if (event.key === 'Escape') invalidate();
    });
    button.addEventListener('click', async () => {
        invalidate();
        const query = input.value.trim();
        if (query.length < 3) { status.textContent = 'Enter at least three characters to search.'; return; }
        const currentVersion = version;
        controller = new AbortController();
        const activeController = controller;
        const timeout = window.setTimeout(() => activeController.abort(), 15000);
        button.disabled = true;
        status.textContent = 'Searching addresses near Mandaluyong…';
        try {
            const url = new URL(root.dataset.searchUrl, window.location.origin);
            url.searchParams.set('q', query.slice(0, 150));
            const response = await fetch(url, {signal: activeController.signal, credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            if (!response.ok || response.redirected) throw new Error('Search failed');
            const data = await response.json();
            if (currentVersion !== version) return;
            const matches = data.results || [];
            status.textContent = matches.length ? 'Choose a result, then verify the barangay and map pin.' : 'No matches found. Try a nearby landmark or enter the address manually and place the pin.';
            for (const match of matches) {
                const choice = document.createElement('button');
                choice.type = 'button';
                choice.className = 'block w-full border-b border-slate-100 bg-white px-4 py-3 text-left text-sm text-slate-700 hover:bg-sky-50 focus:bg-sky-50';
                choice.textContent = match.label;
                choice.addEventListener('click', () => {
                    input.value = match.label.slice(0, 255);
                    const street = document.getElementById('street');
                    if (street && match.street) street.value = match.street.slice(0, 255);
                    document.getElementById('latitude').value = Number(match.latitude).toFixed(7);
                    document.getElementById('longitude').value = Number(match.longitude).toFixed(7);
                    document.dispatchEvent(new CustomEvent('incident-address-selected', {detail: match}));
                    invalidate();
                    status.textContent = 'Address selected. Verify the barangay and drag the pin to the exact incident spot.';
                });
                results.append(choice);
            }
            results.hidden = matches.length === 0;
        } catch (error) {
            if (currentVersion === version) status.textContent = 'Search could not complete. Try again, or enter the address manually and select the map location.';
        } finally {
            window.clearTimeout(timeout);
            if (currentVersion === version) button.disabled = false;
        }
    });
}
