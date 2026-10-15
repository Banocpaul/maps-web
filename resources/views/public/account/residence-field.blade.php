<label class="block text-sm font-semibold">Residence type
<select name="residence_type" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white p-3">
<option value="">Not recorded</option>
@foreach(['Homeowner', 'Tenant', 'Boarder', 'Living with family', 'Other'] as $type)
<option @selected(old('residence_type', $resident->residence_type ?? '') === $type)>{{ $type }}</option>
@endforeach
</select></label>
