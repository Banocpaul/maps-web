@extends('layouts.app')

@section('title', 'Flood GIS Mapping | M.A.P.S.')
@section('page-title', 'Flood GIS Mapping')
@section('page-description', 'Observed flood extents and affected barangays')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        #flood-gis-map { height: 65vh; min-height: 420px; z-index: 0; }
        .flood-gis-popup { min-width: 200px; }
        .flood-gis-popup p { margin: 6px 0; }
        .flood-record-button { display: block; width: 100%; padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .flood-record-button:hover { background: #eff6ff; }
        .flood-record-button:focus-visible { outline: 3px solid #2563eb; outline-offset: -3px; }
    </style>
@endpush

@section('content')
    <div class="space-y-6">
        <section class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-950">Flood GIS Map</h1>
                <p class="mt-2 text-sm text-slate-600">Active flooded stretches. Select a line to view its code and recorded time.</p>
            </div>
            <div class="flex gap-3">
                @if (auth()->user()->hasPermission('flood.create'))
                    <a href="{{ route('operational-records.flood.create') }}" class="rounded-xl bg-sky-700 px-4 py-2.5 font-semibold text-white">Plot flood</a>
                @endif
                <button id="refresh-flood-map" type="button" class="rounded-xl border border-blue-700 px-4 py-2.5 font-semibold text-blue-700">Refresh Map</button>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-4 lg:grid-cols-4" aria-label="Flood summary">
            @foreach (['active_floods' => 'Active Flood Observations', 'mapped_floods' => 'Mapped Flood Extents', 'barangays' => 'Affected Barangays', 'extent_length_m' => 'Mapped Extent Length (m)'] as $key => $label)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-slate-600">{{ $label }}</p>
                    <p id="flood-stat-{{ $key }}" class="mt-2 text-3xl font-bold text-slate-950">—</p>
                </article>
            @endforeach
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-end gap-4">
                <div>
                    <label for="flood-barangay-filter" class="block text-sm font-semibold text-slate-700">Barangay</label>
                    <select id="flood-barangay-filter" class="mt-1 rounded-lg border border-slate-300 px-3 py-2"><option value="">All barangays</option></select>
                </div>
                <div>
                    <label for="flood-level-filter" class="block text-sm font-semibold text-slate-700">Flood level code</label>
                    <select id="flood-level-filter" class="mt-1 rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">All levels</option>
                        @foreach (['A', 'B', 'C', 'D'] as $level)
                            <option value="{{ $level }}">Level {{ $level }}</option>
                        @endforeach
                    </select>
                </div>
                <p class="text-sm text-slate-600">Level colors:
                    @foreach (['A' => '#39FF14', 'B' => '#FFF200', 'C' => '#FF9500', 'D' => '#FF3B1F'] as $level => $color)
                        <span class="ml-2 inline-flex items-center gap-1"><span style="display:inline-block;width:18px;height:6px;background:{{ $color }};border:1px solid #475569" aria-hidden="true"></span>{{ $level }}</span>
                    @endforeach
                </p>
            </div>
            <p id="flood-map-status" role="status" aria-live="polite" class="mb-3 text-sm text-slate-600">Loading flood observations…</p>
            <div class="grid gap-4 xl:grid-cols-3">
                <div id="flood-gis-map" class="rounded-xl xl:col-span-2" aria-label="Active flood extent map"></div>
                <aside class="rounded-xl border border-slate-200">
                    <h2 class="border-b border-slate-200 p-4 font-semibold text-slate-950">Active Flood Observations</h2>
                    <div id="flood-record-list" style="max-height:65vh;overflow-y:auto"></div>
                </aside>
            </div>
        </section>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const status = document.getElementById('flood-map-status');
            const refresh = document.getElementById('refresh-flood-map');
            const barangay = document.getElementById('flood-barangay-filter');
            const level = document.getElementById('flood-level-filter');
            const list = document.getElementById('flood-record-list');
            if (typeof L === 'undefined') {
                status.textContent = 'The map library could not load. Check your connection and reload this page.';
                refresh.disabled = true;
                return;
            }
            const map = L.map('flood-gis-map').setView([14.5794, 121.0359], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);
            const extents = L.featureGroup().addTo(map);
            const colors = { A: '#39FF14', B: '#FFF200', C: '#FF9500', D: '#FF3B1F' };
            let records = [];
            let unmapped = 0;
            function escapeHtml(value) {
                const element = document.createElement('span');
                element.textContent = value ?? 'Not available';
                return element.innerHTML;
            }
            function observationTime(value) {
                if (!value) return 'Not available';
                return new Date(value).toLocaleString('en-PH', { timeZone: 'Asia/Manila' }) + ' (Manila time)';
            }
            function showRecords() {
                extents.clearLayers();
                list.replaceChildren();
                const visible = records.filter(record => (!barangay.value || record.barangay === barangay.value)
                    && (!level.value || record.level_code === level.value));
                visible.forEach(record => {
                    const name = `Flood observation #${record.id} in ${record.barangay || 'Mandaluyong'}`;
                    L.geoJSON(record.geometry, { style: { color: '#0f172a', weight: 10, opacity: 0.8 } }).addTo(extents);
                    const line = L.geoJSON(record.geometry, {
                        style: { color: colors[record.level_code] || '#64748b', weight: 6, opacity: 1 },
                    }).bindPopup(`<div class="flood-gis-popup"><strong>${escapeHtml(name)}</strong>
                        <p>Location: ${escapeHtml(record.location)}</p>
                        <p>Level code: ${escapeHtml(record.level_code)}</p>
                        <p>Status: ${escapeHtml(record.status)}</p>
                        <p>Observed: ${escapeHtml(observationTime(record.observed_at))}</p>
                        <p>Extent length: ${escapeHtml(record.length_m)} m</p>
                        ${record.manage_url ? `<a href="${escapeHtml(record.manage_url)}">Manage flood</a>` : ''}</div>`).addTo(extents);
                    line.eachLayer(layer => {
                        const path = layer.getElement();
                        if (!path) return;
                        path.setAttribute('tabindex', '0');
                        path.setAttribute('role', 'button');
                        path.setAttribute('aria-label', name);
                        path.addEventListener('keydown', event => {
                            if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                layer.openPopup();
                            }
                        });
                    });
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'flood-record-button';
                    button.textContent = `${name} · Level ${record.level_code || 'unclassified'} · ${record.location || 'Location not recorded'} · ${observationTime(record.observed_at)}`;
                    button.addEventListener('click', () => {
                        map.fitBounds(line.getBounds(), { padding: [30, 30], maxZoom: 17 });
                        line.getLayers()[0]?.openPopup();
                    });
                    list.appendChild(button);
                });
                if (extents.getLayers().length) map.fitBounds(extents.getBounds(), { padding: [30, 30], maxZoom: 16 });
                else {
                    map.setView([14.5794, 121.0359], 13);
                    const empty = document.createElement('p');
                    empty.className = 'p-4 text-sm text-slate-600';
                    empty.textContent = 'No mapped active floods match these filters.';
                    list.appendChild(empty);
                }
                status.textContent = `${visible.length} active flood extent(s) shown. `
                    + (unmapped ? `${unmapped} active observation(s) have missing or invalid extent geometry and cannot be mapped.` : '');
            }
            async function loadRecords() {
                refresh.disabled = true;
                status.textContent = 'Loading flood observations…';
                try {
                    const response = await fetch('{{ route('gis.data', request('hazard') === 'flood' ? ['hazard' => 'flood'] : []) }}', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store',
                    });
                    if (!response.ok) throw new Error('Request failed');
                    const data = await response.json();
                    records = data.floods || [];
                    unmapped = data.statistics.unmapped_floods;
                    Object.entries(data.statistics).forEach(([key, value]) => {
                        const element = document.getElementById(`flood-stat-${key}`);
                        if (element) element.textContent = value.toLocaleString('en-PH');
                    });
                    const selectedBarangay = barangay.value;
                    barangay.replaceChildren(new Option('All barangays', ''));
                    [...new Set(records.map(record => record.barangay).filter(Boolean))].sort().forEach(name => barangay.add(new Option(name, name)));
                    if ([...barangay.options].some(option => option.value === selectedBarangay)) barangay.value = selectedBarangay;
                    showRecords();
                } catch (error) {
                    status.textContent = 'Flood observations could not be refreshed. Check your connection or sign in again. Any previously displayed records may be out of date.';
                } finally {
                    refresh.disabled = false;
                }
            }
            refresh.addEventListener('click', loadRecords);
            barangay.addEventListener('change', showRecords);
            level.addEventListener('change', showRecords);
            loadRecords();
        });
    </script>
@endsection
