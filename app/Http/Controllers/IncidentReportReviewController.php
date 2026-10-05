<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\FloodTrainingRecord;
use App\Models\PublicIncidentReport;
use App\Services\FireIncidentAlertService;
use App\Services\FloodObservationEnrichmentService;
use App\Services\IncidentReportArea;
use App\Services\IncidentReportWorkflow;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        return view('incident-reports.index', compact('reports', 'counts', 'types'));
    }

    public function show(
        Request $request,
        PublicIncidentReport $publicReport,
        IncidentReportWorkflow $workflow,
        IncidentReportArea $area
    ): View {
        $workflow->authorize($request->user(), $publicReport->incident_type);
        $publicReport->load(['events.actor', 'fireIncident', 'floodTrainingRecord']);
        $barangays = Barangay::active()->orderBy('name')->get(['id', 'name']);
        $boundaries = $area->boundaries();
        $canReview = $request->user()->hasPermission('public-submissions.review');
        $canReject = $request->user()->hasPermission('public-submissions.reject');
        $canPublish = $request->user()->hasPermission('public-submissions.approve')
            && $request->user()->hasPermission($publicReport->incident_type.'.create');

        return view('incident-reports.show', compact(
            'publicReport', 'barangays', 'boundaries', 'canReview', 'canReject', 'canPublish'
        ));
    }

    public function photo(Request $request, PublicIncidentReport $publicReport, IncidentReportWorkflow $workflow): StreamedResponse
    {
        $workflow->authorize($request->user(), $publicReport->incident_type);
        abort_unless($publicReport->photo_path && Storage::disk('local')->exists($publicReport->photo_path), 404);

        return Storage::disk('local')->response($publicReport->photo_path, null, [
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
        $data = $request->validate(['validation_notes' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($publicReport, $data, $request): void {
            $report = PublicIncidentReport::query()->lockForUpdate()->findOrFail($publicReport->id);
            abort_unless($report->status === 'Pending', 409, 'Only pending reports can be validated.');
            $report->update([
                'status' => 'Validated', 'validation_notes' => $data['validation_notes'],
                'validated_by' => $request->user()->id, 'validated_at' => now(),
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
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($publicReport, $data, $request): void {
            $report = PublicIncidentReport::query()->lockForUpdate()->findOrFail($publicReport->id);
            abort_unless(in_array($report->status, ['Pending', 'Validated'], true), 409,
                'Only pending or validated reports can be rejected.');
            $previous = $report->status;
            $report->update([
                'status' => 'Rejected', 'rejection_reason' => $data['rejection_reason'],
                'rejected_by' => $request->user()->id, 'rejected_at' => now(),
            ]);
            $report->events()->create([
                'actor_id' => $request->user()->id, 'from_status' => $previous, 'to_status' => 'Rejected',
                'notes' => $data['rejection_reason'],
            ]);
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
            $pin = $request->validate([
                'barangay_id' => ['required', 'integer', Rule::exists('barangays', 'id')->where('is_active', true)],
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
                'reported_at' => ['required', 'date'],
                'responded_at' => ['nullable', 'date'],
                'resolved_at' => ['nullable', 'date'],
            ]);
            if (! $area->contains((float) $pin['latitude'], (float) $pin['longitude'])) {
                throw ValidationException::withMessages(['latitude' => 'Place the confirmed fire pin inside Mandaluyong.']);
            }
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
                || ! is_numeric($point[1]) || ! $area->contains((float) $point[1], (float) $point[0])) {
                throw ValidationException::withMessages(['geometry_json' => 'Every flood line point must be inside Mandaluyong.']);
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
            $workflow->published($report, 'flood_training_record_id', $record->id, $request->user());

            return $record;
        });
        $enrichment->enrich($record);

        return redirect()->route('public-submissions.show', $publicReport)
            ->with('success', 'Published to Flood Operations. Active floods are now visible on the public map.');
    }
}
