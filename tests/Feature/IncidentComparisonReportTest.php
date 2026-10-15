<?php
namespace Tests\Feature;
use App\Models\{Barangay, FireIncident, FloodIncidentRecord, FloodTrainingRecord, Role, User};
use App\Services\IncidentComparisonService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IncidentComparisonReportTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void {
        parent::setUp(); $this->withoutVite(); Http::preventStrayRequests();
        $this->seed(RolePermissionSeeder::class);
        $this->travelTo(\Carbon\Carbon::parse('2026-10-15 08:00:00', 'UTC'));
        // Isolate comparison fixtures from the imported historical dataset.
        FloodIncidentRecord::query()->delete();
    }
    private function user(string $role = 'operations-manager'): User {
        return User::factory()->create(['role_id' => Role::where('slug', $role)->value('id'), 'is_active' => true]);
    }
    private function barangay(string $name = 'Hulo'): Barangay {
        return Barangay::firstOrCreate(['name' => $name], ['district' => 2, 'is_active' => true]);
    }
    private function fire(string $date, string $barangay = 'Hulo'): FireIncident {
        return FireIncident::create(['incident_number' => 'COMPARE-'.uniqid(), 'barangay_id' => $this->barangay($barangay)->id,
            'incident_type' => 'Residential fire', 'location' => 'Test location', 'severity' => 'Major', 'status' => 'Resolved',
            'occurred_at' => \Carbon\Carbon::parse($date, 'Asia/Manila')->utc(),
            'reported_at' => \Carbon\Carbon::parse($date, 'Asia/Manila')->utc(), 'alarm_level' => '2nd']);
    }
    private function flood(string $date, string $barangay = 'Hulo'): FloodIncidentRecord {
        $at = \Carbon\Carbon::parse($date, 'Asia/Manila');
        return FloodIncidentRecord::create(['event_id' => 'COMPARE-'.uniqid(), 'barangay' => $barangay,
            'flood_start_datetime' => $date, 'observation_datetime' => $date, 'event_date' => $at->toDateString(),
            'status' => 'Subsided', 'flood_code' => 'B', 'year' => $at->year, 'month' => $at->month,
            'day_of_week' => $at->format('l')]);
    }
    public function test_percentage_change_handles_increases_decreases_and_zero_baselines(): void {
        $this->assertSame('50.0% increase', IncidentComparisonService::comparison(15,10)['label']);
        $this->assertSame('25.0% decrease', IncidentComparisonService::comparison(15,20)['label']);
        $this->assertSame(-100.0, IncidentComparisonService::comparison(0,5)['percent']);
        $this->assertNull(IncidentComparisonService::comparison(5,0)['percent']);
        $this->assertSame('No baseline records', IncidentComparisonService::comparison(5,0)['label']);
        $this->assertSame('No change', IncidentComparisonService::comparison(0,0)['label']);
    }
    public function test_default_compares_full_2025_against_full_2024_and_includes_archived_records(): void {
        $this->fire('2025-12-31 23:59:59')->forceFill(['archived_at' => now()])->save();
        $this->fire('2025-01-01 00:00:00');
        $this->fire('2024-12-31 23:59:59');
        $this->fire('2026-01-01 00:00:00');
        $this->flood('2025-12-31 23:59:59'); $this->flood('2024-02-29 12:00:00');
        $response = $this->actingAs($this->user())->get(route('incident-comparison.index'))->assertOk();
        $report = $response->viewData('report');
        $this->assertSame(2025, $report['year']); $this->assertFalse($report['incomplete']);
        $this->assertSame(2, $report['cards']['fire']['current']); $this->assertSame(1, $report['cards']['fire']['previous']);
        $this->assertSame(3, $report['cards']['all']['current']); $this->assertSame(2, $report['cards']['all']['previous']);
        $this->assertCount(12, $report['months']);
        $this->assertSame('50.0% increase', $report['cards']['all']['label']);
        $this->assertSame(2, $report['months'][11]['current']);
        $this->assertSame('Dec 31, 2025', $report['sources'][0]['current_latest']);
        Http::assertNothingSent();
    }
    public function test_current_year_is_clearly_partial_while_previous_year_remains_full(): void {
        $this->fire('2026-10-15 15:59:00'); $this->fire('2026-10-15 16:01:00');
        $this->fire('2025-12-31 23:59:59');
        $response = $this->actingAs($this->user())->get(route('incident-comparison.index', ['year'=>2026]))->assertOk()->assertSee('Current year is incomplete.')->assertSee('not an equal-duration comparison');
        $report = $response->viewData('report');
        $this->assertSame(1, $report['cards']['fire']['current']); $this->assertSame(1, $report['cards']['fire']['previous']);
        $this->assertNull($report['months'][10]['current']);
        $this->assertSame('Not yet elapsed', $report['months'][10]['label']);
        $this->assertSame(1, $report['months'][11]['previous']);
    }
    public function test_hazard_roles_cannot_request_the_other_hazard_and_print_obeys_permissions(): void {
        $this->actingAs($this->user('fire-responder'))->get(route('incident-comparison.index', ['hazard'=>'flood']))->assertSessionHasErrors('hazard');
        $response = $this->get(route('incident-comparison.index', ['hazard'=>'all','print'=>1]))->assertOk();
        $this->assertSame(['fire'], $response->viewData('report')['types']);
        $this->actingAs($this->user('flood-analyst'))->get(route('incident-comparison.index', ['hazard'=>'fire']))->assertSessionHasErrors('hazard');
        $this->actingAs($this->user('public-resident'))->get(route('incident-comparison.index', ['print'=>1]))->assertForbidden();
    }
    public function test_barangay_filter_normalizes_names_and_preserves_observation_counts(): void {
        $barangay = $this->barangay('New Zañiga');
        $this->flood('2025-07-01 00:00:00', 'New Zaniga');
        $this->flood('2025-07-02 00:00:00', 'Hulo');
        FloodTrainingRecord::create(['observed_at' => '2025-07-03 00:00:00', 'barangay' => 'New Zañiga', 'month'=>7,
            'risk_level'=>'Medium', 'flood_level_code'=>'B', 'flood_status'=>'Subsided']);
        $response = $this->actingAs($this->user())->get(route('incident-comparison.index', ['barangay_id'=>$barangay->id, 'hazard'=>'flood']))->assertOk();
        $report=$response->viewData('report');
        $this->assertSame(2,$report['cards']['flood']['current']);
        $this->assertSame('New Zañiga',$report['barangays'][0]['name']);
        $this->assertSame(2,$report['categories']['flood'][1]['current']);
        $this->assertSame(1,$report['sources'][1]['current']);
    }
    public function test_deleted_and_superseded_records_are_excluded_and_invalid_years_rejected(): void {
        $this->fire('2025-07-01 00:00:00')->delete();
        $this->fire('2025-07-01 00:00:00')->update(['record_classification'=>'Superseded']);
        $this->flood('2025-07-01 00:00:00')->delete();
        $response=$this->actingAs($this->user())->get(route('incident-comparison.index'))->assertOk();
        $this->assertSame(0,$response->viewData('report')['cards']['all']['current']);
        $this->get(route('incident-comparison.index',['year'=>2027]))->assertSessionHasErrors('year');
        $this->get(route('incident-comparison.index',['year'=>2000]))->assertSessionHasErrors('year');
        $this->get(route('incident-comparison.index',['year'=>2025,'print'=>1]))->assertOk()->assertSee('Calendar-year comparison');
    }
    public function test_unknown_flood_codes_remain_in_category_totals_and_summary_is_visible(): void {
        $record = $this->flood('2025-01-01 00:00:00'); $record->update(['flood_code' => 'E']);
        $this->actingAs($this->user());
        $response = $this->get(route('incident-comparison.index', ['hazard'=>'flood']))->assertOk();
        $report = $response->viewData('report');
        $this->assertSame(1, $report['categories']['flood'][4]['current']);
        $this->assertSame('Unknown / other', $report['categories']['flood'][4]['name']);
        $this->get(route('public-submissions.index'))->assertOk()->assertSee('Annual official incident comparison')->assertSee('2025 versus 2024');
    }

    public function test_custom_year_pair_and_print_use_the_selected_baseline(): void {
        $this->fire('2012-01-01 00:00:00');
        $this->fire('2020-02-01 00:00:00'); $this->fire('2020-12-31 23:59:59');
        $this->fire('2019-06-01 00:00:00');
        $this->actingAs($this->user());
        $filters = ['year' => 2020, 'baseline_year' => 2012, 'hazard' => 'fire'];
        $response = $this->get(route('incident-comparison.index', $filters))->assertOk()->assertSee('2020 compared with 2012');
        $report = $response->viewData('report');
        $this->assertSame(2012, $report['baseline_year']);
        $this->assertSame(2, $report['cards']['fire']['current']);
        $this->assertSame(1, $report['cards']['fire']['previous']);
        $this->assertEquals(100, $report['cards']['fire']['percent']);
        $this->get(route('incident-comparison.index', $filters + ['print' => 1]))->assertOk()->assertSee('2020 compared with 2012');
        $this->get(route('incident-comparison.index', ['year' => 2020, 'baseline_year' => 2020]))->assertSessionHasErrors('baseline_year');
        $reverse = $this->get(route('incident-comparison.index', ['year' => 2012, 'baseline_year' => 2020, 'hazard' => 'fire']))->assertOk()->viewData('report');
        $this->assertEquals(-50, $reverse['cards']['fire']['percent']);
    }
    public function test_current_year_can_be_baseline_without_future_months_becoming_zero(): void {
        $this->actingAs($this->user());
        $report = $this->get(route('incident-comparison.index', ['year' => 2012, 'baseline_year' => 2026]))->assertOk()->viewData('report');
        $this->assertTrue($report['incomplete']);
        $this->assertNull($report['months'][11]['previous']);
        $this->assertNull($report['months'][11]['percent']);
    }
}
