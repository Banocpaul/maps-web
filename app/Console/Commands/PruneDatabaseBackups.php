<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class PruneDatabaseBackups extends Command
{
    protected $signature = 'maps:backup:prune';

    protected $description = 'Delete expired scheduled backups according to retention rules';

    public function handle(DatabaseBackupService $service): int
    {
        try {
            $deleted = $service->prune();
            $this->info("Pruning completed. {$deleted} expired backup(s) deleted.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
