@extends('layouts.app')
@section('title', 'Review Incident Report | M.A.P.S.')
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
    .report-panel{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;margin-bottom:20px}.report-panel h2{font-size:20px;font-weight:700;margin-bottom:12px}
    .report-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.report-fields label{font-size:14px;font-weight:600}.report-fields input,.report-fields select,.report-fields textarea,.review-notes{display:block;width:100%;margin-top:6px;border:1px solid #cbd5e1;border-radius:8px;padding:10px;font:inherit}.span-full{grid-column:1/-1}
    #review-map{height:380px;z-index:0;border-radius:12px;margin:16px 0}.report-button{display:inline-block;background:#1d4ed8;color:#fff;border:0;border-radius:8px;padding:11px 18px;font-weight:700;margin-top:14px;cursor:pointer}.report-button:disabled{opacity:.5;cursor:not-allowed}.report-reject{background:#be123c}.report-muted{color:#475569;font-size:14px}.map-tools button{padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;margin-right:8px;background:#f8fafc}
    @media(max-width:640px){.report-fields{grid-template-columns:1fr}.report-panel{padding:16px}#review-map{height:300px}}
</style>
@endpush
@section('content')
<a href="{{ route('public-submissions.index') }}" class="mb-4 inline-block font-semibold text-blue-700">← Incident Reports</a>
<div class="report-panel">
    <div class="flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-bold">{{ ucfirst($publicReport->incident_type) }} report</h1><strong>{{ $publicReport->status }}</strong></div>
    <p class="mt-2 font-semibold">{{ $publicReport->reference }}</p>
    <p class="report-muted">Reporter: {{ $publicReport->submitter?->name ?? 'Legacy public report' }} · Reporter barangay: {{ $publicReport->reporterBarangay?->name ?? 'Not recorded' }}</p>
    <p class="report-muted">Submitted {{ $publicReport->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} · Original pin: {{ number_format($publicReport->latitude, 7) }}, {{ number_format($publicReport->longitude, 7) }}</p>
    <div id="review-map" aria-label="Reported incident location"></div>
    @if($publicReport->status === 'Validated' && $canPublish)
        @if($publicReport->incident_type === 'fire')
            <p class="report-muted">Click the map to move the confirmed fire location. The orange marker shows the original public pin.</p>
        @else
            <div class="map-tools"><button type="button" id="undo-line">Undo last point</button><button type="button" id="clear-line">Clear line</button></div>
            <p class="report-muted">Click along the confirmed flooded stretch to draw a line. Use at least two points. The orange marker is the original public pin.</p>
        @endif
    @endif
    <p id="review-map-status" class="report-muted" role="status" aria-live="polite"></p>
</div>

@if($publicReport->photo_path)
<div class="report-panel"><h2>Photo attached by the public</h2>
    @if($photoAvailable)
    <a href="{{ route('public-submissions.photo', $publicReport) }}" target="_blank" rel="noopener">
        <img src="{{ route('public-submissions.photo', $publicReport) }}" alt="Public photo for {{ $publicReport->reference }}" style="display:block;max-width:100%;max-height:440px;object-fit:contain;border-radius:12px" loading="lazy">
    </a>
    <p class="report-muted mt-3">Open the photo to view it at full size. Verify it alongside the report location and your field checks.</p>
    @else
    <p class="report-muted" role="status">The attached photo is currently unavailable. Ask your administrator to check the report photo storage or restore the original file from a backup.</p>
    @endif
</div>
@endif

@if($publicReport->status === 'Pending' && $canReview)
<form method="POST" action="{{ route('public-submissions.validate', $publicReport) }}" class="report-panel">
    @csrf<h2>Validate the report</h2><p class="report-muted">Confirm the incident through your response team or field verification, then record how it was verified.</p>
    <label for="validation-notes" class="mt-4 block font-semibold">Verification notes</label><textarea name="validation_notes" id="validation-notes" class="review-notes" rows="3" required minlength="5" maxlength="2000">{{ old('validation_notes') }}</textarea>
    <button class="report-button">Mark as validated</button>
</form>
@endif
@if($publicReport->validation_notes)
    <div class="report-panel"><h2>Validation notes</h2><p class="whitespace-pre-wrap">{{ $publicReport->validation_notes }}</p></div>
@endif

@if($publicReport->status === 'Validated' && $canPublish)
<form method="POST" action="{{ route('public-submissions.publish', $publicReport) }}" class="report-panel" id="publish-report-form">
    @csrf<h2>Add official {{ $publicReport->incident_type }} details</h2><p class="report-muted mb-4">All date and time fields use Philippine time (Asia/Manila).</p>
    <div class="report-fields">
        <label>Barangay<select name="barangay_id" required><option value="">Select barangay</option>@foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @selected((string) old('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>@endforeach</select></label>
        @if($publicReport->incident_type === 'fire')
            <label>Fire incident type<input name="incident_type" value="{{ old('incident_type') }}" placeholder="e.g. Residential fire" required maxlength="100"></label>
            <label class="span-full">Confirmed location / landmark<input name="location" value="{{ old('location') }}" required maxlength="255"></label>
            <label>Street (optional)<input name="street" value="{{ old('street') }}" maxlength="255"></label><label>Corner (optional)<input name="corner" value="{{ old('corner') }}" maxlength="255"></label>
            <label>Severity<select name="severity" required><option value="">Select severity</option>@foreach(['Minor','Moderate','Major'] as $value)<option @selected(old('severity') === $value)>{{ $value }}</option>@endforeach</select></label>
            <label>Operational status<select name="status" required>@foreach(['Reported','Responding','Controlled','Resolved'] as $value)<option @selected(old('status', 'Reported') === $value)>{{ $value }}</option>@endforeach</select></label>
            <label>Reported at<input type="datetime-local" name="reported_at" value="{{ old('reported_at', $publicReport->created_at->timezone('Asia/Manila')->format('Y-m-d\TH:i')) }}" required></label>
            <label>Responded at (optional)<input type="datetime-local" name="responded_at" value="{{ old('responded_at') }}"></label>
            <label>Fire Out (PHT)<input type="datetime-local" name="fire_out_at" value="{{ old('fire_out_at') }}"></label>
            @include('fire.incidents.record-fields')
            <input type="hidden" name="latitude" id="official-latitude" value="{{ old('latitude', $publicReport->latitude) }}"><input type="hidden" name="longitude" id="official-longitude" value="{{ old('longitude', $publicReport->longitude) }}">
        @else
            <label>Confirmed location / landmark<input name="location_name" value="{{ old('location_name') }}" required maxlength="255"></label>
            <label>Observed at<input type="datetime-local" name="observed_at" value="{{ old('observed_at', $publicReport->created_at->timezone('Asia/Manila')->format('Y-m-d\TH:i')) }}" required></label>
            <label>Verified flood level<select name="flood_level_code" required><option value="">Select level</option>@foreach(['A','B','C','D'] as $value)<option @selected(old('flood_level_code') === $value)>{{ $value }}</option>@endforeach</select></label>
            <label>Flood status<select name="flood_status" id="flood-status" required>@foreach(['Active','Subsided'] as $value)<option @selected(old('flood_status', 'Active') === $value)>{{ $value }}</option>@endforeach</select></label>
            <label>Subsided at (required if subsided)<input type="datetime-local" name="subsided_at" id="subsided-at" value="{{ old('subsided_at') }}"></label>
            <input type="hidden" name="geometry_json" id="flood-geometry" value="{{ old('geometry_json') }}">
        @endif
        <label class="span-full">Responder remarks (optional)<textarea name="remarks" rows="3" maxlength="2000">{{ old('remarks') }}</textarea></label>
    </div>
    <button class="report-button" id="publish-report-button">Publish official incident</button>
    <p class="report-muted mt-3">Publishing a fire uses the existing automatic SMS process. Flood observations follow the separate training review process.</p>
</form>
@endif
@if($publicReport->status === 'Published')
<div class="report-panel"><h2>Official incident</h2>
    @if($publicReport->fireIncident)
        <a class="font-semibold text-blue-700" href="{{ route('fire-incidents.show', $publicReport->fireIncident) }}">Open {{ $publicReport->fireIncident->incident_number }}</a>
    @elseif($publicReport->floodTrainingRecord)
        <p>Flood observation #{{ $publicReport->flood_training_record_id }} · {{ $publicReport->floodTrainingRecord->barangay }}</p><a class="font-semibold text-blue-700" href="{{ route('flood-operation.index') }}">Open Flood Operations</a>
    @else
        <p>The linked official incident is no longer available.</p>
    @endif
</div>
@endif
@if(in_array($publicReport->status, ['Pending', 'Validated'], true) && $canReject)
<form method="POST" action="{{ route('public-submissions.reject', $publicReport) }}" class="report-panel">
    @csrf<h2>Reject report</h2><p class="report-muted">Use for a false report, duplicate, or an incident that could not be confirmed.</p>
    <label for="rejection-reason" class="mt-4 block font-semibold">Reason</label><textarea name="rejection_reason" id="rejection-reason" class="review-notes" rows="2" required minlength="5" maxlength="2000">{{ old('rejection_reason') }}</textarea>
    <button class="report-button report-reject">Reject report</button>
</form>
@endif
<div class="report-panel"><h2>Report history</h2><ol class="space-y-4">
    @foreach($publicReport->events as $event)
        <li class="border-l-2 border-blue-200 pl-4"><strong>{{ $event->to_status }}</strong> · {{ $event->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}<p class="report-muted">{{ $event->actor?->name ?? 'Public / former user' }}</p><p class="whitespace-pre-wrap">{{ $event->notes }}</p></li>
    @endforeach
</ol></div>
@endsection
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
    const status=document.getElementById('review-map-status');
    const form=document.getElementById('publish-report-form');
    const button=document.getElementById('publish-report-button');
    if(!window.L){status.textContent='The map could not load. Reload before publishing.';if(button)button.disabled=true;return;}
    const origin={{ Illuminate\Support\Js::from([$publicReport->latitude, $publicReport->longitude]) }};
    const type={{ Illuminate\Support\Js::from($publicReport->incident_type) }};
    const map=L.map('review-map').setView(origin,17);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap contributors'}).addTo(map);
    L.geoJSON({{ Illuminate\Support\Js::from($boundaries) }},{style:{color:'#64748b',weight:1,fillOpacity:0.015}}).addTo(map);
    L.circleMarker(origin,{radius:8,color:'#c2410c',fillColor:'#fb923c',fillOpacity:1}).addTo(map).bindPopup('Original public report');
    let points=[], line;
    if(form && type==='fire') {
        const latitude=document.getElementById('official-latitude'),longitude=document.getElementById('official-longitude');
        const marker=L.marker([Number(latitude.value),Number(longitude.value)],{draggable:true}).addTo(map);
        const update=position=>{marker.setLatLng(position);latitude.value=position.lat.toFixed(7);longitude.value=position.lng.toFixed(7);status.textContent='Confirmed pin: '+latitude.value+', '+longitude.value;};
        marker.on('dragend',()=>update(marker.getLatLng()));map.on('click',e=>update(e.latlng));update(marker.getLatLng());
    }
    if(form && type==='flood') {
        const geometry=document.getElementById('flood-geometry');
        try {const old=JSON.parse(geometry.value);if(old.type==='LineString' && Array.isArray(old.coordinates))points=old.coordinates.map(p=>[p[1],p[0]]);} catch {}
        line=L.polyline(points,{color:'#2563eb',weight:6}).addTo(map);
        const sync=()=>{line.setLatLngs(points);geometry.value=points.length>=2?JSON.stringify({type:'LineString',coordinates:points.map(p=>[p[1],p[0]])}):'';button.disabled=points.length<2;status.textContent=points.length+' flood line points selected.';};
        map.on('click',e=>{if(points.length>=100){status.textContent='A maximum of 100 points is allowed.';return;}points.push([e.latlng.lat,e.latlng.lng]);sync();});
        document.getElementById('undo-line').addEventListener('click',()=>{points.pop();sync();});
        document.getElementById('clear-line').addEventListener('click',()=>{points=[];sync();});sync();
        const floodStatus=document.getElementById('flood-status'),subsided=document.getElementById('subsided-at');
        const syncStatus=()=>{subsided.required=floodStatus.value==='Subsided';};floodStatus.addEventListener('change',syncStatus);syncStatus();
    }
    const publishedGeometry={{ Illuminate\Support\Js::from($publicReport->floodTrainingRecord?->geometry_geojson) }};
    const publishedFire={{ Illuminate\Support\Js::from($publicReport->fireIncident ? [(float) $publicReport->fireIncident->latitude, (float) $publicReport->fireIncident->longitude] : null) }};
    if(publishedGeometry){const extent=L.geoJSON(publishedGeometry,{style:{color:'#2563eb',weight:6}}).addTo(map);map.fitBounds(extent.getBounds(),{padding:[25,25]});}
    if(publishedFire)L.marker(publishedFire).addTo(map).bindPopup('Published fire location');
    if(form)form.addEventListener('submit',()=>{button.disabled=true;button.textContent='Publishing…';});
})();
</script>
@endpush
