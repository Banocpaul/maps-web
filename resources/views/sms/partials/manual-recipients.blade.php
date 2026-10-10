@php
    $selectedRecipientIds = array_map('strval', array_filter((array) old('recipient_ids', []), 'is_scalar'));
@endphp
<fieldset data-recipient-search-scope>
    <legend class="mb-2 text-sm font-medium text-slate-700">Select Recipients</legend>
    @include('sms.partials.recipient-search', ['searchId' => 'manual-recipient-search', 'showSelection' => true])
    <div class="mt-3 space-y-3 rounded-lg border border-slate-200 p-3">
        @foreach ($recipientGroups as $group)
            <section data-recipient-group data-recipient-group-key="{{ $group['key'] }}" aria-labelledby="manual-{{ $group['key'] }}-heading">
                <h3 id="manual-{{ $group['key'] }}-heading" class="flex items-center gap-2 border-b border-slate-100 pb-2 text-sm font-semibold text-slate-800">
                    {{ $group['label'] }} <span data-recipient-group-count class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $group['recipients']->count() }}</span>
                </h3>
                @if ($group['key'] === 'public')
                    <p class="my-2 text-xs text-slate-500">Barangay alerts; residents manage their preferences.</p>
                @endif
                <div class="max-h-44 overflow-y-auto">
                @foreach ($group['recipients'] as $recipient)
                    <label data-recipient-row data-recipient-id="{{ $recipient->id }}" data-recipient-search-text="{{ implode(' ', [$recipient->full_name, $recipient->phone_number, $recipient->position, $recipient->barangay?->name, $recipient->office_or_barangay]) }}" data-recipient-phone="{{ $recipient->phone_number }}" class="flex items-start gap-3 rounded-lg p-2 hover:bg-slate-50">
                        <input type="checkbox" name="recipient_ids[]" value="{{ $recipient->id }}" class="mt-1 rounded border-slate-300" @checked($recipient->is_active && $recipient->user_id === null && in_array((string) $recipient->id, $selectedRecipientIds, true)) @disabled(! $recipient->is_active || $recipient->user_id !== null)>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-slate-900">{{ $recipient->full_name }}</span>
                            <span class="block text-xs text-slate-500">
                                {{ $recipient->phone_number }} · {{ $recipient->barangay?->name ?: ($recipient->office_or_barangay ?: 'Unassigned') }}
                                @unless ($recipient->is_active)
                                    — Inactive
                                @endunless
                            </span>
                        </span>
                    </label>
                @endforeach
                <p data-recipient-empty @if ($group['recipients']->isNotEmpty()) hidden @endif class="px-2 py-3 text-sm text-slate-500">No recipients to display.</p>
                </div>
            </section>
        @endforeach
    </div>
</fieldset>
