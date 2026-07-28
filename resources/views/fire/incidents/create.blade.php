@extends('layouts.app')

@section('title', 'Add Fire Incident | M.A.P.S.')
@section('page-title', 'Add Fire Incident')
@section('page-description', 'Record a new fire incident in the M.A.P.S. system')

@section('content')
    <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
>

    <style>
        .incident-form-page {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .incident-form-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .incident-form-header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #111827;
        }

        .incident-form-header p {
            margin: 0.35rem 0 0;
            color: #6b7280;
        }

        .incident-panel {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.8rem;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        }

        .incident-section-title {
            margin: 0 0 1rem;
            font-size: 1rem;
            color: #111827;
        }

        .incident-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .incident-field {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .incident-field-full {
            grid-column: 1 / -1;
        }

        .incident-field label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #374151;
        }

        .incident-required {
            color: #b91c1c;
        }

        .incident-field input,
        .incident-field select,
        .incident-field textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 0.55rem;
            padding: 0.7rem 0.8rem;
            background: #ffffff;
            color: #111827;
            box-sizing: border-box;
        }

        .incident-field input,
        .incident-field select {
            min-height: 43px;
        }

        .incident-field textarea {
            min-height: 130px;
            resize: vertical;
        }

        .incident-field input:focus,
        .incident-field select:focus,
        .incident-field textarea:focus {
            outline: none;
            border-color: #b91c1c;
            box-shadow: 0 0 0 3px rgba(185, 28, 28, 0.1);
        }

        .incident-coordinate-input {
            background: #f9fafb !important;
            cursor: not-allowed;
        }

        .incident-help {
            color: #6b7280;
            font-size: 0.78rem;
            line-height: 1.5;
        }

        .incident-error {
            color: #b91c1c;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .incident-alert {
            border-radius: 0.6rem;
            padding: 1rem;
        }

        .incident-alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .incident-alert-error ul {
            margin: 0.5rem 0 0;
            padding-left: 1.25rem;
        }

        .incident-map-instruction {
            margin: 0 0 1rem;
            padding: 0.85rem 1rem;
            border: 1px solid #fecaca;
            border-radius: 0.65rem;
            background: #fef2f2;
            color: #991b1b;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .incident-map-wrapper {
            position: relative;
        }

        #incident-map {
            position: relative;
            z-index: 1;
            width: 100%;
            height: 440px;
            min-height: 360px;
            border: 1px solid #d1d5db;
            border-radius: 0.7rem;
            background: #e5e7eb;
            cursor: crosshair;
        }

        .incident-map-status {
            margin-top: 0.8rem;
            padding: 0.7rem 0.8rem;
            border-radius: 0.55rem;
            background: #f9fafb;
            color: #4b5563;
            font-size: 0.82rem;
        }

        .incident-map-status-selected {
            background: #dcfce7;
            color: #166534;
        }

        .incident-map-status-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .incident-map-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.8rem;
            flex-wrap: wrap;
        }

        .incident-map-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0.55rem 0.8rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background: #ffffff;
            color: #374151;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
        }

        .incident-map-button:hover {
            background: #f9fafb;
        }

        .incident-map-button-danger {
            border-color: #fecaca;
            color: #b91c1c;
        }

        .incident-marker {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border: 3px solid #ffffff;
            border-radius: 999px;
            background: #dc2626;
            color: #ffffff;
            font-size: 18px;
            font-weight: 900;
            box-shadow: 0 3px 10px rgba(15, 23, 42, 0.4);
        }

        .incident-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .incident-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0.65rem 1rem;
            border: 0;
            border-radius: 0.55rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .incident-button-primary {
            background: #b91c1c;
            color: #ffffff;
        }

        .incident-button-primary:hover {
            background: #991b1b;
        }

        .incident-button-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .incident-button-secondary:hover {
            background: #d1d5db;
        }

        @media (max-width: 760px) {
            .incident-grid {
                grid-template-columns: 1fr;
            }

            .incident-field-full {
                grid-column: auto;
            }

            .incident-actions {
                justify-content: stretch;
            }

            .incident-button {
                flex: 1;
            }

            #incident-map {
                height: 380px;
            }
        }
    </style>

    <div class="incident-form-page">
        <section class="incident-form-header">
            <div>
                <h1>Add Fire Incident</h1>
                <p>
                    Enter the verified incident information and pinpoint the
                    exact fire location on the map.
                </p>
            </div>

            <a
                href="{{ route('fire-incidents.index') }}"
                class="incident-button incident-button-secondary"
            >
                Back to Incidents
            </a>
        </section>

        @if ($errors->any())
            <div class="incident-alert incident-alert-error">
                <strong>Please correct the following errors:</strong>

                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            id="fire-incident-form"
            method="POST"
            action="{{ route('fire-incidents.store') }}"
        >
            @csrf

            <div class="incident-form-page">
                <section class="incident-panel">
                    <h2 class="incident-section-title">
                        Incident Information
                    </h2>

                    <div class="incident-grid">
                        <div class="incident-field">
                            <label for="barangay_id">
                                Barangay
                                <span class="incident-required">*</span>
                            </label>

                            <select
                                id="barangay_id"
                                name="barangay_id"
                                required
                            >
                                <option value="">
                                    Select a barangay
                                </option>

                                @foreach ($barangays as $barangay)
                                    <option
                                        value="{{ $barangay->id }}"
                                        @selected(
                                            (string) old('barangay_id')
                                            ===
                                            (string) $barangay->id
                                        )
                                    >
                                        {{ $barangay->name }}
                                    </option>
                                @endforeach
                            </select>

                            @error('barangay_id')
                                <span class="incident-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="incident-field">
                            <label for="incident_type">
                                Incident Type
                                <span class="incident-required">*</span>
                            </label>

                            <input
                                id="incident_type"
                                name="incident_type"
                                type="text"
                                maxlength="100"
                                value="{{ old('incident_type') }}"
                                placeholder="Example: Residential fire"
                                required
                            >

                            @error('incident_type')
                                <span class="incident-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="incident-field incident-field-full">
                            <label for="location">
                                Exact Location
                                <span class="incident-required">*</span>
                            </label>

                            <input
                                id="location"
                                name="location"
                                type="text"
                                maxlength="255"
                                value="{{ old('location') }}"
                                placeholder="Street, building, landmark, or complete incident location"
                                required
                            >

                            @error('location')
                                <span class="incident-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="incident-panel">
                    <h2 class="incident-section-title">
                        Fire Incident Location
                    </h2>

                    <div class="incident-map-instruction">
                        Click the exact fire location on the map. You may drag
                        the red marker afterward to make the location more
                        accurate. A map location is required before saving.
                    </div>

                    <div class="incident-map-wrapper">
                        <div id="incident-map"></div>
                    </div>

                    <div
                        id="incident-map-status"
                        class="incident-map-status"
                    >
                        No fire location selected yet.
                    </div>

                    <div class="incident-map-actions">
                        <button
                            id="center-mandaluyong-button"
                            type="button"
                            class="incident-map-button"
                        >
                            Center on Mandaluyong
                        </button>

                        <button
                            id="clear-location-button"
                            type="button"
                            class="incident-map-button incident-map-button-danger"
                        >
                            Clear Selected Location
                        </button>
                    </div>

                    <div class="incident-grid" style="margin-top: 1rem;">
                        <div class="incident-field">
                            <label for="latitude">
                                Latitude
                                <span class="incident-required">*</span>
                            </label>

                            <input
                                id="latitude"
                                name="latitude"
                                type="number"
                                step="0.0000001"
                                min="-90"
                                max="90"
                                value="{{ old('latitude') }}"
                                class="incident-coordinate-input"
                                placeholder="Select a location on the map"
                                readonly
                                required
                            >

                            @error('latitude')
                                <span class="incident-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="incident-field">
                            <label for="longitude">
                                Longitude
                                <span class="incident-required">*</span>
                            </label>

                            <input
                                id="longitude"
                                name="longitude"
                                type="number"
                                step="0.0000001"
                                min="-180"
                                max="180"
                                value="{{ old('longitude') }}"
                                class="incident-coordinate-input"
                                placeholder="Select a location on the map"
                                readonly
                                required
                            >

                            @error('longitude')
                                <span class="incident-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="incident-panel">
                    <h2 class="incident-section-title">
                        Classification and Status
                    </h2>

                    <div class="incident-grid">
                        <div class="incident-field">
                            <label for="severity">
                                Severity
                                <span class="incident-required">*</span>
                            </label>

                            <select
                                id="severity"
                                name="severity"
                                required
                            >
                                <option value="">
                                    Select severity
                                </option>

                                <option
                                    value="Minor"
                                    @selected(old('severity') === 'Minor')
                                >
                                    Minor
                                </option>

                                <option
                                    value="Moderate"
                                    @selected(old('severity') === 'Moderate')
                                >
                                    Moderate
                                </option>

                                <option
                                    value="Major"
                                    @selected(old('severity') === 'Major')
                                >
                                    Major
                                </option>
                            </select>

                            @error('severity')
                                <span class="incident-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="incident-field">
                            <label for="status">
                                Status
                                <span class="incident-required">*</span>
                            </label>

                            <select
                                id="status"
                                name="status"
                                required
                            >
                                <option value="">
                                    Select status
                                </option>

                                <option
                                    value="Pending"
                                    @selected(
                                        old('status', 'Pending')
                                        ===
                                        'Pending'
                                    )
                                >
                                    Pending
                                </option>

                                <option
                                    value="Responding"
                                    @selected(
                                        old('status') === 'Responding'
                                    )
                                >
                                    Responding
                                </option>

                                <option
                                    value="Controlled"
                                    @selected(
                                        old('status') === 'Controlled'
                                    )
                                >
                                    Controlled
                                </option>

                                <option
                                    value="Resolved"
                                    @selected(
                                        old('status') === 'Resolved'
                                    )
                                >
                                    Resolved
                                </option>
                            </select>

                            @error('status')
                                <span class="incident-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="incident-panel">
                    <h2 class="incident-section-title">
                        Incident Timeline
                    </h2>

                    <div class="incident-grid">
                        <div class="incident-field">
                            <label for="reported_at">
                                Reported At
                                <span class="incident-required">*</span>
                            </label>

                            <input
                                id="reported_at"
                                name="reported_at"
                                type="datetime-local"
                                value="{{ old(
                                    'reported_at',
                                    now()->format('Y-m-d\TH:i')
                                ) }}"
                                required
                            >

                            @error('reported_at')
                                <span class="incident-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="incident-field">
                            <label for="responded_at">
                                Responded At
                            </label>

                            <input
                                id="responded_at"
                                name="responded_at"
                                type="datetime-local"
                                value="{{ old('responded_at') }}"
                            >

                            @error('responded_at')
                                <span class="incident-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>

                        <div class="incident-field">
                            <label for="resolved_at">
                                Resolved At
                            </label>

                            <input
                                id="resolved_at"
                                name="resolved_at"
                                type="datetime-local"
                                value="{{ old('resolved_at') }}"
                            >

                            @error('resolved_at')
                                <span class="incident-error">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>
                    </div>
                </section>

                <section class="incident-panel">
                    <h2 class="incident-section-title">
                        Additional Remarks
                    </h2>

                    <div class="incident-field">
                        <label for="remarks">
                            Remarks
                        </label>

                        <textarea
                            id="remarks"
                            name="remarks"
                            maxlength="2000"
                            placeholder="Add operational notes, reported damage, response details, or other relevant information."
                        >{{ old('remarks') }}</textarea>

                        <span class="incident-help">
                            Maximum of 2,000 characters.
                        </span>

                        @error('remarks')
                            <span class="incident-error">
                                {{ $message }}
                            </span>
                        @enderror
                    </div>
                </section>

                <section class="incident-panel">
                    <div class="incident-actions">
                        <a
                            href="{{ route('fire-incidents.index') }}"
                            class="incident-button incident-button-secondary"
                        >
                            Cancel
                        </a>

                        <button
                            id="reset-form-button"
                            type="reset"
                            class="incident-button incident-button-secondary"
                        >
                            Reset
                        </button>

                        <button
                            type="submit"
                            class="incident-button incident-button-primary"
                        >
                            Save Fire Incident
                        </button>
                    </div>
                </section>
            </div>
        </form>
    </div>

   <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const defaultLatitude = 14.5794;
            const defaultLongitude = 121.0359;
            const defaultZoom = 14;

            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');
            const mapStatus = document.getElementById(
                'incident-map-status'
            );
            const centerButton = document.getElementById(
                'center-mandaluyong-button'
            );
            const clearButton = document.getElementById(
                'clear-location-button'
            );
            const form = document.getElementById(
                'fire-incident-form'
            );
            const resetButton = document.getElementById(
                'reset-form-button'
            );

            if (typeof L === 'undefined') {
                mapStatus.classList.add(
                    'incident-map-status-error'
                );

                mapStatus.textContent =
                    'The map could not be loaded. Check your internet connection.';

                return;
            }

            const oldLatitude = Number(latitudeInput.value);
            const oldLongitude = Number(longitudeInput.value);

            const hasOldCoordinates =
                Number.isFinite(oldLatitude) &&
                Number.isFinite(oldLongitude) &&
                latitudeInput.value !== '' &&
                longitudeInput.value !== '';

            const initialLatitude = hasOldCoordinates
                ? oldLatitude
                : defaultLatitude;

            const initialLongitude = hasOldCoordinates
                ? oldLongitude
                : defaultLongitude;

            const map = L.map('incident-map', {
                center: [
                    initialLatitude,
                    initialLongitude
                ],
                zoom: hasOldCoordinates ? 17 : defaultZoom,
                zoomControl: true,
            });

            L.tileLayer(
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }
            ).addTo(map);

            const fireMarkerIcon = L.divIcon({
                className: '',
                html: `
                    <div class="incident-marker">
                        !
                    </div>
                `,
                iconSize: [38, 38],
                iconAnchor: [19, 19],
                popupAnchor: [0, -20],
            });

            let incidentMarker = null;

            function updateCoordinateFields(latitude, longitude) {
                latitudeInput.value = Number(latitude).toFixed(7);
                longitudeInput.value = Number(longitude).toFixed(7);

                mapStatus.classList.remove(
                    'incident-map-status-error'
                );

                mapStatus.classList.add(
                    'incident-map-status-selected'
                );

                mapStatus.textContent =
                    'Fire location selected: ' +
                    Number(latitude).toFixed(7) +
                    ', ' +
                    Number(longitude).toFixed(7);
            }

            function createOrMoveMarker(
                latitude,
                longitude,
                shouldOpenPopup = true
            ) {
                const coordinates = [
                    Number(latitude),
                    Number(longitude)
                ];

                if (incidentMarker === null) {
                    incidentMarker = L.marker(
                        coordinates,
                        {
                            icon: fireMarkerIcon,
                            draggable: true,
                            zIndexOffset: 1000,
                        }
                    )
                        .bindPopup(
                            '<strong>Selected fire incident location</strong><br>' +
                            'Drag the marker to adjust the position.'
                        )
                        .addTo(map);

                    incidentMarker.on(
                        'dragend',
                        function (event) {
                            const position = event.target.getLatLng();

                            updateCoordinateFields(
                                position.lat,
                                position.lng
                            );
                        }
                    );
                } else {
                    incidentMarker.setLatLng(coordinates);
                }

                updateCoordinateFields(
                    coordinates[0],
                    coordinates[1]
                );

                if (shouldOpenPopup) {
                    incidentMarker.openPopup();
                }
            }

            function clearSelectedLocation() {
                if (incidentMarker !== null) {
                    map.removeLayer(incidentMarker);
                    incidentMarker = null;
                }

                latitudeInput.value = '';
                longitudeInput.value = '';

                mapStatus.classList.remove(
                    'incident-map-status-selected',
                    'incident-map-status-error'
                );

                mapStatus.textContent =
                    'No fire location selected yet.';
            }

            map.on('click', function (event) {
                createOrMoveMarker(
                    event.latlng.lat,
                    event.latlng.lng
                );
            });

            centerButton.addEventListener(
                'click',
                function () {
                    map.setView(
                        [
                            defaultLatitude,
                            defaultLongitude
                        ],
                        defaultZoom
                    );
                }
            );

            clearButton.addEventListener(
                'click',
                function () {
                    clearSelectedLocation();

                    map.setView(
                        [
                            defaultLatitude,
                            defaultLongitude
                        ],
                        defaultZoom
                    );
                }
            );

            form.addEventListener(
                'submit',
                function (event) {
                    if (
                        latitudeInput.value === '' ||
                        longitudeInput.value === ''
                    ) {
                        event.preventDefault();

                        mapStatus.classList.remove(
                            'incident-map-status-selected'
                        );

                        mapStatus.classList.add(
                            'incident-map-status-error'
                        );

                        mapStatus.textContent =
                            'Please click the map and select the exact fire location before saving.';

                        document
                            .getElementById('incident-map')
                            .scrollIntoView({
                                behavior: 'smooth',
                                block: 'center',
                            });

                        return;
                    }
                }
            );

            resetButton.addEventListener(
                'click',
                function () {
                    window.setTimeout(function () {
                        clearSelectedLocation();

                        map.setView(
                            [
                                defaultLatitude,
                                defaultLongitude
                            ],
                            defaultZoom
                        );
                    }, 0);
                }
            );

            if (hasOldCoordinates) {
                createOrMoveMarker(
                    oldLatitude,
                    oldLongitude,
                    false
                );
            }

            window.addEventListener(
                'resize',
                function () {
                    map.invalidateSize();
                }
            );

            window.setTimeout(function () {
                map.invalidateSize(true);
            }, 300);
        });
    </script>
@endsection