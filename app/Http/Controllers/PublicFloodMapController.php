<?php

namespace App\Http\Controllers;

use App\Models\FloodTrainingRecord;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicFloodMapController extends Controller
{
    private const LEVELS = [
        'A' => ['label' => 'Level A', 'depth' => '0.5 ft', 'color' => '#16a34a'],
        'B' => ['label' => 'Level B', 'depth' => '1.5 ft', 'color' => '#eab308'],
        'C' => ['label' => 'Level C', 'depth' => '3.0 ft', 'color' => '#f97316'],
        'D' => ['label' => 'Level D', 'depth' => '4.0 ft', 'color' => '#dc2626'],
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
            ->map(fn (FloodTrainingRecord $record): ?array => $this->toPublicFlood($record))
            ->filter()
            ->values();

        return view('public.flood-map', [
            'floods' => $floods,
            'statistics' => $this->statistics($floods),
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

            if (
                $longitude < -180
                || $longitude > 180
                || $latitude < -90
                || $latitude > 90
            ) {
                return false;
            }
        }

        return true;
    }

    private function statistics(Collection $floods): array
    {
        return [
            'total' => $floods->count(),
            'barangays' => $floods->pluck('barangay')->unique()->count(),
            'level_a' => $floods->where('level_code', 'A')->count(),
            'level_b' => $floods->where('level_code', 'B')->count(),
            'level_c' => $floods->where('level_code', 'C')->count(),
            'level_d' => $floods->where('level_code', 'D')->count(),
        ];
    }
}
