<section
    id="dataset-management"
    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
>
    <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">
                Flood Operations
            </p>

            <h2 class="mt-1 text-xl font-semibold text-slate-950">
                Recorded Flood Observations
            </h2>

            <p class="mt-1 text-sm text-slate-600">
                Record verified flooding by barangay, level, and mapped road extent.
            </p>
        </div>

        @if (auth()->user()?->hasPermission('flood.create'))
            <button
                id="dataset-add-button"
                type="button"
                class="inline-flex items-center justify-center rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-800"
            >
                Add Flood Record
            </button>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-4 border-b border-slate-200 bg-slate-50 px-5 py-5 sm:px-6 lg:grid-cols-6">
        @foreach ([
            ['id' => 'dataset-total', 'label' => 'Total Records', 'class' => 'text-slate-950'],
            ['id' => 'dataset-included', 'label' => 'Included', 'class' => 'text-sky-700'],
            ['id' => 'dataset-level-a', 'label' => 'Level A', 'class' => 'text-emerald-600'],
            ['id' => 'dataset-level-b', 'label' => 'Level B', 'class' => 'text-amber-600'],
            ['id' => 'dataset-level-c', 'label' => 'Level C', 'class' => 'text-orange-600'],
            ['id' => 'dataset-level-d', 'label' => 'Level D', 'class' => 'text-red-600'],
        ] as $card)
            <article class="rounded-xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    {{ $card['label'] }}
                </p>

                <p id="{{ $card['id'] }}" class="mt-2 text-2xl font-bold {{ $card['class'] }}">
                    0
                </p>
            </article>
        @endforeach
    </div>

    <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:px-6 lg:flex-row lg:items-center">
        <input
            id="dataset-search"
            type="search"
            placeholder="Search barangay..."
            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 lg:max-w-md"
        >

        <select
            id="dataset-risk-filter"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
        >
            <option value="all">All flood levels</option>
            <option value="A">Level A</option>
            <option value="B">Level B</option>
            <option value="C">Level C</option>
            <option value="D">Level D</option>
        </select>

        <button
            id="dataset-refresh-button"
            type="button"
            class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
            Refresh
        </button>

        <p id="dataset-status" class="text-sm text-slate-500 lg:ml-auto">
            Loading dataset...
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Barangay</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Flood Level</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Mapped Extent</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-600">Actions</th>
                </tr>
            </thead>

            <tbody id="dataset-table-body" class="divide-y divide-slate-200 bg-white"></tbody>
        </table>
    </div>

    <div id="dataset-empty" class="hidden px-6 py-12 text-center text-sm text-slate-500">
        No flood training records found.
    </div>

    <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <p id="dataset-page-text" class="text-sm text-slate-500">Page 1</p>

        <div class="flex gap-2">
            <button
                id="dataset-prev"
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Previous
            </button>

            <button
                id="dataset-next"
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Next
            </button>
        </div>
    </div>
</section>

<div
    id="dataset-modal"
    class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/70 p-4"
    role="dialog"
    aria-modal="true"
