@php $confidencePercent = \App\Support\PredictionConfidence::percent($item); @endphp
@if ($confidencePercent !== null)
    <span class="font-semibold tabular-nums text-slate-900">{{ number_format($confidencePercent, 1) }}%</span>
@else
    <span class="text-slate-400">Unavailable</span>
@endif
