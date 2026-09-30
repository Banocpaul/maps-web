@extends('layouts.app')

@section('title', 'Public Advisories')

@section('content')
@php
    $typeLabels = [
        'flood' => 'Flood',
        'fire' => 'Fire',
        'weather' => 'Weather',
        'evacuation' => 'Evacuation',
        'class-suspension' => 'Class Suspension',
        'general' => 'General',
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-blue-600">
                Public Information
            </p>

            <h1 class="mt-1 text-3xl font-black text-slate-900">
                Public Advisories
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                Publish and review official public notices. Advisories are shown
                to residents from newest to oldest by advisory date.
            </p>
        </div>

        <a
            href="{{ route('advisories.create') }}"
            class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700"
        >
            + Create Advisory
        </a>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">
                        Published Advisories
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Newest advisory dates appear first.
                    </p>
                </div>

                <div class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-600">
                    {{ $advisories->total() }} total
                </div>
            </div>
        </div>

        @if ($advisories->isEmpty())
            <div class="px-6 py-14 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                    </svg>
                </div>

                <h3 class="mt-4 text-lg font-bold text-slate-900">
                    No public advisories yet
                </h3>

                <p class="mt-2 text-sm text-slate-500">
                    Create the first official advisory when a public notice is needed.
                </p>
            </div>
        @else
            <div class="divide-y divide-slate-200">
                @foreach ($advisories as $advisory)
                    @php
                        $isOwner = (int) $advisory->user_id === (int) auth()->id();
                        $roleName = $advisory->user?->role?->name ?? 'Former/Unavailable Account';
                        $posterName = $advisory->user?->full_name
                            ?: $advisory->user?->name
                            ?: 'Former/Unavailable Account';

                        $photoUrl = null;

                        if ($advisory->photo_path) {
                            try {
                                $photoUrl = \Illuminate\Support\Facades\Storage::disk(
                                    config('filesystems.advisory_disk', 'public')
                                )->url($advisory->photo_path);
                            } catch (\Throwable $exception) {
                                $photoUrl = null;
                            }
                        }
                    @endphp

                    <article class="p-5 sm:p-6">
                        <div class="grid gap-5 lg:grid-cols-[150px_minmax(0,1fr)_180px]">
                            <div>
                                <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-4 text-center">
                                    <p class="text-xs font-black uppercase tracking-[0.16em] text-blue-600">
                                        Advisory Date
                                    </p>

                                    <p class="mt-2 text-2xl font-black leading-none text-blue-950">
                                        {{ $advisory->advisory_date->format('M d') }}
                                    </p>

                                    <p class="mt-1 text-sm font-bold text-blue-700">
                                        {{ $advisory->advisory_date->format('Y') }}
                                    </p>

                                    <p class="mt-2 text-xs font-semibold text-blue-600">
                                        {{ $advisory->advisory_date->format('l') }}
                                    </p>
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-slate-700">
                                        {{ $typeLabels[$advisory->type] ?? ucfirst(str_replace('-', ' ', $advisory->type)) }}
                                    </span>

                                    @if ($isOwner)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                                            Your advisory
                                        </span>
                                    @endif
                                </div>

                                <h3 class="mt-3 break-words text-xl font-black text-slate-900">
                                    {{ $advisory->subject }}
                                </h3>

                                <p class="mt-3 whitespace-pre-line break-words text-sm leading-6 text-slate-600">
                                    {{ \Illuminate\Support\Str::limit($advisory->message, 320) }}
                                </p>

                                @if ($photoUrl)
                                    <div class="mt-4">
                                        <img
                                            src="{{ $photoUrl }}"
                                            alt="Photo attached to {{ $advisory->subject }}"
                                            class="max-h-72 w-full rounded-xl border border-slate-200 object-cover sm:max-w-xl"
                                            loading="lazy"
                                        >
                                    </div>
                                @endif

                                <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-500">
                                    <span>
                                        Posted by
                                        <strong class="text-slate-700">{{ $posterName }}</strong>
                                    </span>

                                    <span>
                                        Department/Role:
                                        <strong class="text-slate-700">{{ $roleName }}</strong>
                                    </span>

                                    <span>
                                        Published:
                                        <strong class="text-slate-700">
                                            {{ $advisory->created_at?->format('M d, Y h:i A') }}
                                        </strong>
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-start justify-start gap-2 lg:justify-end">
                                @if ($isOwner)
                                    <a
                                        href="{{ route('advisories.edit', $advisory) }}"
                                        class="inline-flex rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route('advisories.destroy', $advisory) }}"
                                        onsubmit="return confirm('Delete this public advisory? This action cannot be undone.');"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="inline-flex rounded-lg bg-red-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-red-700"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs font-semibold text-slate-400">
                                        View only
                                    </span>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($advisories->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">
                    {{ $advisories->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
