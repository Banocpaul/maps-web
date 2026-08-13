<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <form method="GET" action="{{ route('dashboard') }}" class="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
        <div>
            <label for="year" class="mb-2 block text-sm font-medium text-slate-700">Data year</label>
            <select id="year" name="year" class="w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">All years</option>
                @foreach ($availableYears as $year)
                    <option value="{{ $year }}" @selected((string) $selectedYear === (string) $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="barangay_id" class="mb-2 block text-sm font-medium text-slate-700">Barangay</label>
            <select id="barangay_id" name="barangay_id" class="w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                <option value="">All barangays</option>
                @foreach ($barangays as $barangay)
                    <option value="{{ $barangay->id }}" @selected((string) $selectedBarangayId === (string) $barangay->id)>
                        {{ $barangay->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="rounded-xl bg-sky-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-800">
                Apply
            </button>
            <a href="{{ route('dashboard') }}" class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Reset
            </a>
        </div>
    </form>
</section>
