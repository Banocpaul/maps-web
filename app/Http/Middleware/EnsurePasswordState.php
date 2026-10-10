<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordState
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }
        if (Auth::viaRemember() && ! $request->session()->has('password_version')) {
            $request->session()->put('password_version', $user->password_version);
        }
        if ($request->routeIs('profile', 'profile.*', 'password.*', 'public.account.password')) {
            abort_unless($user->is_active && $user->role()->where('is_active', true)->exists(), 403);
        }
        if ((int) $request->session()->get('password_version', 0) !== $user->password_version) {
            $login = $user->isPublicResident() ? 'public.login' : 'login';
            // Do not rotate another browser's valid remember token on a stale session.
            Auth::logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($login)->withErrors(['email' => 'Your password changed. Please sign in again.']);
        }
        if ($user->must_change_password && ! $request->routeIs('password.required', 'password.complete', 'logout')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Set your new password before continuing.', 'redirect' => route('password.required')], 403);
            }

            return redirect()->route('password.required');
        }

        return $next($request);
    }
}
