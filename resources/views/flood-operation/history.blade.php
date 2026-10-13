<section id="flood-history-records" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-5 sm:px-6">
        <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Flood Operations</p>
        <h2 class="mt-1 text-xl font-semibold text-slate-950">Flood History &amp; Records</h2>
        <p class="mt-1 text-sm text-slate-600">Review subsided incidents and historical dataset records. Each entry keeps its original record and source.</p>
    </div>
    <div class="flex flex-wrap gap-2 border-b border-slate-200 bg-slate-50 p-4" role="group" aria-label="Record source">
        @foreach(['subsided' => 'Subsided Incidents', 'historical' => 'Historical Dataset', 'all' => 'All Records'] as $tab => $label)
        <button type="button" data-history-tab="{{ $tab }}" aria-pressed="{{ $tab === 'subsided' ? 'true' : 'false' }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">{{ $label }} <span data-history-count="{{ $tab }}">—</span></button>
        @endforeach
    </div>
    <form id="flood-history-filters" class="flex flex-wrap items-end gap-3 border-b border-slate-200 p-4">
        <label class="text-sm font-semibold text-slate-700">Barangay<input name="search" type="search" maxlength="150" placeholder="Search barangay" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="text-sm font-semibold text-slate-700">Year<input name="year" type="number" min="1900" max="2100" placeholder="All years" class="mt-1 block w-28 rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="text-sm font-semibold text-slate-700">Flood code<select name="level" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 font-normal"><option value="">All codes</option>@foreach(['A','B','C','D'] as $code)<option>{{ $code }}</option>@endforeach</select></label>
        <label class="text-sm font-semibold text-slate-700">From<input name="date_from" type="date" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <label class="text-sm font-semibold text-slate-700">To<input name="date_to" type="date" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 font-normal"></label>
        <button class="rounded-lg bg-sky-700 px-4 py-2 font-semibold text-white">Apply filters</button>
        <button type="reset" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700">Clear</button>
        <button type="button" id="flood-history-refresh" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700">Refresh</button>
    </form>
    <p id="flood-history-status" class="px-5 py-3 text-sm text-slate-600" role="status" aria-live="polite">Loading flood records…</p>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead class="border-y border-slate-200 bg-slate-50 text-xs uppercase text-slate-600"><tr>
            @foreach(['Record','Source','Start / observed (PHT)','Subsided (PHT)','Barangay','Code','Status','Duration (hours)','Details'] as $heading)<th class="whitespace-nowrap px-4 py-3">{{ $heading }}</th>@endforeach
        </tr></thead>
        <tbody id="flood-history-body" class="divide-y divide-slate-200"></tbody>
    </table></div>
    <div class="flex items-center justify-between gap-3 border-t border-slate-200 p-4"><p id="flood-history-page" class="text-sm text-slate-500">25 records per page</p><div class="flex gap-2"><button type="button" id="flood-history-prev" disabled class="rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:opacity-40">Previous</button><button type="button" id="flood-history-next" disabled class="rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:opacity-40">Next</button></div></div>
</section>
<dialog id="flood-record-details" aria-labelledby="flood-record-details-title" style="position:fixed;inset:0;margin:auto;width:calc(100vw - 2rem);max-width:48rem;max-height:calc(100dvh - 3rem);overflow-y:auto;padding:0;border:1px solid #e2e8f0;border-radius:1rem;background:#fff;color:#0f172a;font-family:inherit;box-shadow:0 25px 50px -12px rgb(15 23 42 / .25)">
    <div class="flex items-center justify-between border-b border-slate-200 p-5"><h2 id="flood-record-details-title" class="text-xl font-semibold">Flood record details</h2><button id="flood-record-details-close" type="button" aria-label="Close flood record details" class="rounded-lg px-3 py-1 text-2xl text-slate-500">&times;</button></div>
    <div id="flood-record-details-content" class="space-y-4 p-5"></div>
