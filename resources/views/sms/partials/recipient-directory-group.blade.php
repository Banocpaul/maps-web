<section data-recipient-group data-recipient-group-key="{{ $group['key'] }}" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="directory-{{ $group['key'] }}-heading">
    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
        <h3 id="directory-{{ $group['key'] }}-heading" class="font-semibold text-slate-900">{{ $group['label'] }}</h3>
        <span data-recipient-group-count class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $group['recipients']->count() }}</span>
    </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Name
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Phone
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Location
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Alerts
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Status
                        </th>

                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-slate-500">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 bg-white">
                    @foreach ($group['recipients'] as $recipient)
                        <tr data-recipient-row data-recipient-id="{{ $recipient->id }}" data-recipient-search-text="{{ implode(' ', [$recipient->full_name, $recipient->phone_number, $recipient->position, $recipient->barangay?->name, $recipient->office_or_barangay]) }}" data-recipient-phone="{{ $recipient->phone_number }}">
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900">
                                    {{ $recipient->full_name }}
                                </p>

                                <p class="text-xs text-slate-500">
                                    {{ $recipient->position ?: 'No position specified' }}
                                </p>
                            </td>

                            <td class="px-6 py-4 text-sm text-slate-700">
                                {{ $recipient->phone_number }}
                            </td>

                            <td class="px-6 py-4 text-sm text-slate-700">
                                {{ $recipient->barangay?->name ?: ($recipient->office_or_barangay ?: '—') }}
                            </td>

                            <td class="px-6 py-4 text-sm text-slate-700">
                                <div class="flex flex-wrap gap-1">
                                    @if ($recipient->receive_flood_alerts)
                                        <span class="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-700">
                                            Flood
                                        </span>
                                    @endif

                                    @if ($recipient->receive_fire_alerts)
                                        <span class="rounded-full bg-orange-100 px-2 py-1 text-xs text-orange-700">
                                            Fire
                                        </span>
                                    @endif

                                    @if ($recipient->receive_general_alerts)
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-700">
                                            General
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                @if ($recipient->is_active)
                                    <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                        Active
                                    </span>
                                @else
                                    <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">
                                        Inactive
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-right">
                               @if($recipient->user_id !== null)
                                    <span class="text-xs text-slate-500">Resident manages preferences</span>
                               @elseif(auth()->user()?->hasPermission('sms.recipients.manage'))
                                    <div class="flex justify-end gap-2">
                                        <form
                                            method="POST"
                                            action="{{ route('sms.recipients.status', $recipient) }}"
                                        >
                                            @csrf
                                            @method('PATCH')

                                            <button
                                                type="submit"
                                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                            >
                                                {{ $recipient->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="{{ route('sms.recipients.destroy', $recipient) }}"
                                            onsubmit="return confirm('Delete this SMS recipient?')"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                        <tr data-recipient-empty @if ($group['recipients']->isNotEmpty()) hidden @endif>
                            <td
                                colspan="6"
                                class="px-6 py-10 text-center text-sm text-slate-500"
                            >
                                No recipients to display.
                            </td>
                        </tr>
                </tbody>
            </table>
        </div>
</section>
