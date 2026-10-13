{{-- One Flood Prediction workspace with current forecast and saved history tabs.
     The administrator must not receive the run-prediction tab. --}}
@php
    $showCurrentTab = auth()->user()?->hasPermission('prediction.run')
        && ! auth()->user()?->isAdministrator();
@endphp
<nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-3" aria-label="Flood prediction sections">
    @if ($showCurrentTab && Route::has('prediction.index'))
        <a href="{{ route('prediction.index') }}"
           @class([
               'inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-semibold transition',
               'bg-sky-700 text-white' => $activeTab === 'current',
               'bg-slate-100 text-slate-700 hover:bg-slate-200' => $activeTab !== 'current',
           ])
           @if ($activeTab === 'current') aria-current="page" @endif>
            Current Prediction
        </a>
    @endif
    @if (Route::has('prediction.history.index'))
        <a href="{{ route('prediction.history.index') }}"
           @class([
               'inline-flex items-center rounded-lg px-4 py-2.5 text-sm font-semibold transition',
               'bg-sky-700 text-white' => $activeTab === 'history',
               'bg-slate-100 text-slate-700 hover:bg-slate-200' => $activeTab !== 'history',
           ])
           @if ($activeTab === 'history') aria-current="page" @endif>
            Prediction History
        </a>
    @endif
</nav>
