<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Report an Incident | Mandaluyong Flood & Fire</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        *{box-sizing:border-box}body{margin:0;line-height:1.6}
        main{margin:0}.report-content{max-width:1280px;margin:-24px auto 40px;padding:0 20px;position:relative}.report-summary{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;box-shadow:0 10px 24px #0f172a08}.dialog-head h2{font-size:22px;font-weight:800}
        .error ul{list-style:disc;padding-left:22px}.muted{color:#475569}.choices{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin:30px 0}
        .choice{background:white;border:1px solid #e2e8f0;border-radius:16px;padding:24px;text-align:left;cursor:pointer;box-shadow:0 1px 3px #0f172a08;color:inherit;font:inherit}
        .choice:hover{border-color:#2563eb;transform:translateY(-2px);box-shadow:0 12px 24px #0f172a0f}.choice strong{display:block;font-size:25px}.choice span{display:block;color:#475569;margin-top:8px}.badge svg{width:26px;height:26px}.badge{display:inline-flex!important;width:48px;height:48px;border-radius:14px;align-items:center;justify-content:center;font-size:26px;margin-bottom:18px}
        .fire .badge{background:#fff1f2;color:#be123c}.flood .badge{background:#eff6ff;color:#2563eb}.notice{border:1px solid #cbd5e1;background:white;border-radius:14px;padding:18px;margin:20px 0}.success{background:#ecfdf5;border-color:#86efac}.error{background:#fff1f2;border-color:#fda4af}
        dialog{margin:auto;background:white;color:#0f172a;width:min(780px,calc(100% - 24px));max-height:95vh;overflow:auto;border:0;border-radius:16px;padding:24px;box-shadow:0 24px 90px #0005}dialog::backdrop{background:#0f172a99}.dialog-head{display:flex;justify-content:space-between;align-items:center;gap:12px}.dialog-head h2{margin:0}
        #report-map{height:min(48vh,400px);min-height:260px;border-radius:12px;border:1px solid #cbd5e1;margin:16px 0;z-index:0}.buttons{display:flex;justify-content:flex-end;gap:12px;margin-top:20px;flex-wrap:wrap}
        button.primary,button.secondary{border:0;border-radius:10px;padding:12px 20px;font:inherit;font-weight:700;cursor:pointer}.primary{background:#2563eb;color:white}.secondary{background:#e2e8f0;color:#0f172a}button:disabled{opacity:.5;cursor:not-allowed}button:focus-visible,a:focus-visible{outline:3px solid #38bdf8;outline-offset:3px}.trap{position:absolute;left:-10000px}
        .photo-field{border:1px solid #cbd5e1;border-radius:12px;padding:16px;margin-top:18px}.photo-field label{display:block;font-weight:700}.photo-field input{display:block;margin:10px 0;max-width:100%;font:inherit}.photo-field input::file-selector-button{padding:6px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;margin-right:8px;cursor:pointer}.photo-field p{margin:8px 0;font-size:14px}.photo-preview{display:block;max-width:100%;max-height:220px;border-radius:10px;object-fit:contain;margin:12px 0}.photo-preview[hidden]{display:none}.photo-error{color:#be123c}
        @media(max-width:560px){.choices{grid-template-columns:1fr}.choice{padding:22px}.report-content{padding:0 16px}.report-summary{padding:20px}dialog{padding:16px}.buttons button{flex:1}}
    </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
@include('public.partials.header', ['reportPage' => true])
<main>
    <section class="bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 text-white">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-20">
            <div class="max-w-3xl">
                <span class="inline-flex rounded-full border border-blue-300/20 bg-blue-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-blue-200">Public Incident Reporting</span>
                <h1 class="mt-6 text-4xl font-black leading-tight sm:text-5xl">Report a fire or flood in <span class="text-blue-400">Mandaluyong.</span></h1>
                <p class="mt-5 max-w-2xl text-base leading-8 text-slate-300">Help responders find the incident. Select the incident type, place a pin, and optionally attach a photo.</p>
            </div>
        </div>
    </section>
    <div class="report-content">
    <div class="report-summary"><strong>Reporting as {{ auth()->user()->name }}.</strong><p class="muted">Your report goes to staff for validation before it is added to the official incident map. <a class="font-semibold text-blue-700 underline" href="{{ route('public.reports') }}">Track your submissions in My Reports.</a></p></div>
    @if(session('report_reference'))
        <div class="notice success" role="status"><strong>Your report was submitted.</strong><br>Reference: <strong>{{ session('report_reference') }}</strong><br>Staff will validate the report before adding it to the official incident map.</div>
    @endif
    @if($errors->any())
        <div class="notice error" role="alert"><strong>Please check your report.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <div class="choices">
        <button class="choice fire" type="button" data-report-type="fire"><span class="badge" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c1 4-2 5-2 8 0 1 1 2 2 2s2-1 2-2c3 2 4 4 4 6a6 6 0 0 1-12 0c0-4 4-6 6-14Z"/></svg></span><strong>Report a fire</strong><span>Pin the location of the fire incident.</span></button>
        <button class="choice flood" type="button" data-report-type="flood"><span class="badge" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 15c2-2 4 2 6 0s4 2 6 0 4 2 6 0M3 20c2-2 4 2 6 0s4 2 6 0 4 2 6 0M12 3l-4 6a4 4 0 0 0 8 0l-4-6Z"/></svg></span><strong>Report a flood</strong><span>Pin the location where you observed flooding.</span></button>
    </div>
    <div class="notice"><strong>For immediate assistance, call the emergency hotline listed on the public portal.</strong><br>This report is sent for staff review. Submitting it does not confirm that responders have been dispatched.</div>
    <noscript><div class="notice error">Enable JavaScript to select a location on the map.</div></noscript>
    </div>
</main>
@include('public.partials.footer')
<dialog id="report-dialog" aria-labelledby="report-title">
    <div class="dialog-head"><h2 id="report-title">Report an incident</h2><button type="button" class="secondary" id="close-report" aria-label="Close map">✕</button></div>
    <p>Tap or click the incident location inside Mandaluyong. Drag the pin to adjust it.</p>
    <div id="report-map" aria-label="Select incident location on the map"></div>
    <p id="pin-status" role="status" aria-live="polite">Select the incident location to continue.</p>
    <form method="POST" action="{{ route('public.incident-reports.store') }}" id="report-form" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="incident_type" id="incident-type" value="{{ old('incident_type') }}">
        <input type="hidden" name="latitude" id="report-latitude" value="{{ old('latitude') }}">
        <input type="hidden" name="longitude" id="report-longitude" value="{{ old('longitude') }}">
        <input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">
        <div class="trap" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="photo-field">
            <label for="report-photo">Attach a photo (optional)</label>
            <input type="file" name="photo" id="report-photo" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-help photo-error">
            <p class="muted" id="photo-help">One JPG, PNG, or WEBP photo, up to 2 MB. Staff will see it when reviewing your report.</p>
            @if($errors->any())<p class="muted">If you attached a photo before, please select it again.</p>@endif
            <p class="photo-error" id="photo-error" role="alert"></p>
            <img id="photo-preview" class="photo-preview" alt="Selected incident photo" hidden>
            <button type="button" class="secondary" id="remove-photo" hidden>Remove photo</button>
        </div>
        <div class="buttons"><button type="button" class="secondary" id="cancel-report">Cancel</button><button type="submit" class="primary" id="submit-report" disabled>Submit report</button></div>
    </form>
</dialog>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
    const boundaries = {{ Illuminate\Support\Js::from($boundaries) }};
    const dialog = document.getElementById('report-dialog');
    const type = document.getElementById('incident-type');
    const latitude = document.getElementById('report-latitude');
    const longitude = document.getElementById('report-longitude');
    const status = document.getElementById('pin-status');
    const submit = document.getElementById('submit-report');
    const photo = document.getElementById('report-photo');
    const preview = document.getElementById('photo-preview');
    const photoError = document.getElementById('photo-error');
    const removePhoto = document.getElementById('remove-photo');
    let photoUrl;
    function clearPhoto() {
        if(photoUrl) URL.revokeObjectURL(photoUrl);
        photoUrl=null;photo.value='';preview.removeAttribute('src');preview.hidden=true;
        removePhoto.hidden=true;photoError.textContent='';
    }
    photo.addEventListener('change',()=>{
        const file=photo.files[0];
        if(photoUrl) URL.revokeObjectURL(photoUrl);
        photoUrl=null;preview.removeAttribute('src');preview.hidden=true;removePhoto.hidden=true;photoError.textContent='';
        if(!file) return;
        if(!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size>2*1024*1024) {
            photo.value='';photoError.textContent='Choose a JPG, PNG, or WEBP photo up to 2 MB.';return;
        }
        photoUrl=URL.createObjectURL(file);preview.src=photoUrl;preview.hidden=false;removePhoto.hidden=false;
    });
    removePhoto.addEventListener('click',clearPhoto);
    let map, pin;
    function inRing(x,y,ring) {
        let inside = false;
        for(let i=0,j=ring.length-1;i<ring.length;j=i++) {
            const [xi,yi]=ring[i], [xj,yj]=ring[j];
            const cross=(x-xi)*(yj-yi)-(y-yi)*(xj-xi);
            if(Math.abs(cross)<1e-10 && x>=Math.min(xi,xj) && x<=Math.max(xi,xj) && y>=Math.min(yi,yj) && y<=Math.max(yi,yj)) return true;
            if((yi>y)!==(yj>y) && x<(xj-xi)*(y-yi)/(yj-yi)+xi) inside=!inside;
        }
        return inside;
    }
    function inCity(lat,lng) {
        return boundaries.features.some(f => {
            const polygons=f.geometry.type==='Polygon'?[f.geometry.coordinates]:f.geometry.type==='MultiPolygon'?f.geometry.coordinates:[];
            return polygons.some(rings => inRing(lng,lat,rings[0]) && !rings.slice(1).some(ring=>inRing(lng,lat,ring)));
        });
    }
    function setPin(position) {
        if(!pin) {
            pin=L.marker(position,{draggable:true}).addTo(map);
            pin.on('dragend',()=>setPin(pin.getLatLng()));
        } else pin.setLatLng(position);
        const valid=inCity(position.lat,position.lng);
        latitude.value=valid?position.lat.toFixed(7):'';
        longitude.value=valid?position.lng.toFixed(7):'';
        submit.disabled=!valid;
        status.textContent=valid?'Selected location: '+latitude.value+', '+longitude.value:'Please move the pin inside Mandaluyong City.';
    }
    function openReport(selected) {
        if(!['fire','flood'].includes(selected)) return;
        const changed=type.value!==selected;
        type.value=selected;
        document.getElementById('report-title').textContent=selected==='fire'?'Report a fire':'Report a flood';
        dialog.showModal();
        if(!window.L) {status.textContent='The map could not load. Check your connection and reload this page.';submit.disabled=true;return;}
        if(!map) {
            map=L.map('report-map').setView([14.5832,121.039],14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap contributors'}).addTo(map);
            const outline=L.geoJSON(boundaries,{style:{color:'#2563eb',weight:1,fillOpacity:0.035}}).addTo(map);
            map.fitBounds(outline.getBounds());
            map.on('click',e=>setPin(e.latlng));
        }
        if(changed) {
            clearPhoto();
            if(pin){map.removeLayer(pin);pin=null;}
            latitude.value='';longitude.value='';submit.disabled=true;
            status.textContent='Select the incident location to continue.';
        } else if(latitude.value && longitude.value) setPin(L.latLng(Number(latitude.value),Number(longitude.value)));
        requestAnimationFrame(()=>map.invalidateSize());
    }
    document.querySelectorAll('[data-report-type]').forEach(button=>button.addEventListener('click',()=>openReport(button.dataset.reportType)));
    document.getElementById('close-report').addEventListener('click',()=>dialog.close());
    document.getElementById('cancel-report').addEventListener('click',()=>dialog.close());
    document.getElementById('report-form').addEventListener('submit',e=>{
        if(!latitude.value || !longitude.value) {e.preventDefault();return;}
        submit.disabled=true;submit.textContent='Submitting…';
    });
    const previousType={{ Illuminate\Support\Js::from(old('incident_type')) }};
    if(previousType) openReport(previousType);
})();
</script>
</body>
</html>
