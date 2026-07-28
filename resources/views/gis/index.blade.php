@extends('layouts.app')

@section('title', 'GIS Mapping | M.A.P.S.')
@section('page-title', 'GIS Mapping')
@section('page-description', 'Interactive fire incident and hydrant response map')

@section('content')
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <style>
        .gis-page {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .gis-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .gis-header h1 {
            margin: 0;
            color: #111827;
            font-size: 1.55rem;
        }

        .gis-header p {
            margin: 0.35rem 0 0;
            color: #6b7280;
        }

        .gis-button {
            min-height: 42px;
            padding: 0.65rem 1rem;
            border: 0;
            border-radius: 0.55rem;
            background: #1d4ed8;
            color: #ffffff;
            font-weight: 700;
            cursor: pointer;
        }

        .gis-button:disabled {
            cursor: not-allowed;
            opacity: 0.6;
        }

        .gis-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .gis-stat-card,
        .gis-panel,
        .gis-response-panel {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.8rem;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
        }

        .gis-stat-card {
            padding: 1rem;
        }

        .gis-stat-label {
            display: block;
            margin-bottom: 0.4rem;
            color: #6b7280;
            font-size: 0.85rem;
        }

        .gis-stat-value {
            color: #111827;
            font-size: 1.65rem;
            font-weight: 800;
        }

        .gis-workspace {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(300px, 0.8fr);
            gap: 1rem;
            align-items: start;
        }

        .gis-panel {
            overflow: hidden;
        }

        .gis-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .gis-toolbar h2 {
            margin: 0;
            color: #111827;
            font-size: 1rem;
        }

        .gis-toolbar p {
            margin: 0.3rem 0 0;
            color: #6b7280;
            font-size: 0.82rem;
        }

        .gis-legend {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .gis-legend-item {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: #4b5563;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .gis-legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            border: 2px solid #ffffff;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.2);
        }

        .gis-legend-hydrant {
            background: #2563eb;
        }

        .gis-legend-incident {
            background: #dc2626;
        }

        .gis-legend-origin {
            background: #f59e0b;
        }

        .gis-status {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #4b5563;
            font-size: 0.85rem;
        }

        .gis-status-error {
            background: #fef2f2;
            color: #991b1b;
        }

        #gis-map {
            position: relative;
            z-index: 1;
            width: 100%;
            height: 680px;
            min-height: 440px;
            background: #e5e7eb;
            cursor: crosshair;
        }

        .gis-response-panel {
            padding: 1rem;
            position: sticky;
            top: 1rem;
        }

        .gis-response-panel h2 {
            margin: 0;
            color: #111827;
            font-size: 1.1rem;
        }

        .gis-response-help {
            margin: 0.45rem 0 1rem;
            color: #6b7280;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .gis-empty-state {
            padding: 1rem;
            border: 1px dashed #cbd5e1;
            border-radius: 0.65rem;
            background: #f8fafc;
            color: #64748b;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .gis-nearest-card {
            display: none;
        }

        .gis-nearest-card.is-visible {
            display: block;
        }

        .gis-badge {
            display: inline-flex;
            align-items: center;
            margin-bottom: 0.8rem;
            padding: 0.3rem 0.55rem;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 0.75rem;
            font-weight: 800;
        }

        .gis-nearest-code {
            margin: 0;
            color: #111827;
            font-size: 1.35rem;
            font-weight: 800;
        }

        .gis-nearest-location {
            margin: 0.35rem 0 1rem;
            color: #4b5563;
            line-height: 1.4;
        }

        .gis-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .gis-detail {
            padding: 0.75rem;
            border-radius: 0.6rem;
            background: #f8fafc;
        }

        .gis-detail-label {
            display: block;
            margin-bottom: 0.25rem;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .gis-detail-value {
            color: #111827;
            font-size: 0.92rem;
            font-weight: 800;
        }

        .gis-record-link {
            display: block;
            width: 100%;
            padding: 0.7rem 0.9rem;
            border-radius: 0.55rem;
            background: #1d4ed8;
            color: #ffffff;
            text-align: center;
            font-weight: 800;
            text-decoration: none;
        }

        .gis-list-title {
            margin: 1.25rem 0 0.65rem;
            color: #111827;
            font-size: 0.95rem;
        }

        .gis-nearest-list {
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
        }

        .gis-nearest-list-item {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.55rem;
            background: #ffffff;
            text-align: left;
            cursor: pointer;
        }

        .gis-nearest-list-item:hover {
            background: #f8fafc;
        }

        .gis-nearest-list-item strong {
            display: block;
            color: #111827;
        }

        .gis-nearest-list-item span {
            display: block;
            margin-top: 0.2rem;
            color: #6b7280;
            font-size: 0.78rem;
        }

        .gis-popup {
            min-width: 220px;
        }

        .gis-popup h3 {
            margin: 0 0 0.55rem;
            color: #111827;
            font-size: 1rem;
        }

        .gis-popup p {
            margin: 0.25rem 0;
            color: #374151;
            font-size: 0.82rem;
        }

        .gis-popup a,
        .gis-popup button {
            display: inline-block;
            margin-top: 0.55rem;
            color: #1d4ed8;
            font-weight: 700;
            text-decoration: none;
        }

        .gis-popup button {
            padding: 0;
            border: 0;
            background: transparent;
            cursor: pointer;
        }

        .gis-marker {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border: 3px solid #ffffff;
            border-radius: 999px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.35);
        }

        .gis-marker-hydrant {
            background: #2563eb;
        }

        .gis-marker-incident {
            background: #dc2626;
        }

        .gis-marker-origin {
            background: #f59e0b;
        }

        .gis-marker-nearest {
            width: 38px;
            height: 38px;
            background: #16a34a;
            animation: gisPulse 1.3s infinite;
        }

        @keyframes gisPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.55);
            }

            70% {
                box-shadow: 0 0 0 14px rgba(22, 163, 74, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(22, 163, 74, 0);
            }
        }

        @media (max-width: 1100px) {
            .gis-workspace {
                grid-template-columns: 1fr;
            }

            .gis-response-panel {
                position: static;
            }
        }

        @media (max-width: 900px) {
            .gis-stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            #gis-map {
                height: 580px;
            }
        }

        @media (max-width: 640px) {
            .gis-stat-grid,
            .gis-detail-grid {
                grid-template-columns: 1fr;
            }

            #gis-map {
                height: 480px;
            }
        }
    </style>

    <div class="gis-page">
        <section class="gis-header">
            <div>
                <h1>GIS Emergency Response Assistant</h1>
                <p>
                    Click anywhere on the map or select a fire incident to
                    locate the nearest active fire hydrant.
                </p>
            </div>

            <button
                id="refresh-map-button"
                type="button"
                class="gis-button"
            >
                Refresh Map
            </button>
        </section>

        <section class="gis-stat-grid">
            <article class="gis-stat-card">
                <span class="gis-stat-label">Total Hydrants</span>
                <strong class="gis-stat-value">
                    {{ number_format($statistics['hydrants'] ?? 0) }}
                </strong>
            </article>

            <article class="gis-stat-card">
                <span class="gis-stat-label">Active Hydrants</span>
                <strong class="gis-stat-value">
                    {{ number_format($statistics['active_hydrants'] ?? 0) }}
                </strong>
            </article>

            <article class="gis-stat-card">
                <span class="gis-stat-label">Fire Incidents</span>
                <strong class="gis-stat-value">
                    {{ number_format($statistics['fire_incidents'] ?? 0) }}
                </strong>
            </article>

            <article class="gis-stat-card">
                <span class="gis-stat-label">Open Incidents</span>
                <strong class="gis-stat-value">
                    {{ number_format($statistics['open_incidents'] ?? 0) }}
                </strong>
            </article>
        </section>

        <section class="gis-workspace">
            <div class="gis-panel">
                <div class="gis-toolbar">
                    <div>
                        <h2>Mandaluyong Fire Response Map</h2>
                        <p>
                            Distances are straight-line estimates until
                            road routing is added.
                        </p>
                    </div>

                    <div class="gis-legend">
                        <span class="gis-legend-item">
                            <span class="gis-legend-dot gis-legend-hydrant"></span>
                            Hydrant
                        </span>

                        <span class="gis-legend-item">
                            <span class="gis-legend-dot gis-legend-incident"></span>
                            Incident
                        </span>

                        <span class="gis-legend-item">
                            <span class="gis-legend-dot gis-legend-origin"></span>
                            Selected location
                        </span>
                    </div>
                </div>

                <div id="map-status" class="gis-status">
                    Loading GIS records...
                </div>

                <div id="gis-map"></div>
            </div>

            <aside class="gis-response-panel">
                <h2>Nearest Hydrant</h2>

                <p class="gis-response-help">
                    Click the map or use “Find nearest hydrant” from an
                    incident marker.
                </p>

                <div id="nearest-empty-state" class="gis-empty-state">
                    No response location has been selected yet.
                </div>

                <div id="nearest-card" class="gis-nearest-card">
                    <span class="gis-badge">Nearest active hydrant</span>

                    <h3 id="nearest-code" class="gis-nearest-code">—</h3>

                    <p
                        id="nearest-location"
                        class="gis-nearest-location"
                    >
                        —
                    </p>

                    <div class="gis-detail-grid">
                        <div class="gis-detail">
                            <span class="gis-detail-label">Barangay</span>
                            <strong
                                id="nearest-barangay"
                                class="gis-detail-value"
                            >
                                —
                            </strong>
                        </div>

                        <div class="gis-detail">
                            <span class="gis-detail-label">Status</span>
                            <strong
                                id="nearest-status"
                                class="gis-detail-value"
                            >
                                —
                            </strong>
                        </div>

                        <div class="gis-detail">
                            <span class="gis-detail-label">Distance</span>
                            <strong
                                id="nearest-distance"
                                class="gis-detail-value"
                            >
                                —
                            </strong>
                        </div>

                        <div class="gis-detail">
                            <span class="gis-detail-label">
                                Estimated travel
                            </span>
                            <strong
                                id="nearest-time"
                                class="gis-detail-value"
                            >
                                —
                            </strong>
                        </div>

                        <div class="gis-detail">
                            <span class="gis-detail-label">
                                Last inspection
                            </span>
                            <strong
                                id="nearest-inspection"
                                class="gis-detail-value"
                            >
                                —
                            </strong>
                        </div>

                        <div class="gis-detail">
                            <span class="gis-detail-label">
                                Selected coordinates
                            </span>
                            <strong
                                id="nearest-origin"
                                class="gis-detail-value"
                            >
                                —
                            </strong>
                        </div>
                    </div>

                    <a
                        id="nearest-record-link"
                        href="#"
                        class="gis-record-link"
                    >
                        View Hydrant Record
                    </a>

                    <h3 class="gis-list-title">
                        Five Closest Active Hydrants
                    </h3>

                    <div
                        id="nearest-list"
                        class="gis-nearest-list"
                    ></div>
                </div>
            </aside>
        </section>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const statusElement = document.getElementById('map-status');
            const refreshButton = document.getElementById(
                'refresh-map-button'
            );
            const emptyState = document.getElementById(
                'nearest-empty-state'
            );
            const nearestCard = document.getElementById('nearest-card');
            const nearestList = document.getElementById('nearest-list');

            if (typeof L === 'undefined') {
                statusElement.classList.add('gis-status-error');
                statusElement.textContent =
                    'Leaflet could not be loaded. Check your connection.';

                return;
            }

            const map = L.map('gis-map', {
                center: [14.5794, 121.0359],
                zoom: 13,
                zoomControl: true,
            });

            L.tileLayer(
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }
            ).addTo(map);

            const hydrantLayer = L.layerGroup().addTo(map);
            const incidentLayer = L.layerGroup().addTo(map);
            const responseLayer = L.layerGroup().addTo(map);

            const barangayLayer = L.geoJSON(null, {
    style: function () {
        return {
            color: "#1e293b",
            weight: 2,
            fillColor: "#22c55e",
            fillOpacity: 0.25,
        };
    },

    onEachFeature: function (feature, layer) {

        const properties = feature.properties;

        layer.bindPopup(`
            <div class="gis-popup">
                <h3>${properties.barangay}</h3>

                <p>
                    <strong>City:</strong>
                    ${properties.city}
                </p>

                <p>
                    <strong>Region:</strong>
                    ${properties.region}
                </p>

                <p>
                    <strong>PSGC:</strong>
                    ${properties.psgc_code}
                </p>
            </div>
        `);

    }

}).addTo(map);

            const hydrantMarkers = new Map();

           L.control.layers(
    null,
    {
        "Barangays": barangayLayer,
        "Fire Hydrants": hydrantLayer,
        "Fire Incidents": incidentLayer,
        "Response Assistant": responseLayer,
    },
                {
                    collapsed: false,
                }
            ).addTo(map);

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function createMarkerIcon(type, isNearest = false) {
                let symbol = 'H';
                let className = 'gis-marker-hydrant';

                if (type === 'incident') {
                    symbol = '!';
                    className = 'gis-marker-incident';
                }

                if (type === 'origin') {
                    symbol = '●';
                    className = 'gis-marker-origin';
                }

                if (isNearest) {
                    symbol = 'H';
                    className = 'gis-marker-nearest';
                }

                return L.divIcon({
                    className: '',
                    html: `
                        <div class="gis-marker ${className}">
                            ${symbol}
                        </div>
                    `,
                    iconSize: isNearest ? [38, 38] : [30, 30],
                    iconAnchor: isNearest ? [19, 19] : [15, 15],
                    popupAnchor: [0, -18],
                });
            }

            function formatDistance(meters) {
                const value = Number(meters);

                if (!Number.isFinite(value)) {
                    return 'Not available';
                }

                if (value < 1000) {
                    return `${Math.round(value)} meters`;
                }

                return `${(value / 1000).toFixed(2)} kilometers`;
            }

            function createHydrantPopup(hydrant) {
                return `
                    <div class="gis-popup">
                        <h3>${escapeHtml(hydrant.code || 'Fire Hydrant')}</h3>
                        <p><strong>Barangay:</strong> ${escapeHtml(hydrant.barangay || 'Not recorded')}</p>
                        <p><strong>Location:</strong> ${escapeHtml(hydrant.location || 'Not recorded')}</p>
                        <p><strong>Status:</strong> ${escapeHtml(hydrant.status || 'Not recorded')}</p>
                        <a href="${escapeHtml(hydrant.url || '#')}">
                            View hydrant record
                        </a>
                    </div>
                `;
            }

            function createIncidentPopup(incident) {
                return `
                    <div class="gis-popup">
                        <h3>${escapeHtml(incident.incident_number || 'Fire Incident')}</h3>
                        <p><strong>Barangay:</strong> ${escapeHtml(incident.barangay || 'Not recorded')}</p>
                        <p><strong>Location:</strong> ${escapeHtml(incident.location || 'Not recorded')}</p>
                        <p><strong>Severity:</strong> ${escapeHtml(incident.severity || 'Not recorded')}</p>
                        <p><strong>Status:</strong> ${escapeHtml(incident.status || 'Not recorded')}</p>
                        <button
                            type="button"
                            class="find-nearest-for-incident"
                            data-latitude="${Number(incident.latitude)}"
                            data-longitude="${Number(incident.longitude)}"
                        >
                            Find nearest hydrant
                        </button>
                    </div>
                `;
            }

            function updateNearestPanel(data) {
                const nearest = data.nearest_hydrant;

                if (!nearest) {
                    emptyState.style.display = 'block';
                    emptyState.textContent =
                        'No active hydrant with valid coordinates was found.';

                    nearestCard.classList.remove('is-visible');
                    return;
                }

                emptyState.style.display = 'none';
                nearestCard.classList.add('is-visible');

                document.getElementById('nearest-code').textContent =
                    nearest.code || 'Fire Hydrant';

                document.getElementById('nearest-location').textContent =
                    nearest.location || 'Location not recorded';

                document.getElementById('nearest-barangay').textContent =
                    nearest.barangay || 'Not recorded';

                document.getElementById('nearest-status').textContent =
                    nearest.status || 'Not recorded';

                document.getElementById('nearest-distance').textContent =
                    formatDistance(nearest.distance_meters);

                document.getElementById('nearest-time').textContent =
                    `${nearest.estimated_drive_minutes} minute(s)`;

                document.getElementById(
                    'nearest-inspection'
                ).textContent =
                    nearest.last_inspection_date || 'Not recorded';

                document.getElementById('nearest-origin').textContent =
                    `${Number(data.origin.latitude).toFixed(5)}, ` +
                    `${Number(data.origin.longitude).toFixed(5)}`;

                const recordLink = document.getElementById(
                    'nearest-record-link'
                );

                recordLink.href = nearest.url || '#';

                nearestList.innerHTML = '';

                (data.hydrants || []).forEach(function (hydrant, index) {
                    const button = document.createElement('button');

                    button.type = 'button';
                    button.className = 'gis-nearest-list-item';

                    button.innerHTML = `
                        <strong>
                            ${index + 1}. ${escapeHtml(hydrant.code || 'Hydrant')}
                        </strong>
                        <span>
                            ${escapeHtml(hydrant.location || 'Location not recorded')}
                        </span>
                        <span>
                            ${formatDistance(hydrant.distance_meters)}
                        </span>
                    `;

                    button.addEventListener('click', function () {
                        map.setView(
                            [
                                Number(hydrant.latitude),
                                Number(hydrant.longitude),
                            ],
                            17
                        );

                        const marker = hydrantMarkers.get(
                            Number(hydrant.id)
                        );

                        if (marker) {
                            marker.openPopup();
                        }
                    });

                    nearestList.appendChild(button);
                });
            }

            async function findNearestHydrants(
                latitude,
                longitude
            ) {
                responseLayer.clearLayers();

                statusElement.classList.remove('gis-status-error');
                statusElement.textContent =
                    'Searching for the nearest active hydrant...';

                const originMarker = L.marker(
                    [latitude, longitude],
                    {
                        icon: createMarkerIcon('origin'),
                    }
                )
                    .bindPopup('Selected emergency response location')
                    .addTo(responseLayer);

                originMarker.openPopup();

                try {
                    const endpoint = new URL(
                        '{{ route('gis.nearest-hydrants') }}',
                        window.location.origin
                    );

                    endpoint.searchParams.set('latitude', latitude);
                    endpoint.searchParams.set('longitude', longitude);
                    endpoint.searchParams.set('limit', 5);

                    const response = await fetch(endpoint.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        cache: 'no-store',
                    });

                    if (!response.ok) {
                        throw new Error(
                            `Nearest-hydrant request failed with status ${response.status}.`
                        );
                    }

                    const data = await response.json();
                    const nearest = data.nearest_hydrant;

                    updateNearestPanel(data);

                    if (!nearest) {
                        statusElement.textContent =
                            'No active hydrants were found.';

                        return;
                    }

                    const nearestLatitude = Number(nearest.latitude);
                    const nearestLongitude = Number(nearest.longitude);

                    L.polyline(
                        [
                            [latitude, longitude],
                            [nearestLatitude, nearestLongitude],
                        ],
                        {
                            weight: 5,
                            opacity: 0.85,
                            dashArray: '10 8',
                        }
                    ).addTo(responseLayer);

                    const highlightedMarker = L.marker(
                        [nearestLatitude, nearestLongitude],
                        {
                            icon: createMarkerIcon('hydrant', true),
                            zIndexOffset: 1000,
                        }
                    )
                        .bindPopup(
                            createHydrantPopup(nearest)
                        )
                        .addTo(responseLayer);

                    highlightedMarker.openPopup();

                    map.fitBounds(
                        [
                            [latitude, longitude],
                            [nearestLatitude, nearestLongitude],
                        ],
                        {
                            padding: [50, 50],
                            maxZoom: 17,
                        }
                    );

                    statusElement.textContent =
                        `${nearest.code} is the nearest active hydrant, ` +
                        `${formatDistance(nearest.distance_meters)} away.`;
                } catch (error) {
                    console.error(error);

                    statusElement.classList.add('gis-status-error');
                    statusElement.textContent =
                        'The nearest-hydrant search failed. Check the browser console and Laravel log.';
                }
            }

            async function loadMapData() {
                refreshButton.disabled = true;
                refreshButton.textContent = 'Loading...';

                statusElement.classList.remove('gis-status-error');
                statusElement.textContent = 'Loading GIS records...';

                hydrantLayer.clearLayers();
                incidentLayer.clearLayers();
                responseLayer.clearLayers();
                hydrantMarkers.clear();
barangayLayer.clearLayers();
                try {
                    const geojsonResponse = await fetch('/geojson/mandaluyong_barangays.geojson');

const geojson = await geojsonResponse.json();

barangayLayer.addData(geojson);
                    const response = await fetch(
                        '{{ route('gis.data') }}',
                        {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            cache: 'no-store',
                        }
                    );

                    if (!response.ok) {
                        throw new Error(
                            `GIS request failed with status ${response.status}.`
                        );
                    }

                    const data = await response.json();
                    const bounds = [];

                    (data.hydrants || []).forEach(function (hydrant) {
                        const latitude = Number(hydrant.latitude);
                        const longitude = Number(hydrant.longitude);

                        if (
                            !Number.isFinite(latitude) ||
                            !Number.isFinite(longitude)
                        ) {
                            return;
                        }

                        const marker = L.marker(
                            [latitude, longitude],
                            {
                                icon: createMarkerIcon('hydrant'),
                            }
                        )
                            .bindPopup(createHydrantPopup(hydrant))
                            .addTo(hydrantLayer);

                        hydrantMarkers.set(
                            Number(hydrant.id),
                            marker
                        );

                        bounds.push([latitude, longitude]);
                    });

                    (data.incidents || []).forEach(function (incident) {
                        const latitude = Number(incident.latitude);
                        const longitude = Number(incident.longitude);

                        if (
                            !Number.isFinite(latitude) ||
                            !Number.isFinite(longitude)
                        ) {
                            return;
                        }

                        L.marker(
                            [latitude, longitude],
                            {
                                icon: createMarkerIcon('incident'),
                            }
                        )
                            .bindPopup(createIncidentPopup(incident))
                            .addTo(incidentLayer);

                        bounds.push([latitude, longitude]);
                    });

                    if (bounds.length > 0) {
                        map.fitBounds(bounds, {
                            padding: [35, 35],
                            maxZoom: 16,
                        });
                    }

                    const hydrantCount = (data.hydrants || []).length;
                    const incidentCount = (data.incidents || []).length;

                    statusElement.textContent =
                        `${hydrantCount} mapped hydrant(s) and ` +
                        `${incidentCount} mapped incident(s) loaded.`;
                } catch (error) {
                    console.error(error);

                    statusElement.classList.add('gis-status-error');
                    statusElement.textContent =
                        'GIS records could not be loaded. Check the browser console and Laravel log.';
                } finally {
                    refreshButton.disabled = false;
                    refreshButton.textContent = 'Refresh Map';

                    window.setTimeout(function () {
                        map.invalidateSize(true);
                    }, 200);
                }
            }

            map.on('click', function (event) {
                findNearestHydrants(
                    event.latlng.lat,
                    event.latlng.lng
                );
            });

            document.addEventListener('click', function (event) {
                const button = event.target.closest(
                    '.find-nearest-for-incident'
                );

                if (!button) {
                    return;
                }

                const latitude = Number(button.dataset.latitude);
                const longitude = Number(button.dataset.longitude);

                if (
                    Number.isFinite(latitude) &&
                    Number.isFinite(longitude)
                ) {
                    map.closePopup();
                    findNearestHydrants(latitude, longitude);
                }
            });

            refreshButton.addEventListener('click', loadMapData);

            window.addEventListener('resize', function () {
                map.invalidateSize();
            });

            window.setTimeout(function () {
                map.invalidateSize(true);
            }, 300);

            loadMapData();
        });
    </script>
@endsection
