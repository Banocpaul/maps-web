import test from 'node:test';
import assert from 'node:assert/strict';
import { initializePublicReportAddress, matchIncidentBarangay } from '../../resources/js/public-report-address.js';

const originalGlobals = { document: globalThis.document, window: globalThis.window, fetch: globalThis.fetch };
test.afterEach(() => Object.assign(globalThis, originalGlobals));

class Surface {
    constructor() { this.listeners = new Map(); this.value = ''; this.disabled = false; this.dataset = {}; }
    addEventListener(name, listener) {
        if (!this.listeners.has(name)) this.listeners.set(name, []);
        this.listeners.get(name).push(listener);
    }
    async emit(name, detail) { await Promise.all((this.listeners.get(name) || []).map(listener => listener({ detail }))); }
}

function setup(fetcher = async () => ({ ok: true, redirected: false, json: async () => ({ street: '', barangay_candidates: [] }) })) {
    const elements = new Map();
    const element = id => {
        if (!elements.has(id)) elements.set(id, new Surface());
        return elements.get(id);
    };
    const document = new Surface();
    document.getElementById = element;
    element('report-form').dataset.addressDetailsUrl = '/public-portal/report-address/details';
    element('incident-barangay').options = [
        { value: '', textContent: 'Select barangay' }, { value: '11', textContent: 'Plainview' },
        { value: '22', textContent: 'Hulo' }, { value: '33', textContent: 'New Zañiga' },
    ];
    element('report-latitude').value = '14.5720890';
    element('report-longitude').value = '121.0338128';
    globalThis.document = document;
    globalThis.window = { location: { origin: 'https://maps.test' }, setTimeout, clearTimeout };
    globalThis.fetch = fetcher;
    initializePublicReportAddress();
    return { element, emit: (name, detail) => document.emit(name, detail),
        choose: match => document.emit('public-report-address-selected', { latitude: 14.572089, longitude: 121.0338128, ...match }) };
}

test('barangay matching recognizes prefixes and accents without guessing from landmark names', () => {
    const app = setup();
    const select = app.element('incident-barangay');
    assert.equal(matchIncidentBarangay(select, [' Barangay Hulo ']), '22');
    assert.equal(matchIncidentBarangay(select, ['Brgy. New Zaniga']), '33');
    assert.equal(matchIncidentBarangay(select, ['Plainview Subdivision']), '');
    assert.equal(matchIncidentBarangay(select, ['Plainview', 'Hulo']), '');
});

test('a complete search result immediately fills street and barangay without another request', async () => {
    let calls = 0;
    const app = setup(async () => { calls++; throw new Error('No lookup needed'); });
    await app.choose({ street: 'Shaw Boulevard', barangay_candidates: ['Barangay Plainview'] });
    assert.equal(app.element('report-street').value, 'Shaw Boulevard');
    assert.equal(app.element('incident-barangay').value, '11');
    assert.equal(calls, 0);
});

test('landmark selection resolves street and barangay from the selected point', async () => {
    let requested;
    const app = setup(async url => {
        requested = new URL(url);
        return { ok: true, json: async () => ({ street: 'San Isidro Street', barangay_candidates: ['Brgy. Hulo'] }) };
    });
    app.element('report-street').value = 'Old street';
    app.element('incident-barangay').value = '11';
    await app.choose({ label: 'Plainview Subdivision, Mandaluyong', street: '', barangay_candidates: [] });
    assert.equal(requested.searchParams.get('latitude'), '14.572089');
    assert.equal(requested.searchParams.get('longitude'), '121.0338128');
    assert.equal(app.element('report-street').value, 'San Isidro Street');
    assert.equal(app.element('incident-barangay').value, '22');
    assert.equal(app.element('submit-report').disabled, false);
});

test('a late lookup cannot overwrite a newer selected address', async () => {
    let resolve;
    const app = setup(() => new Promise(done => { resolve = done; }));
    const old = app.choose({ street: '', barangay_candidates: [] });
    assert.equal(app.element('submit-report').disabled, true);
    await app.choose({ street: 'New Street', barangay_candidates: ['Plainview'] });
    resolve({ ok: true, json: async () => ({ street: 'Old Street', barangay_candidates: ['Hulo'] }) });
    await old;
    assert.equal(app.element('report-street').value, 'New Street');
    assert.equal(app.element('incident-barangay').value, '11');
    assert.equal(app.element('submit-report').disabled, false);
});

test('manual corrections made during lookup are preserved', async () => {
    let resolve;
    const app = setup(() => new Promise(done => { resolve = done; }));
    const pending = app.choose({ street: '', barangay_candidates: [] });
    app.element('report-street').value = 'Corrected Lane';
    await app.element('report-street').emit('input');
    app.element('incident-barangay').value = '33';
    await app.element('incident-barangay').emit('change');
    resolve({ ok: true, json: async () => ({ street: 'Nearby Street', barangay_candidates: ['Hulo'] }) });
    await pending;
    assert.equal(app.element('report-street').value, 'Corrected Lane');
    assert.equal(app.element('incident-barangay').value, '33');
});

test('provider failure preserves known fields and permits manual completion', async () => {
    const app = setup(async () => { throw new Error('Provider unavailable'); });
    await app.choose({ street: 'Known Street', barangay_candidates: [] });
    assert.equal(app.element('report-street').value, 'Known Street');
    assert.equal(app.element('incident-barangay').value, '');
    assert.equal(app.element('submit-report').disabled, false);
    assert.match(app.element('report-address-status').textContent, /manually/);
});

test('moving the pin cancels lookup and rejects stale address details', async () => {
    let resolve;
    const app = setup(() => new Promise(done => { resolve = done; }));
    const pending = app.choose({ street: '', barangay_candidates: [] });
    app.element('report-latitude').value = '14.5800000';
    await app.emit('public-report-pin-changed');
    resolve({ ok: true, json: async () => ({ street: 'Previous Street', barangay_candidates: ['Hulo'] }) });
    await pending;
    assert.equal(app.element('report-street').value, '');
    assert.equal(app.element('incident-barangay').value, '');
    assert.equal(app.element('submit-report').disabled, false);
});

test('ambiguous or missing barangay data prompts manual completion', async () => {
    const app = setup();
    await app.choose({ street: 'Known Street', barangay_candidates: ['Hulo', 'Plainview'] });
    assert.equal(app.element('incident-barangay').value, '');
    assert.match(app.element('report-address-status').textContent, /barangay manually/);
});
