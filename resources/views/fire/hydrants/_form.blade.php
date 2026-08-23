@csrf
@if ($method !== 'POST')
    @method($method)
@endif

<div class="grid gap-5 md:grid-cols-2">
    <label><span class="text-sm font-medium text-slate-700">Barangay</span><select name="barangay_id" required class="mt-2 w-full rounded-xl border-slate-300"><option value="">Select barangay</option>@foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @selected((int) old('barangay_id', $fireHydrant?->barangay_id) === $barangay->id)>{{ $barangay->name }}</option>@endforeach</select></label>
    <label><span class="text-sm font-medium text-slate-700">Hydrant code</span><input name="hydrant_code" required maxlength="100" value="{{ old('hydrant_code', $fireHydrant?->hydrant_code) }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
    <label class="md:col-span-2"><span class="text-sm font-medium text-slate-700">Location</span><input name="location" required maxlength="255" value="{{ old('location', $fireHydrant?->location) }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
    <label><span class="text-sm font-medium text-slate-700">Latitude</span><input name="latitude" type="number" step="0.0000001" min="-90" max="90" value="{{ old('latitude', $fireHydrant?->latitude) }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
    <label><span class="text-sm font-medium text-slate-700">Longitude</span><input name="longitude" type="number" step="0.0000001" min="-180" max="180" value="{{ old('longitude', $fireHydrant?->longitude) }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
    <label><span class="text-sm font-medium text-slate-700">Status</span><select name="status" required class="mt-2 w-full rounded-xl border-slate-300">@foreach(['Active', 'Inactive', 'Maintenance'] as $status)<option value="{{ $status }}" @selected(old('status', $fireHydrant?->status ?? 'Active') === $status)>{{ $status }}</option>@endforeach</select></label>
    <label><span class="text-sm font-medium text-slate-700">Installation date</span><input name="installation_date" type="date" value="{{ old('installation_date', $fireHydrant?->installation_date?->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
    <label><span class="text-sm font-medium text-slate-700">Last inspection date</span><input name="last_inspection_date" type="date" value="{{ old('last_inspection_date', $fireHydrant?->last_inspection_date?->format('Y-m-d')) }}" class="mt-2 w-full rounded-xl border-slate-300"></label>
    <label class="md:col-span-2"><span class="text-sm font-medium text-slate-700">Remarks</span><textarea name="remarks" rows="4" maxlength="2000" class="mt-2 w-full rounded-xl border-slate-300">{{ old('remarks', $fireHydrant?->remarks) }}</textarea></label>
</div>

@if ($errors->any())
    <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="mt-6 flex justify-end gap-3"><a href="{{ route('fire-hydrants.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a><button class="rounded-xl bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">{{ $submitLabel }}</button></div>
