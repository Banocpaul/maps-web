<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('public-portal/*')
            ? route('public.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isPublicResident()
            ? route('public.portal') : route('dashboard'));

        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'admin' => \App\Http\Middleware\EnsureAdministrator::class,
            'advisory.publisher' => \App\Http\Middleware\EnsureAdvisoryPublisher::class,
            'resident' => \App\Http\Middleware\EnsurePublicResident::class,
        ]);

        $middleware->appendToGroup('web', \App\Http\Middleware\EnsurePasswordState::class);

        $middleware->appendToGroup(
            'web',
            \App\Http\Middleware\UpdateUserLastSeen::class
        );

        $middleware->appendToGroup(
            'web',
            \App\Http\Middleware\LogUserActivity::class
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $exception, Request $request) {
            if (! $request->routeIs('admin.backups.*') || $request->expectsJson()) {
                return null;
            }
            $seconds = max(1, (int) ($exception->getHeaders()['Retry-After'] ?? 60));
            return redirect()->route('admin.backups.index')
                ->with('backup_cooldown', [
                    'action' => $request->route()->getName(),
                    'until' => now()->timestamp + $seconds,
                ]);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->create();
