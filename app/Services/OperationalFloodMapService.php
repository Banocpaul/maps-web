<?php

namespace App\Services;

use App\Models\FloodIncidentRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OperationalFloodMapService
{
    public function active(): Collection
    {
        return FloodIncidentRecord::where('status', 'Active')->whereNotNull('geometry_geojson')
            ->latest('observation_datetime')->get()->filter(function (FloodIncidentRecord $record): bool {
                $geometry = $record->geometry_geojson;
                if (! is_array($geometry) || ($geometry['type'] ?? null) !== 'LineString'
                    || ! is_array($geometry['coordinates'] ?? null) || count($geometry['coordinates']) < 2) return false;
                foreach ($geometry['coordinates'] as $point) {
                    if (! is_array($point) || count($point) !== 2 || ! isset($point[0], $point[1])
                        || ! is_numeric($point[0]) || ! is_numeric($point[1])
                        || $point[0] < -180 || $point[0] > 180 || $point[1] < -90 || $point[1] > 90) return false;
                }
                return in_array($record->flood_code, ['A', 'B', 'C', 'D'], true);
            })->map(fn (FloodIncidentRecord $record): array => [
                'id' => 'record-'.$record->id, 'record_id' => $record->id,
                'barangay' => $record->barangay, 'location' => $record->barangay.' flood extent',
                'observed_at' => Carbon::parse($record->observation_datetime, 'Asia/Manila')->toIso8601String(),
                'level_code' => $record->flood_code, 'status' => $record->status,
                'length_m' => round((float) $record->extent_length_m, 1), 'geometry' => $record->geometry_geojson,
            ])->values();
    }
}
