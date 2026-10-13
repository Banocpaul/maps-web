@extends('layouts.app')

@section('title', 'SMS Center')

@push('styles')
<style>
    /* The SMS page alone needs consistent hidden states under Tailwind utilities. */
    .maps-sms-page [data-recipient-row][hidden],
    .maps-sms-page [data-recipient-empty][hidden],
    .maps-sms-page [data-recipient-group][hidden] { display: none !important; }
    .maps-sms-page summary::-webkit-details-marker { display: none; }
    .maps-sms-page details[open] .sms-group-chevron { transform: rotate(180deg); }
</style>
@endpush

@section('content')
<div class="maps-sms-page space-y-6 rounded-3xl bg-gradient-to-b from-sky-50/80 via-slate-50/70 to-slate-50/30 p-3 sm:p-5 lg:p-6">

    <header class="flex flex-wrap items-center gap-4">
        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 to-blue-700 text-white shadow-sm" aria-hidden="true">
            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 5h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-5 3v-3a2 2 0 0 1-1-2V7a2 2 0 0 1 2-2Z"/>
                <path d="M8 10h8M8 14h5"/>
            </svg>
        </span>
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">SMS Center</h1>
            <p class="mt-1 text-sm leading-6 text-slate-600">Manage recipients, automation rules, manual messages, and SMS logs.</p>
        </div>
    </header>

    @if (session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
            <p class="font-semibold">Please review the following:</p>
            <ul class="mt-1 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="SMS delivery overview">
        <div class="flex items-center gap-4 rounded-2xl border border-slate-200/90 bg-white px-5 py-5 shadow-sm">
            <span class="flex h-13 w-13 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-blue-600" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="9" cy="8" r="3"/><path d="M3 19v-1c0-3 2.5-5 6-5s6 2 6 5v1H3ZM17 5a3 3 0 0 1 0 6m1 3c2.5.5 3 2 3 4v1h-4"/></svg>
            </span>
            <div><p class="text-sm font-medium text-slate-600">Active Recipients</p><p class="mt-1 text-3xl font-bold tabular-nums text-slate-950">{{ $statistics['active_recipients'] ?? 0 }}</p></div>
        </div>
        <div class="flex items-center gap-4 rounded-2xl border border-slate-200/90 bg-white px-5 py-5 shadow-sm">
            <span class="flex h-13 w-13 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </span>
            <div><p class="text-sm font-medium text-slate-600">Enabled Rules</p><p class="mt-1 text-3xl font-bold tabular-nums text-slate-950">{{ $statistics['enabled_rules'] ?? 0 }}</p></div>
        </div>
        <div class="flex items-center gap-4 rounded-2xl border border-emerald-100 bg-white px-5 py-5 shadow-sm">
            <span class="flex h-13 w-13 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4 20-7ZM11 13l11-11"/></svg>
            </span>
            <div><p class="text-sm font-medium text-slate-600">Sent Today</p><p class="mt-1 text-3xl font-bold tabular-nums text-emerald-600">{{ $statistics['sent_today'] ?? 0 }}</p></div>
        </div>
        <div class="flex items-center gap-4 rounded-2xl border border-rose-100 bg-white px-5 py-5 shadow-sm">
            <span class="flex h-13 w-13 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 9v5m0 3h.01"/></svg>
            </span>
            <div><p class="text-sm font-medium text-slate-600">Failed Today</p><p class="mt-1 text-3xl font-bold tabular-nums text-rose-600">{{ $statistics['failed_today'] ?? 0 }}</p></div>
        </div>
    </div>

    <div class="grid items-start gap-5 xl:grid-cols-2">
        @if (auth()->user()?->hasPermission('sms.recipients.manage'))
            <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm" aria-labelledby="sms-add-heading">
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-blue-600" aria-hidden="true">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="10" cy="7" r="3"/><path d="M3.5 20v-2c0-3.4 2.6-5.5 6.5-5.5 1.6 0 2.9.3 4 .9M18 14v7m-3.5-3.5h7"/></svg>
                    </span>
                    <div><h2 id="sms-add-heading" class="text-lg font-bold text-slate-900">Add SMS Recipient</h2>
                        <p class="mt-0.5 text-xs leading-5 text-slate-500">Register a new recipient to receive alerts and notifications.</p></div>
                </div>
                <form method="POST" action="{{ route('sms.recipients.store') }}" class="space-y-5 p-5 sm:p-6">
                    @csrf
                    <div>
                        <label for="sms-full_name" class="mb-2 block text-sm font-semibold text-slate-800">Full Name <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <input id="sms-full_name" type="text" name="full_name" value="{{ old('full_name') }}"
                            placeholder="Enter full name" autocomplete="name" maxlength="255" required
                            class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div>
                        <label for="sms-phone_number" class="mb-2 block text-sm font-semibold text-slate-800">Phone Number <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <input id="sms-phone_number" type="tel" name="phone_number" value="{{ old('phone_number') }}"
                            placeholder="09XXXXXXXXX" autocomplete="tel" maxlength="30" required
                            class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="sms-position" class="mb-2 block text-sm font-semibold text-slate-800">Position</label>
                            <input id="sms-position" type="text" name="position" value="{{ old('position') }}" maxlength="255" placeholder="e.g. Team Leader"
                                class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        </div>
                        <div>
                            <label for="sms-office_or_barangay" class="mb-2 block text-sm font-semibold text-slate-800">Office or Barangay</label>
                            <input id="sms-office_or_barangay" type="text" name="office_or_barangay" value="{{ old('office_or_barangay') }}" maxlength="255" placeholder="Enter office or barangay"
                                class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        </div>
                    </div>
                    <div>
                        <label for="sms-barangay_id" class="mb-2 block text-sm font-semibold text-slate-800">Assigned Barangay for Automatic Alerts</label>
                        <select id="sms-barangay_id" name="barangay_id"
                            class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="">No barangay assignment</option>
                            @foreach ($barangays as $barangay)
                                <option value="{{ $barangay->id }}" @selected((string) old('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-slate-500">Required for automatic barangay-specific fire alerts.</p>
                    </div>
                    <fieldset class="rounded-xl border border-sky-100 bg-sky-50/70 p-4">
                        <legend class="sr-only">Alert preferences and recipient status</legend>
                        <div class="mb-3 flex items-start gap-2">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg>
                            <div><p class="text-sm font-semibold text-slate-900">Alert Preferences</p><p class="text-xs text-slate-500">Choose which notifications this recipient receives.</p></div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-center gap-2.5 text-sm text-slate-700"><input type="checkbox" name="receive_flood_alerts" value="1" @checked(old('receive_flood_alerts', true)) class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">Flood alerts</label>
                            <label class="flex items-center gap-2.5 text-sm text-slate-700"><input type="checkbox" name="receive_fire_alerts" value="1" @checked(old('receive_fire_alerts', false)) class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">Fire alerts</label>
                            <label class="flex items-center gap-2.5 text-sm text-slate-700"><input type="checkbox" name="receive_general_alerts" value="1" @checked(old('receive_general_alerts', true)) class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">General alerts</label>
                            <label class="flex items-center gap-2.5 text-sm text-slate-700"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">Active</label>
                        </div>
                    </fieldset>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                        <span aria-hidden="true" class="text-lg leading-none">+</span> Add Recipient
                    </button>
                </form>
            </section>
        @endif

        @if (auth()->user()?->hasPermission('sms.send'))
            <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm" aria-labelledby="sms-send-heading">
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-blue-600" aria-hidden="true">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4 20-7ZM11 13l11-11"/></svg>
                    </span>
                    <div><h2 id="sms-send-heading" class="text-lg font-bold text-slate-900">Send Manual SMS</h2>
                        <p class="mt-0.5 text-xs leading-5 text-slate-500">Select eligible recipients and compose your message.</p></div>
                </div>
                <form method="POST" action="{{ route('sms.send') }}" class="space-y-5 p-5 sm:p-6">
                    @csrf
                    @include('sms.partials.manual-recipients')
                    <div>
                        <label for="sms-message" class="mb-2 block text-sm font-semibold text-slate-800">Message <span class="text-rose-600" aria-hidden="true">*</span></label>
                        <textarea id="sms-message" name="message" rows="5" maxlength="1000" required placeholder="Type the message to send to selected recipients..."
                            class="block w-full resize-y rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">{{ old('message') }}</textarea>
                        <p class="mt-1.5 text-xs text-slate-500">Maximum 1,000 characters. SMS delivery may use multiple segments.</p>
                    </div>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m22 2-7 20-4-9-9-4 20-7Z"/></svg>
                        Send SMS
                    </button>
                </form>
            </section>
        @endif
    </div>

    <section data-recipient-search-scope class="space-y-4" aria-labelledby="recipient-directory-heading">
        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm sm:px-6">
            <h2 id="recipient-directory-heading" class="mb-1 text-lg font-bold text-slate-900">SMS Recipients</h2>
            <p class="mb-4 text-xs text-slate-500">Search and manage registered officers and resident alert preferences.</p>
            @include('sms.partials.recipient-search', ['searchId' => 'directory-recipient-search'])
        </div>
        @foreach ($recipientGroups as $group)
            @include('sms.partials.recipient-directory-group')
        @endforeach
    </section>

    <section id="sms-logs" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
                Recent SMS History
            </h2>
            <form method="GET" action="{{ route('sms.index') }}#sms-logs" class="mt-3 flex flex-wrap items-end gap-3">
                <div><label for="sms-log-status" class="mb-1 block text-xs font-semibold">Status</label><select id="sms-log-status" name="status" class="rounded-lg border-slate-300 text-sm"><option value="">All statuses</option>@foreach (['pending', 'sent', 'failed'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
                <div><label for="sms-log-date" class="mb-1 block text-xs font-semibold">Date (Manila)</label><input id="sms-log-date" type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="rounded-lg border-slate-300 text-sm"></div>
                <button class="rounded-lg bg-sky-700 px-3 py-2 text-sm font-semibold text-white">Apply</button>
                <a href="{{ route('sms.index') }}#sms-logs" class="px-3 py-2 text-sm font-semibold text-slate-600">Reset</a>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Date
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Recipient
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Source
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Status
                        </th>

                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                            Message
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 bg-white">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                {{ $log->created_at?->format('M d, Y h:i A') }}
                            </td>

                            <td class="px-6 py-4">
                                <p class="text-sm font-medium text-slate-900">
                                    {{ $log->recipient_name ?: 'Unknown recipient' }}
                                </p>

                                <p class="text-xs text-slate-500">
                                    {{ $log->phone_number }}
                                </p>
                            </td>

                            <td class="px-6 py-4 text-sm capitalize text-slate-700">
                                {{ $log->source }}
                            </td>

                            <td class="px-6 py-4">
                                @if ($log->status === 'sent')
                                    <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                        Sent
                                    </span>
                                @elseif ($log->status === 'failed')
                                    <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">
                                        Failed
                                    </span>
                                @else
                                    <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-700">
                                        Pending
                                    </span>
                                @endif
                            </td>

                            <td class="max-w-md px-6 py-4 text-sm text-slate-700">
                                <p class="line-clamp-2">
                                    {{ $log->message }}
                                </p>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="5"
                                class="px-6 py-10 text-center text-sm text-slate-500"
                            >
                                No SMS messages have been recorded.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
