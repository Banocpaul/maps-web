@if (auth()->user()?->hasPermission('flood.edit'))
    @if ($record->status === 'Active')
        @if ($record->flood_code !== 'D')
            <form class="inline-flex items-center gap-2" method="POST" action="{{ route('operational-records.flood.raise', $record->id) }}">
                @csrf
                <select name="flood_code" aria-label="Raise flood code for record {{ $record->id }}" class="rounded-lg border-slate-300 py-1 text-sm">@foreach (['A', 'B', 'C', 'D'] as $code)@if ($code > $record->flood_code)<option value="{{ $code }}">{{ $code }}</option>@endif @endforeach</select>
                <button class="font-semibold text-amber-800">Raise code</button>
            </form>
        @endif
        <form class="ml-3 inline" method="POST" action="{{ route('operational-records.flood.subside', $record->id) }}" onsubmit="return confirm('Mark this flood as subsided? The code will be locked.');">@csrf<button class="font-semibold text-emerald-700">Mark subsided</button></form>
    @endif
    @if (($record->enrichment_status ?? null) === 'Pending data')
        <form class="ml-3 inline" method="POST" action="{{ route('operational-records.flood.enrich', $record->id) }}">@csrf<button class="font-semibold text-sky-700">Retry data</button></form>
    @endif
@endif
