<div class="flex flex-wrap items-end gap-2">
    <div class="min-w-0 flex-1">
        <label for="{{ $searchId }}" class="mb-2 block text-sm font-medium text-slate-700">Search recipients</label>
        <input id="{{ $searchId }}" type="search" data-recipient-search placeholder="Name, phone, position, or barangay" autocomplete="off" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
    </div>
    <button type="button" data-recipient-search-clear disabled class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-default disabled:opacity-40">Clear</button>
</div>
<div class="mt-2 flex flex-wrap justify-between gap-2 text-xs text-slate-500" aria-live="polite" aria-atomic="true">
    <span data-recipient-result-count>{{ $recipients->count() }} shown</span>
    @if ($showSelection ?? false)
        <span data-recipient-selected-count>0 selected</span>
    @endif
</div>
