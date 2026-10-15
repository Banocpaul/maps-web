<?php
namespace Tests\Feature;
use App\Models\{FireIncident, FloodTrainingRecord, User, Role};
use App\Services\IncidentArchiveAutomation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonthlyIncidentArchivesTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void {
        parent::setUp(); $this->withoutVite();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-15 08:00:00', 'UTC'));
    }
    private function fire(string $date = '2020-01-01 00:00:00', string $status = 'Resolved'): FireIncident {
        return FireIncident::create(['incident_number' => 'AUTO-'.uniqid(), 'incident_type' => 'Residential fire',
            'location' => 'Archive test street', 'severity' => 'Minor', 'status' => $status,
            'reported_at' => $date, 'occurred_at' => $date, 'fire_out_at' => $status === 'Resolved' ? $date : null]);
    }
    public function test_initial_pass_archives_all_eligible_records_in_batches_and_logs_exact_ids(): void {
        $eligible = [];
        for ($i=0; $i<205; $i++) $eligible[] = $this->fire()->id;
        $recent = $this->fire('2026-01-01 00:00:00'); $active = $this->fire('2020-01-01 00:00:00', 'Responding');
        $observation = FloodTrainingRecord::create(['observed_at' => '2020-01-01 00:00:00', 'barangay' => 'Hulo',
            'month' => 1, 'risk_level' => 'Medium', 'flood_level_code' => 'B', 'flood_status' => 'Subsided',
            'include_in_training' => true, 'review_status' => 'Approved']);
        $this->artisan('incidents:archive', ['--initial' => true])->assertSuccessful();
        $this->assertSame(205, FireIncident::whereNotNull('archived_at')->count());
        $this->assertNull($recent->fresh()->archived_at); $this->assertNull($active->fresh()->archived_at);
        $this->assertTrue($observation->fresh()->include_in_training);
        $this->assertNotNull($observation->fresh()->archived_at);
        $logs = \App\Models\ActivityLog::where('action', 'initial_archive_incidents')->where('subject_type', FireIncident::class)->get();
        $logged = $logs->flatMap(fn ($log) => $log->new_values['record_ids'])->all();
        $this->assertSame($eligible, $logged);
        $this->assertDatabaseHas('incident_archive_automation', ['id' => 1, 'last_completed_month' => '2026-10']);
    }
    public function test_redeploy_and_same_month_checks_do_not_rearchive_restored_incidents(): void {
        $record = $this->fire(); $runner = app(IncidentArchiveAutomation::class);
        $runner->run(true);
        $record->forceFill(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null])->save();
        $this->assertTrue($runner->run(true)['skipped']);
        $this->assertTrue($runner->run()['skipped']);
        $this->assertNull($record->fresh()->archived_at);
    }
    public function test_new_month_catches_newly_eligible_records_and_runs_once(): void {
        $record = $this->fire('2023-10-20 00:00:00');
        $runner = app(IncidentArchiveAutomation::class); $runner->run(true);
        $this->assertNull($record->fresh()->archived_at);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-01 00:10:00', 'Asia/Manila'));
        $result = $runner->run();
        $this->assertFalse($result['skipped']); $this->assertSame(1, $result['counts']['fire']);
        $this->assertNotNull($record->fresh()->archived_at);
        $this->assertTrue($runner->run()['skipped']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'monthly_archive_incidents', 'user_name' => 'System']);
    }
    public function test_failed_run_rolls_back_records_and_completion_state_for_retry(): void {
        $record = $this->fire();
        $fail = true;
        DB::listen(function ($query) use (&$fail): void {
            if ($fail && str_starts_with($query->sql, 'update "incident_archive_automation"')) throw new \RuntimeException('Simulated ledger failure');
        });
        try { app(IncidentArchiveAutomation::class)->run(true); $this->fail('Expected simulated failure.'); }
        catch (\RuntimeException $error) { $this->assertSame('Simulated ledger failure', $error->getMessage()); }
        $this->assertNull($record->fresh()->archived_at);
        $this->assertNull(DB::table('incident_archive_automation')->value('initial_completed_at'));
        $fail = false;
        $this->assertFalse(app(IncidentArchiveAutomation::class)->run(true)['skipped']);
        $this->assertNotNull($record->fresh()->archived_at);
    }
    public function test_command_requires_one_explicit_mode(): void {
        $this->artisan('incidents:archive')->assertFailed();
        $this->artisan('incidents:archive', ['--initial' => true, '--monthly' => true])->assertFailed();
    }
}
