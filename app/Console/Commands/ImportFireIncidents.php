<?php

namespace App\Console\Commands;

use App\Services\FireIncidentRecordImporter;
use Illuminate\Console\Command;

class ImportFireIncidents extends Command
{
    protected $signature = 'fire:import {file? : Path to the normalized fire records JSON}';

    protected $description = 'Activate all 117 Fire Records(1).xlsx rows as the current project dataset';

    public function handle(FireIncidentRecordImporter $importer): int
    {
        try {
            $result = $importer->import($this->argument('file'));
            $this->table(['Result', 'Count'], collect($result)->map(fn ($count, $key) => [ucfirst($key), $count])->values()->all());
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error('Fire import failed: '.$error->getMessage());
            return self::FAILURE;
        }
    }
}
