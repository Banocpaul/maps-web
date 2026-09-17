<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class CreateDatabaseBackup extends Command
{
    protected $signature = 'maps:backup:create
        {--type=manual : manual, scheduled, or pre_restore}';

    protected $description = 'Create, encrypt, and upload a TiDB database backup';

    public function handle(DatabaseBackupService $service): int
    {
        try {
            $this->info('Creating encrypted M.A.P.S. database backup...');
            $backup = $service->create((string) $this->option('type'));
            $this->newLine();
            $this->info('Backup completed successfully.');
            $this->table(
                ['ID', 'Type', 'File', 'Size', 'SHA-256'],
                [[
                    $backup->uuid,
                    $backup->type,
                    $backup->filename,
                    $backup->formattedSize(),
                    $backup->sha256,
                ]]
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
