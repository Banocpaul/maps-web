import { normalizeBarangay } from './barangay-location.js';

// Known Mandaluyong locality names returned in structured address metadata.
// Keep this explicit: arbitrary landmark names must not select an alert barangay.
const barangayAliases = new Map([
    ['plainviewsubdivision', 'plainview'],
]);

export function matchIncidentBarangay(select, candidates) {
    const names = (Array.isArray(candidates) ? candidates : []).map(name => {
        const normalized = normalizeBarangay(String(name).trim());
        return barangayAliases.get(normalized) ?? normalized;
    });
    const options = [...select.options].filter(option => option.value && names.includes(normalizeBarangay(option.textContent.trim())));
    return options.length === 1 ? options[0].value : '';
}

export function initializePublicReportAddress() {
    const form = document.getElementById('report-form');
    const select = document.getElementById('incident-barangay');
    const street = document.getElementById('report-street');
    const status = document.getElementById('report-address-status');
    if (!form?.dataset.addressDetailsUrl || !select || !street || !status) return;

    const latitude = document.getElementById('report-latitude');
    const longitude = document.getElementById('report-longitude');
    const submit = document.getElementById('submit-report');
    let version = 0, streetEdits = 0, barangayEdits = 0;
    let controller, pending = false;
    const validPin = () => latitude.value.trim() !== '' && longitude.value.trim() !== ''
        && Number.isFinite(Number(latitude.value)) && Number.isFinite(Number(longitude.value))
        && Math.abs(Number(latitude.value)) <= 90 && Math.abs(Number(longitude.value)) <= 180;
    const cancel = () => {
        version++;
        controller?.abort();
        if (pending) {
            pending = false;
            submit.disabled = !validPin();
            status.textContent = 'Verify the incident barangay and street for the selected map pin.';
        }
    };
    street.addEventListener('input', () => streetEdits++);
    select.addEventListener('change', () => barangayEdits++);
    document.addEventListener('public-report-pin-changed', cancel);
    document.getElementById('report-dialog')?.addEventListener('close', cancel);
    document.getElementById('report-address-query')?.addEventListener('input', cancel);
    document.getElementById('report-address-search')?.addEventListener('click', cancel);

    document.addEventListener('public-report-address-selected', async event => {
        cancel();
        const current = version;
        const match = event.detail;
        street.value = String(match.street || '').slice(0, 255);
        select.value = matchIncidentBarangay(select, match.barangay_candidates);
        if (street.value && select.value) {
            status.textContent = 'Barangay and street filled. Verify the fields and map pin before submitting.';
            return;
        }

        const originalStreetEdits = streetEdits;
        const originalBarangayEdits = barangayEdits;
        const activeController = new AbortController();
        controller = activeController;
        const timer = window.setTimeout(() => activeController.abort(), 15000);
        pending = true;
        submit.disabled = true;
        status.textContent = 'Finding barangay and street for the selected address…';
        try {
            const url = new URL(form.dataset.addressDetailsUrl, window.location.origin);
            url.searchParams.set('latitude', String(match.latitude));
            url.searchParams.set('longitude', String(match.longitude));
            const response = await fetch(url, {signal: activeController.signal, credentials: 'same-origin', headers: {'Accept': 'application/json'}});
            if (!response.ok || response.redirected) throw new Error('Address details unavailable');
            const details = await response.json();
            if (current !== version) return;
            if (!street.value && streetEdits === originalStreetEdits) street.value = String(details.street || '').slice(0, 255);
            if (!select.value && barangayEdits === originalBarangayEdits) select.value = matchIncidentBarangay(select, details.barangay_candidates);
            const missing = [];
            if (!select.value) missing.push('barangay');
            if (!street.value.trim()) missing.push('street');
            status.textContent = missing.length
                ? 'Address selected. Please complete the '+missing.join(' and ')+' manually and verify the map pin.'
                : 'Barangay and street filled. Verify the fields and map pin before submitting.';
        } catch {
            if (current === version) status.textContent = 'Address details unavailable. Select the barangay and enter the street manually.';
        } finally {
            window.clearTimeout(timer);
            if (current === version) {
                pending = false;
                controller = undefined;
                submit.disabled = !validPin();
            }
        }
    });
}
