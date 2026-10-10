<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\FireIncident;
use App\Services\FireIncidentAlertService;
use App\Services\IncidentReportWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FireIncidentController extends Controller
{
    /**
     * Display all fire incidents.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate(['record_classification' => ['nullable', Rule::in(['Current', 'Superseded'])]]);
        $classification = $filters['record_classification'] ?? 'Current';
        $query = FireIncident::withoutGlobalScope('operational_records')
            ->when($classification === 'Current', fn ($query) => $query->whereIn('record_classification', ['Reported', 'Dataset']),
                fn ($query) => $query->where('record_classification', $classification))
            ->with('barangay')
            ->latest('reported_at');

        if ($request->filled('search')) {
            $search = trim(
                $request->string('search')->toString()
            );

            $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where(
                        'incident_number',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'incident_type',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'location',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere('source_barangay', 'like', "%{$search}%")
                    ->orWhereHas(
                        'barangay',
                        function ($barangayQuery) use ($search) {
                            $barangayQuery->where(
                                'name',
                                'like',
                                "%{$search}%"
                            );
                        }
                    );
            });
        }

        if ($request->input('status') === 'active') {
            $query->active();
        } elseif ($request->filled('status')) {
            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('severity')) {
            $query->where(
                'severity',
                $request->string('severity')->toString()
            );
        }

        if ($request->filled('barangay_id')) {
            $query->where(
                'barangay_id',
                $request->integer('barangay_id')
            );
        }

        $incidents = $query
            ->paginate(15)
            ->withQueryString();

        $barangays = Barangay::active()
            ->orderBy('name')
            ->get();

        $statistics = [
            'total' => FireIncident::count(),

            'active' => FireIncident::whereIn(
                'status',
                [
                    'Reported',
                    'Responding',
                    'Controlled',
                ]
            )->count(),

            'resolved' => FireIncident::where(
                'status',
                'Resolved'
            )->count(),

            'major' => FireIncident::where(
                'severity',
                'Major'
            )->count(),
        ];

        return view(
            'fire.incidents.index',
            compact(
                'incidents',
                'barangays',
                'statistics'
            )
        );
    }

    /**
     * Show the form for creating a fire incident.
     */
    public function create(): View
    {
        $barangays = Barangay::active()
            ->orderBy('name')
            ->get();

        return view(
            'fire.incidents.create',
            compact('barangays')
        );
    }

    /**
     * Store a new fire incident.
     */
    public function store(
        Request $request,
        FireIncidentAlertService $fireIncidentAlertService,
        IncidentReportWorkflow $reportWorkflow
    ): RedirectResponse
    {
        $validated = $request->validate(
            $this->validationRules(),
            $this->validationMessages()
        );

        $reportData = $request->validate(['public_report_id' => ['nullable', 'integer', 'min:1']]);
        $reportId = $reportData['public_report_id'] ?? null;
        $validated = $this->normalizeIncidentTimes($validated);

        $fireIncident = DB::transaction(
            function () use ($validated, $reportId, $reportWorkflow, $request): FireIncident {
                $publicReport = $reportId
                    ? $reportWorkflow->lockForPublication((int) $reportId, 'fire', $request->user())
                    : null;
                $validated['incident_number'] =
                    $this->generateIncidentNumber();

                if ($publicReport) {
                    $validated['data_source'] = 'Public report verified by staff';
                    $validated['remarks'] = 'Public report '.$publicReport->reference.'. '.($validated['remarks'] ?? '');
                }
                $incident = FireIncident::create($validated);
                if ($publicReport) {
                    $reportWorkflow->published($publicReport, 'fire_incident_id', $incident->id, $request->user());
                }

                return $incident;
            }
        );

        try {
            $alertSummary = $fireIncidentAlertService->sendCreatedAlert(
                $fireIncident,
                auth()->id()
            );
            $smsStatus = $alertSummary['eligible'] === 0
                ? ' No active fire-alert recipients are assigned to this barangay.'
                : sprintf(
                    ' SMS alerts: %d sent, %d failed, %d duplicate skipped.',
                    $alertSummary['sent'],
                    $alertSummary['failed'],
                    $alertSummary['skipped']
                );
        } catch (\Throwable $exception) {
            report($exception);
            $smsStatus = ' The incident was saved, but automatic SMS processing could not be completed. Check the SMS logs.';
        }

        return redirect()
            ->route(
                'fire-incidents.show',
                $fireIncident
            )
            ->with(
                'success',
                'Fire incident recorded successfully.' . ($fireIncident->status === 'Resolved' ? ' Saved in history.' : ' Available on the GIS map.'.$smsStatus)
            );
    }

    /**
     * Display a specific fire incident.
     */
    public function show(
        FireIncident $fireIncident
    ): View {
        $fireIncident->load('barangay');

        return view(
            'fire.incidents.show',
            compact('fireIncident')
        );
    }

    /**
     * Show the form for editing a fire incident.
     */
    public function edit(
        FireIncident $fireIncident
    ): View {
        $this->ensureIncidentIsEditable(
            $fireIncident
        );

        $barangays = Barangay::active()
            ->orderBy('name')
            ->get();

        return view(
            'fire.incidents.edit',
            compact(
                'fireIncident',
                'barangays'
            )
        );
    }

    /**
     * Update an existing fire incident.
     */
    public function update(
        Request $request,
        FireIncident $fireIncident
    ): RedirectResponse {
        $this->ensureIncidentIsEditable(
            $fireIncident
        );

        $validated = $request->validate(
            $this->validationRules(),
            $this->validationMessages()
        );

        DB::transaction(function () use ($fireIncident, $validated): void {
            $current = FireIncident::lockForUpdate()->findOrFail($fireIncident->id);
            $this->ensureIncidentIsEditable($current);
            $current->update($this->normalizeIncidentTimes($validated, $current));
        });

        return redirect()
            ->route(
                'fire-incidents.show',
                $fireIncident
            )
            ->with(
                'success',
                'Fire incident updated successfully.'
            );
    }

    /**
     * Delete a fire incident.
     */
    public function destroy(
        FireIncident $fireIncident
    ): RedirectResponse {
        $this->ensureIncidentIsEditable(
            $fireIncident
        );

        DB::transaction(function () use ($fireIncident): void {
            $current = FireIncident::withoutGlobalScope('operational_records')->lockForUpdate()->findOrFail($fireIncident->id);
            $this->ensureIncidentIsEditable($current);
            $current->delete();
        });

        return redirect()
            ->route('fire-incidents.index')
            ->with(
                'success',
                'Fire incident deleted successfully.'
            );
    }

    /**
     * Prevent resolved incidents from being changed or deleted.
     */
    private function normalizeIncidentTimes(array $validated, ?FireIncident $existing = null): array
    {
        // datetime-local fields contain Manila wall time; the database stores UTC.
        foreach (['reported_at', 'responded_at', 'resolved_at', 'occurred_at', 'fire_out_at'] as $field) {
            if (! empty($validated[$field])) {
                $validated[$field] = \Carbon\Carbon::parse($validated[$field], 'Asia/Manila')
                    ->utc()->toDateTimeString();
            }
        }

        $validated['occurred_at'] = $validated['occurred_at'] ?? $existing?->occurred_at?->toDateTimeString() ?? $validated['reported_at'];
        $validated['fire_out_at'] = $validated['fire_out_at'] ?? $validated['resolved_at'] ?? null;
        if ($validated['fire_out_at']) {
            if ($validated['fire_out_at'] < $validated['occurred_at']
                || (! empty($validated['responded_at']) && $validated['fire_out_at'] < $validated['responded_at'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['fire_out_at' => 'Fire out cannot precede occurrence or response.']);
            }
            if (! empty($validated['resolved_at']) && $validated['resolved_at'] !== $validated['fire_out_at']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['fire_out_at' => 'Fire out and resolved time must match.']);
            }
            if ($validated['status'] !== 'Resolved') {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'Mark the incident resolved when the fire is out.']);
            }
            $validated['resolved_at'] = $validated['fire_out_at'];
        } elseif ($validated['status'] === 'Resolved') {
            throw \Illuminate\Validation\ValidationException::withMessages(['fire_out_at' => 'Enter when the fire was out.']);
        }
        $validated['coordinate_accuracy'] = 'Verified';
        return $validated;
    }

    private function ensureIncidentIsEditable(
        FireIncident $fireIncident
    ): void {
        abort_if(
            $fireIncident->status === 'Resolved' || in_array($fireIncident->record_classification, ['Example', 'Superseded'], true),
            403,
            'Resolved fire incidents are locked and can no longer be edited or deleted.'
        );
    }

    /**
     * Shared validation rules for creating and updating incidents.
     */
    private function validationRules(): array
    {
        return [
            'barangay_id' => [
                'required',
                'integer',
                Rule::exists('barangays', 'id')->where('is_active', true),
            ],

            'incident_type' => [
                'required',
                'string',
                'max:100',
            ],

            'location' => [
                'required',
                'string',
                'max:255',
            ],

            'street' => [
                'nullable',
                'string',
                'max:255',
            ],

            'corner' => [
                'nullable',
                'string',
                'max:255',
            ],

            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'severity' => [
                'required',
                Rule::in([
                    'Minor',
                    'Moderate',
                    'Major',
                ]),
            ],

            'status' => [
                'required',
                Rule::in([
                    'Reported',
                    'Responding',
                    'Controlled',
                    'Resolved',
                ]),
            ],

            'reported_at' => [
                'required',
                'date',
            ],

            'responded_at' => [
                'nullable',
                'date',
                'after_or_equal:reported_at',
            ],

            'resolved_at' => [
                'nullable',
                'date',
                'after_or_equal:reported_at',
            ],

            'occurred_at' => ['nullable', 'date', 'before_or_equal:reported_at'],
            'fire_out_at' => ['nullable', 'date'],
            'individuals_affected' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'houses_destroyed' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'alarm_level' => ['nullable', Rule::in(['1st', '2nd', '3rd', '4th', '5th', 'Task Force Alpha', 'Task Force Bravo', 'General Alarm'])],
            'cause' => ['nullable', 'string', 'max:255'],
            'record_classification' => ['prohibited'], 'source_record' => ['prohibited'],
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Custom validation messages.
     */
    private function validationMessages(): array
    {
        return [
            'barangay_id.required' =>
                'Please select the barangay where the fire incident occurred.',

            'barangay_id.exists' =>
                'The selected barangay is invalid.',

            'incident_type.required' =>
                'Please enter the fire incident type.',

            'location.required' =>
                'Please enter the exact location of the fire incident.',

            'latitude.required' =>
                'Please select the exact fire location on the map.',

            'longitude.required' =>
                'Please select the exact fire location on the map.',

            'latitude.between' =>
                'The selected latitude is invalid.',

            'longitude.between' =>
                'The selected longitude is invalid.',

            'severity.required' =>
                'Please select the fire incident severity.',

            'severity.in' =>
                'The selected severity is invalid.',

            'status.required' =>
                'Please select the fire incident status.',

            'status.in' =>
                'The selected status is invalid.',

            'reported_at.required' =>
                'Please enter when the fire incident was reported.',

            'responded_at.after_or_equal' =>
                'The response time cannot be earlier than the reported time.',

            'resolved_at.after_or_equal' =>
                'The resolved time cannot be earlier than the reported time.',

            'remarks.max' =>
                'The remarks must not exceed 2,000 characters.',
        ];
    }

    /**
     * Generate the next unique incident number.
     *
     * Example: FI-2026-0001
     */
    private function generateIncidentNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "FI-{$year}-";

        $lastIncident = FireIncident::withoutGlobalScope('operational_records')->withTrashed()
            ->where(
                'incident_number',
                'like',
                "{$prefix}%"
            )
            ->lockForUpdate()
            ->orderByDesc('incident_number')
            ->first();

        $nextNumber = 1;

        if ($lastIncident !== null) {
            $lastSequence = (int) substr(
                $lastIncident->incident_number,
                -4
            );

            $nextNumber = $lastSequence + 1;
        }

        return $prefix . str_pad(
            (string) $nextNumber,
            4,
            '0',
            STR_PAD_LEFT
        );
    }
}
