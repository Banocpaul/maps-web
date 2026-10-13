@php
    $selectedRecipientIds = array_map('strval', array_filter((array) old('recipient_ids', []), 'is_scalar'));
@endphp
<fieldset data-recipient-search-scope>
    <legend class="mb-3 text-sm font-semibold text-slate-800">Select Recipients</legend>
    @include('sms.partials.recipient-search', ['searchId' => 'manual-recipient-search', 'showSelection' => true])
    <p class="mt-2 text-xs leading-5 text-slate-500">Only active internal officers can receive manual SMS. Public residents receive their subscribed automatic alerts.</p>
    <div class="mt-4 space-y-3">
        @foreach ($recipientGroups as $group)
            <details open data-recipient-group data-recipient-group-key="{{ $group['key'] }}"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white"
                aria-labelledby="manual-{{ $group['key'] }}-heading">
                <summary id="manual-{{ $group['key'] }}-heading"
                    class="flex cursor-pointer list-none items-center justify-between gap-3 bg-sky-50/70 px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-sky-100/70">
                    <span class="flex min-w-0 items-center gap-2">
                        <svg class="h-4 w-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="8" r="3"/><path d="M2 19v-1c0-3 2-5 6-5s6 2 6 5v1H2Zm14-14a3 3 0 0 1 0 6m1 3c2 .5 3 2 3 4v1h-3"/></svg>
                        {{ $group['label'] }}
                        <span data-recipient-group-count class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">{{ $group['recipients']->count() }}</span>
                    </span>
                    <svg class="sms-group-chevron h-4 w-4 shrink-0 text-slate-500 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </summary>
                @if ($group['key'] === 'public')
                    <p class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">Residents manage their own subscriptions; manual sending is unavailable.</p>
                @endif
                <div class="max-h-72 divide-y divide-slate-100 overflow-y-auto px-2">
                    @foreach ($group['recipients'] as $recipient)
                        <label data-recipient-row data-recipient-id="{{ $recipient->id }}"
                            data-recipient-search-text="{{ implode(' ', [$recipient->full_name, $recipient->phone_number, $recipient->position, $recipient->barangay?->name, $recipient->office_or_barangay]) }}"
                            data-recipient-phone="{{ $recipient->phone_number }}"
                            class="flex items-center gap-3 rounded-lg px-2 py-3 transition hover:bg-slate-50">
                            <input type="checkbox" name="recipient_ids[]" value="{{ $recipient->id }}"
                                class="h-4 w-4 shrink-0 rounded border-slate-300 text-blue-600 focus:ring-blue-500 disabled:cursor-not-allowed"
                                @checked($recipient->is_active && $recipient->user_id === null && in_array((string) $recipient->id, $selectedRecipientIds, true))
                                @disabled(! $recipient->is_active || $recipient->user_id !== null)>
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-100 text-xs font-bold text-blue-700" aria-hidden="true">
                                {{ mb_strtoupper(mb_substr(trim($recipient->full_name), 0, 1)) }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-900">{{ $recipient->full_name }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ $recipient->phone_number }} · {{ $recipient->barangay?->name ?: ($recipient->office_or_barangay ?: 'Unassigned') }}</span>
                            </span>
                            @unless ($recipient->is_active)
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-medium text-slate-600">Inactive</span>
                            @endunless
                        </label>
                    @endforeach
                    <p data-recipient-empty @if ($group['recipients']->isNotEmpty()) hidden @endif class="px-3 py-5 text-sm text-slate-500">No recipients to display.</p>
                </div>
            </details>
        @endforeach
    </div>
</fieldset>
