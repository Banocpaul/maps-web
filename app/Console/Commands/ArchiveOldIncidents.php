<?php
namespace App\Console\Commands;
use App\Services\IncidentArchiveAutomation;
use Illuminate\Console\Command;
class ArchiveOldIncidents extends Command {
    protected $signature = 'incidents:archive {--initial : Run the owner-authorized one-time archive} {--monthly : Run if this month is due}';
    protected $description = 'Archive closed incidents older than three years with durable once-per-month tracking';
    public function handle(IncidentArchiveAutomation $automation): int {
        if ((bool) $this->option('initial') === (bool) $this->option('monthly')) {
            $this->error('Choose exactly one of --initial or --monthly.'); return self::FAILURE;
        }
        $result = $automation->run((bool) $this->option('initial'));
        $this->info(json_encode($result));
        return self::SUCCESS;
    }
}
