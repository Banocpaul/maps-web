<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Display the login screen.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function showPublicLoginForm(): View
    {
        return view('public.account.login');
    }

    /**
     * Authenticate the user.
     */
    public function login(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $credentials = $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
            'remember' => [
                'nullable',
                'boolean',
            ],
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $remember = $request->boolean('remember');

        if (!Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $remember)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'The provided email address or password is incorrect.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user()->load('role');

        if (!$user->is_active) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account is currently inactive. Contact the system administrator.',
            ]);
        }

       $role = $user->role()->first();

if (!$role || !$role->is_active) {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    throw ValidationException::withMessages([
        'email' => 'This account does not have an active system role.',
    ]);
}

        RateLimiter::clear($throttleKey);

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $request->session()->put('password_version', $user->password_version);
        if ($user->must_change_password) {
            return redirect()->route('password.required');
        }

        if ($user->isPublicResident()) {
            $intended = $request->session()->pull('url.intended');
            $allowed = [route('public.account'), route('public.reports'), route('public.incident-reports.create')];

            return redirect()->to(in_array($intended, $allowed, true) ? $intended : route('public.account'))
                ->with('success', 'Welcome back, '.$user->full_name.'.');
        }

        return redirect()
            ->intended(route('dashboard'))
            ->with('success', 'Welcome back, ' . $user->full_name . '.');
    }

    /**
     * Sign out the authenticated user.
     */
    public function logout(Request $request): RedirectResponse
    {
        $publicResident = $request->user()?->isPublicResident();
        if ($request->user() && Schema::hasColumn('users', 'last_seen_at')) {
            $request->user()->forceFill(['last_seen_at' => null])->save();
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route($publicResident ? 'public.portal' : 'login')
            ->with('success', 'You have been signed out successfully.');
    }

    /**
     * Build a unique rate-limiting key.
     */
    private function throttleKey(Request $request): string
    {
        return Str::lower((string) $request->input('email'))
            . '|'
            . $request->ip();
    }
}
