<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(
        Request $request,
        Closure $next,
        string $permission
    ): Response {
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
         * Query the Role relationship explicitly.
         *
         * This avoids the naming conflict between:
         * - users.role string column
         * - User::role() relationship
         */
        $role = $user->role()->first();

        if (! $role || ! $role->is_active) {
            abort(
                403,
                'Your account does not have an active role.'
            );
        }

        if (! $user->hasPermission($permission)) {
            abort(
                403,
                'You do not have permission to access this page.'
            );
        }

        return $next($request);
    }
}