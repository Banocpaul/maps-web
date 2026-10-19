<?php

namespace Tests\Feature;

use App\Http\Controllers\AddressSearchController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class AddressSearchTest extends TestCase
{
    private function details(array $coordinates = ['latitude' => 14.572089, 'longitude' => 121.0338128], bool $allowed = true): \Illuminate\Http\JsonResponse
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('isPublicResident')->once()->andReturn($allowed);
        $request = Request::create('/public-portal/report-address/details', 'GET', $coordinates);
        $request->setUserResolver(fn () => $user);

        return (new AddressSearchController())->residentDetails($request);
    }

    private function search(string $query = 'Shaw', bool $allowed = true): \Illuminate\Http\JsonResponse
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasAnyPermission')->once()->andReturn($allowed);
        $request = Request::create('/fire-address/search', 'GET', ['q' => $query]);
        $request->setUserResolver(fn () => $user);
        return (new AddressSearchController())($request);
    }

    public function test_search_returns_local_coordinates_and_caches_results(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();
        Http::fake(['*' => Http::response(['features' => [
            ['geometry' => ['type' => 'Point', 'coordinates' => [121.035, 14.58]],
                'properties' => ['name' => 'Shaw Boulevard', 'city' => 'Mandaluyong', 'type' => 'street', 'locality' => 'Addition Hills']],
            ['geometry' => ['type' => 'Point', 'coordinates' => [120.98, 14.60]],
                'properties' => ['name' => 'Outside the city']],
            ['geometry' => ['type' => 'Point', 'coordinates' => [121.035, 14.58]],
                'properties' => ['name' => 'Other city', 'city' => 'Pasig']],
        ]])]);
        $response = $this->search();
        $this->assertCount(1, $response->getData(true)['results']);
        $this->assertSame(14.58, $response->getData(true)['results'][0]['latitude']);
        $this->assertSame('Shaw Boulevard', $response->getData(true)['results'][0]['street']);
        $this->assertSame(['Addition Hills'], $response->getData(true)['results'][0]['barangay_candidates']);
        $this->assertSame('', $response->getData(true)['results'][0]['corner']);
        $this->search();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['bbox'] === '121.010,14.558,121.065,14.610');
    }

    public function test_provider_failure_preserves_manual_entry_message(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();
        Http::fake(['*' => Http::response([], 503)]);
        $response = $this->search();
        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('manually', $response->getData(true)['message']);
    }

    public function test_guest_search_requires_login(): void
    {
        $this->getJson(route('fire-address.search'))->assertUnauthorized();
    }

    public function test_users_without_fire_edit_or_create_permission_are_denied(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->search('Shaw', false);
    }

    public function test_landmark_details_return_the_nearby_street_and_barangay_and_are_cached(): void
    {
        config(['cache.default' => 'array', 'services.address_search.reverse_url' => 'https://reverse.test/reverse']);
        Cache::flush();
        Http::fake(['*' => Http::response(['features' => [[
            'geometry' => ['type' => 'Point', 'coordinates' => [121.0338, 14.5721]],
            'properties' => ['name' => 'San Isidro Street', 'type' => 'street', 'osm_key' => 'highway',
                'city' => 'Mandaluyong', 'district' => 'Barangay Hulo'],
        ]]])]);

        $response = $this->details();
        $this->assertSame('San Isidro Street', $response->getData(true)['street']);
        $this->assertSame(['Barangay Hulo'], $response->getData(true)['barangay_candidates']);
        $this->details();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['layer'] === 'street' && $request['limit'] === 1
            && $request['radius'] === 0.2 && $request['lat'] === 14.572089 && $request['lon'] === 121.0338128);
    }

    public function test_reverse_lookup_does_not_treat_a_landmark_name_as_a_street(): void
    {
        config(['cache.default' => 'array', 'services.address_search.reverse_url' => 'https://reverse.test/reverse']);
        Cache::flush();
        Http::fake(['*' => Http::response(['features' => [[
            'properties' => ['name' => 'Plainview Subdivision', 'type' => 'other', 'city' => 'Mandaluyong'],
        ]]])]);
        $this->assertSame(['street' => '', 'barangay_candidates' => []], $this->details()->getData(true));
    }

    public function test_reverse_lookup_ignores_details_from_another_city(): void
    {
        config(['cache.default' => 'array', 'services.address_search.reverse_url' => 'https://reverse.test/reverse']);
        Cache::flush();
        Http::fake(['*' => Http::response(['features' => [[
            'properties' => ['name' => 'Other Street', 'type' => 'street', 'city' => 'Pasig', 'district' => 'San Jose'],
        ]]])]);
        $this->assertSame(['street' => '', 'barangay_candidates' => []], $this->details()->getData(true));
    }

    public function test_reverse_provider_failure_keeps_manual_entry_available(): void
    {
        config(['cache.default' => 'array', 'services.address_search.reverse_url' => 'https://reverse.test/reverse']);
        Cache::flush();
        Http::fake(['*' => Http::response([], 503)]);
        $response = $this->details();
        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('manually', $response->getData(true)['message']);
    }

    public function test_reverse_lookup_rejects_invalid_coordinates_before_calling_the_provider(): void
    {
        Http::fake();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            $this->details(['latitude' => 91, 'longitude' => 121.0]);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_reverse_lookup_is_not_available_to_non_residents(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->details(allowed: false);
    }

    public function test_reverse_lookup_requires_login(): void
    {
        $this->getJson(route('public.incident-reports.address-details', ['latitude' => 14.572089, 'longitude' => 121.0338128]))
            ->assertUnauthorized();
    }
}
