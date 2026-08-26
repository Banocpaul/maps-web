<?php

namespace App\Services;

use App\Models\FireHydrant;
use App\Models\FireIncident;

class NearestFireHydrantService
{
    public function findForIncident(FireIncident $incident): ?array
    {
        if ($incident->latitude === null || $incident->longitude === null) {
            return null;
        }

        $nearest = null;

        foreach (FireHydrant::query()
            ->active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->cursor() as $hydrant) {
            $distanceMeters = $this->distanceMeters(
                (float) $incident->latitude,
                (float) $incident->longitude,
                (float) $hydrant->latitude,
                (float) $hydrant->longitude
            );

            if ($nearest === null || $distanceMeters < $nearest['distance_meters']) {
                $nearest = [
                    'hydrant' => $hydrant,
                    'distance_meters' => $distanceMeters,
                ];
            }
        }

        return $nearest;
    }

    private function distanceMeters(
        float $latitudeOne,
        float $longitudeOne,
        float $latitudeTwo,
        float $longitudeTwo
    ): float {
        $earthRadiusMeters = 6371000;
        $latitudeDelta = deg2rad($latitudeTwo - $latitudeOne);
        $longitudeDelta = deg2rad($longitudeTwo - $longitudeOne);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeOne))
            * cos(deg2rad($latitudeTwo))
            * sin($longitudeDelta / 2) ** 2;

        return round(
            $earthRadiusMeters * 2 * atan2(sqrt($a), sqrt(1 - $a)),
            1
        );
    }
}
