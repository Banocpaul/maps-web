export function initializeFloodRecordMap() {
    const element = document.getElementById('flood-record-map');
    if (!element) return;
    const status = document.getElementById('flood-record-map-status');
    const form = document.getElementById('flood-record-form');
    const input = document.getElementById('flood-record-geometry');
    const barangay = document.getElementById('flood-record-barangay');
    const code = document.getElementById('flood-record-code');
    const config = JSON.parse(element.dataset.config);
    const colors = { A: '#39FF14', B: '#FFF200', C: '#FF9500', D: '#FF3B1F' };
    if (typeof L === 'undefined' || !L.Control.Draw) {
        status.textContent = 'Map unavailable. Check your connection and reload.';
        form.addEventListener('submit', event => event.preventDefault());
        return;
    }
    const map = L.map(element).setView([14.5794, 121.0359], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);
    const lines = new L.FeatureGroup().addTo(map);
    if (!config.closed) {
        map.addControl(new L.Control.Draw({
            draw: { polyline: { shapeOptions: { color: colors[code.value], weight: 6 } },
                marker: false, polygon: false, rectangle: false, circle: false, circlemarker: false },
            edit: { featureGroup: lines, remove: true },
        }));
    }
    function saveLine(layer) {
        layer.setStyle({ color: colors[code.value], weight: 6 });
        input.value = JSON.stringify(layer.toGeoJSON().geometry);
        const points = layer.getLatLngs();
        const length = points.slice(1).reduce((total, point, index) => total + points[index].distanceTo(point), 0);
        status.textContent = `Flood line: ${length.toFixed(1)} m`;
    }
    map.on(L.Draw.Event.CREATED, event => {
        lines.clearLayers();
        lines.addLayer(event.layer);
        saveLine(event.layer);
    });
    map.on(L.Draw.Event.EDITED, event => event.layers.eachLayer(saveLine));
    const clear = () => { lines.clearLayers(); input.value = ''; status.textContent = 'Use the line tool on the map.'; };
    map.on(L.Draw.Event.DELETED, clear);
    document.getElementById('flood-record-clear')?.addEventListener('click', clear);
    const profile = () => {
        const selected = config.barangays[barangay.value];
        document.getElementById('flood-record-profile').textContent = selected
            ? `${selected.nearest_waterway ?? 'Waterway unavailable'} · Elevation: ${selected.elevation_m ?? 'unavailable'} m` : '';
        return selected;
    };
    barangay.addEventListener('change', () => {
        const selected = profile();
        if (selected?.latitude != null && selected?.longitude != null) {
            map.setView([Number(selected.latitude), Number(selected.longitude)], 16);
        }
    });
    code.addEventListener('change', () => lines.eachLayer(saveLine));
    profile();
    const validGeometry = config.geometry?.type === 'LineString'
        && Array.isArray(config.geometry.coordinates) && config.geometry.coordinates.length >= 2
        && config.geometry.coordinates.every(point => Array.isArray(point) && point.length === 2
            && point.every(value => value != null && String(value).trim() !== '')
            && Number.isFinite(Number(point[0])) && Number.isFinite(Number(point[1]))
            && Math.abs(Number(point[0])) <= 180 && Math.abs(Number(point[1])) <= 90);
    if (validGeometry) {
        const layer = L.geoJSON(config.geometry).getLayers()[0];
        if (layer) {
            lines.addLayer(layer);
            saveLine(layer);
            map.fitBounds(layer.getBounds(), { padding: [25, 25], maxZoom: 17 });
        }
    } else {
        input.value = '';
        const selected = config.barangays[barangay.value];
        if (selected?.latitude != null && selected?.longitude != null) map.setView([Number(selected.latitude), Number(selected.longitude)], 16);
    }
    form.addEventListener('submit', event => {
        if (!input.value) {
            event.preventDefault();
            status.textContent = 'Draw a flood line before saving.';
            element.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    });
}
