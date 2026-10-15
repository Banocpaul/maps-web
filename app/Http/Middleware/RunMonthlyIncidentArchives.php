<?php
namespace App\Http\Middleware;
use App\Services\IncidentArchiveAutomation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class RunMonthlyIncidentArchives {
    public function handle(Request $request, Closure $next): Response { return $next($request); }
    public function terminate(Request $request, Response $response): void {
        if (! config('incident_archives.automatic') || app()->runningInConsole() || $response->getStatusCode() >= 500) return;
        try { app(IncidentArchiveAutomation::class)->run(); }
        catch (\Throwable $error) { report($error); }
    }
}
