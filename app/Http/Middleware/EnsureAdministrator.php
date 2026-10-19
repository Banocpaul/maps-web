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
                && $user->hasPermission('users.manage')
                && $user->isAdministrator(),
            403,
            'Only administrators may access this protected area.'
        );

        return $next($request);
    }
}
