@extends('layouts.app')

@section('title', 'SMS Center')

@section('content')
<div class="space-y-6">

    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                SMS Center
            </h1>

            <p class="text-sm text-slate-500">
                Manage recipients, automation rules, manual messages, and SMS logs.
            </p>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">
                Active Recipients
            </p>

            <p class="mt-2 text-3xl font-bold text-slate-900">
                {{ $statistics['active_recipients'] ?? 0 }}
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">
                Enabled Rules
            </p>

            <p class="mt-2 text-3xl font-bold text-slate-900">
                {{ $statistics['enabled_rules'] ?? 0 }}
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">
                Sent Today
            </p>

            <p class="mt-2 text-3xl font-bold text-green-600">
                {{ $statistics['sent_today'] ?? 0 }}
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm font-medium text-slate-500">
                Failed Today
            </p>

            <p class="mt-2 text-3xl font-bold text-red-600">
                {{ $statistics['failed_today'] ?? 0 }}
            </p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">

        @if(auth()->user()?->hasPermission('sms.recipients.manage'))
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">
                        Add SMS Recipient
                    </h2>
                </div>

                <form
                    method="POST"
                    action="{{ route('sms.recipients.store') }}"
                    class="space-y-4 p-6"
                >
                    @csrf

                    <div>
                        <label for="sms-full_name" class="mb-1 block text-sm font-medium text-slate-700">
                            Full Name
                        </label>

                        <input id="sms-full_name"
                            type="text"
                            name="full_name"
                            value="{{ old('full_name') }}"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <div>
                        <label for="sms-phone_number" class="mb-1 block text-sm font-medium text-slate-700">
                            Phone Number
                        </label>

                        <input id="sms-phone_number"
                            type="text"
                            name="phone_number"
                            value="{{ old('phone_number') }}"
                            placeholder="09XXXXXXXXX"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        >
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="sms-position" class="mb-1 block text-sm font-medium text-slate-700">
                                Position
                            </label>

                            <input id="sms-position"
                                type="text"
                                name="position"
                                value="{{ old('position') }}"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >
                        </div>

                        <div>
                            <label for="sms-office_or_barangay" class="mb-1 block text-sm font-medium text-slate-700">
                                Office or Barangay
                            </label>

                            <input id="sms-office_or_barangay"
                                type="text"
                                name="office_or_barangay"
                                value="{{ old('office_or_barangay') }}"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="sms-barangay_id" class="mb-1 block text-sm font-medium text-slate-700">
                            Assigned Barangay for Automatic Alerts
                        </label>

                        <select id="sms-barangay_id"
                            name="barangay_id"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                            <option value="">No barangay assignment</option>
                            @foreach ($barangays as $barangay)
                                <option value="{{ $barangay->id }}" @selected((string) old('barangay_id') === (string) $barangay->id)>
                                    {{ $barangay->name }}
                                </option>
                            @endforeach
                        </select>

                        <p class="mt-1 text-xs text-slate-500">
                            Required for automatic barangay-specific fire alerts.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="receive_flood_alerts"
                                value="1"
                                checked
                                class="rounded border-slate-300"
                            >
                            Flood alerts
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="receive_fire_alerts"
                                value="1"
                                class="rounded border-slate-300"
                            >
                            Fire alerts
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="receive_general_alerts"
                                value="1"
                                checked
                                class="rounded border-slate-300"
                            >
                            General alerts
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                checked
                                class="rounded border-slate-300"
                            >
                            Active
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
                    >
                        Add Recipient
                    </button>
                </form>
            </section>
        @endif

        @if(auth()->user()?->hasPermission('sms.send'))
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">
                        Send Manual SMS
                    </h2>
                </div>

                <form
                    method="POST"
                    action="{{ route('sms.send') }}"
                    class="space-y-4 p-6"
                >
                    @csrf

                    @include('sms.partials.manual-recipients')

                    <div>
                        <label for="sms-message" class="mb-1 block text-sm font-medium text-slate-700">
                            Message
                        </label>

                        <textarea id="sms-message"
                            name="message"
                            rows="6"
                            maxlength="1000"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                        >{{ old('message') }}</textarea>
                    </div>

                    <button
                        type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white hover:bg-blue-700"
                    >
                        Send SMS
                    </button>
                </form>
            </section>
        @endif
    </div>

    <section data-recipient-search-scope class="space-y-4" aria-labelledby="recipient-directory-heading">
        <div class="rounded-xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
            <h2 id="recipient-directory-heading" class="mb-3 text-lg font-semibold text-slate-900">SMS Recipients</h2>
            @include('sms.partials.recipient-search', ['searchId' => 'directory-recipient-search'])
        </div>
        @foreach ($recipientGroups as $group)
            @include('sms.partials.recipient-directory-group')
        @endforeach
    </section>

    <section id="sms-logs" class="rounded-xl border border-slate-200 bg-white shadow-sm">
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
