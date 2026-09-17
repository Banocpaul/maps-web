<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class RestoreDatabaseBackup extends Command
{
    protected $signature = 'maps:backup:restore
        {backup : Backup UUID}
        {--confirm= : Must be exactly RESTORE-MAPS}
        {--skip-safety-backup : Do not create a pre-restore backup}';

    protected $description = 'Restore TiDB from a verified encrypted backup';

    public function handle(DatabaseBackupService $service): int
    {
        $backup = DatabaseBackup::query()
            ->where('uuid', $this->argument('backup'))
            ->first();

        if ($backup === null) {
            $this->error('The requested backup was not found.');

            return self::FAILURE;
        }

        $this->warn('DANGER: This replaces the current database with the selected backup.');
        $confirmation = (string) ($this->option('confirm') ?: $this->ask(
            'Type RESTORE-MAPS to continue'
        ));

        if (! hash_equals('RESTORE-MAPS', $confirmation)) {
            $this->error('Restore cancelled: confirmation did not match.');

            return self::FAILURE;
        }

        try {
            $safetyBackup = null;

            if (! $this->option('skip-safety-backup')) {
                $this->info('Creating the required pre-restore safety backup...');
                $safetyBackup = $service->create('pre_restore');
                $this->info("Safety backup created: {$safetyBackup->uuid}");
            }

            $this->info('Verifying and restoring the selected backup...');
            $service->restore($backup);

            if ($safetyBackup !== null) {
                $service->preserveRecordAfterRestore($safetyBackup, null, false);
            }

            $this->info('Database restore completed successfully.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
