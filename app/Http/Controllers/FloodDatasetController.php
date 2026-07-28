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
            ->riskLevel(
                $request->string('risk_level')->toString()
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

            'high' => FloodTrainingRecord::query()
                ->where('risk_level', 'High')
                ->count(),

            'medium' => FloodTrainingRecord::query()
                ->where('risk_level', 'Medium')
                ->count(),

            'low' => FloodTrainingRecord::query()
                ->where('risk_level', 'Low')
                ->count(),
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
                'Flood training record added successfully.',

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
                'Flood training record updated successfully.',

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
        $timestamp = strtotime(
            $validated['observed_at']
        );

        $validated['month'] = (int) date(
            'n',
            $timestamp
        );

        $validated['is_weekend'] = in_array(
            (int) date('N', $timestamp),
            [6, 7],
            true
        );

        $validated['wet_season'] = (bool)
            $validated['wet_season'];

        $validated['storm_signal'] = (int)
            $validated['storm_signal'];

        $validated['include_in_training'] =
            array_key_exists(
                'include_in_training',
                $validated
            )
                ? (bool) $validated['include_in_training']
                : true;

        if (
            $validated['include_in_training'] === true
        ) {
            $validated['exclusion_reason'] = null;
        }

        return $validated;
    }

    /**
     * Validation rules shared by store and update.
     */
    private function validationRules(): array
    {
        return [
            'observed_at' => [
                'required',
                'date',
            ],

            'barangay' => [
                'required',
                'string',
                'max:100',
            ],

            'data_source' => [
                'nullable',
                'string',
                'max:150',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'wet_season' => [
                'required',
                'boolean',
            ],

            'storm_signal' => [
                'required',
                'integer',
                'between:0,5',
            ],

            'nearest_waterway' => [
                'nullable',
                'string',
                'max:150',
            ],

            'elevation_m' => [
                'required',
                'numeric',
                'between:0,1000',
            ],

            'distance_to_waterway_m' => [
                'required',
                'numeric',
                'min:0',
            ],

            'drainage_index' => [
                'required',
                'numeric',
                'between:0,1',
            ],

            'impervious_surface_ratio' => [
                'required',
                'numeric',
                'between:0,1',
            ],

            'population_density_per_km2' => [
                'required',
                'numeric',
                'min:0',
            ],

            'historical_flood_count_5y' => [
                'required',
                'integer',
                'min:0',
            ],

            'rainfall_24h_mm' => [
                'required',
                'numeric',
                'min:0',
            ],

            'rainfall_3d_mm' => [
                'required',
                'numeric',
                'min:0',
            ],

            'rainfall_7d_mm' => [
                'required',
                'numeric',
                'min:0',
            ],

            'temperature_c' => [
                'required',
                'numeric',
                'between:0,60',
            ],

            'humidity_pct' => [
                'required',
                'numeric',
                'between:0,100',
            ],

            'wind_speed_kph' => [
                'required',
                'numeric',
                'min:0',
            ],

            'tide_level_m' => [
                'required',
                'numeric',
                'min:0',
            ],

            'risk_level' => [
                'required',
                Rule::in([
                    'Low',
                    'Medium',
                    'High',
                ]),
            ],

            'flood_depth_mm' => [
                'required',
                'numeric',
                'min:0',
            ],

            'duration_hours' => [
                'required',
                'numeric',
                'min:0',
            ],

            'include_in_training' => [
                'nullable',
                'boolean',
            ],

            'exclusion_reason' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}