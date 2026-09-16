<?php

namespace App\Http\Controllers;

use App\Models\FloodTrainingRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FloodDatasetController extends Controller
{
    /**
     * Return paginated flood training records and dataset statistics.
     */
    public function index(Request $request): JsonResponse
    {
        $records = FloodTrainingRecord::query()
            ->search(
                $request->string('search')->toString()
            )
            ->when(
                $request->filled('flood_level_code') && $request->string('flood_level_code')->toString() !== 'all',
                fn ($query) => $query->where('flood_level_code', $request->string('flood_level_code')->toString())
            )
            ->latest('observed_at')
            ->paginate(
                perPage: min(
                    max(
                        $request->integer('per_page', 10),
                        5
                    ),
                    100
                )
            );

        $statistics = [
            'total' => FloodTrainingRecord::count(),

            'included' => FloodTrainingRecord::query()
                ->includedInTraining()
                ->count(),

            'level_a' => FloodTrainingRecord::where('flood_level_code', 'A')->count(),
            'level_b' => FloodTrainingRecord::where('flood_level_code', 'B')->count(),
            'level_c' => FloodTrainingRecord::where('flood_level_code', 'C')->count(),
            'level_d' => FloodTrainingRecord::where('flood_level_code', 'D')->count(),
        ];

        return response()->json([
            'statistics' => $statistics,
            'records' => $records,
        ]);
    }

    /**
     * Store a newly verified flood training record.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            $this->validationRules()
        );

        $validated = $this->prepareValidatedData(
            $validated
        );

        $validated['created_by'] = auth()->id();

        $record = null;

        DB::transaction(function () use (
            $validated,
            &$record
        ): void {
            $record = FloodTrainingRecord::create(
                $validated
            );
        });

        return response()->json([
            'message' =>
                'Flood observation added successfully.',

            'record' => $record,
        ], 201);
    }

    /**
     * Return one flood training record.
     */
    public function show(
        FloodTrainingRecord $floodTrainingRecord
    ): JsonResponse {
        return response()->json([
            'record' => $floodTrainingRecord,
        ]);
    }

    /**
     * Update an existing flood training record.
     */
    public function update(
        Request $request,
        FloodTrainingRecord $floodTrainingRecord
    ): JsonResponse {
        $validated = $request->validate(
            $this->validationRules()
        );

        $validated = $this->prepareValidatedData(
            $validated
        );

        DB::transaction(function () use (
            $validated,
            $floodTrainingRecord
        ): void {
            $floodTrainingRecord->update(
                $validated
            );
        });

        return response()->json([
            'message' =>
                'Flood observation updated successfully.',

            'record' => $floodTrainingRecord->fresh(),
        ]);
    }

    /**
     * Soft-delete a flood training record.
     */
    public function destroy(
        FloodTrainingRecord $floodTrainingRecord
    ): JsonResponse {
        $floodTrainingRecord->delete();

        return response()->json([
            'message' =>
                'Flood training record deleted successfully.',
        ]);
    }

    /**
     * Include or exclude a record from future model training.
     */
    public function toggleTraining(
        Request $request,
        FloodTrainingRecord $floodTrainingRecord
    ): JsonResponse {
        $validated = $request->validate([
            'include_in_training' => [
                'required',
                'boolean',
            ],

            'exclusion_reason' => [
                Rule::requiredIf(
                    ! $request->boolean(
                        'include_in_training'
                    )
                ),
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $includeInTraining = (bool)
            $validated['include_in_training'];

        $floodTrainingRecord->update([
            'include_in_training' =>
                $includeInTraining,

            'exclusion_reason' =>
                $includeInTraining
                    ? null
                    : $validated['exclusion_reason'],
        ]);

        return response()->json([
            'message' => $includeInTraining
                ? 'Record restored to the training dataset.'
                : 'Record excluded from model training.',

            'record' => $floodTrainingRecord->fresh(),
        ]);
    }

    /**
     * Add calculated date-related values before saving.
     */
    private function prepareValidatedData(
        array $validated
    ): array {
        $validated['observed_at'] ??= now()->toDateTimeString();
        $validated['location_name'] ??= $validated['barangay'].' flood extent';
        $validated['flood_status'] = 'Active';
        $timestamp = strtotime($validated['observed_at']);

        $validated['month'] = (int) date(
            'n',
            $timestamp
        );

        $validated['is_weekend'] = in_array(
            (int) date('N', $timestamp),
            [6, 7],
            true
        );

        $validated['wet_season'] = in_array((int) date('n', $timestamp), [5, 6, 7, 8, 9, 10, 11], true);
        $validated['storm_signal'] = (int) ($validated['storm_signal'] ?? 0);

        $level = [
            'A' => ['risk' => 'Low', 'depth' => 152.4],
            'B' => ['risk' => 'Medium', 'depth' => 457.2],
            'C' => ['risk' => 'High', 'depth' => 914.4],
            'D' => ['risk' => 'High', 'depth' => 1219.2],
        ][$validated['flood_level_code']];

        $validated['risk_level'] = $level['risk'];
        $validated['flood_depth_mm'] = $level['depth'];
        $validated['geometry_type'] = $validated['geometry_geojson']['type'];
        $validated['include_in_training'] = false;
        $validated['exclusion_reason'] = 'Field observation pending predictor enrichment.';

        return $validated;
    }

    /**
     * Validation rules shared by store and update.
     */
    private function validationRules(): array
    {
        return [
            'observed_at' => ['nullable', 'date'],
            'barangay' => ['required', 'string', 'max:100'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'flood_level_code' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'flood_status' => ['nullable', Rule::in(['Active'])],
            'geometry_type' => ['required', Rule::in(['LineString'])],
            'geometry_geojson' => ['required', 'array'],
            'geometry_geojson.type' => ['required', Rule::in(['LineString'])],
            'geometry_geojson.coordinates' => ['required', 'array', 'min:2'],
            'geometry_geojson.coordinates.*' => ['required', 'array', 'size:2'],
            'geometry_geojson.coordinates.*.*' => ['required', 'numeric'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'extent_length_m' => ['required', 'numeric', 'gt:0'],
            'affected_area_m2' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
