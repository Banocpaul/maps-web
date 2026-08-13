<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null
                && $user->is_active
                && $user->isAdministrator(),
            403,
            'Only administrators may access activity logs.'
        );

        return $next($request);
    }
}
