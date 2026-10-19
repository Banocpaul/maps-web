<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\FireIncident;
use App\Models\FloodTrainingRecord;
use App\Models\PublicIncidentReport;
use App\Services\FireIncidentAlertService;
use App\Services\FloodIncidentAlertService;
use App\Services\FloodObservationEnrichmentService;
use App\Services\IncidentReportArea;
use App\Services\IncidentReportWorkflow;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncidentReportReviewController extends Controller
{
    public function index(Request $request, IncidentReportWorkflow $workflow): View
    {
        $types = $workflow->allowedTypes($request->user());
        abort_unless($types && $request->user()->hasPermission('public-submissions.view'), 403);
        $filters = $request->validate([
            'type' => ['nullable', Rule::in($types)],
            'status' => ['nullable', Rule::in(['Pending', 'Validated', 'Published', 'Rejected'])],
        ]);
        $query = PublicIncidentReport::query()->whereIn('incident_type', $types);
        $counts = (clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $reports = $query->when($filters['type'] ?? null, fn ($q, $v) => $q->where('incident_type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->latest('created_at')->orderByDesc('id')->paginate(15)->withQueryString();

        $settings = DB::table('incident_report_settings')->where('id', 1)->first();
        $comparisonTypes = isset($filters['type']) ? [$filters['type']] : $types;
        $annualComparison = app(\App\Services\IncidentComparisonService::class)->build(now('Asia/Manila')->year - 1, $comparisonTypes);
        return view('incident-reports.index', compact('reports', 'counts', 'types', 'settings', 'annualComparison'));
    }

    public function show(
        Request $request,
        PublicIncidentReport $publicReport,
        IncidentReportWorkflow $workflow,
        IncidentReportArea $area
    ): View {
        $workflow->authorize($request->user(), $publicReport->incident_type);
        $publicReport->load(['events.actor', 'fireIncident', 'floodTrainingRecord', 'submitter', 'reporterBarangay']);
        $barangays = Barangay::active()->orderBy('name')->get(['id', 'name']);
        $boundaries = $area->boundaries();
        $barangayKey = static function (string $name): string {
            $key = preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($name)));
            $key = str_replace('hagdangbato', 'hagdanbato', $key);
            return $key === 'wackwackgreenhillseast' ? 'wackwackgreenhills' : $key;
        };
        foreach ($boundaries['features'] as &$feature) {
            $matches = $barangays->filter(fn ($barangay) => $barangayKey($barangay->name)
                === $barangayKey($feature['properties']['barangay']));
            $feature['properties']['review_barangay_id'] = $matches->count() === 1 ? $matches->first()->id : null;
        }
        unset($feature);
        $pinNames = $area->barangayNames($publicReport->latitude, $publicReport->longitude);
        $suggestedBarangayId = null;
        if (count($pinNames) === 1) {
            $matches = $barangays->filter(fn ($barangay) => $barangayKey($barangay->name) === $barangayKey($pinNames[0]));
            $suggestedBarangayId = $matches->count() === 1 ? $matches->first()->id : null;
        }
        $canReview = $request->user()->hasPermission('public-submissions.review');
        $canReject = $request->user()->hasPermission('public-submissions.reject');
        $canPublish = $request->user()->hasPermission('public-submissions.approve')
            && $request->user()->hasPermission($publicReport->incident_type.'.create');
        $photoAvailable = false;
        if ($publicReport->photo_path) {
            try {
                $photoAvailable = Storage::disk($publicReport->photo_disk ?: 'local')->exists($publicReport->photo_path);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $matchingFires = collect();
        $fireSearch = $request->validate(['fire_search' => ['nullable', 'string', 'max:100']])['fire_search'] ?? '';
        if ($publicReport->incident_type === 'fire') {
            $query = FireIncident::query()->where('record_classification', 'Reported')->with('barangay');
            if ($fireSearch !== '') {
                // Search by official number; linking always requires human confirmation.
                $matchingFires = $query->where('incident_number', $fireSearch)->limit(10)->get();
            } else {
                $matchingFires = $query->whereIn('status', ['Reported', 'Responding', 'Controlled'])
                    ->whereBetween('reported_at', [$publicReport->created_at->copy()->subDay(), $publicReport->created_at->copy()->addDay()])
                    ->whereBetween('latitude', [$publicReport->latitude - .005, $publicReport->latitude + .005])
                    ->whereBetween('longitude', [$publicReport->longitude - .0052, $publicReport->longitude + .0052])
                    ->get()->map(function ($fire) use ($publicReport) {
                        $lat1 = deg2rad($publicReport->latitude);
                        $lat2 = deg2rad((float) $fire->latitude);
                        $a = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2)
                            * sin(deg2rad((float) $fire->longitude - $publicReport->longitude) / 2) ** 2;
                        $fire->match_distance_m = round(6371000 * 2 * asin(sqrt(min(1, $a))));
                        return $fire;
                    })->filter(fn ($fire) => $fire->match_distance_m <= 500)
                    ->sortBy('match_distance_m')->take(10)->values();
            }
        }

        return view('incident-reports.show', compact(
            'publicReport', 'barangays', 'boundaries', 'canReview', 'canReject', 'canPublish', 'photoAvailable', 'matchingFires', 'fireSearch', 'suggestedBarangayId'
        ));
    }

    public function linkFire(Request $request, PublicIncidentReport $publicReport, IncidentReportWorkflow $workflow): RedirectResponse
    {
        $workflow->authorize($request->user(), $publicReport->incident_type, 'approve');
        abort_unless($publicReport->incident_type === 'fire', 422);
        $data = $request->validate([
            'fire_incident_id' => ['required', 'integer'],
            'link_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $publicReport, $workflow, $data): void {
            $report = $workflow->lockForPublication($publicReport->id, 'fire', $request->user());
            $fire = FireIncident::query()->where('record_classification', 'Reported')
                ->lockForUpdate()->findOrFail($data['fire_incident_id']);
            $report->update(['fire_incident_id' => $fire->id, 'status' => 'Published',
                'published_by' => $request->user()->id, 'published_at' => now()]);
            $report->events()->create(['actor_id' => $request->user()->id,
                'from_status' => 'Validated', 'to_status' => 'Published',
                'notes' => 'Linked to existing fire '.$fire->incident_number.' (#'.$fire->id.'). '.$data['link_reason']]);
        });
        return back()->with('success', 'Report linked to the existing fire. No new incident or duplicate SMS was created.');
    }

    public function unlinkFire(Request $request, PublicIncidentReport $publicReport, IncidentReportWorkflow $workflow): RedirectResponse
    {
        $workflow->authorize($request->user(), $publicReport->incident_type, 'approve');
        abort_unless($publicReport->incident_type === 'fire' && $request->user()->hasPermission('fire.edit'), 403);
        $data = $request->validate(['link_reason' => ['required', 'string', 'min:5', 'max:2000'],
            'expected_fire_incident_id' => ['required', 'integer']]);
        DB::transaction(function () use ($request, $publicReport, $data): void {
            $report = PublicIncidentReport::query()->lockForUpdate()->findOrFail($publicReport->id);
            abort_unless($report->status === 'Published' && $report->fire_incident_id
                && (int) $report->fire_incident_id === (int) $data['expected_fire_incident_id'], 409,
                'The link changed. Reload before correcting it.');
            $oldId = $report->fire_incident_id;
            $report->update(['fire_incident_id' => null, 'status' => 'Validated', 'published_by' => null, 'published_at' => null]);
            $report->events()->create(['actor_id' => $request->user()->id,
                'from_status' => 'Published', 'to_status' => 'Validated',
                'notes' => 'Removed incorrect link to fire #'.$oldId.'. Official incident retained. '.$data['link_reason']]);
        });
        return back()->with('success', 'Incorrect link removed. Link the validated report to the correct fire or create its official incident.');
    }

    public function photo(Request $request, PublicIncidentReport $publicReport, IncidentReportWorkflow $workflow): StreamedResponse
    {
        $workflow->authorize($request->user(), $publicReport->incident_type);
        $disk = Storage::disk($publicReport->photo_disk ?: 'local');
        abort_unless($publicReport->photo_path && $disk->exists($publicReport->photo_path), 404);

        return $disk->response($publicReport->photo_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function validateReport(
        Request $request,
        PublicIncidentReport $publicReport,
        IncidentReportWorkflow $workflow
    ): RedirectResponse {
        $workflow->authorize($request->user(), $publicReport->incident_type, 'review');
        $data = $request->validate(['validation_notes' => ['required', 'string', 'min:5', 'max:2000'],
            'alarm_level' => ['nullable', Rule::in(\App\Models\FireIncidentUpdate::ALARMS)],
            'alarm_source' => ['nullable', 'string', 'max:255'],
            'injured_count' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'deceased_count' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ]);
        DB::transaction(function () use ($publicReport, $data, $request): void {
            $report = PublicIncidentReport::query()->lockForUpdate()->findOrFail($publicReport->id);
            abort_unless($report->status === 'Pending', 409, 'Only pending reports can be validated.');
            $report->update([
                'status' => 'Validated', 'validation_notes' => $data['validation_notes'],
                'validated_by' => $request->user()->id, 'validated_at' => now(),
                'alarm_level' => $report->incident_type === 'fire' ? ($data['alarm_level'] ?? null) : null,
                'alarm_source' => $report->incident_type === 'fire' ? ($data['alarm_source'] ?? null) : null,
                'injured_count' => $data['injured_count'] ?? null, 'deceased_count' => $data['deceased_count'] ?? null,
            ]);
            $report->events()->create([
                'actor_id' => $request->user()->id, 'from_status' => 'Pending', 'to_status' => 'Validated',
                'notes' => $data['validation_notes'],
            ]);
        });

        return back()->with('success', 'Report validated. Add the official incident details to publish it.');
    }

    public function reject(
        Request $request,
        PublicIncidentReport $publicReport,
        IncidentReportWorkflow $workflow
    ): RedirectResponse {
        $workflow->authorize($request->user(), $publicReport->incident_type, 'reject');
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'min:5', 'max:2000'],
            'confirmed_fraud' => ['nullable', 'boolean']]);
        DB::transaction(function () use ($publicReport, $data, $request): void {
            $report = PublicIncidentReport::query()->lockForUpdate()->findOrFail($publicReport->id);
            abort_unless(in_array($report->status, ['Pending', 'Validated'], true), 409,
                'Only pending or validated reports can be rejected.');
            $resident = $report->submitted_by ? \App\Models\User::lockForUpdate()->find($report->submitted_by) : null;
            $previous = $report->status;
            $report->update([
                'status' => 'Rejected', 'rejection_reason' => $data['rejection_reason'],
                'confirmed_fraud' => $request->boolean('confirmed_fraud'),
                'rejected_by' => $request->user()->id, 'rejected_at' => now(),
            ]);
            $report->events()->create([
                'actor_id' => $request->user()->id, 'from_status' => $previous, 'to_status' => 'Rejected',
                'notes' => ($request->boolean('confirmed_fraud') ? 'Confirmed fraudulent report. ' : '').$data['rejection_reason'],
            ]);
            if ($resident && $resident->isPublicResident() && $request->boolean('confirmed_fraud')
                && PublicIncidentReport::where('submitted_by', $resident->id)->where('confirmed_fraud', true)->count() >= 3
                && ! $resident->suspended_at) {
                $resident->forceFill(['suspended_at' => now(), 'suspension_reason' => 'Three confirmed fraudulent incident reports.',
                    'is_active' => false, 'password_version' => $resident->password_version + 1])->save();
                $resident->smsRecipient()->update(['is_active' => false]);
            }
        });

        return back()->with('success', 'Report rejected. No official incident was created.');
    }

    public function publish(
        Request $request,
        PublicIncidentReport $publicReport,
        IncidentReportWorkflow $workflow,
        IncidentReportArea $area,
        FireIncidentAlertService $fireAlerts,
        FloodObservationEnrichmentService $enrichment
    ): RedirectResponse {
        $workflow->authorize($request->user(), $publicReport->incident_type, 'approve');
        abort_unless($publicReport->status === 'Validated', 409, 'Validate the report before publishing.');

        if ($publicReport->incident_type === 'fire') {
            $request->validate([
                'barangay_id' => ['required', 'integer', Rule::exists('barangays', 'id')->where('is_active', true)],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'reported_at' => ['required', 'date'],
                'responded_at' => ['nullable', 'date'],
                'resolved_at' => ['nullable', 'date'],
            ]);
            // Reuse existing validation, numbering, GIS integration and automatic SMS.
            $request->merge(['public_report_id' => $publicReport->id]);

            return app(FireIncidentController::class)->store($request, $fireAlerts, $workflow);
        }

        $data = $request->validate([
            'barangay_id' => ['required', 'integer', Rule::exists('barangays', 'id')->where('is_active', true)],
            'location_name' => ['required', 'string', 'max:255'],
            'observed_at' => ['required', 'date'],
            'flood_level_code' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'flood_status' => ['required', Rule::in(['Active', 'Subsided'])],
            'subsided_at' => [Rule::requiredIf($request->input('flood_status') === 'Subsided'), 'nullable', 'date', 'after:observed_at'],
            'injured_count' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'deceased_count' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'geometry_json' => ['required', 'string', 'max:20000', 'json'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $geometry = json_decode($data['geometry_json'], true);
        $coordinates = $geometry['coordinates'] ?? null;
        if (($geometry['type'] ?? null) !== 'LineString' || ! is_array($coordinates)
            || count($coordinates) < 2 || count($coordinates) > 100 || ! array_is_list($coordinates)) {
            throw ValidationException::withMessages(['geometry_json' => 'Draw a confirmed flood line with 2 to 100 points.']);
        }
        foreach ($coordinates as $point) {
            if (! is_array($point) || ! array_is_list($point) || count($point) !== 2 || ! is_numeric($point[0])
                || ! is_numeric($point[1]) || $point[0] < -180 || $point[0] > 180
                || $point[1] < -90 || $point[1] > 90) {
                throw ValidationException::withMessages(['geometry_json' => 'Every flood line point must have valid longitude and latitude coordinates.']);
            }
        }
        $length = $area->lineLength($coordinates);
        if ($length < 1) {
            throw ValidationException::withMessages(['geometry_json' => 'Draw a flood line at least one metre long.']);
        }
        $observed = Carbon::parse($data['observed_at'], 'Asia/Manila');
        $subsided = $data['flood_status'] === 'Subsided' ? Carbon::parse($data['subsided_at'], 'Asia/Manila') : null;
        $level = ['A' => ['Low', 152.4], 'B' => ['Medium', 457.2], 'C' => ['High', 914.4], 'D' => ['High', 1219.2]][$data['flood_level_code']];

        $record = DB::transaction(function () use (
            $request, $publicReport, $workflow, $data, $coordinates, $length, $observed, $subsided, $level
        ): FloodTrainingRecord {
            $report = $workflow->lockForPublication($publicReport->id, 'flood', $request->user());
            $barangay = Barangay::active()->findOrFail($data['barangay_id']);
            $record = FloodTrainingRecord::create([
                'injured_count' => $data['injured_count'] ?? $report->injured_count,
                'deceased_count' => $data['deceased_count'] ?? $report->deceased_count,
                'barangay' => $barangay->name, 'location_name' => $data['location_name'],
                'observed_at' => $observed->copy()->utc(),
                'month' => $observed->month, 'is_weekend' => $observed->isWeekend(),
                'wet_season' => $observed->month >= 5 && $observed->month <= 11, 'storm_signal' => 0,
                'geometry_type' => 'LineString',
                'geometry_geojson' => ['type' => 'LineString', 'coordinates' => $coordinates],
                'longitude' => $coordinates[0][0], 'latitude' => $coordinates[0][1],
                'extent_length_m' => $length,
                'flood_level_code' => $data['flood_level_code'], 'flood_status' => $data['flood_status'],
                'risk_level' => $level[0], 'flood_depth_mm' => $level[1],
                'subsided_at' => $subsided?->copy()->utc(),
                'duration_hours' => $subsided ? round($observed->diffInMinutes($subsided) / 60, 2) : 0,
                'data_source' => 'Public report verified by staff',
                'remarks' => 'Public report '.$report->reference.'. '.($data['remarks'] ?? ''),
                'created_by' => $request->user()->id,
                'include_in_training' => false, 'enrichment_status' => 'Pending Enrichment', 'review_status' => 'Pending',
                'exclusion_reason' => 'Awaiting predictor enrichment and separate training review.',
            ]);
            if ($subsided) $record->forceFill(['handling_completed_at' => now()])->save();
            $workflow->published($report, 'flood_training_record_id', $record->id, $request->user());

            return $record;
        });
        try {
            app(FloodIncidentAlertService::class)->sendCreatedAlert($record, $request->user()->id);
        } catch (\Throwable $exception) {
            report($exception);
        }
        $enrichment->enrich($record);

        return redirect()->route('public-submissions.show', $publicReport)
            ->with('success', 'Published to Flood Operations. Active floods are now visible on the public map.');
    }
}
