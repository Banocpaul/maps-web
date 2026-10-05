<?php

namespace App\Services;

class IncidentReportArea
{
    private ?array $boundaries = null;

    public function boundaries(): array
    {
        return $this->boundaries ??= json_decode(
            file_get_contents(public_path('geojson/mandaluyong_barangays.geojson')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public function contains(float $latitude, float $longitude): bool
    {
        foreach ($this->boundaries()['features'] as $feature) {
            $geometry = $feature['geometry'];
            $polygons = $geometry['type'] === 'Polygon'
                ? [$geometry['coordinates']]
                : ($geometry['type'] === 'MultiPolygon' ? $geometry['coordinates'] : []);
            foreach ($polygons as $rings) {
                if (! $this->inRing($longitude, $latitude, $rings[0])) {
                    continue;
                }
                $insideHole = false;
                foreach (array_slice($rings, 1) as $hole) {
                    if ($this->inRing($longitude, $latitude, $hole)) {
                        $insideHole = true;
                        break;
                    }
                }
                if (! $insideHole) {
                    return true;
                }
            }
        }

        return false;
    }

    private function inRing(float $x, float $y, array $ring): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            $cross = ($x - $xi) * ($yj - $yi) - ($y - $yi) * ($xj - $xi);
            if (abs($cross) < 1e-10 && $x >= min($xi, $xj) && $x <= max($xi, $xj)
                && $y >= min($yi, $yj) && $y <= max($yi, $yj)) {
                return true;
            }
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    public function lineLength(array $coordinates): float
    {
        $length = 0.0;
        for ($i = 1; $i < count($coordinates); $i++) {
            [$lng1, $lat1] = $coordinates[$i - 1];
            [$lng2, $lat2] = $coordinates[$i];
            $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
                + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
            $length += 6371000 * 2 * asin(sqrt(min(1.0, max(0.0, $a))));
        }

        return round($length, 2);
    }
}
