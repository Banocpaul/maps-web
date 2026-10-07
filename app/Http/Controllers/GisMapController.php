<?php

namespace App\Http\Controllers;

use App\Models\FireHydrant;
use App\Models\FireIncident;
use App\Models\FloodTrainingRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GisMapController extends Controller
{
    /**
     * Display the GIS map page.
     */
    public function index(Request $request): View
    {
        if ($request->user()->hasRole('flood-analyst')) {
            return view('gis.flood');
        }

        $statistics = [
            'hydrants' => FireHydrant::count(),

            'active_hydrants' => FireHydrant::where(
                'status',
                'Active'
            )->count(),

            'fire_incidents' => FireIncident::count(),

            'open_incidents' => FireIncident::whereNotIn(
                'status',
                [
                    'Resolved',
                    'Closed',
                ]
            )->count(),
        ];

        return view('gis.index', compact('statistics'));
    }

    /**
     * Return hydrants and ACTIVE fire incidents with valid coordinates.
     */
    public function data(Request $request): JsonResponse
    {
        if ($request->user()->hasRole('flood-analyst')) {
            return $this->floodData();
        }

        $hydrants = FireHydrant::query()
            ->with('barangay:id,name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function (FireHydrant $hydrant): array {
                return [
                    'id' => $hydrant->id,
                    'type' => 'hydrant',
                    'code' => $hydrant->hydrant_code,
                    'barangay' => $hydrant->barangay?->name,
                    'location' => $hydrant->location,
                    'latitude' => (float) $hydrant->latitude,
                    'longitude' => (float) $hydrant->longitude,
                    'status' => $hydrant->status,
                    'last_inspection_date' => $this->formatDate(
                        $hydrant->last_inspection_date
                    ),
                    'url' => route(
                        'fire-hydrants.show',
                        $hydrant
                    ),
                ];
            })
            ->values();

        // Only show ACTIVE incidents on the GIS map
        $incidents = FireIncident::query()
            ->active()
            ->with('barangay:id,name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function (FireIncident $incident): array {
                return [
                    'id' => $incident->id,
                    'type' => 'incident',
                    'incident_number' => $incident->incident_number,
                    'barangay' => $incident->barangay?->name,
                    'location' => $incident->location,
                    'latitude' => (float) $incident->latitude,
                    'longitude' => (float) $incident->longitude,
                    'severity' => $incident->severity,
                    'status' => $incident->status,
                    'reported_at' => $this->formatDateTime(
                        $incident->reported_at
                    ),
                    'url' => route(
                        'fire-incidents.show',
                        $incident
                    ),
                ];
            })
            ->values();

        return response()->json([
            'center' => [
                'latitude' => 14.5794,
                'longitude' => 121.0359,
                'zoom' => 13,
            ],

            'hydrants' => $hydrants,
            'incidents' => $incidents,
        ]);
    }

    /**
     * Find the nearest active fire hydrants to a selected coordinate.
     */
    public function nearestHydrants(
        Request $request
    ): JsonResponse {
        abort_if($request->user()->hasRole('flood-analyst'), 403);

        $validated = $request->validate([
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

            'limit' => [
                'nullable',
                'integer',
                'between:1,10',
            ],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $limit = (int) ($validated['limit'] ?? 5);

        $hydrants = FireHydrant::query()
            ->with('barangay:id,name')
            ->where('status', 'Active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function (FireHydrant $hydrant) use (
                $latitude,
                $longitude
            ): array {
                $hydrantLatitude = (float) $hydrant->latitude;
                $hydrantLongitude = (float) $hydrant->longitude;

                $distanceMeters = $this->calculateDistanceMeters(
                    $latitude,
                    $longitude,
                    $hydrantLatitude,
                    $hydrantLongitude
                );

                return [
                    'id' => $hydrant->id,
                    'type' => 'hydrant',
                    'code' => $hydrant->hydrant_code,
                    'barangay' => $hydrant->barangay?->name,
                    'location' => $hydrant->location,
                    'latitude' => $hydrantLatitude,
                    'longitude' => $hydrantLongitude,
                    'status' => $hydrant->status,

                    'last_inspection_date' => $this->formatDate(
                        $hydrant->last_inspection_date
                    ),

                    'distance_meters' => round(
                        $distanceMeters,
                        2
                    ),

                    'distance_kilometers' => round(
                        $distanceMeters / 1000,
                        3
                    ),

                    'estimated_drive_minutes' =>
                        $this->estimateDriveMinutes(
                            $distanceMeters
                        ),

                    'url' => route(
                        'fire-hydrants.show',
                        $hydrant
                    ),
                ];
            })
            ->sortBy('distance_meters')
            ->take($limit)
            ->values();

        return response()->json([
            'origin' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],

            'count' => $hydrants->count(),

            'nearest_hydrant' => $hydrants->first(),

            'hydrants' => $hydrants,
        ]);
    }

    /** Return observed active flood extents, without querying fire records. */
    private function floodData(): JsonResponse
    {
        $records = FloodTrainingRecord::query()
            ->where('flood_status', 'Active')
            ->latest('observed_at')
            ->get();

        $floods = $records->filter(function (FloodTrainingRecord $record): bool {
            $geometry = $record->geometry_geojson;
            if (! is_array($geometry) || ($geometry['type'] ?? null) !== 'LineString'
                || ! is_array($geometry['coordinates'] ?? null) || count($geometry['coordinates']) < 2) {
                return false;
            }
            foreach ($geometry['coordinates'] as $coordinate) {
                if (! is_array($coordinate) || count($coordinate) !== 2
                    || ! isset($coordinate[0], $coordinate[1])
                    || ! is_numeric($coordinate[0]) || ! is_numeric($coordinate[1])
                    || $coordinate[0] < -180 || $coordinate[0] > 180
                    || $coordinate[1] < -90 || $coordinate[1] > 90) {
                    return false;
                }
            }
            return true;
        })->map(fn (FloodTrainingRecord $record): array => [
            'id' => $record->id,
            'barangay' => $record->barangay,
            'location' => $record->location_name,
            'observed_at' => $this->formatDateTime($record->observed_at),
            'level_code' => $record->flood_level_code,
            'status' => $record->flood_status,
            'length_m' => round((float) $record->extent_length_m, 1),
            'geometry' => [
                'type' => 'LineString',
                'coordinates' => array_map(
                    fn (array $point): array => [(float) $point[0], (float) $point[1]],
                    $record->geometry_geojson['coordinates']
                ),
            ],
        ])->values();

        return response()->json([
            'floods' => $floods,
            'statistics' => [
                'active_floods' => $records->count(),
                'mapped_floods' => $floods->count(),
                'unmapped_floods' => $records->count() - $floods->count(),
                'barangays' => $records->pluck('barangay')->filter()->unique()->count(),
                'extent_length_m' => round($floods->sum('length_m'), 1),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Calculate straight-line distance using the Haversine formula.
     */
    private function calculateDistanceMeters(
        float $latitudeOne,
        float $longitudeOne,
        float $latitudeTwo,
        float $longitudeTwo
    ): float {
        $earthRadiusMeters = 6371000;

        $latitudeOneRadians = deg2rad(
            $latitudeOne
        );

        $latitudeTwoRadians = deg2rad(
            $latitudeTwo
        );

        $latitudeDifference = deg2rad(
            $latitudeTwo - $latitudeOne
        );

        $longitudeDifference = deg2rad(
            $longitudeTwo - $longitudeOne
        );

        $a = sin($latitudeDifference / 2) ** 2
            + cos($latitudeOneRadians)
            * cos($latitudeTwoRadians)
            * sin($longitudeDifference / 2) ** 2;

        $centralAngle = 2 * atan2(
            sqrt($a),
            sqrt(1 - $a)
        );

        return $earthRadiusMeters * $centralAngle;
    }

    /**
     * Estimate travel time.
     */
    private function estimateDriveMinutes(
        float $distanceMeters
    ): float {
        $averageSpeedKilometersPerHour = 25;

        $distanceKilometers = $distanceMeters / 1000;

        $minutes = (
            $distanceKilometers
            / $averageSpeedKilometersPerHour
        ) * 60;

        return round(
            max($minutes, 0.1),
            1
        );
    }

    private function formatDate(
        mixed $value
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return date(
            'Y-m-d',
            strtotime((string) $value)
        );
    }

    private function formatDateTime(
        mixed $value
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return \Carbon\CarbonImmutable::instance($value)->timezone('Asia/Manila')->toIso8601String();
        }

        return \Carbon\CarbonImmutable::parse((string) $value, 'UTC')->timezone('Asia/Manila')->toIso8601String();
    }
}
