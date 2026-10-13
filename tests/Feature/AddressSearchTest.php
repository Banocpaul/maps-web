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
}
