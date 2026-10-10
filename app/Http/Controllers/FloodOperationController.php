<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Services\FloodPredictionService;
use App\Services\PredictionHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class FloodOperationController extends Controller
{
    public function __construct(
        private readonly FloodPredictionService $floodPredictionService,
        private readonly PredictionHistoryService $predictionHistoryService
    ) {
    }

    public function index(): View
    {
        return view('flood-operation.index', [
            'barangayNames' => Barangay::query()
                ->active()
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    /**
     * Run a hypothetical rainfall-only citywide severity simulation.
     *
     * The operator supplies rainfall accumulation only. Date/time,
     * temperature, wind, storm-signal default, and barangay geographic
     * attributes are supplied automatically by the system/API.
     */
    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rainfall_24h_mm' => ['required', 'numeric', 'min:0', 'max:2000'],
            'rainfall_3d_mm' => ['required', 'numeric', 'min:0', 'max:4000'],
            'rainfall_7d_mm' => ['required', 'numeric', 'min:0', 'max:8000'],
        ]);

        $rainfall24h = (float) $validated['rainfall_24h_mm'];
        $rainfall3d = (float) $validated['rainfall_3d_mm'];
        $rainfall7d = (float) $validated['rainfall_7d_mm'];

        if ($rainfall3d < $rainfall24h) {
            return response()->json([
                'message' => (
                    'Rainfall in the last 3 days must be greater than '
                    . 'or equal to rainfall in the last 24 hours.'
                ),
            ], 422);
        }

        if ($rainfall7d < $rainfall3d) {
            return response()->json([
                'message' => (
                    'Rainfall in the last 7 days must be greater than '
                    . 'or equal to rainfall in the last 3 days.'
                ),
            ], 422);
        }

        $execution = null;
        try {
            $execution = $this->predictionHistoryService->start($request->user(), 24, 'Simulation');
            $barangays = $this->getBarangayProfiles();

            $payload = [
                'forecast_hours' => 24,
                'simulation' => [
                    'rainfall_24h_mm' => $rainfall24h,
                    'rainfall_3d_mm' => $rainfall3d,
                    'rainfall_7d_mm' => $rainfall7d,
                ],
                'barangays' => $barangays,
            ];

            $this->predictionHistoryService->captureInput($execution, $payload);
            $result = $this->floodPredictionService
                ->predictCitywide($payload);
            $this->predictionHistoryService->complete($execution, $result);

            Log::info('Rainfall-only flood severity simulation completed.', [
                'rainfall_24h_mm' => $rainfall24h,
                'rainfall_3d_mm' => $rainfall3d,
                'rainfall_7d_mm' => $rainfall7d,
                'barangay_count' => count($barangays),
                'user_id' => auth()->id(),
            ]);

            return response()->json(array_merge($result, [
                'history_url' => route('prediction.history.show', $execution),
            ]));
        } catch (RuntimeException $exception) {
            $this->predictionHistoryService->fail($execution, $exception);
            Log::error('Rainfall severity simulation failed.', [
                'message' => $exception->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        } catch (Throwable $exception) {
            $this->predictionHistoryService->fail($execution, $exception);
            Log::error('Unexpected rainfall simulation error.', [
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'message' => (
                    'The rainfall simulation could not be completed: '
                    . $exception->getMessage()
                ),
            ], 500);
        }
    }

    /**
     * Build the geographic profile required by the A-D severity model.
     */
    private function getBarangayProfiles(): array
    {
        if (! DB::getSchemaBuilder()->hasTable('barangays')) {
            throw new RuntimeException(
                'The barangays database table was not found.'
            );
        }

        $rows = DB::table('barangays')
            ->where('is_active', 1)
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            throw new RuntimeException(
                'No active barangay records were found.'
            );
        }

        return $rows->map(function (object $row): array {
            $data = (array) $row;

            return [
                'barangay_id' => (int) ($data['id'] ?? 0),
                'barangay' => (string) ($data['name'] ?? ''),
                'nearest_waterway' => (string) (
                    $data['nearest_waterway'] ?? 'Unknown'
                ),
                'elevation_m' => (float) ($data['elevation_m'] ?? 0),
                'distance_to_waterway_m' => (float) (
                    $data['distance_to_waterway_m'] ?? 0
                ),
                'drainage_index' => (float) (
                    $data['drainage_index'] ?? 0
                ),
                'impervious_surface_ratio' => (float) (
                    $data['impervious_surface_ratio'] ?? 0
                ),
                'population_density_per_km2' => (float) (
                    $data['population_density_per_km2'] ?? 0
                ),
                'historical_flood_count_5y' => (int) (
                    $data['historical_flood_count_5y'] ?? 0
                ),
                'waterway_type' => (string) (
                    $data['waterway_type'] ?? 'Unknown'
                ),
                'previous_floods_30d' => (int) (
                    $data['previous_floods_30d'] ?? 0
                ),
                'days_since_previous_flood' => (float) (
                    $data['days_since_previous_flood'] ?? 999
                ),
            ];
        })->all();
    }
}
