<div class="grid gap-4 sm:grid-cols-2 mt-4">
@foreach(['injured_count' => 'Injured', 'deceased_count' => 'Deceased'] as $field => $label)
<label class="block font-semibold">{{ $label }} (leave blank if unknown)
<input name="{{ $field }}" type="number" min="0" max="4294967295" step="1" value="{{ old($field, $record->{$field} ?? null) }}" class="block w-full rounded-lg border border-slate-300 p-2 mt-2"></label>
@endforeach
</div>
