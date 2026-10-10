<div class="grid gap-5 sm:grid-cols-2">
    <label class="block text-sm font-semibold" for="first_name">First name
        <input id="first_name" name="first_name" autocomplete="given-name" required maxlength="100" value="{{ old('first_name', $resident->first_name ?? '') }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white p-3">
    </label>
    <label class="block text-sm font-semibold" for="last_name">Last name
        <input id="last_name" name="last_name" autocomplete="family-name" required maxlength="100" value="{{ old('last_name', $resident->last_name ?? '') }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white p-3">
    </label>
    <label class="block text-sm font-semibold" for="contact_number">Mobile number
        <input id="contact_number" name="contact_number" type="tel" autocomplete="tel" required maxlength="30" placeholder="09171234567" value="{{ old('contact_number', $resident->contact_number ?? '') }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white p-3">
    </label>
    <label class="block text-sm font-semibold" for="barangay_id">Your barangay
        <select id="barangay_id" name="barangay_id" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white p-3">
            <option value="">Select your barangay</option>
            @foreach($barangays as $barangay)<option value="{{ $barangay->id }}" @selected((string) old('barangay_id', $resident->barangay_id ?? '') === (string) $barangay->id)>{{ $barangay->name }}</option>@endforeach
        </select>
    </label>
</div>
<fieldset class="mt-6 space-y-3 rounded-xl border border-blue-200 bg-blue-50 p-5">
    <legend class="px-2 font-bold">Barangay SMS alerts (optional)</legend>
    <input type="hidden" name="receive_flood_alerts" value="0">
    <input type="hidden" name="receive_fire_alerts" value="0">
    <p class="text-sm text-slate-600">Choose the messages you want to receive at your mobile number for your selected barangay. You can turn them off at any time.</p>
    <label class="flex items-center gap-3"><input type="checkbox" name="receive_flood_alerts" value="1" @checked(old('receive_flood_alerts', $resident->receive_flood_alerts ?? false)) class="h-5 w-5">Receive flood alerts</label>
    <label class="flex items-center gap-3"><input type="checkbox" name="receive_fire_alerts" value="1" @checked(old('receive_fire_alerts', $resident->receive_fire_alerts ?? false)) class="h-5 w-5">Receive fire alerts</label>
    <p class="text-xs text-slate-600">Alerts are sent when staff record confirmed active incidents. Delivery depends on mobile coverage and the SMS gateway.</p>
</fieldset>
