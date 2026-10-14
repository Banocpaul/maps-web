<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\FireIncident;
use App\Models\FloodTrainingRecord;
use App\Models\Permission;
use App\Models\PublicIncidentReport;
use App\Models\PublicIncidentReportEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\FireIncidentAlertService;
use App\Services\FloodObservationEnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicIncidentReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->actingAs(User::factory()->create([
            'role_id' => Role::where('slug', 'public-resident')->value('id'),
            'barangay_id' => $this->barangay()->id, 'is_active' => true,
        ]));
    }

    private function operator(string $slug = 'operations-manager'): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $slug, 'is_active' => true]);
        foreach (['public-submissions.view', 'public-submissions.review', 'public-submissions.approve',
            'public-submissions.reject', 'fire.create', 'fire.view', 'flood.create', 'prediction.view'] as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], [
                'name' => $slug, 'module' => 'test', 'is_active' => true,
            ]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true, 'first_name' => 'Test', 'last_name' => 'Operator']);
    }

    private function submission(string $type = 'fire', ?string $token = null): array
    {
        return ['incident_type' => $type, 'latitude' => 14.5794, 'longitude' => 121.0359,
            'submission_token' => $token ?? (string) Str::uuid()];
    }

    private function report(string $type = 'fire', string $status = 'Pending'): PublicIncidentReport
    {
        return PublicIncidentReport::create($this->submission($type) + [
            'reference' => 'PR-'.Str::ulid(), 'status' => $status,
        ]);
    }

    private function photo(): UploadedFile
    {
        // Real PNG bytes allow validation without requiring the GD extension.
        return UploadedFile::fake()->createWithContent('incident.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4UGEEAAP2AZuSRYHnAAAAAElFTkSuQmCC'
        ));
    }

    public function test_ten_distinct_residents_verify_each_hazard_without_publishing(): void
    {
        foreach (['fire', 'flood'] as $type) {
            for ($i = 1; $i <= 10; $i++) {
                $resident = User::factory()->create([
                    'role_id' => Role::where('slug', 'public-resident')->value('id'),
                    'barangay_id' => $this->barangay()->id, 'is_active' => true,
                ]);
                $this->actingAs($resident)->post(route('public.incident-reports.store'), $this->submission($type))
                    ->assertRedirect()->assertSessionHasNoErrors();
                $this->assertSame($i < 10 ? 0 : 10,
                    PublicIncidentReport::where('incident_type', $type)->where('status', 'Validated')->count());
            }
        }
        $this->assertSame(20, PublicIncidentReport::where('community_verified', true)->count());
        $this->assertDatabaseCount('fire_incidents', 0);
        $this->assertDatabaseCount('flood_training_records', 0);
    }

    public function test_repeated_resident_different_hazard_far_pin_and_expired_reports_do_not_count(): void
    {
        $verification = app(\App\Services\PublicReportVerification::class);
        $settings = (object) ['threshold' => 2, 'radius_m' => 100, 'window_minutes' => 120];
        $first = $this->report();
        $first->forceFill(['submitted_by' => Auth::id()])->save();
        $verification->verify($first, $settings);
        $repeat = $this->report();
        $repeat->forceFill(['submitted_by' => Auth::id()])->save();
        $verification->verify($repeat, $settings);
        $this->assertSame('Pending', $first->fresh()->status);
        foreach (['flood', 'far', 'expired'] as $scenario) {
            $report = $this->report($scenario === 'flood' ? 'flood' : 'fire');
            $report->forceFill(['submitted_by' => User::factory()->create()->id,
                'latitude' => $scenario === 'far' ? 14.59 : 14.5794])->save();
            if ($scenario === 'expired') {
                $first->forceFill(['created_at' => now()->subHours(3)])->save();
                $repeat->forceFill(['created_at' => now()->subHours(3)])->save();
            }
            $verification->verify($report, $settings);
            $this->assertSame('Pending', $report->fresh()->status);
        }
    }

    public function test_only_operations_can_change_verification_settings(): void
    {
        $data = ['threshold' => 5, 'radius_m' => 80, 'window_minutes' => 60];
        $this->post(route('public-submissions.settings'), $data)->assertForbidden();
        $this->actingAs($this->operator('fire-responder'))
            ->post(route('public-submissions.settings'), $data)->assertForbidden();
        $this->actingAs($this->operator())->post(route('public-submissions.settings'), $data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('incident_report_settings', $data);
        $this->post(route('public-submissions.settings'), array_replace($data, ['threshold' => 0]))
            ->assertSessionHasErrors('threshold');
    }

    public function test_public_can_attach_a_photo_to_a_fire_or_flood_report(): void
    {
        foreach (['fire', 'flood'] as $type) {
            $this->post(route('public.incident-reports.store'), $this->submission($type) + ['photo' => $this->photo()])
                ->assertRedirect()->assertSessionHas('report_reference');
            $report = PublicIncidentReport::where('incident_type', $type)->firstOrFail();
            $this->assertSame('Pending', $report->status);
            $this->assertDatabaseHas('activity_logs', [
                'user_id' => Auth::id(), 'route_name' => 'public.incident-reports.store',
            ]);
            $this->assertStringStartsWith('incident-report-photos/', $report->photo_path);
            Storage::disk('local')->assertExists($report->photo_path);
        }
        $this->assertCount(2, Storage::disk('local')->allFiles('incident-report-photos'));
        $this->assertDatabaseCount('fire_incidents', 0);
        $this->assertDatabaseCount('flood_training_records', 0);
    }

    public function test_repeated_photo_submission_keeps_the_original_photo_without_an_orphan(): void
    {
        $payload = $this->submission();
        $this->post(route('public.incident-reports.store'), $payload + ['photo' => $this->photo()])->assertRedirect();
        $firstPath = PublicIncidentReport::firstOrFail()->photo_path;
        $this->post(route('public.incident-reports.store'), $payload + ['photo' => $this->photo()])->assertRedirect();
        $this->assertDatabaseCount('public_incident_reports', 1);
        $this->assertSame($firstPath, PublicIncidentReport::firstOrFail()->photo_path);
        $this->assertCount(1, Storage::disk('local')->allFiles('incident-report-photos'));
    }

    public function test_photo_stays_on_its_original_private_disk_after_configuration_changes(): void
    {
        Storage::fake('report-archive');
        config(['filesystems.incident_report_disk' => 'report-archive']);
        $this->post(route('public.incident-reports.store'), $this->submission() + ['photo' => $this->photo()])
            ->assertRedirect()->assertSessionHas('report_reference');
        $report = PublicIncidentReport::sole();
        $this->assertSame('report-archive', $report->photo_disk);
        Storage::disk('report-archive')->assertExists($report->photo_path);
        $this->assertSame('private', Storage::disk('report-archive')->getVisibility($report->photo_path));
        Storage::disk('local')->assertMissing($report->photo_path);

        config(['filesystems.incident_report_disk' => 'local']);
        Auth::logout();
        $this->get(route('public-submissions.photo', $report))->assertRedirect(route('login'));
        $this->actingAs($this->operator())->get(route('public-submissions.photo', $report))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('public-submissions.show', $report))->assertSee('Open the photo');
    }

    public function test_missing_legacy_photo_has_a_visible_explanation_without_a_broken_image(): void
    {
        $report = $this->report();
        $report->forceFill(['photo_path' => 'incident-report-photos/missing.png'])->save();
        $this->actingAs($this->operator())->get(route('public-submissions.show', $report))
            ->assertOk()->assertSee('The attached photo is currently unavailable.')
            ->assertDontSee('alt="Public photo', false);
    }

    public function test_invalid_and_oversized_photos_are_rejected_without_saving_a_report(): void
    {
        foreach ([
            UploadedFile::fake()->createWithContent('fake.jpg', 'This is text, not a photo.'),
            UploadedFile::fake()->createWithContent('drawing.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            $this->photo()->size(2049),
        ] as $photo) {
            $this->post(route('public.incident-reports.store'), $this->submission() + ['photo' => $photo])
                ->assertSessionHasErrors('photo');
        }
        $this->assertDatabaseCount('public_incident_reports', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('incident-report-photos'));
    }

    public function test_photo_access_requires_the_matching_staff_role(): void
    {
        $this->post(route('public.incident-reports.store'), $this->submission() + ['photo' => $this->photo()])->assertRedirect();
        $report = PublicIncidentReport::firstOrFail();
        $url = route('public-submissions.photo', $report);
        Auth::logout();
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($this->operator('flood-analyst'))->get($url)->assertForbidden();
        $this->actingAs($this->operator('administrator'))->get($url)->assertForbidden();
        foreach (['fire-responder', 'operations-manager'] as $role) {
            $this->actingAs($this->operator($role))->get($url)->assertOk()
                ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->get(route('public-submissions.show', $report))->assertOk()
                ->assertSee('Photo attached by the public')->assertSee($url, false);
        }
        $flood = $this->report('flood');
        $flood->forceFill(['photo_path' => $report->photo_path])->save();
        $this->actingAs($this->operator('fire-responder'))->get(route('public-submissions.photo', $flood))->assertForbidden();
        $this->actingAs($this->operator('flood-analyst'))->get(route('public-submissions.photo', $flood))->assertOk();
    }

    public function test_missing_report_photo_returns_not_found(): void
    {
        $report = $this->report();
        $this->actingAs($this->operator())->get(route('public-submissions.photo', $report))->assertNotFound();
        $report->forceFill(['photo_path' => 'incident-report-photos/missing.png'])->save();
        $this->get(route('public-submissions.photo', $report))->assertNotFound();
    }

    public function test_photo_is_removed_if_the_report_transaction_fails(): void
    {
        $this->withoutExceptionHandling();
        PublicIncidentReportEvent::creating(function (): void {
            throw new \RuntimeException('Simulated report audit failure');
        });
        try {
            $this->post(route('public.incident-reports.store'), $this->submission() + ['photo' => $this->photo()]);
            $this->fail('The simulated failure must roll back the report.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated report audit failure', $exception->getMessage());
        } finally {
            PublicIncidentReportEvent::flushEventListeners();
        }
        $this->assertDatabaseCount('public_incident_reports', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles('incident-report-photos'));
    }

    private function barangay(): Barangay
    {
        return Barangay::firstOrCreate(['name' => 'Hulo'], ['district' => 2, 'is_active' => true]);
    }

    private function fireDetails(): array
    {
        return [
            'barangay_id' => $this->barangay()->id, 'incident_type' => 'Residential fire',
            'location' => 'Verified local test location', 'severity' => 'Minor', 'status' => 'Reported',
            'latitude' => 14.5794, 'longitude' => 121.0359, 'reported_at' => '2026-10-05T21:00',
        ];
    }

    private function floodDetails(): array
    {
        return [
            'barangay_id' => $this->barangay()->id, 'location_name' => 'Verified flood stretch',
            'observed_at' => '2026-10-05T21:00', 'flood_level_code' => 'B', 'flood_status' => 'Active',
            'geometry_json' => json_encode(['type' => 'LineString',
                'coordinates' => [[121.0359, 14.5794], [121.0370, 14.5800]]]),
        ];
    }

    public function test_public_can_submit_a_pin_without_creating_an_official_incident(): void
    {
        $this->post(route('public.incident-reports.store'), $this->submission())
            ->assertRedirect(route('public.incident-reports.create'))->assertSessionHas('report_reference');
        $this->assertDatabaseHas('public_incident_reports', ['incident_type' => 'fire', 'status' => 'Pending']);
        $this->assertDatabaseCount('public_incident_report_events', 1);
        $this->assertDatabaseCount('fire_incidents', 0);
        $this->assertDatabaseCount('flood_training_records', 0);
        $this->assertDatabaseCount('sms_logs', 0);
    }

    public function test_public_form_and_validated_staff_review_page_render(): void
    {
        $this->get(route('public.incident-reports.create'))->assertOk()->assertSee('Report a fire')->assertSee('Report a flood');
        $this->actingAs($this->operator())->get(route('public-submissions.show', $this->report('fire', 'Validated')))
            ->assertOk()->assertSee('Publish official incident');
        $this->get(route('public-submissions.show', $this->report('flood', 'Validated')))
            ->assertOk()->assertSee('Undo last point');
    }

    public function test_regular_staff_fire_creation_still_works_without_a_public_report(): void
    {
        $this->mock(FireIncidentAlertService::class, fn ($mock) => $mock->shouldReceive('sendCreatedAlert')->once()
            ->andReturn(['eligible' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0]));
        $this->actingAs($this->operator('fire-responder'))->post(route('fire-incidents.store'), $this->fireDetails())->assertRedirect();
        $this->assertDatabaseCount('fire_incidents', 1);
        $this->assertDatabaseCount('public_incident_reports', 0);
    }

    public function test_repeated_submission_of_the_same_form_does_not_create_duplicates(): void
    {
        $payload = $this->submission('flood');
        $this->post(route('public.incident-reports.store'), $payload)->assertRedirect();
        $this->post(route('public.incident-reports.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('public_incident_reports', 1);
        $this->assertDatabaseCount('public_incident_report_events', 1);
    }

    public function test_missing_pin_and_outside_city_pin_are_rejected(): void
    {
        $this->post(route('public.incident-reports.store'), ['incident_type' => 'fire', 'submission_token' => Str::uuid()])
            ->assertSessionHasErrors(['latitude', 'longitude']);
        $this->post(route('public.incident-reports.store'), array_replace($this->submission(), ['latitude' => 10]))
            ->assertSessionHasErrors('latitude');
        $this->assertDatabaseCount('public_incident_reports', 0);
    }

    public function test_public_cannot_set_a_report_status_or_publish_an_incident(): void
    {
        $this->post(route('public.incident-reports.store'), $this->submission() + ['status' => 'Published', 'fire_incident_id' => 999])
            ->assertRedirect();
        $report = PublicIncidentReport::firstOrFail();
        $this->assertSame('Pending', $report->status);
        $this->assertNull($report->fire_incident_id);
        $this->post(route('public-submissions.publish', $report), [])->assertForbidden();
    }

    public function test_fire_responder_cannot_read_or_modify_flood_reports_even_with_permissions(): void
    {
        $flood = $this->report('flood');
        $fire = $this->report('fire');
        $user = $this->operator('fire-responder');
        $this->actingAs($user)->get(route('public-submissions.index'))->assertOk()
            ->assertSee($fire->reference)->assertDontSee($flood->reference);
        $this->get(route('public-submissions.show', $flood))->assertForbidden();
        $this->post(route('public-submissions.validate', $flood), ['validation_notes' => 'Verified in the field.'])->assertForbidden();
        $this->post(route('public-submissions.reject', $flood), ['rejection_reason' => 'Duplicate report.'])->assertForbidden();
    }

    public function test_flood_analyst_and_administrator_cannot_review_fire_reports(): void
    {
        $report = $this->report();
        $this->actingAs($this->operator('flood-analyst'))->get(route('public-submissions.show', $report))->assertForbidden();
        $this->actingAs($this->operator('administrator'))->get(route('public-submissions.index'))->assertForbidden();
    }

    public function test_validation_records_actor_and_notes_without_publishing(): void
    {
        $report = $this->report();
        $user = $this->operator();
        $this->actingAs($user)->post(route('public-submissions.validate', $report), ['validation_notes' => 'Confirmed by the responding team.'])->assertRedirect();
        $this->assertDatabaseHas('public_incident_reports', ['id' => $report->id, 'status' => 'Validated', 'validated_by' => $user->id]);
        $this->assertDatabaseHas('public_incident_report_events', ['public_incident_report_id' => $report->id, 'actor_id' => $user->id, 'to_status' => 'Validated']);
        $this->assertDatabaseCount('fire_incidents', 0);
        $this->post(route('public-submissions.validate', $report), ['validation_notes' => 'Repeated verification.'])->assertStatus(409);
    }

    public function test_pending_and_rejected_reports_cannot_be_published(): void
    {
        $user = $this->operator();
        foreach (['Pending', 'Rejected'] as $status) {
            $this->actingAs($user)->post(route('public-submissions.publish', $this->report('fire', $status)), $this->fireDetails())->assertStatus(409);
        }
        $this->assertDatabaseCount('fire_incidents', 0);
    }

    public function test_validated_fire_is_published_once_and_uses_existing_sms_after_save(): void
    {
        $report = $this->report('fire', 'Validated');
        $user = $this->operator('fire-responder');
        $this->mock(FireIncidentAlertService::class, function ($mock) use ($report): void {
            $mock->shouldReceive('sendCreatedAlert')->once()->andReturnUsing(function ($incident, $sentBy) use ($report): array {
                $this->assertSame('Published', $report->fresh()->status);
                $this->assertSame($incident->id, $report->fresh()->fire_incident_id);

                return ['eligible' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
            });
        });
        $this->actingAs($user)->post(route('public-submissions.publish', $report), $this->fireDetails())->assertRedirect();
        $this->assertDatabaseCount('fire_incidents', 1);
        $incident = FireIncident::firstOrFail();
        $this->assertSame('2026-10-05 13:00:00', $incident->reported_at->format('Y-m-d H:i:s'));
        $this->post(route('public-submissions.publish', $report), $this->fireDetails())->assertStatus(409);
        $this->post(route('fire-incidents.store'), $this->fireDetails() + ['public_report_id' => $report->id])->assertStatus(409);
        $this->assertDatabaseCount('fire_incidents', 1);
    }

    public function test_invalid_official_details_leave_the_validated_report_unpublished(): void
    {
        $report = $this->report('fire', 'Validated');
        $payload = $this->fireDetails();
        unset($payload['severity']);
        $this->actingAs($this->operator())->post(route('public-submissions.publish', $report), $payload)->assertSessionHasErrors('severity');
        $this->assertSame('Validated', $report->fresh()->status);
        $this->assertDatabaseCount('fire_incidents', 0);
    }

    public function test_verified_flood_line_is_published_to_existing_observations_and_public_map(): void
    {
        $report = $this->report('flood', 'Validated');
        $this->mock(FloodObservationEnrichmentService::class, fn ($mock) => $mock->shouldReceive('enrich')->once()->andReturnUsing(fn ($record) => $record));
        $this->actingAs($this->operator('flood-analyst'))->post(route('public-submissions.publish', $report), $this->floodDetails())->assertRedirect();
        $record = FloodTrainingRecord::firstOrFail();
        $this->assertFalse($record->include_in_training);
        $this->assertSame('Pending', $record->review_status);
        $this->assertSame('LineString', $record->geometry_type);
        $this->assertGreaterThan(0, $record->extent_length_m);
        $this->assertSame('2026-10-05 13:00:00', $record->observed_at->format('Y-m-d H:i:s'));
        $this->assertSame('Published', $report->fresh()->status);
        $this->assertSame($record->id, $report->fresh()->flood_training_record_id);
        $this->get(route('public.flood-map'))->assertOk()->assertViewHas('floods', fn ($items) => $items->contains('id', $record->id));
        $this->post(route('public-submissions.publish', $report), $this->floodDetails())->assertStatus(409);
        $this->assertDatabaseCount('flood_training_records', 1);
    }

    public function test_flood_line_must_have_distinct_points_inside_the_city(): void
    {
        $report = $this->report('flood', 'Validated');
        foreach ([[[121.0359,14.5794],[121.0359,14.5794]], [[121.0359,14.5794],[120,10]]] as $points) {
            $payload = $this->floodDetails();
            $payload['geometry_json'] = json_encode(['type' => 'LineString', 'coordinates' => $points]);
            $this->actingAs($this->operator())->post(route('public-submissions.publish', $report), $payload)->assertSessionHasErrors('geometry_json');
        }
        $this->assertSame('Validated', $report->fresh()->status);
        $this->assertDatabaseCount('flood_training_records', 0);
    }

    public function test_rejection_requires_a_reason_and_does_not_create_incidents(): void
    {
        $report = $this->report();
        $this->actingAs($this->operator())->post(route('public-submissions.reject', $report), [])->assertSessionHasErrors('rejection_reason');
        $this->post(route('public-submissions.reject', $report), ['rejection_reason' => 'Duplicate of a confirmed incident.'])->assertRedirect();
        $this->assertSame('Rejected', $report->fresh()->status);
        $this->assertDatabaseCount('fire_incidents', 0);
        $this->post(route('public-submissions.validate', $report), ['validation_notes' => 'Attempting to reopen.'])->assertStatus(409);
    }

    private function linkableFire(array $overrides = []): FireIncident
    {
        return FireIncident::create(array_replace($this->fireDetails(), [
            'incident_number' => 'FIRE-'.Str::ulid(), 'record_classification' => 'Reported',
            'reported_at' => now(),
        ], $overrides));
    }

    public function test_multiple_validated_reports_link_to_one_fire_without_new_incidents_or_sms(): void
    {
        $fire = $this->linkableFire();
        $user = $this->operator('fire-responder');
        $this->mock(FireIncidentAlertService::class, fn ($mock) => $mock->shouldNotReceive('sendCreatedAlert'));
        foreach (range(1, 3) as $i) {
            $report = $this->report('fire', 'Validated');
            $this->actingAs($user)->post(route('public-submissions.link-fire', $report), [
                'fire_incident_id' => $fire->id, 'link_reason' => 'Confirmed same building by response team.',
            ])->assertRedirect();
            $this->assertSame($fire->id, $report->fresh()->fire_incident_id);
            $this->assertSame('Published', $report->fresh()->status);
            $this->assertDatabaseHas('public_incident_report_events', [
                'public_incident_report_id' => $report->id, 'actor_id' => $user->id, 'to_status' => 'Published',
            ]);
            $this->post(route('public-submissions.link-fire', $report), [
                'fire_incident_id' => $fire->id, 'link_reason' => 'Repeated duplicate request.',
            ])->assertStatus(409);
        }
        $this->assertDatabaseCount('fire_incidents', 1);
        $this->assertDatabaseCount('sms_logs', 0);
        $this->assertCount(3, $fire->publicReports);
        $this->get(route('fire-incidents.show', $fire))->assertOk()->assertSee('3 public reports linked');
    }

    public function test_fire_link_requires_validation_reason_and_real_operational_incident(): void
    {
        $fire = $this->linkableFire();
        $this->actingAs($this->operator());
        foreach (['Pending', 'Rejected'] as $status) {
            $this->post(route('public-submissions.link-fire', $this->report('fire', $status)), [
                'fire_incident_id' => $fire->id, 'link_reason' => 'Verified same fire.',
            ])->assertStatus(409);
        }
        $report = $this->report('fire', 'Validated');
        $this->post(route('public-submissions.link-fire', $report), ['fire_incident_id' => $fire->id])
            ->assertSessionHasErrors('link_reason');
        $dataset = $this->linkableFire(['record_classification' => 'Dataset']);
        $this->post(route('public-submissions.link-fire', $report), [
            'fire_incident_id' => $dataset->id, 'link_reason' => 'Should not link dataset.',
        ])->assertNotFound();
        $this->assertNull($report->fresh()->fire_incident_id);
    }

    public function test_fire_link_denies_public_and_wrong_hazard_roles(): void
    {
        $report = $this->report('fire', 'Validated');
        $payload = ['fire_incident_id' => $this->linkableFire()->id, 'link_reason' => 'Verified same fire.'];
        $this->post(route('public-submissions.link-fire', $report), $payload)->assertForbidden();
        $this->actingAs($this->operator('flood-analyst'))->post(route('public-submissions.link-fire', $report), $payload)
            ->assertForbidden();
        $this->actingAs($this->operator('operations-manager'))->post(
            route('public-submissions.link-fire', $this->report('flood', 'Validated')), $payload)->assertStatus(422);
    }

    public function test_nearby_suggestions_exclude_far_old_resolved_and_dataset_fires(): void
    {
        $report = $this->report();
        $near = $this->linkableFire();
        $far = $this->linkableFire(['latitude' => 14.60]);
        $old = $this->linkableFire(['reported_at' => now()->subDays(3)]);
        $closed = $this->linkableFire(['status' => 'Resolved']);
        $dataset = $this->linkableFire(['record_classification' => 'Dataset']);
        $this->actingAs($this->operator())->get(route('public-submissions.show', $report))->assertOk()
            ->assertViewHas('matchingFires', fn ($items) => $items->pluck('id')->all() === [$near->id]);
        // A late report can still be linked after a deliberate exact-number lookup.
        $this->get(route('public-submissions.show', $report).'?fire_search='.$closed->incident_number)
            ->assertOk()->assertViewHas('matchingFires', fn ($items) => $items->pluck('id')->all() === [$closed->id]);
    }

    public function test_incorrect_link_can_be_removed_and_relinked_with_audit_and_stale_guard(): void
    {
        $first = $this->linkableFire(); $second = $this->linkableFire();
        $report = $this->report('fire', 'Validated'); $user = $this->operator();
        $permission = Permission::firstOrCreate(['slug' => 'fire.edit'], ['name' => 'Edit fire', 'module' => 'fire', 'is_active' => true]);
        $user->role->permissions()->syncWithoutDetaching([$permission->id]);
        $this->actingAs($user)->post(route('public-submissions.link-fire', $report), [
            'fire_incident_id' => $first->id, 'link_reason' => 'Confirmed same incident.',
        ])->assertRedirect();
        $this->post(route('public-submissions.unlink-fire', $report), [
            'expected_fire_incident_id' => $second->id, 'link_reason' => 'Wrong building was selected.',
        ])->assertStatus(409);
        $this->post(route('public-submissions.unlink-fire', $report), [
            'expected_fire_incident_id' => $first->id, 'link_reason' => 'Wrong building was selected.',
        ])->assertRedirect();
        $this->assertSame('Validated', $report->fresh()->status);
        $this->assertNull($report->fresh()->fire_incident_id);
        $this->assertDatabaseHas('public_incident_report_events', ['public_incident_report_id' => $report->id,
            'actor_id' => $user->id, 'from_status' => 'Published', 'to_status' => 'Validated']);
        $this->post(route('public-submissions.link-fire', $report), [
            'fire_incident_id' => $second->id, 'link_reason' => 'Confirmed correct building.',
        ])->assertRedirect();
        $this->assertSame($second->id, $report->fresh()->fire_incident_id);
        $this->assertDatabaseCount('fire_incidents', 2);
    }

    public function test_public_report_owner_sees_linked_fire_status_without_other_reporters(): void
    {
        $owner = Auth::user(); $report = $this->report('fire', 'Published');
        $fire = $this->linkableFire(['status' => 'Responding']);
        $report->update(['submitted_by' => $owner->id, 'fire_incident_id' => $fire->id]);
        $other = $this->report('fire', 'Published');
        $this->get(route('public.reports'))->assertOk()->assertSee($fire->incident_number)
            ->assertSee('Responding')->assertDontSee($other->reference);
    }

    public function test_review_prefills_incident_barangay_from_pin_instead_of_reporter_home(): void
    {
        $area = new \App\Services\IncidentReportArea;
        $name = $area->barangayNames(14.5794, 121.0359)[0];
        $incidentBarangay = Barangay::firstOrCreate(['name' => $name], ['district' => 2, 'is_active' => true]);
        foreach (['fire', 'flood'] as $type) {
            $report = $this->report($type, 'Validated');
            $report->update(['reporter_barangay_id' => $this->barangay()->id]);
            $this->actingAs($this->operator())->get(route('public-submissions.show', $report))->assertOk()
                ->assertViewHas('suggestedBarangayId', $incidentBarangay->id)
                ->assertSee('id="official-barangay"', false);
        }
    }

    public function test_pin_barangay_does_not_guess_outside_city_or_override_previous_input(): void
    {
        $report = $this->report('fire', 'Validated');
        $report->update(['latitude' => 10, 'longitude' => 120]);
        $this->actingAs($this->operator())->get(route('public-submissions.show', $report))->assertOk()
            ->assertViewHas('suggestedBarangayId', null);
        $this->withSession(['_old_input' => ['barangay_id' => $this->barangay()->id]])
            ->get(route('public-submissions.show', $report))->assertOk()
            ->assertSee('value="'.$this->barangay()->id.'" selected', false);
    }
}