>
    <div class="max-h-[92vh] w-full max-w-6xl overflow-y-auto rounded-2xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
            <div>
                <h2 id="dataset-modal-title" class="text-xl font-semibold text-slate-950">
                    Add Flood Record
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Select the barangay and flood level, then draw the flooded road extent.
                </p>
            </div>

            <button
                id="dataset-close-button"
                type="button"
                class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
            >
                ✕
            </button>
        </div>

        <form id="dataset-form" class="p-5 sm:p-6">
            @csrf
            <input id="dataset-record-id" type="hidden">

            <div
                id="dataset-form-errors"
                class="mb-5 hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            ></div>

            <input id="record-observed_at" name="observed_at" type="hidden">
            <input id="record-geometry_type" name="geometry_type" type="hidden">
            <input id="record-geometry_geojson" name="geometry_geojson" type="hidden">
            <input id="record-latitude" name="latitude" type="hidden">
            <input id="record-longitude" name="longitude" type="hidden">
            <input id="record-extent_length_m" name="extent_length_m" type="hidden">
            <input id="record-affected_area_m2" name="affected_area_m2" type="hidden">

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="record-barangay">
                        Barangay
                    </label>
                    <select
                        id="record-barangay"
                        name="barangay"
                        required
                        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"
                    >
                        <option value="">Select barangay</option>
                        @foreach ($barangayNames as $barangay)
                            <option value="{{ $barangay }}">{{ $barangay }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="record-flood_level_code">
                        Flood Level
                    </label>
                    <select
                        id="record-flood_level_code"
                        name="flood_level_code"
                        required
                        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"
                    >
                        <option value="A">A — 0.5 ft</option>
                        <option value="B">B — 1.5 ft</option>
                        <option value="C">C — 3.0 ft</option>
                        <option value="D">D — 4.0 ft</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium text-slate-700">Flood Extent on GIS Map</p>
                            <p class="mt-1 text-xs text-slate-500">Select the line tool, then click along the flooded road. Double-click the final point to finish.</p>
                        </div>
                        <button id="dataset-clear-map" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Clear drawing</button>
                    </div>
                    <div id="dataset-flood-map" class="mt-3 h-96 w-full rounded-xl border border-slate-300"></div>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm"><span class="text-slate-500">Map:</span> <strong id="dataset-geometry-label">No extent drawn</strong></p>
                        <p class="rounded-lg bg-slate-50 px-3 py-2 text-sm"><span class="text-slate-500">Length:</span> <strong id="dataset-length-label">—</strong></p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                <button
                    id="dataset-cancel-button"
                    type="button"
                    class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Cancel
                </button>

                <button
                    id="dataset-save-button"
                    type="submit"
                    class="rounded-xl bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-800 disabled:opacity-60"
                >
                    Save Record
                </button>
            </div>
        </form>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css">
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const endpoint = @json(route('flood-dataset.index'));
    const csrfToken = @json(csrf_token());
    const canCreate = @json(auth()->user()?->hasPermission('flood.create') ?? false);
    const canEdit = @json(auth()->user()?->hasPermission('flood.edit') ?? false);
    const canDelete = @json(auth()->user()?->hasPermission('flood.delete') ?? false);

    const modal = document.getElementById('dataset-modal');
    const form = document.getElementById('dataset-form');
    const tableBody = document.getElementById('dataset-table-body');
    const search = document.getElementById('dataset-search');
    const riskFilter = document.getElementById('dataset-risk-filter');

    let currentPage = 1;
    let lastPage = 1;
    let searchTimer = null;
    let floodMap = null;
    let drawnItems = null;

    loadDataset();

    document.getElementById('dataset-add-button')?.addEventListener('click', openCreateModal);
    document.getElementById('dataset-close-button').addEventListener('click', closeModal);
    document.getElementById('dataset-cancel-button').addEventListener('click', closeModal);
    document.getElementById('dataset-refresh-button').addEventListener('click', () => loadDataset(currentPage));
    document.getElementById('dataset-prev').addEventListener('click', () => currentPage > 1 && loadDataset(currentPage - 1));
    document.getElementById('dataset-next').addEventListener('click', () => currentPage < lastPage && loadDataset(currentPage + 1));

    search.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadDataset(1), 350);
    });

    riskFilter.addEventListener('change', () => loadDataset(1));
    form.addEventListener('submit', saveRecord);

    async function loadDataset(page = 1) {
        setStatus('Loading dataset...');

        const query = new URLSearchParams({
            page,
            search: search.value.trim(),
            flood_level_code: riskFilter.value,
            per_page: 10
        });

        try {
            const response = await fetch(endpoint + '?' + query.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await readJson(response);

            if (!response.ok) {
                throw new Error(data.message || 'Unable to load dataset.');
            }

            renderStatistics(data.statistics || {});
            renderRecords(data.records?.data || []);

            currentPage = data.records?.current_page || 1;
            lastPage = data.records?.last_page || 1;

            document.getElementById('dataset-page-text').textContent =
                `Page ${currentPage} of ${lastPage}`;

            document.getElementById('dataset-prev').disabled = currentPage <= 1;
            document.getElementById('dataset-next').disabled = currentPage >= lastPage;

            setStatus(`${data.records?.total || 0} records found`);
        } catch (error) {
            console.error(error);
            setStatus(error.message);
        }
    }

    function renderStatistics(stats) {
        document.getElementById('dataset-total').textContent = stats.total || 0;
        document.getElementById('dataset-included').textContent = stats.included || 0;
        document.getElementById('dataset-level-a').textContent = stats.level_a || 0;
        document.getElementById('dataset-level-b').textContent = stats.level_b || 0;
        document.getElementById('dataset-level-c').textContent = stats.level_c || 0;
        document.getElementById('dataset-level-d').textContent = stats.level_d || 0;
    }

    function renderRecords(records) {
        tableBody.innerHTML = '';
        document.getElementById('dataset-empty').classList.toggle('hidden', records.length > 0);

        records.forEach(function (record) {
            const row = document.createElement('tr');
            row.className = 'hover:bg-slate-50';

            row.innerHTML = `
                <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600">${escapeHtml(formatDate(record.observed_at))}</td>
                <td class="whitespace-nowrap px-4 py-4 text-sm font-medium text-slate-950">${escapeHtml(record.barangay)}</td>
                <td class="whitespace-nowrap px-4 py-4">${levelBadge(record.flood_level_code)}</td>
                <td class="whitespace-nowrap px-4 py-4 text-sm">${statusBadge(record.flood_status)}</td>
                <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600">${extentLabel(record)}</td>
                <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                    ${canEdit ? `<button type="button" data-edit="${record.id}" class="font-semibold text-sky-700 hover:text-sky-900">Edit</button>` : ''}
                    ${canDelete ? `<button type="button" data-delete="${record.id}" class="ml-3 font-semibold text-red-600 hover:text-red-800">Delete</button>` : ''}
                </td>
            `;

            tableBody.appendChild(row);
        });

        tableBody.querySelectorAll('[data-edit]').forEach(button => {
            button.addEventListener('click', () => editRecord(button.dataset.edit));
        });

        tableBody.querySelectorAll('[data-delete]').forEach(button => {
            button.addEventListener('click', () => deleteRecord(button.dataset.delete));
        });
    }

    function openCreateModal() {
        form.reset();
        document.getElementById('dataset-record-id').value = '';
        document.getElementById('dataset-modal-title').textContent = 'Add Flood Record';
        document.getElementById('record-observed_at').value = toLocalDateTime(new Date());
        document.getElementById('record-flood_level_code').value = 'A';
        clearMapDrawing();
        hideErrors();
        showModal();
    }

    async function editRecord(id) {
        try {
            const response = await fetch(endpoint + '/' + id, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await readJson(response);

            if (!response.ok) {
                throw new Error(data.message || 'Unable to load record.');
            }

            const record = data.record;
            form.reset();

            document.getElementById('dataset-record-id').value = record.id;
            document.getElementById('dataset-modal-title').textContent = 'Edit Flood Record';

            Object.entries(record).forEach(([name, value]) => {
                const field = form.elements.namedItem(name);
                if (field) {
                    field.value = name === 'observed_at'
                        ? toLocalDateTime(value)
                        : (value ?? '');
                }
            });

            loadGeometry(record.geometry_geojson);

            hideErrors();
            showModal();
        } catch (error) {
            alert(error.message);
        }
    }

    async function saveRecord(event) {
        event.preventDefault();

        const saveButton = document.getElementById('dataset-save-button');
        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';
        hideErrors();

        const id = document.getElementById('dataset-record-id').value;
        const payload = Object.fromEntries(new FormData(form).entries());

        payload.geometry_geojson = JSON.parse(payload.geometry_geojson || 'null');

        try {
            const response = await fetch(id ? endpoint + '/' + id : endpoint, {
                method: id ? 'PUT' : 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            const data = await readJson(response);

            if (response.status === 422) {
                showErrors(data.errors || {});
                return;
            }

            if (!response.ok) {
                throw new Error(data.message || 'Unable to save record.');
            }

            closeModal();
            await loadDataset(currentPage);
        } catch (error) {
            showErrors({general: [error.message]});
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = 'Save Record';
        }
    }

    async function deleteRecord(id) {
        if (!confirm('Delete this flood training record?')) {
            return;
        }

        try {
            const response = await fetch(endpoint + '/' + id, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await readJson(response);

            if (!response.ok) {
                throw new Error(data.message || 'Unable to delete record.');
            }

            await loadDataset(currentPage);
        } catch (error) {
            alert(error.message);
        }
    }

    function showModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        initializeFloodMap();
        setTimeout(() => floodMap.invalidateSize(), 100);
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    function initializeFloodMap() {
        if (floodMap) return;

        floodMap = L.map('dataset-flood-map').setView([14.5794, 121.0359], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 20,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(floodMap);

        drawnItems = new L.FeatureGroup().addTo(floodMap);
        floodMap.addControl(new L.Control.Draw({
            position: 'topleft',
            draw: { marker: false, polyline: true, polygon: false, rectangle: false, circle: false, circlemarker: false },
            edit: { featureGroup: drawnItems, remove: true }
        }));

        floodMap.on(L.Draw.Event.CREATED, event => {
            drawnItems.clearLayers();
            drawnItems.addLayer(event.layer);
            storeGeometry(event.layer);
        });
        floodMap.on(L.Draw.Event.EDITED, event => event.layers.eachLayer(storeGeometry));
        floodMap.on(L.Draw.Event.DELETED, clearGeometryFields);
        document.getElementById('dataset-clear-map').addEventListener('click', clearMapDrawing);
    }

    function storeGeometry(layer) {
        const geometry = layer.toGeoJSON().geometry;
        const center = layer instanceof L.Marker ? layer.getLatLng() : layer.getBounds().getCenter();
        let length = null;
        let area = null;

        if (geometry.type === 'LineString') {
            const points = layer.getLatLngs();
            length = points.slice(1).reduce((total, point, index) => total + points[index].distanceTo(point), 0);
        } else if (geometry.type === 'Polygon') {
            area = L.GeometryUtil.geodesicArea(layer.getLatLngs()[0]);
        }

        document.getElementById('record-geometry_type').value = geometry.type;
        document.getElementById('record-geometry_geojson').value = JSON.stringify(geometry);
        document.getElementById('record-latitude').value = center.lat.toFixed(7);
        document.getElementById('record-longitude').value = center.lng.toFixed(7);
        document.getElementById('record-extent_length_m').value = length === null ? '' : length.toFixed(2);
        document.getElementById('record-affected_area_m2').value = area === null ? '' : area.toFixed(2);
        updateMeasurementLabels(geometry.type, length, area);
    }

    function loadGeometry(geometry) {
        initializeFloodMap();
        clearMapDrawing();
        if (!geometry) return;
        const layer = L.geoJSON({ type: 'Feature', properties: {}, geometry }).getLayers()[0];
        if (!layer) return;
        drawnItems.addLayer(layer);
        storeGeometry(layer);
        floodMap.fitBounds(layer instanceof L.Marker ? L.latLngBounds([layer.getLatLng()]) : layer.getBounds(), { maxZoom: 18, padding: [20, 20] });
    }

    function clearMapDrawing() {
        if (drawnItems) drawnItems.clearLayers();
        clearGeometryFields();
    }

    function clearGeometryFields() {
        ['geometry_type', 'geometry_geojson', 'latitude', 'longitude', 'extent_length_m', 'affected_area_m2']
            .forEach(name => document.getElementById(`record-${name}`).value = '');
        updateMeasurementLabels('', null, null);
    }

    function updateMeasurementLabels(type, length, area) {
        document.getElementById('dataset-geometry-label').textContent = type === 'LineString' ? 'Flood extent line' : 'No extent drawn';
        document.getElementById('dataset-length-label').textContent = length === null ? '—' : `${length.toFixed(1)} m`;
    }

    function showErrors(errors) {
        const box = document.getElementById('dataset-form-errors');
        const messages = Object.values(errors).flat();

        box.innerHTML = messages
            .map(message => `<div>• ${escapeHtml(message)}</div>`)
            .join('');

        box.classList.remove('hidden');
    }

    function hideErrors() {
        const box = document.getElementById('dataset-form-errors');
        box.classList.add('hidden');
        box.innerHTML = '';
    }

    function setStatus(message) {
        document.getElementById('dataset-status').textContent = message;
    }

    async function readJson(response) {
        const text = await response.text();

        try {
            return text ? JSON.parse(text) : {};
        } catch {
            throw new Error('The server returned an invalid response.');
        }
    }

    function levelBadge(level) {
        const classes = { A: 'bg-emerald-100 text-emerald-800', B: 'bg-amber-100 text-amber-800', C: 'bg-orange-100 text-orange-800', D: 'bg-red-100 text-red-800' };
        return `<span class="rounded-full px-2.5 py-1 text-xs font-semibold ${classes[level] || 'bg-slate-100 text-slate-700'}">${escapeHtml(level || 'Legacy')}</span>`;
    }

    function statusBadge(status) {
        const classes = status === 'Active' ? 'text-red-700' : status === 'Subsiding' ? 'text-amber-700' : 'text-emerald-700';
        return `<span class="font-semibold ${classes}">${escapeHtml(status || '—')}</span>`;
    }

    function extentLabel(record) {
        if (record.geometry_type === 'LineString') return `${formatNumber(record.extent_length_m)} m`;
        if (record.geometry_type === 'Polygon') return Number(record.affected_area_m2) >= 10000 ? `${(Number(record.affected_area_m2) / 10000).toFixed(2)} ha` : `${formatNumber(record.affected_area_m2)} m²`;
        return record.geometry_type === 'Point' ? 'Point' : '—';
    }

    function formatNumber(value) {
        const number = Number(value);
        return Number.isFinite(number) ? number.toFixed(1) : '0.0';
    }

    function formatDate(value) {
        return value ? new Date(value).toLocaleString() : '—';
    }

    function toLocalDateTime(value) {
        const date = value instanceof Date ? value : new Date(value);
        const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
        return local.toISOString().slice(0, 16);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});
</script>
@endpush
