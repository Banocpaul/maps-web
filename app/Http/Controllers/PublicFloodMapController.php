<?php

namespace App\Http\Controllers;

use App\Models\FireIncident;
use App\Models\FloodTrainingRecord;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicFloodMapController extends Controller
{
    private const LEVELS = [
        'A' => ['label' => 'Level A', 'depth' => '0.5 ft', 'color' => '#39FF14'],
        'B' => ['label' => 'Level B', 'depth' => '1.5 ft', 'color' => '#FFF200'],
        'C' => ['label' => 'Level C', 'depth' => '3.0 ft', 'color' => '#FF9500'],
        'D' => ['label' => 'Level D', 'depth' => '4.0 ft', 'color' => '#FF3B1F'],
    ];

    public function index(): View
    {
        $floods = FloodTrainingRecord::query()
            ->where('flood_status', 'Active')
            ->where('geometry_type', 'LineString')
            ->whereNotNull('geometry_geojson')
            ->whereIn('flood_level_code', array_keys(self::LEVELS))
            ->latest('observed_at')
            ->get()
            ->map(
                fn (FloodTrainingRecord $record): ?array =>
                    $this->toPublicFlood($record)
            )
            ->filter()
            ->values();

        $fires = FireIncident::query()
            ->with('barangay:id,name')
            ->active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest('reported_at')
            ->get()
            ->map(
                fn (FireIncident $incident): ?array =>
                    $this->toPublicFire($incident)
            )
            ->filter()
            ->values();

        return view('public.flood-map', [
            'floods' => $floods,
            'fires' => $fires,
            'statistics' => $this->statistics($floods, $fires),
            'levels' => self::LEVELS,
        ]);
    }

    private function toPublicFlood(FloodTrainingRecord $record): ?array
    {
        $geometry = $record->geometry_geojson;

        if (! $this->isValidLineString($geometry)) {
            return null;
        }

        $level = self::LEVELS[$record->flood_level_code];

        return [
            'id' => $record->id,
            'barangay' => $record->barangay,
            'observed_at' => $record->observed_at?->timezone('Asia/Manila')
                ->format('M d, Y g:i A'),
            'level_code' => $record->flood_level_code,
            'level_label' => $level['label'],
            'depth_label' => $level['depth'],
            'color' => $level['color'],
            'length_m' => round((float) $record->extent_length_m, 1),
            'geometry' => $geometry,
        ];
    }

    private function toPublicFire(FireIncident $incident): ?array
    {
        $latitude = (float) $incident->latitude;
        $longitude = (float) $incident->longitude;

        if (! $this->isValidCoordinate($latitude, $longitude)) {
            return null;
        }

        return [
            'id' => $incident->id,
            'incident_number' => $incident->incident_number,
            'incident_type' => $incident->incident_type,
            'barangay' => $incident->barangay?->name ?? 'Not available',
            'location' => $incident->location,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'reported_at' => $incident->reported_at?->timezone('Asia/Manila')
                ->format('M d, Y g:i A'),
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private function isValidLineString(mixed $geometry): bool
    {
        if (
            ! is_array($geometry)
            || ($geometry['type'] ?? null) !== 'LineString'
            || ! is_array($geometry['coordinates'] ?? null)
            || count($geometry['coordinates']) < 2
        ) {
            return false;
        }

        foreach ($geometry['coordinates'] as $coordinate) {
            if (
                ! is_array($coordinate)
                || count($coordinate) !== 2
                || ! is_numeric($coordinate[0])
                || ! is_numeric($coordinate[1])
            ) {
                return false;
            }

            $longitude = (float) $coordinate[0];
            $latitude = (float) $coordinate[1];

            if (! $this->isValidCoordinate($latitude, $longitude)) {
                return false;
            }
        }

        return true;
    }

    private function isValidCoordinate(
        float $latitude,
        float $longitude
    ): bool {
        return $longitude >= -180
            && $longitude <= 180
            && $latitude >= -90
            && $latitude <= 90;
    }

    private function statistics(
        Collection $floods,
        Collection $fires
    ): array {
        $barangays = $floods
            ->pluck('barangay')
            ->merge($fires->pluck('barangay'))
            ->filter()
            ->unique();

        return [
            'active_total' => $floods->count() + $fires->count(),
            'active_floods' => $floods->count(),
            'active_fires' => $fires->count(),
            'barangays' => $barangays->count(),
            'level_a' => $floods->where('level_code', 'A')->count(),
            'level_b' => $floods->where('level_code', 'B')->count(),
            'level_c' => $floods->where('level_code', 'C')->count(),
            'level_d' => $floods->where('level_code', 'D')->count(),
        ];
    }
}
