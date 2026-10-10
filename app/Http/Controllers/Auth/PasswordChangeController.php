<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordChangeRequest;
use App\Services\PasswordChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

class PasswordChangeController extends Controller
{
    public function show(Request $request): View
    {
        return view($request->boolean('modal') ? 'auth.partials.profile-content' : 'auth.profile', [
            'profileUser' => $request->user(),
            'inModal' => $request->boolean('modal'),
            'change' => PasswordChangeRequest::where('user_id', $request->user()->id)->latest('id')->first(),
        ]);
    }

    public function store(Request $request, PasswordChangeService $passwords): RedirectResponse
    {
        if ($request->filled('email') && is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        }
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);
        $passwords->submit($request->user(), $data['current_password'], $data['password'] ?? null, $data['email'] ?? null);

        $response = $request->boolean('_profile_modal') ? back()->with('open_profile', true) : redirect()->route('profile');

        return $response->with('success', 'Account change requested. Keep using your current login until an administrator approves it.');
    }

    public function required(Request $request): View|RedirectResponse
    {
        return $request->user()->must_change_password ? view('auth.password-required') : redirect()->route('profile');
    }

    public function complete(Request $request, PasswordChangeService $passwords): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:255', 'confirmed']]);
        $user = $passwords->completeReset($request->user(), $data['password']);
        $request->session()->regenerate();
        $request->session()->put('password_version', $user->password_version);

        return redirect()->route($user->isPublicResident() ? 'public.account' : 'dashboard')
            ->with('success', 'Your new password is active. No administrator approval is needed.');
    }

    public function index(): View
    {
        return view('users.password-requests', ['changes' => PasswordChangeRequest::with('user')->latest('id')->paginate(15)]);
    }

    public function review(Request $request, PasswordChangeRequest $change, PasswordChangeService $passwords): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:Approved,Rejected']]);
        $passwords->review($change, $request->user(), $data['decision']);

        return back()->with('success', 'Account change '.strtolower($data['decision']).'.');
    }
}
