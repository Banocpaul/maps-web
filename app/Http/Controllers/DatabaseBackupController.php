<?php

namespace App\Http\Controllers;

use App\Models\DatabaseBackup;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DatabaseBackupController extends Controller
{
    public function index(Request $request): View
    {
        $this->assertAdministrator($request);

        $backups = DatabaseBackup::query()
            ->with(['creator', 'verifier', 'restorer'])
            ->latest('created_at')
            ->paginate(15);

        return view('admin.backups.index', [
            'backups' => $backups,
            'statistics' => [
                'total' => DatabaseBackup::count(),
                'completed' => DatabaseBackup::where('status', 'completed')->count(),
                'failed' => DatabaseBackup::where('status', 'failed')->count(),
                'verified' => DatabaseBackup::whereNotNull('verified_at')->count(),
            ],
            'latestCompleted' => DatabaseBackup::query()
                ->where('status', 'completed')
                ->latest('completed_at')
                ->first(),
            'backupDisk' => (string) config('backup.disk'),
        ]);
    }

    public function store(
        Request $request,
        DatabaseBackupService $service
    ): RedirectResponse {
        $this->assertAdministrator($request);
        $this->validatePassword($request);

        try {
            $backup = $service->create('manual', $request->user()->id);

            return redirect()
                ->route('admin.backups.index')
                ->with('success', "Encrypted backup {$backup->filename} was created successfully.");
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'Backup failed: '.$exception->getMessage());
        }
    }

    public function verify(
        Request $request,
        DatabaseBackup $databaseBackup,
        DatabaseBackupService $service
    ): RedirectResponse {
        $this->assertAdministrator($request);
        $this->validatePassword($request);

        try {
            $service->verify($databaseBackup, $request->user()->id);

            return redirect()
                ->route('admin.backups.index')
                ->with('success', 'Backup integrity verification passed.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'Verification failed: '.$exception->getMessage());
        }
    }

    public function download(
        Request $request,
        DatabaseBackup $databaseBackup,
        DatabaseBackupService $service
    ): StreamedResponse|RedirectResponse {
        $this->assertAdministrator($request);
        $this->validatePassword($request);

        try {
            $stream = $service->downloadStream($databaseBackup);

            return response()->streamDownload(
                function () use ($stream): void {
                    try {
                        fpassthru($stream);
                    } finally {
                        fclose($stream);
                    }
                },
                (string) $databaseBackup->filename,
                ['Content-Type' => 'application/octet-stream']
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'Download failed: '.$exception->getMessage());
        }
    }

    public function destroy(
        Request $request,
        DatabaseBackup $databaseBackup,
        DatabaseBackupService $service
    ): RedirectResponse {
        $this->assertAdministrator($request);
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'confirmation' => ['required', 'in:DELETE BACKUP'],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return back()->withErrors([
                'current_password' => 'The administrator password is incorrect.',
            ]);
        }

        try {
            $filename = $databaseBackup->filename;
            $service->delete($databaseBackup);

            return redirect()
                ->route('admin.backups.index')
                ->with('success', "Backup {$filename} was permanently deleted.");
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.backups.index')
                ->with('error', 'Deletion failed: '.$exception->getMessage());
        }
    }

    private function validatePassword(Request $request): void
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The administrator password is incorrect.',
            ]);
        }
    }

    private function assertAdministrator(Request $request): void
    {
        abort_unless(
            $request->user()?->isAdministrator(),
            403,
            'Only administrators may manage database backups.'
        );
    }
}
