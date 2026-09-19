@extends('layouts.app')

@section('title', 'Backup & Recovery | M.A.P.S.')

@section('content')
    <div class="space-y-6">
        <section class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">
                    Administration
                </p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950">Backup & Recovery</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Create, verify, download, and safely restore encrypted TiDB backups.
                </p>
            </div>

            <button
                type="button"
                data-open-dialog="create-backup-dialog"
                class="inline-flex items-center justify-center rounded-xl bg-sky-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-800"
            >
                Create Manual Backup
            </button>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Total Backups', 'value' => $statistics['total'], 'color' => 'text-slate-950'],
                ['label' => 'Completed', 'value' => $statistics['completed'], 'color' => 'text-emerald-700'],
                ['label' => 'Verified', 'value' => $statistics['verified'], 'color' => 'text-sky-700'],
                ['label' => 'Failed', 'value' => $statistics['failed'], 'color' => 'text-red-700'],
            ] as $statistic)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ $statistic['label'] }}
                    </p>
                    <p class="mt-2 text-3xl font-black {{ $statistic['color'] }}">
                        {{ number_format($statistic['value']) }}
                    </p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                <h2 class="font-bold text-slate-950">Protection status</h2>
                @if ($latestCompleted)
                    <div class="mt-4 flex flex-col gap-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold text-emerald-900">A completed backup is available</p>
                            <p class="mt-1 text-sm text-emerald-800">
                                {{ $latestCompleted->completed_at?->timezone('Asia/Manila')->format('M d, Y h:i A') }}
                                · {{ $latestCompleted->formattedSize() }}
                                · {{ str($latestCompleted->type)->replace('_', ' ')->headline() }}
                            </p>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">
                            {{ $latestCompleted->verified_at ? 'Verified' : 'Verification recommended' }}
                        </span>
                    </div>
                @else
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        No completed backup is available. Create and verify the first backup now.
                    </div>
                @endif
            </article>

            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Storage disk</p>
                <p class="mt-2 text-lg font-bold text-slate-950">{{ $backupDisk }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Production should use a private Cloudflare R2 or S3-compatible bucket, never Render's temporary filesystem.
                </p>
            </article>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-bold text-slate-950">Backup history</h2>
                <p class="mt-1 text-sm text-slate-600">All dates and times are displayed in Asia/Manila.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Created</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Size</th>
                            <th class="px-5 py-3">Verification</th>
                            <th class="px-5 py-3">Created by</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($backups as $backup)
                            <tr class="align-top hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-5 py-4">
                                    <p class="font-medium text-slate-900">
                                        {{ $backup->created_at->timezone('Asia/Manila')->format('M d, Y') }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $backup->created_at->timezone('Asia/Manila')->format('h:i:s A') }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 font-medium text-slate-700">
                                    {{ str($backup->type)->replace('_', ' ')->headline() }}
                                </td>
                                <td class="px-5 py-4">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-xs font-bold',
                                        'bg-emerald-100 text-emerald-800' => $backup->status === 'completed',
                                        'bg-amber-100 text-amber-800' => $backup->status === 'processing',
                                        'bg-red-100 text-red-800' => $backup->status === 'failed',
                                    ])>
                                        {{ str($backup->status)->headline() }}
                                    </span>
                                    @if ($backup->error_message)
                                        <p class="mt-2 max-w-xs text-xs leading-5 text-red-700">
                                            {{ $backup->error_message }}
                                        </p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-700">
                                    {{ $backup->formattedSize() }}
                                </td>
                                <td class="px-5 py-4">
                                    @if ($backup->verified_at)
                                        <p class="font-medium text-sky-700">Verified</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $backup->verified_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}
                                        </p>
                                    @else
                                        <span class="text-slate-500">Not verified</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-slate-700">
                                    {{ $backup->creator?->full_name ?? ($backup->type === 'scheduled' ? 'Render Cron Job' : 'System') }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if ($backup->isCompleted())
                                            <button type="button" data-open-dialog="verify-{{ $backup->uuid }}" class="rounded-lg border border-sky-200 px-3 py-2 text-xs font-semibold text-sky-700 hover:bg-sky-50">
                                                Verify
                                            </button>
                                            <button type="button" data-open-dialog="download-{{ $backup->uuid }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                Download
                                            </button>
                                            @if ($backup->verified_at)
                                                <button type="button" data-open-dialog="restore-{{ $backup->uuid }}" class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-50">
                                                    Restore
                                                </button>
                                            @else
                                                <button type="button" disabled title="Verify this backup before restoring it" class="cursor-not-allowed rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-400">
                                                    Restore
                                                </button>
                                            @endif
                                        @endif
                                        <button type="button" data-open-dialog="delete-{{ $backup->uuid }}" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">
                                    No database backups have been created yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($backups->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $backups->links() }}</div>
            @endif
        </section>

        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
            <strong>Recovery safety:</strong>
            Only a completed and verified backup can be restored. The administrator must provide the current password,
            type <strong>RESTORE-MAPS</strong>, and the system creates a pre-restore safety backup automatically.
        </section>
    </div>

    <dialog id="create-backup-dialog" class="w-full max-w-md rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60">
        <form method="POST" action="{{ route('admin.backups.store') }}" class="p-6">
            @csrf
            <h2 class="text-lg font-bold text-slate-950">Create encrypted backup</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Confirm your administrator password. Keep this page open until the backup finishes.</p>
            <label class="mt-5 block text-sm font-semibold text-slate-700" for="create-current-password">Current password</label>
            <input id="create-current-password" name="current_password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-close-dialog class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                <button type="submit" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white">Create Backup</button>
            </div>
        </form>
    </dialog>

    @foreach ($backups as $backup)
        @if ($backup->isCompleted())
            @foreach (['verify' => 'Verify backup integrity', 'download' => 'Download encrypted backup'] as $action => $title)
                <dialog id="{{ $action }}-{{ $backup->uuid }}" class="w-full max-w-md rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60">
                    <form method="POST" action="{{ route('admin.backups.'.$action, $backup) }}" class="p-6">
                        @csrf
                        <h2 class="text-lg font-bold text-slate-950">{{ $title }}</h2>
                        <p class="mt-2 break-all text-xs text-slate-500">{{ $backup->filename }}</p>
                        <label class="mt-5 block text-sm font-semibold text-slate-700">Current password</label>
                        <input name="current_password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-200">
                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" data-close-dialog class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                            <button type="submit" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-semibold text-white">Continue</button>
                        </div>
                    </form>
                </dialog>
            @endforeach

            @if ($backup->verified_at)
                <dialog id="restore-{{ $backup->uuid }}" class="w-full max-w-md rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60">
                    <form method="POST" action="{{ route('admin.backups.restore', $backup) }}" class="p-6">
                        @csrf
                        <h2 class="text-lg font-bold text-amber-900">Restore this database backup?</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            This replaces the current TiDB data with the selected backup. A new safety backup will be created first.
                        </p>
                        <p class="mt-2 break-all rounded-lg bg-slate-100 p-3 text-xs text-slate-600">{{ $backup->filename }}</p>
                        <label class="mt-5 block text-sm font-semibold text-slate-700">Type RESTORE-MAPS to confirm</label>
                        <input name="confirmation" type="text" required autocomplete="off" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3">
                        <label class="mt-4 block text-sm font-semibold text-slate-700">Current password</label>
                        <input name="current_password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3">
                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" data-close-dialog class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                            <button type="submit" class="rounded-xl bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">Create Safety Backup & Restore</button>
                        </div>
                    </form>
                </dialog>
            @endif
        @endif

        <dialog id="delete-{{ $backup->uuid }}" class="w-full max-w-md rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60">
            <form method="POST" action="{{ route('admin.backups.destroy', $backup) }}" class="p-6">
                @csrf
                @method('DELETE')
                <h2 class="text-lg font-bold text-red-800">Permanently delete backup</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">This removes the encrypted file and its history record. Type <strong>DELETE BACKUP</strong> to confirm.</p>
                <label class="mt-5 block text-sm font-semibold text-slate-700">Confirmation</label>
                <input name="confirmation" type="text" required autocomplete="off" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3">
                <label class="mt-4 block text-sm font-semibold text-slate-700">Current password</label>
                <input name="current_password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3">
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" data-close-dialog class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-red-700 px-4 py-2 text-sm font-semibold text-white">Delete Permanently</button>
                </div>
            </form>
        </dialog>
    @endforeach
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-open-dialog]').forEach((button) => {
            button.addEventListener('click', () => {
                document.getElementById(button.dataset.openDialog)?.showModal();
            });
        });

        document.querySelectorAll('[data-close-dialog]').forEach((button) => {
            button.addEventListener('click', () => button.closest('dialog')?.close());
        });
    </script>
@endpush
