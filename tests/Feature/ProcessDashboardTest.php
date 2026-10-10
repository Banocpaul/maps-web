<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Barangay;
use App\Models\DatabaseBackup;
use App\Models\FireIncident;
use App\Models\FloodTrainingRecord;
use App\Models\Permission;
use App\Models\PredictionExecution;
use App\Models\PublicIncidentReport;
use App\Models\Role;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\FireAnalyticsService;
use App\Services\FloodAnalyticsService;
use App\Services\LiveWeatherService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(6, 0));
        Barangay::create(['name' => 'Hulo', 'district' => 1, 'is_active' => true]);
    }

    public function test_operations_queue_uses_current_tasks_and_excludes_closed_historical_or_deleted_items(): void
    {
        $this->mock(FireAnalyticsService::class, fn ($m) => $m->shouldNotReceive('getDashboardData'));
        $this->mock(FloodAnalyticsService::class, fn ($m) => $m->shouldNotReceive('getDashboardData'));
        $this->mock(LiveWeatherService::class, fn ($m) => $m->shouldNotReceive('getCurrentWeather'));
        $pending = $this->report('fire');
        $validated = $this->report('flood', 'Validated');
        $this->report('fire', 'Published');
        $this->report('flood', 'Rejected');
        $reported = $this->fire('Reported');
        $this->fire('Responding');
        $this->fire('Controlled');
        $resolved = $this->fire('Resolved');
        $this->fire('Reported')->delete();
        $active = $this->flood('Active', 'D');
        $this->flood('Subsided');
        $this->flood(null);
        $this->flood('Active')->delete();
        $forecast = $this->forecast();
        $reviewed = $this->forecast(48);
        $reviewed->remarks()->create(['author_name' => 'Staff', 'body' => 'Reviewed.']);
        $this->forecast(72, 'Simulation');
        $this->forecast(24, 'Forecast', 'Running');
        $this->forecast(48, 'Forecast', 'Failed');
        $user = $this->staff();
        $response = $this->actingAs($user)->get(route('dashboard', ['year' => 2000, 'analytics' => 'flood']));
        $response->assertOk()->assertViewIs('dashboard.index')->assertSee('Work Queue')
            ->assertSee('Publish Incident')->assertSee('Review Report')->assertSee('Review / Add Remark')
            ->assertSee('Map pin to verify')->assertSee('Update Flood Status')
            ->assertDontSee('chart.js')->assertDontSee('fireMonthlyChart')->assertDontSee('name="year"', false);
        $this->assertSame(['reports-Pending' => 1, 'reports-Validated' => 1, 'fire' => 3, 'fire-assessment' => 0, 'flood' => 1, 'prediction' => 1, 'sms' => 0], $this->counts($response));
        $queue = $response->viewData('workQueue')->keyBy('key');
        $this->assertSame(route('public-submissions.show', $validated), $queue['report-'.$validated->id]['url']);
        $this->assertSame(route('fire-incidents.edit', $reported), $queue['fire-'.$reported->id]['url']);
        $this->assertSame(route('flood-operation.index', ['record_id' => $active->id]).'#dataset-management', $queue['flood-'.$active->id]['url']);
        $this->assertFalse($queue->has('fire-'.$resolved->id));
        $this->assertFalse($queue->has('prediction-'.$reviewed->id));
        $this->assertContains($queue->first()['key'], ['fire-'.$reported->id, 'flood-'.$active->id]);

        $this->post(route('prediction.history.remarks', $forecast), ['body' => 'Staff reviewed the forecast.'])->assertRedirect();
        $reported->update(['status' => 'Resolved']);
        $active->update(['flood_status' => 'Subsided']);
        $pending->update(['status' => 'Rejected']);
        $updated = $this->get(route('dashboard'))->assertOk();
        $this->assertSame(0, $this->counts($updated)['prediction']);
        $this->assertSame(0, $this->counts($updated)['flood']);
        $this->assertSame(2, $this->counts($updated)['fire']);
        $this->assertSame(0, $this->counts($updated)['reports-Pending']);
    }

    public function test_roles_only_receive_their_hazard_tasks_even_with_extra_module_permissions(): void
    {
        $fire = $this->report('fire');
        $flood = $this->report('flood');
        $this->fire('Reported');
        $this->flood('Active');
        $this->forecast();
        foreach (['fire-responder', 'flood-analyst'] as $slug) {
            $user = $this->staff($slug);
            $user->role->permissions()->sync(Permission::pluck('id'));
            $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $cards = $this->counts($response);
            $isFire = $slug === 'fire-responder';
            $this->assertSame(1, $cards['reports-Pending']);
            $this->assertSame($isFire, isset($cards['fire']));
            $this->assertSame(! $isFire, isset($cards['flood']));
            $this->assertSame(! $isFire, isset($cards['prediction']));
            $this->assertArrayNotHasKey('sms', $cards);
            $response->assertSee('data-work-task="report-'.($isFire ? $fire->id : $flood->id).'"', false)
                ->assertDontSee('data-work-task="report-'.($isFire ? $flood->id : $fire->id).'"', false);
            $labels = collect($response->viewData('quickLinks'))->pluck('label')->all();
            $this->assertNotContains($isFire ? 'Flood Prediction' : 'Fire Incidents', $labels);
        }
    }

    public function test_view_only_staff_are_offered_view_actions_and_dashboard_requires_active_staff_access(): void
    {
        $this->report('fire');
        $this->report('fire', 'Validated');
        $incident = $this->fire('Reported');
        $user = $this->staff('fire-responder');
        $user->role->permissions()->sync(Permission::whereIn('slug', ['dashboard.view', 'fire.view', 'public-submissions.view'])->pluck('id'));
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $actions = $response->viewData('workQueue')->pluck('action')->all();
        $this->assertEqualsCanonicalizing(['View Report', 'View Report', 'View Incident'], $actions);
        $this->assertSame(route('fire-incidents.show', $incident), $response->viewData('workQueue')->firstWhere('key', 'fire-'.$incident->id)['url']);
        $this->get(route('fire-incidents.edit', $incident))->assertForbidden();
        $user->role->permissions()->detach();
        $this->get(route('dashboard'))->assertForbidden();
        $role = Role::where('slug', 'public-resident')->firstOrFail();
        $role->permissions()->sync(Permission::pluck('id'));
        $resident = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($resident)->get(route('dashboard'))->assertForbidden();
    }

    public function test_sms_failures_and_destination_filters_use_the_manila_day(): void
    {
        $inside = $this->sms('failed', '2026-10-09 16:00:00');
        $end = $this->sms('failed', '2026-10-10 15:59:59');
        $outside = $this->sms('failed', '2026-10-09 15:59:59');
        $this->sms('failed', '2026-10-10 16:00:00');
        $this->sms('sent', '2026-10-10 06:00:00');
        $user = $this->staff();
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->assertSame(2, $this->counts($response)['sms']);
        $this->assertSame(route('sms.index', ['status' => 'failed', 'date' => '2026-10-10']).'#sms-logs', collect($response->viewData('workflowCards'))->firstWhere('key', 'sms')['url']);
        $logs = $this->get(route('sms.index', ['status' => 'failed', 'date' => '2026-10-10']))->assertOk()->viewData('logs');
        $this->assertEqualsCanonicalizing([$inside->id, $end->id], $logs->pluck('id')->all());
        $this->assertFalse($response->viewData('workQueue')->contains('key', 'sms-'.$outside->id));
    }

    public function test_admin_tasks_and_filtered_lists_match_and_system_analytics_stays_separate(): void
    {
        $user = $this->staff('administrator');
        $inactive = $this->staff('fire-responder');
        $inactive->update(['is_active' => false]);
        $unassigned = User::factory()->create(['role_id' => null, 'is_active' => true]);
        $backup = DatabaseBackup::create(['uuid' => Str::uuid(), 'disk' => 'local', 'status' => 'completed', 'filename' => 'awaiting.backup']);
        DatabaseBackup::create(['uuid' => Str::uuid(), 'disk' => 'local', 'status' => 'completed', 'verified_at' => now()]);
        $failed = DatabaseBackup::create(['uuid' => Str::uuid(), 'disk' => 'local', 'status' => 'failed']);
        DatabaseBackup::create(['uuid' => Str::uuid(), 'disk' => 'local', 'status' => 'failed'])->forceFill(['created_at' => now()->subDays(2)])->save();
        $event = ActivityLog::create(['action' => 'failed_login', 'module' => 'authentication', 'description' => 'Sign-in failed']);
        $old = ActivityLog::create(['action' => 'failed_login', 'module' => 'authentication', 'description' => 'Old sign-in failed']);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->sms('failed', '2026-10-10 06:00:00');
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('System Analytics')->assertDontSee('chart.js');
        $this->assertSame(['accounts' => 2, 'backups' => 1, 'backup-failures' => 1, 'security' => 1, 'sms' => 1], $this->counts($response));
        $this->assertEqualsCanonicalizing([$inactive->id, $unassigned->id], $this->get(route('users.index', ['attention' => 1]))->assertOk()->viewData('users')->pluck('id')->all());
        $this->assertSame([$backup->id], $this->get(route('admin.backups.index', ['attention' => 'unverified']))->assertOk()->viewData('backups')->pluck('id')->all());
        $this->assertSame([$failed->id], $this->get(route('admin.backups.index', ['attention' => 'failed-today']))->assertOk()->viewData('backups')->pluck('id')->all());
        $this->assertSame([$event->id], $this->get(route('activity-logs.index', ['action' => 'failed_login', 'today' => 1]))->assertOk()->viewData('logs')->pluck('id')->all());
        $this->get(route('system-analytics.index'))->assertOk()->assertViewIs('system-analytics.index')->assertSee('chart.js');
        $this->actingAs($this->staff())->get(route('system-analytics.index'))->assertForbidden();
    }

    public function test_cards_open_filtered_incident_and_prediction_lists(): void
    {
        $active = $this->fire('Reported');
        $this->fire('Resolved');
        $flood = $this->flood('Active');
        $this->flood('Subsided');
        $run = $this->forecast();
        $reviewed = $this->forecast(48);
        $reviewed->remarks()->create(['author_name' => 'Staff', 'body' => 'Reviewed']);
        $this->forecast(72, 'Simulation');
        $pending = $this->report('fire');
        $this->report('flood', 'Validated');
        $this->actingAs($this->staff());
        $this->assertSame([$active->id], $this->get(route('fire-incidents.index', ['status' => 'active']))->assertOk()->viewData('incidents')->pluck('id')->all());
        $this->getJson(route('flood-dataset.index', ['flood_status' => 'Active']))->assertOk()->assertJsonCount(1, 'records.data')->assertJsonPath('records.data.0.id', $flood->id);
        $this->get(route('flood-operation.index', ['record_id' => $flood->id]))->assertOk()->assertSee('requestedRecord', false);
        $this->assertSame([$run->id], $this->get(route('prediction.history.index', ['needs_remark' => 1]))->assertOk()->viewData('runs')->pluck('id')->all());
        $this->assertSame([$pending->id], $this->get(route('public-submissions.index', ['status' => 'Pending']))->assertOk()->viewData('reports')->pluck('id')->all());
    }

    public function test_single_hazard_analytics_does_not_load_or_expose_the_other_hazard(): void
    {
        $this->mock(FireAnalyticsService::class, fn ($m) => $m->shouldReceive('getDashboardData')->once()->andReturn([]));
        $this->mock(FloodAnalyticsService::class, fn ($m) => $m->shouldReceive('getDashboardData')->once()->andReturn([]));
        $this->mock(LiveWeatherService::class, fn ($m) => $m->shouldReceive('getCurrentWeather')->once()->andReturn([]));
        $this->actingAs($this->staff('fire-responder'))->get(route('incident-analytics.index', ['analytics' => 'flood']))
            ->assertOk()->assertViewHas('selectedAnalytics', 'fire')->assertSee('fireMonthlyChart')->assertDontSee('floodMonthlyChart');
        $this->actingAs($this->staff('flood-analyst'))->get(route('incident-analytics.index', ['analytics' => 'fire']))
            ->assertOk()->assertViewHas('selectedAnalytics', 'flood')->assertSee('floodMonthlyChart')->assertDontSee('fireMonthlyChart');
    }

    private function counts($response): array
    {
        return collect($response->viewData('workflowCards'))->pluck('count', 'key')->all();
    }

    private function staff(string $slug = 'operations-manager'): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $slug)->firstOrFail()->id, 'is_active' => true]);
    }

    private function report(string $type, string $status = 'Pending'): PublicIncidentReport
    {
        return PublicIncidentReport::create(['reference' => 'PR-'.Str::ulid(), 'submission_token' => Str::uuid(),
            'incident_type' => $type, 'latitude' => 14.58, 'longitude' => 121.03, 'status' => $status]);
    }

    private function fire(string $status): FireIncident
    {
        return FireIncident::create(['barangay_id' => Barangay::first()->id, 'incident_number' => 'F-'.Str::ulid(),
            'incident_type' => 'Residential', 'location' => 'Hulo street', 'severity' => 'Minor', 'status' => $status,
            'record_status' => $status === 'Resolved' ? 'Finalized' : 'Open', 'reported_at' => now()]);
    }

    private function flood(?string $status, string $level = 'A'): FloodTrainingRecord
    {
        return FloodTrainingRecord::create(['observed_at' => now(), 'month' => 10, 'barangay' => 'Hulo',
            'risk_level' => 'Low', 'flood_level_code' => $level, 'flood_status' => $status]);
    }

    private function forecast(int $hours = 24, string $kind = 'Forecast', string $status = 'Completed'): PredictionExecution
    {
        return PredictionExecution::create(['forecast_hours' => $hours, 'kind' => $kind, 'status' => $status, 'requested_at' => now(),
            'requested_by_name' => 'Staff', 'input_snapshot' => [], 'result_snapshot' => ['predictions' => []]]);
    }

    private function sms(string $status, string $time): SmsLog
    {
        $log = SmsLog::create(['phone_number' => '09171234567', 'message' => 'Alert', 'status' => $status]);
        $log->forceFill(['created_at' => $time])->save();

        return $log;
    }
}
