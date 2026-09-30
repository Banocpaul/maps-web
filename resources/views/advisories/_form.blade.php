@php
    $editing = isset($advisory);
    $selectedType = old('type', $advisory->type ?? 'general');
    $selectedDate = old(
        'advisory_date',
        isset($advisory)
            ? $advisory->advisory_date->format('Y-m-d')
            : now('Asia/Manila')->format('Y-m-d')
    );

    $typeLabels = [
        'flood' => 'Flood',
        'fire' => 'Fire',
        'weather' => 'Weather',
        'evacuation' => 'Evacuation',
        'class-suspension' => 'Class Suspension',
        'general' => 'General',
    ];

    $existingPhotoUrl = null;

    if ($editing && $advisory->photo_path) {
        try {
            $existingPhotoUrl = \Illuminate\Support\Facades\Storage::disk(
                config('filesystems.advisory_disk', 'public')
            )->url($advisory->photo_path);
        } catch (\Throwable $exception) {
            $existingPhotoUrl = null;
        }
    }
@endphp

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
    <div class="space-y-6">
        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
            <label for="advisory_date" class="block text-xs font-black uppercase tracking-[0.16em] text-blue-700">
                Advisory Date *
            </label>

            <input
                id="advisory_date"
                name="advisory_date"
                type="date"
                required
                value="{{ $selectedDate }}"
                class="mt-3 w-full rounded-xl border border-blue-300 bg-white px-4 py-3 text-lg font-black text-blue-950 outline-none transition focus:border-blue-600 focus:ring-4 focus:ring-blue-100"
            >

            <p class="mt-2 text-xs text-blue-700">
                This is the primary date residents will see. Advisories are sorted by this date from newest to oldest.
            </p>
        </div>

        <div>
            <label for="type" class="block text-sm font-bold text-slate-700">
                Advisory Type *
            </label>

            <select
                id="type"
                name="type"
                required
                class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            >
                @foreach ($types as $type)
                    <option
                        value="{{ $type }}"
                        @selected($selectedType === $type)
                    >
                        {{ $typeLabels[$type] ?? ucfirst(str_replace('-', ' ', $type)) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="subject" class="block text-sm font-bold text-slate-700">
                Subject *
            </label>

            <input
                id="subject"
                name="subject"
                type="text"
                required
                maxlength="255"
                value="{{ old('subject', $advisory->subject ?? '') }}"
                placeholder="Example: Heavy Rainfall Advisory"
                class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            >
        </div>

        <div>
            <label for="message" class="block text-sm font-bold text-slate-700">
                Message *
            </label>

            <textarea
                id="message"
                name="message"
                rows="10"
                required
                maxlength="10000"
                placeholder="Write the complete public advisory message..."
                class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm leading-6 text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            >{{ old('message', $advisory->message ?? '') }}</textarea>

            <p class="mt-2 text-xs text-slate-500">
                Use clear public-facing language. Maximum 10,000 characters.
            </p>
        </div>
    </div>

    <aside class="space-y-5">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-700">
                Optional Photo
            </h2>

            @if ($existingPhotoUrl)
                <img
                    src="{{ $existingPhotoUrl }}"
                    alt="Current advisory photo"
                    class="mt-4 max-h-60 w-full rounded-xl border border-slate-200 bg-white object-cover"
                >
            @endif

            <label for="photo" class="mt-4 block text-sm font-bold text-slate-700">
                {{ $editing && $existingPhotoUrl ? 'Replace photo' : 'Attach photo' }}
            </label>

            <input
                id="photo"
                name="photo"
                type="file"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-3 py-3 text-xs text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-bold file:text-blue-700"
            >

            <p class="mt-2 text-xs leading-5 text-slate-500">
                JPEG, PNG, or WebP. Maximum 5 MB.
            </p>

            @if ($editing && $advisory->photo_path)
                <label class="mt-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-3">
                    <input
                        type="checkbox"
                        name="remove_photo"
                        value="1"
                        class="mt-1 rounded border-red-300 text-red-600 focus:ring-red-500"
                    >
                    <span class="text-xs font-semibold leading-5 text-red-700">
                        Remove the current photo when saving.
                    </span>
                </label>
            @endif
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-sm font-bold text-amber-900">
                Public Notice
            </p>

            <p class="mt-2 text-xs leading-5 text-amber-800">
                Once published, this advisory is intended for the public portal.
                Verify the date, subject, message, and attached image before saving.
            </p>
        </div>
    </aside>
</div>

<div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:justify-end">
    <a
        href="{{ route('advisories.index') }}"
        class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
    >
        Cancel
    </a>

    <button
        type="submit"
        class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700"
    >
        {{ $editing ? 'Save Changes' : 'Publish Advisory' }}
    </button>
</div>
