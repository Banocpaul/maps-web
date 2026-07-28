<section
    id="dataset-management"
    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
>
    <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">
                Training Data
            </p>

            <h2 class="mt-1 text-xl font-semibold text-slate-950">
                Flood Dataset Management
            </h2>

            <p class="mt-1 text-sm text-slate-600">
                Add, review, edit, or remove verified flood observations for future model retraining.
            </p>
        </div>

        <button
            id="dataset-add-button"
            type="button"
            class="inline-flex items-center justify-center rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-800"
        >
            Add Flood Record
        </button>
    </div>

    <div class="grid grid-cols-2 gap-4 border-b border-slate-200 bg-slate-50 px-5 py-5 sm:px-6 lg:grid-cols-5">
        @foreach ([
            ['id' => 'dataset-total', 'label' => 'Total Records', 'class' => 'text-slate-950'],
            ['id' => 'dataset-included', 'label' => 'Included', 'class' => 'text-sky-700'],
            ['id' => 'dataset-high', 'label' => 'High Risk', 'class' => 'text-red-600'],
            ['id' => 'dataset-medium', 'label' => 'Medium Risk', 'class' => 'text-amber-600'],
            ['id' => 'dataset-low', 'label' => 'Low Risk', 'class' => 'text-emerald-600'],
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
            placeholder="Search barangay, source, waterway, or remarks..."
            class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 lg:max-w-md"
        >

        <select
            id="dataset-risk-filter"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
        >
            <option value="all">All risk levels</option>
            <option value="High">High risk</option>
            <option value="Medium">Medium risk</option>
            <option value="Low">Low risk</option>
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
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Rainfall 24h</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Depth</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Duration</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Risk</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Training</th>
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
                    Enter an actual and verified flood observation.
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

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700" for="record-observed_at">
                        Observation Date and Time
                    </label>
                    <input
                        id="record-observed_at"
                        name="observed_at"
                        type="datetime-local"
                        required
                        class="mt-2 block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                    >
                </div>

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
                        @foreach ([
                            'Addition Hills', 'Bagong Silang', 'Barangka Ibaba', 'Barangka Ilaya',
                            'Barangka Itaas', 'Buayang Bato', 'Burol', 'Daang Bakal',
                            'Hagdan Bato Itaas', 'Hagdan Bato Libis', 'Harapin ang Bukas',
                            'Highway Hills', 'Hulo', 'Mabini-J. Rizal', 'Malamig', 'Mauway',
                            'Namayan', 'New Zañiga', 'Old Zañiga', 'Pag-Asa', 'Plainview',
                            'Pleasant Hills', 'Poblacion', 'San Jose', 'Vergara',
                            'Wack-Wack Greenhills'
                        ] as $barangay)
                            <option value="{{ $barangay }}">{{ $barangay }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="record-data_source">
                        Data Source
                    </label>
                    <input
                        id="record-data_source"
                        name="data_source"
                        type="text"
                        placeholder="CDRRMO field report"
                        class="mt-2 block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="record-risk_level">
                        Verified Risk Level
                    </label>
                    <select
                        id="record-risk_level"
                        name="risk_level"
                        required
                        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"
                    >
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>

                @foreach ([
                    ['rainfall_24h_mm', 'Rainfall 24 Hours', '0.01'],
                    ['rainfall_3d_mm', 'Rainfall 3 Days', '0.01'],
                    ['rainfall_7d_mm', 'Rainfall 7 Days', '0.01'],
                    ['temperature_c', 'Temperature', '0.01'],
                    ['humidity_pct', 'Humidity', '0.01'],
                    ['wind_speed_kph', 'Wind Speed', '0.01'],
                    ['tide_level_m', 'Tide Level', '0.01'],
                    ['flood_depth_mm', 'Actual Flood Depth', '0.01'],
                    ['duration_hours', 'Actual Duration', '0.01'],
                    ['elevation_m', 'Elevation', '0.01'],
                    ['distance_to_waterway_m', 'Distance to Waterway', '0.01'],
                    ['drainage_index', 'Drainage Index', '0.0001'],
                    ['impervious_surface_ratio', 'Impervious Surface Ratio', '0.0001'],
                    ['population_density_per_km2', 'Population Density', '0.01'],
                    ['historical_flood_count_5y', 'Historical Flood Count (5y)', '1'],
                ] as [$name, $label, $step])
                    <div>
                        <label class="block text-sm font-medium text-slate-700" for="record-{{ $name }}">
                            {{ $label }}
                        </label>
                        <input
                            id="record-{{ $name }}"
                            name="{{ $name }}"
                            type="number"
                            min="0"
                            step="{{ $step }}"
                            required
                            class="mt-2 block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        >
                    </div>
                @endforeach

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="record-storm_signal">
                        Storm Signal
                    </label>
                    <select
                        id="record-storm_signal"
                        name="storm_signal"
                        required
                        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"
                    >
                        <option value="0">No Signal</option>
                        <option value="1">Signal No. 1</option>
                        <option value="2">Signal No. 2</option>
                        <option value="3">Signal No. 3</option>
                        <option value="4">Signal No. 4</option>
                        <option value="5">Signal No. 5</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="record-wet_season">
                        Wet Season
                    </label>
                    <select
                        id="record-wet_season"
                        name="wet_season"
                        required
                        class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"
                    >
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700" for="record-nearest_waterway">
                        Nearest Waterway
                    </label>
                    <input
                        id="record-nearest_waterway"
                        name="nearest_waterway"
                        type="text"
                        class="mt-2 block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                    >
                </div>

                <div class="md:col-span-2 xl:col-span-4">
                    <label class="block text-sm font-medium text-slate-700" for="record-remarks">
                        Remarks
                    </label>
                    <textarea
                        id="record-remarks"
                        name="remarks"
                        rows="3"
                        class="mt-2 block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                    ></textarea>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const endpoint = @json(route('flood-dataset.index'));
    const csrfToken = @json(csrf_token());

    const modal = document.getElementById('dataset-modal');
    const form = document.getElementById('dataset-form');
    const tableBody = document.getElementById('dataset-table-body');
    const search = document.getElementById('dataset-search');
    const riskFilter = document.getElementById('dataset-risk-filter');

    let currentPage = 1;
    let lastPage = 1;
    let searchTimer = null;

    loadDataset();

    document.getElementById('dataset-add-button').addEventListener('click', openCreateModal);
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
            risk_level: riskFilter.value,
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
        document.getElementById('dataset-high').textContent = stats.high || 0;
        document.getElementById('dataset-medium').textContent = stats.medium || 0;
        document.getElementById('dataset-low').textContent = stats.low || 0;
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
                <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600">${formatNumber(record.rainfall_24h_mm)} mm</td>
                <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600">${formatNumber(record.flood_depth_mm)} mm</td>
                <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-600">${formatNumber(record.duration_hours)} hrs</td>
                <td class="whitespace-nowrap px-4 py-4">${riskBadge(record.risk_level)}</td>
                <td class="whitespace-nowrap px-4 py-4 text-sm">
                    ${record.include_in_training
                        ? '<span class="font-medium text-emerald-700">Included</span>'
                        : '<span class="font-medium text-slate-500">Excluded</span>'}
                </td>
                <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                    <button type="button" data-edit="${record.id}" class="font-semibold text-sky-700 hover:text-sky-900">Edit</button>
                    <button type="button" data-delete="${record.id}" class="ml-3 font-semibold text-red-600 hover:text-red-800">Delete</button>
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
        document.getElementById('record-risk_level').value = 'Low';
        document.getElementById('record-wet_season').value = '1';
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

            document.getElementById('record-wet_season').value = record.wet_season ? '1' : '0';

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

        payload.wet_season = Number(payload.wet_season);
        payload.storm_signal = Number(payload.storm_signal);
        payload.include_in_training = true;

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
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
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

    function riskBadge(risk) {
        const classes = risk === 'High'
            ? 'bg-red-100 text-red-800'
            : risk === 'Medium'
                ? 'bg-amber-100 text-amber-800'
                : 'bg-emerald-100 text-emerald-800';

        return `<span class="rounded-full px-2.5 py-1 text-xs font-semibold ${classes}">${escapeHtml(risk)}</span>`;
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