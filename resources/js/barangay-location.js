export function normalizeBarangay(name) {
    return String(name).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
        .replace(/hagdang/g, 'hagdan').replace(/greenhills east/g, 'greenhills').replace(/[^a-z0-9]/g, '');
}

function inRing(point, ring) {
    const [x, y] = point;
    let inside = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const [xi, yi] = ring[i];
        const [xj, yj] = ring[j];
        const cross = (x - xi) * (yj - yi) - (y - yi) * (xj - xi);
        if (Math.abs(cross) < 1e-12 && x >= Math.min(xi, xj) && x <= Math.max(xi, xj) && y >= Math.min(yi, yj) && y <= Math.max(yi, yj)) return true;
        if ((yi > y) !== (yj > y) && x < (xj - xi) * (y - yi) / (yj - yi) + xi) inside = !inside;
    }
    return inside;
}

export function containsPoint(point, geometry) {
    const polygons = geometry?.type === 'Polygon' ? [geometry.coordinates] : geometry?.type === 'MultiPolygon' ? geometry.coordinates : [];
    return polygons.some(rings => rings.length && inRing(point, rings[0]) && !rings.slice(1).some(ring => inRing(point, ring)));
}

export function initializeBarangayLocation() {
    const root = document.querySelector('[data-address-search]');
    const select = document.getElementById('barangay_id');
    if (!root || !select) return;
    const status = root.querySelector('[data-barangay-location-status]');
    let boundaries;
    let version = 0;
    let selectedAddress;
    document.addEventListener('incident-address-context', event => { selectedAddress = event.detail; });
    const load = () => {
        if (!boundaries) boundaries = fetch(root.dataset.boundaryUrl, {signal: AbortSignal.timeout(10000)})
            .then(response => { if (!response.ok) throw new Error('Boundaries unavailable'); return response.json(); })
            .catch(error => { boundaries = undefined; throw error; });
        return boundaries;
    };
    document.addEventListener('incident-coordinates-changed', async event => {
        const current = ++version;
        const {latitude, longitude} = event.detail;
        const fallback = selectedAddress && Math.abs(latitude - Number(selectedAddress.latitude)) < 0.000001 && Math.abs(longitude - Number(selectedAddress.longitude)) < 0.000001
            ? selectedAddress.barangayFallback : '';
        selectedAddress = undefined;
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) { status.textContent = ''; return; }
        status.textContent = 'Finding barangay from map location…';
        try {
            const data = await load();
            if (current !== version) return;
            const matches = data.features.filter(feature => containsPoint([longitude, latitude], feature.geometry));
            const names = [...new Set(matches.map(feature => normalizeBarangay(feature.properties.barangay)))];
            const options = [...select.options].filter(option => option.value && names.includes(normalizeBarangay(option.textContent)));
            if (names.length === 1 && options.length === 1) {
                select.value = options[0].value;
                select.dispatchEvent(new Event('change', {bubbles: true}));
                status.textContent = `Barangay automatically selected: ${options[0].textContent.trim()}. Verify the incident location.`;
            } else if (names.length === 0 && fallback) {
                select.value = fallback;
                select.dispatchEvent(new Event('change', {bubbles: true}));
                status.textContent = `Barangay selected from address details: ${select.selectedOptions[0].textContent.trim()}. Verify the map pin.`;
            } else {
                select.value = '';
                select.dispatchEvent(new Event('change', {bubbles: true}));
                status.textContent = names.length > 1 ? 'Pin is on a barangay boundary. Select the correct barangay manually.' : 'Location could not be matched to a barangay. Check the pin and select the barangay manually.';
            }
        } catch {
            if (current === version) status.textContent = 'Barangay lookup unavailable. Select the barangay manually.';
        }
    });
    root.closest('form')?.addEventListener('reset', () => { version++; status.textContent = ''; });
}
