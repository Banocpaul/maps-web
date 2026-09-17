<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Flood Advisory Map | Mandaluyong Flood & Fire</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIINfQ3ynhhdJpXO2uD1SsA+Z0T3ufyNPL0="
        crossorigin=""
    >

    <style>
        #public-flood-map {
            min-height: 560px;
            height: 68vh;
        }

        .leaflet-popup-content-wrapper {
            border-radius: 0.9rem;
        }

        .flood-popup {
            min-width: 210px;
        }
    </style>
</head>

<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <header class="border-b border-slate-800 bg-slate-950 text-white shadow-lg">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <div>
                <p class="text-lg font-black tracking-wide">Mandaluyong Flood & Fire</p>
                <p class="text-xs text-slate-400">Public Live Flood Advisory Map</p>
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
                Public Flood Advisory
            </p>

            <h1 class="mt-2 text-3xl font-black">Active mapped flood extents</h1>

            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">
                Colored road lines represent active flood observations reported by authorized operations personnel. Select a line to view its barangay, flood level, recorded length, and observation time.
            </p>
        </section>

        <section class="grid grid-cols-2 gap-4 lg:grid-cols-6">
            @foreach ([
                ['label' => 'Active Floods', 'value' => $statistics['total'], 'class' => 'text-slate-950'],
                ['label' => 'Barangays', 'value' => $statistics['barangays'], 'class' => 'text-blue-700'],
                ['label' => 'Level A', 'value' => $statistics['level_a'], 'class' => 'text-green-700'],
                ['label' => 'Level B', 'value' => $statistics['level_b'], 'class' => 'text-yellow-700'],
                ['label' => 'Level C', 'value' => $statistics['level_c'], 'class' => 'text-orange-700'],
                ['label' => 'Level D', 'value' => $statistics['level_d'], 'class' => 'text-red-700'],
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
            <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-bold">Flood extent GIS map</h2>
                    <p id="map-status" class="mt-1 text-sm text-slate-600">
                        {{ $statistics['total'] > 0
                            ? $statistics['total'].' active flood extent(s) displayed.'
                            : 'No active mapped floods are currently reported.' }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm font-semibold">
                    @foreach ($levels as $code => $level)
                        <span class="inline-flex items-center gap-2">
                            <span
                                class="h-3 w-7 rounded-full"
                                style="background-color: {{ $level['color'] }}"
                            ></span>
                            {{ $code }} — {{ $level['depth'] }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div id="public-flood-map" aria-label="Public active flood advisory map"></div>
        </section>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
            <strong>Safety reminder:</strong>
            Avoid entering a mapped flooded road. Conditions may change quickly. Follow current instructions from Mandaluyong CDRRMO and emergency personnel.
        </section>
    </main>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const floods = {{ Illuminate\Support\Js::from($floods) }};
            const map = L.map('public-flood-map', {
                scrollWheelZoom: true,
            }).setView([14.5794, 121.0359], 14);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            const layers = [];

            floods.forEach((flood) => {
                const coordinates = flood.geometry.coordinates.map(
                    ([longitude, latitude]) => [latitude, longitude]
                );

                const line = L.polyline(coordinates, {
                    color: flood.color,
                    weight: 8,
                    opacity: 0.9,
                    lineCap: 'round',
                    lineJoin: 'round',
                }).addTo(map);

                line.bindTooltip(
                    `${escapeHtml(flood.barangay)} — Level ${escapeHtml(flood.level_code)}`,
                    { sticky: true }
                );

                line.bindPopup(`
                    <div class="flood-popup">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span style="width:12px;height:12px;border-radius:999px;background:${flood.color};display:inline-block;"></span>
                            <strong>${escapeHtml(flood.level_label)} (${escapeHtml(flood.depth_label)})</strong>
                        </div>
                        <p style="margin:5px 0;"><strong>Barangay:</strong> ${escapeHtml(flood.barangay)}</p>
                        <p style="margin:5px 0;"><strong>Mapped length:</strong> ${Number(flood.length_m).toLocaleString()} m</p>
                        <p style="margin:5px 0;"><strong>Status:</strong> Active</p>
                        <p style="margin:5px 0;"><strong>Observed:</strong> ${escapeHtml(flood.observed_at || 'Not available')}</p>
                    </div>
                `);

                layers.push(line);
            });

            if (layers.length > 0) {
                const bounds = L.featureGroup(layers).getBounds();

                if (bounds.isValid()) {
                    map.fitBounds(bounds.pad(0.18), { maxZoom: 17 });
                }
            }

            document.getElementById('refresh-map').addEventListener('click', () => {
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