</dialog>
<style>#flood-record-details::backdrop{background:rgb(15 23 42 / .55);backdrop-filter:blur(3px)}[data-history-tab][aria-pressed="true"]{background:#0369a1;color:white;border-color:#0369a1}</style>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const endpoint=@json(route('flood-history.index'));
    const filters=document.getElementById('flood-history-filters');
    const body=document.getElementById('flood-history-body');
    const status=document.getElementById('flood-history-status');
    const dialog=document.getElementById('flood-record-details');
    const content=document.getElementById('flood-record-details-content');
    const tabs=[...document.querySelectorAll('[data-history-tab]')];
    let tab='subsided',page=1,lastPage=1,requestController,detailController;
    const escape=value=>String(value ?? 'Not recorded').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
    const date=value=>value?new Date(value).toLocaleString('en-PH',{timeZone:'Asia/Manila'}):'Not recorded';
    async function loadRecords(targetPage=1) {
        requestController?.abort();const controller=new AbortController();requestController=controller;
        const query=new URLSearchParams(new FormData(filters));query.set('tab',tab);query.set('page',targetPage);
        status.textContent='Loading flood records…';
        document.getElementById('flood-history-prev').disabled=true;document.getElementById('flood-history-next').disabled=true;
        try {
            const response=await fetch(endpoint+'?'+query,{headers:{Accept:'application/json'},cache:'no-store',signal:controller.signal});
            const data=await response.json();
            if(!response.ok)throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Unable to load records.');
            page=data.records.current_page;lastPage=data.records.last_page;
            body.innerHTML=data.records.data.map(record=>`<tr>
                <td class="px-4 py-3">${escape(record.reference)}</td><td class="px-4 py-3">${escape(record.source)}</td>
                <td class="whitespace-nowrap px-4 py-3">${escape(date(record.start_at))}</td><td class="whitespace-nowrap px-4 py-3">${escape(date(record.end_at))}</td>
                <td class="px-4 py-3">${escape(record.barangay)}</td><td class="px-4 py-3 font-semibold">${escape(record.code)}</td><td class="px-4 py-3">${escape(record.status)}</td>
                <td class="px-4 py-3">${escape(record.duration_hours ?? 'Not assessed')}</td><td class="px-4 py-3"><button type="button" data-record-origin="${escape(record.origin)}" data-record-id="${escape(record.id)}" class="font-semibold text-sky-700 underline">View</button></td></tr>`).join('') || '<tr><td colspan="9" class="p-8 text-center text-slate-500">No records match these filters.</td></tr>';
            document.querySelectorAll('[data-history-count]').forEach(el=>el.textContent='('+Number(data.counts[el.dataset.historyCount] || 0).toLocaleString('en-PH')+')');
            document.getElementById('flood-history-page').textContent=`Page ${page} of ${lastPage} · 25 records per page`;
            status.textContent=Number(data.records.total).toLocaleString('en-PH')+' records found. Times shown in Philippine time.';
            document.getElementById('flood-history-prev').disabled=page<=1;document.getElementById('flood-history-next').disabled=page>=lastPage;
        } catch(error) {if(error.name!=='AbortError')status.textContent=error.message+' Previously displayed records may be out of date.';}
    }
    tabs.forEach(button=>button.addEventListener('click',()=>{tab=button.dataset.historyTab;tabs.forEach(el=>el.setAttribute('aria-pressed',String(el===button)));loadRecords();}));
    filters.addEventListener('submit',event=>{event.preventDefault();loadRecords();});
    filters.addEventListener('reset',()=>setTimeout(()=>loadRecords(),0));
    document.getElementById('flood-history-refresh').addEventListener('click',()=>loadRecords(page));
    document.getElementById('flood-history-prev').addEventListener('click',()=>page>1&&loadRecords(page-1));
    document.getElementById('flood-history-next').addEventListener('click',()=>page<lastPage&&loadRecords(page+1));
    document.addEventListener('flood-records-updated',()=>loadRecords());
    document.getElementById('flood-record-details-close').addEventListener('click',()=>dialog.close());
    dialog.addEventListener('close',()=>detailController?.abort());
    body.addEventListener('click',async event=>{
        const button=event.target.closest('[data-record-id]');if(!button)return;
        detailController?.abort();detailController=new AbortController();
        content.textContent='Loading record…';dialog.showModal();
        try {
            const response=await fetch(endpoint+'/'+button.dataset.recordOrigin+'/'+button.dataset.recordId,{headers:{Accept:'application/json'},cache:'no-store',signal:detailController.signal});
            if(!response.ok)throw new Error('Unable to load this record. It may have been removed or your session expired.');
            const data=await response.json();
            const labels={reference:'Record',source:'Source',barangay:'Barangay',location:'Location',code:'Flood code',status:'Status',start_at:'Start / observed (PHT)',end_at:'Subsided (PHT)',duration_hours:'Duration (hours)',latitude:'Latitude',longitude:'Longitude',rainfall_24h_mm:'Rainfall 24h (mm)',rainfall_3d_mm:'Rainfall 3 days (mm)',rainfall_7d_mm:'Rainfall 7 days (mm)',temperature_c:'Temperature (°C)',humidity_pct:'Humidity (%)',nearest_waterway:'Nearest waterway',elevation_m:'Elevation (m)',distance_to_waterway_m:'Distance to waterway (m)',enrichment_status:'Weather enrichment'};
            content.innerHTML='<dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">'+Object.entries(labels).map(([key,label])=>`<div class="rounded-lg bg-slate-50 p-3"><dt class="text-sm text-slate-500">${escape(label)}</dt><dd class="mt-1 font-semibold">${escape(key.endsWith('_at')?date(data.record[key]):data.record[key])}</dd></div>`).join('')+'</dl><h3 class="text-lg font-semibold">Change history</h3>';
            const history=document.createElement('div');history.className='space-y-3';
            if(!data.history.length)history.textContent='No operational changes recorded for this entry.';
            data.history.forEach(change=>{const item=document.createElement('p');item.className='whitespace-pre-wrap border-l-2 border-sky-200 pl-3 text-sm';item.textContent=change.action+' · '+change.actor+' · '+date(change.at)+'\n'+change.code+(change.recorded_at?'\nRecorded: '+date(change.recorded_at):'')+(change.note?'\n'+change.note:'');history.appendChild(item);});
            content.appendChild(history);
        } catch(error) {if(error.name!=='AbortError')content.textContent=error.message;}
    });
    loadRecords();
});
</script>
@endpush
