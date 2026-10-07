<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Active Flood & Fire Map | Mandaluyong Flood & Fire
    </title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <style>
        .leaflet-container {
            position: relative;
            overflow: hidden;
            background: #dbe4ea;
            outline-offset: 1px;
        }

        .leaflet-pane,
        .leaflet-tile,
        .leaflet-marker-icon,
        .leaflet-marker-shadow,
        .leaflet-tile-container,
        .leaflet-pane > svg,
        .leaflet-pane > canvas,
        .leaflet-zoom-box,
        .leaflet-image-layer,
        .leaflet-layer {
            position: absolute;
            top: 0;
            left: 0;
        }

        .leaflet-map-pane,
        .leaflet-tile-pane,
        .leaflet-overlay-pane,
        .leaflet-shadow-pane,
        .leaflet-marker-pane,
        .leaflet-tooltip-pane,
        .leaflet-popup-pane {
            position: absolute;
            top: 0;
            left: 0;
        }

        .leaflet-tile-pane { z-index: 200; }
        .leaflet-overlay-pane { z-index: 400; }
        .leaflet-shadow-pane { z-index: 500; }
        .leaflet-marker-pane { z-index: 600; }
        .leaflet-tooltip-pane { z-index: 650; }
        .leaflet-popup-pane { z-index: 700; }

        .leaflet-tile,
        .leaflet-marker-icon,
        .leaflet-marker-shadow {
            max-width: none !important;
            max-height: none !important;
        }

        .leaflet-tile { visibility: hidden; }
        .leaflet-tile-loaded { visibility: inherit; }

        .leaflet-top,
        .leaflet-bottom {
            position: absolute;
            z-index: 1000;
            pointer-events: none;
        }

        .leaflet-top { top: 0; }
        .leaflet-right { right: 0; }
        .leaflet-bottom { bottom: 0; }
        .leaflet-left { left: 0; }

        .leaflet-control {
            position: relative;
            z-index: 800;
            pointer-events: auto;
        }

        #public-incident-map {
            position: relative;
            z-index: 0;
            width: 100%;
            min-height: 560px;
            height: 68vh;
        }

        .leaflet-popup-content-wrapper {
            border-radius: 0.9rem;
        }

        .incident-popup {
            min-width: 220px;
        }

        .flood-legend-swatch {
            display: inline-block;
            width: 28px;
            height: 12px;
            border: 2px solid #ffffff;
            border-radius: 999px;
            background: var(--flood-color);
            box-shadow:
                0 0 0 1px rgba(15, 23, 42, 0.72),
                0 0 8px var(--flood-color),
                0 1px 3px rgba(15, 23, 42, 0.28);
        }

        .fire-map-div-icon {
            border: 0 !important;
            background: transparent !important;
        }

        .fire-map-marker {
            display: flex;
            width: 38px;
            height: 38px;
            align-items: center;
            justify-content: center;
            border: 3px solid #ffffff;
            border-radius: 999px;
            background: #FF3131;
            color: #ffffff;
            box-shadow:
                0 0 12px rgba(255, 49, 49, 0.9),
                0 2px 9px rgba(127, 29, 29, 0.5),
                0 0 0 1px rgba(127, 29, 29, 0.45);
        }

        .fire-map-marker svg {
            width: 21px;
            height: 21px;
        }

        .fire-legend-marker {
            display: inline-flex;
            width: 24px;
            height: 24px;
            align-items: center;
            justify-content: center;
            border: 2px solid #ffffff;
            border-radius: 999px;
            background: #FF3131;
            color: #ffffff;
            box-shadow:
                0 0 7px rgba(255, 49, 49, 0.85),
                0 0 0 1px rgba(127, 29, 29, 0.45),
                0 1px 3px rgba(127, 29, 29, 0.3);
        }

        .fire-legend-marker svg {
            width: 14px;
            height: 14px;
        }
    </style>
</head>

