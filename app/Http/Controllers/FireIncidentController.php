<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\FireIncident;
use App\Services\FireIncidentAlertService;
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
        $query = FireIncident::query()
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

        if ($request->filled('status')) {
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
        FireIncidentAlertService $fireIncidentAlertService
    ): RedirectResponse
    {
        $validated = $request->validate(
            $this->validationRules(),
            $this->validationMessages()
        );

        $fireIncident = DB::transaction(
            function () use ($validated): FireIncident {
                $validated['incident_number'] =
                    $this->generateIncidentNumber();

                return FireIncident::create($validated);
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
                'Fire incident recorded successfully. The location is now available on the GIS map.' . $smsStatus
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

        $fireIncident->update($validated);

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

        $fireIncident->delete();

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
    private function ensureIncidentIsEditable(
        FireIncident $fireIncident
    ): void {
        abort_if(
            $fireIncident->status === 'Resolved',
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
                'exists:barangays,id',
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

        $lastIncident = FireIncident::withTrashed()
            ->where(
                'incident_number',
                'like',
                "{$prefix}%"
            )
            ->lockForUpdate()
            ->orderByRaw(
                "CAST(SUBSTRING_INDEX(incident_number, '-', -1) AS UNSIGNED) DESC"
            )
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
