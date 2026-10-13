<div data-address-search data-boundary-url="{{ asset('geojson/mandaluyong_barangays.geojson') }}" data-search-url="{{ route('fire-address.search') }}" class="mt-2">
    <button type="button" data-address-search-button class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800 disabled:opacity-50">Search address</button>
    <p data-address-search-status class="mt-2 text-sm text-slate-600" role="status">Enter a street, building, or landmark, then search. Confirm the selected barangay and adjust the map pin.</p>
    <div data-address-search-results class="mt-2 overflow-hidden rounded-lg border border-slate-200" hidden></div>
    <p data-barangay-location-status class="mt-2 text-sm text-sky-800" role="status"></p>
    <p class="mt-2 text-xs text-slate-500">Search by Photon · <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener" class="underline">© OpenStreetMap contributors</a></p>
</div>
