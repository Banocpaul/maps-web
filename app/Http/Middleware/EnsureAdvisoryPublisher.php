<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdvisoryPublisher
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            auth()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'Your account is inactive.',
                ]);
        }

        /*
         * Only operational departments may publish advisories.
         *
         * Administrator is intentionally excluded.
         * System Viewer is read-only and is also excluded.
         */
        if (! $user->hasAnyRole([
            'operations-manager',
            'flood-analyst',
            'fire-responder',
        ])) {
            abort(
                403,
                'Your role is not allowed to publish public advisories.'
            );
        }

        return $next($request);
    }
}
