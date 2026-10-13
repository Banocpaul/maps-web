<section class="incident-panel">
    <h2 class="incident-section-title">Confirm Incident Location</h2>
    <p class="text-sm text-slate-600">Select an address above, click the map, or drag the pin to adjust the location.</p>
    <div id="address-edit-map" class="mt-3 rounded-xl" style="height: 320px"></div>
    <p id="address-edit-map-status" class="mt-2 text-sm text-slate-600" role="status"></p>
</section>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const status = document.getElementById('address-edit-map-status');
    if (typeof L === 'undefined') { status.textContent = 'Map unavailable. You can enter coordinates manually.'; return; }
    const latitude = document.getElementById('latitude');
    const longitude = document.getElementById('longitude');
    const map = L.map('address-edit-map').setView([14.5794, 121.0359], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '© OpenStreetMap contributors'}).addTo(map);
    let marker;
    const place = (lat, lon, zoom = false) => {
        if (!Number.isFinite(lat) || !Number.isFinite(lon) || Math.abs(lat) > 90 || Math.abs(lon) > 180) return;
        if (!marker) {
            marker = L.marker([lat, lon], {draggable: true}).addTo(map);
            marker.on('dragend', () => { const point = marker.getLatLng(); place(point.lat, point.lng); });
        } else marker.setLatLng([lat, lon]);
        latitude.value = lat.toFixed(7);
        longitude.value = lon.toFixed(7);
        status.textContent = `Selected coordinates: ${latitude.value}, ${longitude.value}. Verify the selected barangay.`;
        if (zoom) map.setView([lat, lon], 17);
    };
    const fromFields = () => {
        if (latitude.value !== '' && longitude.value !== '') place(Number(latitude.value), Number(longitude.value), true);
    };
    fromFields();
    latitude.addEventListener('change', fromFields);
    longitude.addEventListener('change', fromFields);
    map.on('click', event => place(event.latlng.lat, event.latlng.lng));
    document.addEventListener('incident-address-selected', event => place(Number(event.detail.latitude), Number(event.detail.longitude), true));
});
</script>
