<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <article class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm">
        <p class="text-sm font-medium text-red-700">Active Fire Incidents</p>
        <p class="mt-2 text-3xl font-bold text-red-700">{{ number_format($fireOperations['active'] ?? 0) }}</p>
    </article>
    <article class="rounded-2xl border border-sky-200 bg-sky-50 p-5 shadow-sm">
        <p class="text-sm font-medium text-sky-700">Flood Records</p>
        <p class="mt-2 text-3xl font-bold text-sky-800">{{ number_format($floodDashboard['kpis']['total_records'] ?? 0) }}</p>
    </article>
    <article class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">SMS Sent Today</p>
        <p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($operationsSummary['sms_sent_today'] ?? 0) }}</p>
    </article>
    <article class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">SMS Failed Today</p>
        <p class="mt-2 text-3xl font-bold text-amber-700">{{ number_format($operationsSummary['sms_failed_today'] ?? 0) }}</p>
    </article>
</section>

<section class="mt-6 flex flex-col gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-5 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h2 class="font-semibold text-blue-950">Incident Analytics</h2>
        <p class="mt-1 text-sm text-blue-700">View fire and flood charts, filters, and records.</p>
    </div>
    <a href="{{ route('incident-analytics.index') }}" class="inline-flex justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Open Incident Analytics</a>
</section>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"><div><h2 class="font-semibold text-slate-950">Command Shortcuts</h2><p class="mt-1 text-sm text-slate-500">Open the operational module required for coordination</p></div><div class="flex flex-wrap gap-2"><a href="{{ route('operational-records.index') }}" class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Operational Records</a><a href="{{ route('gis.index') }}" class="rounded-xl bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Open Citywide Map</a>@if (Route::has('sms.index'))<a href="{{ route('sms.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">SMS Center</a>@endif</div></div>
</section>
