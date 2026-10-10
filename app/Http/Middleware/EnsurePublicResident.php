<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePublicResident
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->is_active && $user->isPublicResident()
            && $user->role()->where('is_active', true)->exists(), 403);

        return $next($request);
    }
}