<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">

    <header class="border-b border-slate-800 bg-slate-950 text-white shadow-lg">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div>
                <p class="text-lg font-black tracking-wide">
                    Mandaluyong Flood & Fire
                </p>

                <p class="text-xs text-slate-400">
                    Public Active Flood & Fire Map
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a
                    href="{{ route('public.portal') }}"
                    class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold transition hover:bg-white hover:text-slate-950"
                >
                    Back to Public Portal
                </a>

                <button
                    id="refresh-map"
                    type="button"
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold transition hover:bg-blue-700"
                >
                    Refresh Map
                </button>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">

        <section class="rounded-2xl bg-gradient-to-r from-blue-950 to-slate-900 p-6 text-white shadow-lg">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-300">
                Public Incident Information
            </p>

            <h1 class="mt-2 text-3xl font-black">
                Active flood and fire incidents
            </h1>

            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">
                Flood lines keep their A–D color coding with high-visibility
                neon colors and a contrasting outline. Neon red flame markers show active fire
                incidents reported in Mandaluyong. Use the filters
                below to view all active incidents, flood only, or fire only.
            </p>
        </section>

        <section class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ([
                [
                    'label' => 'All Active',
                    'value' => $statistics['active_total'],
                    'class' => 'text-slate-950',
                ],
                [
                    'label' => 'Active Floods',
                    'value' => $statistics['active_floods'],
                    'class' => 'text-blue-700',
                ],
                [
                    'label' => 'Active Fires',
                    'value' => $statistics['active_fires'],
                    'class' => 'text-red-700',
                ],
                [
                    'label' => 'Affected Barangays',
                    'value' => $statistics['barangays'],
                    'class' => 'text-violet-700',
                ],
            ] as $item)
                <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                        {{ $item['label'] }}
                    </p>

                    <p class="mt-2 text-2xl font-black {{ $item['class'] }}">
                        {{ $item['value'] }}
                    </p>
                </article>
            @endforeach
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 class="text-lg font-bold">
                        Active incident GIS map
                    </h2>

                    <p
                        id="map-status"
                        class="mt-1 text-sm text-slate-600"
                    >
                        {{ $statistics['active_total'] > 0
                            ? $statistics['active_total'].' active incident(s) displayed.'
                            : 'No active mapped flood or fire incidents are currently reported.' }}
                    </p>
                </div>

                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap gap-2" aria-label="Map filters">
                        <button
                            type="button"
                            data-map-filter="all"
                            class="map-filter rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white"
                        >
                            All Active
                        </button>

                        <button
                            type="button"
                            data-map-filter="flood"
                            class="map-filter rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-bold text-blue-700 transition hover:bg-blue-50"
                        >
                            Flood
                        </button>

                        <button
                            type="button"
                            data-map-filter="fire"
                            class="map-filter rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-bold text-red-700 transition hover:bg-red-50"
                        >
                            Fire
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs font-semibold text-slate-700">
                        @foreach ($levels as $code => $level)
                            <span class="inline-flex items-center gap-2">
                                <span
                                    class="flood-legend-swatch"
                                    style="--flood-color: {{ $level['color'] }}"
                                ></span>

                                Flood {{ $code }} — {{ $level['depth'] }}
                            </span>
                        @endforeach

                        <span class="inline-flex items-center gap-2">
                            <span class="fire-legend-marker" aria-hidden="true">
                                <svg viewBox="0 0 24 24" role="img">
                                    <path
                                        fill="currentColor"
                                        d="M12 2C8.5 6 9.2 8.6 6.7 11.1A6.9 6.9 0 0 0 5 15.6C5 19.1 8.1 22 12 22s7-2.9 7-6.4c0-3.4-2-6.5-5.6-9.2.1 2.3-.7 4.1-2.1 5.4.2-3.2-.7-6.3.7-9.8Z"
                                    ></path>
                                </svg>
                            </span>
                            Active Fire
                        </span>
                    </div>
                </div>
            </div>

            <div
                id="public-incident-map"
                aria-label="Public active flood and fire incident map"
            ></div>
        </section>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
            <strong>Safety reminder:</strong>
            Do not enter flooded roads or approach an active fire scene.
            Conditions can change quickly. Keep access routes clear and follow
            instructions from Mandaluyong CDRRMO, BFP, and emergency personnel.
        </section>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const floods = {{ Illuminate\Support\Js::from($floods) }};
            const fires = {{ Illuminate\Support\Js::from($fires) }};

            const mapElement = document.getElementById('public-incident-map');
            const statusElement = document.getElementById('map-status');
            const filterButtons = document.querySelectorAll('[data-map-filter]');

            if (!mapElement) {
                return;
            }

            if (typeof L === 'undefined') {
                statusElement.textContent =
                    'The GIS map library could not be loaded.';
                statusElement.classList.add('text-red-700');
                return;
            }

            const map = L.map(mapElement, {
                scrollWheelZoom: true,
                zoomControl: true,
            }).setView([14.5794, 121.0359], 14);

            L.tileLayer(
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors',
                }
            ).addTo(map);

            const floodGroup = L.layerGroup().addTo(map);
            const fireGroup = L.layerGroup().addTo(map);

            const floodLayers = [];
            const fireLayers = [];

            const fireIcon = L.divIcon({
                className: 'fire-map-div-icon',
                html: `
                    <div class="fire-map-marker" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path
                                fill="currentColor"
                                d="M12 2C8.5 6 9.2 8.6 6.7 11.1A6.9 6.9 0 0 0 5 15.6C5 19.1 8.1 22 12 22s7-2.9 7-6.4c0-3.4-2-6.5-5.6-9.2.1 2.3-.7 4.1-2.1 5.4.2-3.2-.7-6.3.7-9.8Z"
                            ></path>
                        </svg>
                    </div>
                `,
                iconSize: [38, 38],
                iconAnchor: [19, 19],
                popupAnchor: [0, -21],
                tooltipAnchor: [0, -18],
            });

            floods.forEach((flood) => {
                if (
                    !flood.geometry
                    || flood.geometry.type !== 'LineString'
                    || !Array.isArray(flood.geometry.coordinates)
                ) {
                    return;
                }

                const coordinates = flood.geometry.coordinates
                    .map(([longitude, latitude]) => [
                        Number(latitude),
                        Number(longitude),
                    ])
                    .filter(
                        ([latitude, longitude]) =>
                            Number.isFinite(latitude)
                            && Number.isFinite(longitude)
                    );

                if (coordinates.length < 2) {
                    return;
                }

                const shadowLine = L.polyline(coordinates, {
                    color: '#020617',
                    weight: 20,
                    opacity: 0.42,
                    lineCap: 'round',
                    lineJoin: 'round',
                    interactive: false,
                });

                const glowLine = L.polyline(coordinates, {
                    color: flood.color,
                    weight: 17,
                    opacity: 0.34,
                    lineCap: 'round',
                    lineJoin: 'round',
                    interactive: false,
                });

                const casingLine = L.polyline(coordinates, {
                    color: '#ffffff',
                    weight: 13,
                    opacity: 0.96,
                    lineCap: 'round',
                    lineJoin: 'round',
                    interactive: false,
                });

                const line = L.polyline(coordinates, {
                    color: flood.color,
                    weight: 8,
                    opacity: 1,
                    lineCap: 'round',
                    lineJoin: 'round',
                });

                const floodFeature = L.featureGroup([
                    shadowLine,
                    glowLine,
                    casingLine,
                    line,
                ]);

                line.bindTooltip(
                    `${escapeHtml(flood.barangay)} — Flood Level ${escapeHtml(flood.level_code)}`,
                    { sticky: true }
                );

                line.bindPopup(`
                    <div class="incident-popup">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span style="
                                width:12px;
                                height:12px;
                                border-radius:999px;
                                background:${flood.color};
                                display:inline-block;
                            "></span>

                            <strong>
                                ${escapeHtml(flood.level_label)}
                                (${escapeHtml(flood.depth_label)})
                            </strong>
                        </div>

                        <p style="margin:5px 0;">
                            <strong>Type:</strong> Active Flood
                        </p>

                        <p style="margin:5px 0;">
                            <strong>Barangay:</strong>
                            ${escapeHtml(flood.barangay)}
                        </p>

                        <p style="margin:5px 0;">
                            <strong>Mapped length:</strong>
                            ${Number(flood.length_m).toLocaleString()} m
                        </p>

                        <p style="margin:5px 0;">
                            <strong>Status:</strong> Active
                        </p>

                        <p style="margin:5px 0;">
                            <strong>Observed:</strong>
                            ${escapeHtml(flood.observed_at || 'Not available')}
                        </p>
                    </div>
                `);

                floodFeature.addTo(floodGroup);
                floodLayers.push(floodFeature);
            });

            fires.forEach((fire) => {
                const latitude = Number(fire.latitude);
                const longitude = Number(fire.longitude);

                if (
                    !Number.isFinite(latitude)
                    || !Number.isFinite(longitude)
                ) {
                    return;
                }

                const marker = L.marker(
                    [latitude, longitude],
                    {
                        icon: fireIcon,
                        riseOnHover: true,
                        keyboard: true,
                        alt: 'Active fire incident',
                    }
                );

                marker.bindTooltip(
                    `${escapeHtml(fire.barangay)} — Active Fire`,
                    { sticky: true }
                );

                marker.bindPopup(`
                    <div class="incident-popup">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span style="
                                width:12px;
                                height:12px;
                                border-radius:999px;
                                background:#FF3131;
                                display:inline-block;
                            "></span>

                            <strong>Active Fire Incident</strong>
                        </div>

                        <p style="margin:5px 0;">
                            <strong>Incident type:</strong>
                            ${escapeHtml(fire.incident_type || 'Fire')}
                        </p>

                        <p style="margin:5px 0;">
                            <strong>Barangay:</strong>
                            ${escapeHtml(fire.barangay)}
                        </p>

                        <p style="margin:5px 0;">
                            <strong>Location:</strong>
                            ${escapeHtml(fire.location || 'Not available')}
                        </p>

                        <p style="margin:5px 0;">
                            <strong>Severity:</strong>
                            ${escapeHtml(fire.severity || 'Not available')}
                        </p>

                        <p style="margin:5px 0;">
                            <strong>Status:</strong>
                            ${escapeHtml(fire.status || 'Active')}
                        </p>

                        <p style="margin:5px 0;">
                            <strong>Reported:</strong>
                            ${escapeHtml(fire.reported_at || 'Not available')}
                        </p>
                    </div>
                `);

                marker.addTo(fireGroup);
                fireLayers.push(marker);
            });

            let activeFilter = 'all';

            const visibleLayers = () => {
                if (activeFilter === 'flood') {
                    return floodLayers;
                }

                if (activeFilter === 'fire') {
                    return fireLayers;
                }

                return [...floodLayers, ...fireLayers];
            };

            const updateStatus = () => {
                if (!statusElement) {
                    return;
                }

                if (activeFilter === 'flood') {
                    statusElement.textContent =
                        floodLayers.length > 0
                            ? `${floodLayers.length} active flood extent(s) displayed.`
                            : 'No active mapped floods are currently reported.';
                    return;
                }

                if (activeFilter === 'fire') {
                    statusElement.textContent =
                        fireLayers.length > 0
                            ? `${fireLayers.length} active fire incident(s) displayed.`
                            : 'No active mapped fire incidents are currently reported.';
                    return;
                }

                const total = floodLayers.length + fireLayers.length;

                statusElement.textContent =
                    total > 0
                        ? `${total} active incident(s) displayed: ${floodLayers.length} flood, ${fireLayers.length} fire.`
                        : 'No active mapped flood or fire incidents are currently reported.';
            };

            const fitVisibleLayers = () => {
                map.invalidateSize();

                const layers = visibleLayers();

                if (layers.length === 0) {
                    map.setView([14.5794, 121.0359], 14);
                    return;
                }

                const bounds = L.featureGroup(layers).getBounds();

                if (bounds.isValid()) {
                    map.fitBounds(
                        bounds.pad(0.18),
                        { maxZoom: 16 }
                    );
                }
            };

            const setFilter = (filter) => {
                activeFilter = filter;

                if (filter === 'all' || filter === 'flood') {
                    if (!map.hasLayer(floodGroup)) {
                        floodGroup.addTo(map);
                    }
                } else if (map.hasLayer(floodGroup)) {
                    map.removeLayer(floodGroup);
                }

                if (filter === 'all' || filter === 'fire') {
                    if (!map.hasLayer(fireGroup)) {
                        fireGroup.addTo(map);
                    }
                } else if (map.hasLayer(fireGroup)) {
                    map.removeLayer(fireGroup);
                }

                filterButtons.forEach((button) => {
                    const isActive =
                        button.dataset.mapFilter === filter;

                    button.classList.toggle(
                        'bg-slate-900',
                        isActive
                    );
                    button.classList.toggle(
                        'text-white',
                        isActive
                    );
                    button.classList.toggle(
                        'bg-white',
                        !isActive
                    );
                });

                updateStatus();
                fitVisibleLayers();
            };

            filterButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    setFilter(button.dataset.mapFilter || 'all');
                });
            });

            requestAnimationFrame(() => {
                updateStatus();
                fitVisibleLayers();
            });

            window.addEventListener('load', () => {
                fitVisibleLayers();
            });

            window.addEventListener('resize', () => {
                map.invalidateSize();
            });

            document
                .getElementById('refresh-map')
                ?.addEventListener('click', () => {
                    window.location.reload();
                });

            function escapeHtml(value) {
                const element = document.createElement('div');
                element.textContent = String(value ?? '');
                return element.innerHTML;
            }
        });
    </script>

</body>
</html>
