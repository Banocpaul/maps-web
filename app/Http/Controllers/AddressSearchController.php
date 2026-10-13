<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AddressSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['fire.create', 'fire.edit']), 403);
        return $this->search($request);
    }

    public function residentSearch(Request $request): JsonResponse
    {
        abort_unless($request->user()->isPublicResident(), 403);
        return $this->search($request);
    }

    private function search(Request $request): JsonResponse
    {
        $input = $request->validate(['q' => ['required', 'string', 'min:3', 'max:150']]);
        $query = trim(preg_replace('/\s+/u', ' ', $input['q']));
        if (mb_strlen($query) < 3) {
            return response()->json(['message' => 'Enter at least three characters.'], 422);
        }

        try {
            $results = Cache::remember('address-search:v2:'.hash('sha256', mb_strtolower($query)), now()->addDay(), function () use ($query): array {
                $data = Http::acceptJson()->connectTimeout(4)->timeout(10)
                    ->get(config('services.address_search.url'), [
                        'q' => $query,
                        'lat' => 14.5794,
                        'lon' => 121.0359,
                        'bbox' => '121.010,14.558,121.065,14.610',
                        'limit' => 8,
                        'lang' => 'en',
                    ])->throw()->json();
                $results = [];
                foreach (($data['features'] ?? []) as $feature) {
                    $coordinates = $feature['geometry']['coordinates'] ?? [];
                    $properties = $feature['properties'] ?? [];
                    if (($feature['geometry']['type'] ?? null) !== 'Point' || count($coordinates) < 2
                        || ! is_numeric($coordinates[0]) || ! is_numeric($coordinates[1])) {
                        continue;
                    }
                    [$longitude, $latitude] = $coordinates;
                    if ($longitude < 121.010 || $longitude > 121.065 || $latitude < 14.558 || $latitude > 14.610) {
                        continue;
                    }
                    $city = $properties['city'] ?? '';
                    if ($city !== '' && ! str_contains(mb_strtolower($city), 'mandaluyong')) {
                        continue;
                    }
                    $parts = array_filter([
                        $properties['name'] ?? '',
                        trim(($properties['housenumber'] ?? '').' '.($properties['street'] ?? '')),
                        $properties['district'] ?? $properties['locality'] ?? '',
                        $city,
                    ]);
                    $label = mb_substr(implode(', ', array_unique($parts)), 0, 255);
                    if ($label === '') continue;
                    $street = $properties['street'] ?? '';
                    if ($street === '' && (($properties['type'] ?? '') === 'street' || ($properties['osm_key'] ?? '') === 'highway')) {
                        $street = $properties['name'] ?? '';
                    }
                    $results[] = ['label' => $label, 'street' => $street,
                        'barangay_candidates' => array_values(array_filter([
                            $properties['district'] ?? '', $properties['locality'] ?? '', $properties['suburb'] ?? '',
                        ])),
                        'corner' => '',
                        'latitude' => (float) $latitude, 'longitude' => (float) $longitude];
                }
                return $results;
            });
            return response()->json(['results' => $results]);
        } catch (Throwable) {
            return response()->json(['message' => 'Address search is unavailable. Enter the address manually and select the map location.'], 503);
        }
    }
}
