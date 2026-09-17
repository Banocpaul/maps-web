<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class VerifyDatabaseBackup extends Command
{
    protected $signature = 'maps:backup:verify
        {backup? : Backup UUID; omit to verify the latest completed backup}';

    protected $description = 'Verify the checksum, encryption, and compression of a backup';

    public function handle(DatabaseBackupService $service): int
    {
        $identifier = $this->argument('backup');
        $backup = DatabaseBackup::query()
            ->when(
                $identifier,
                fn ($query) => $query->where('uuid', $identifier),
                fn ($query) => $query->where('status', 'completed')->latest('completed_at')
            )
            ->first();

        if ($backup === null) {
            $this->error('No matching completed backup was found.');

            return self::FAILURE;
        }

        try {
            $service->verify($backup);
            $this->info("Backup {$backup->uuid} passed verification.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
