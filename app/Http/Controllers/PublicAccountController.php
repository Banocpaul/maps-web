<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\PublicIncidentReport;
use App\Models\Role;
use App\Models\User;
use App\Services\ResidentSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicAccountController extends Controller
{
    public function register(): View
    {
        return view('public.account.register', ['barangays' => Barangay::active()->orderBy('name')->get()]);
    }

    public function store(Request $request, ResidentSubscriptionService $subscriptions): RedirectResponse
    {
        $data = $this->validatedProfile($request);
        $data += $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);
        $role = Role::where('slug', 'public-resident')->where('is_active', true)->firstOrFail();
        $user = DB::transaction(function () use ($data, $request, $role, $subscriptions): User {
            $user = User::create([
                'name' => $data['first_name'].' '.$data['last_name'],
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
                'email' => $data['email'], 'password' => $data['password'],
                'contact_number' => $data['contact_number'], 'barangay_id' => $data['barangay_id'],
                'receive_flood_alerts' => $request->boolean('receive_flood_alerts'),
                'receive_fire_alerts' => $request->boolean('receive_fire_alerts'),
                'role_id' => $role->id, 'is_active' => true,
            ]);
            $subscriptions->sync($user);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();

        $intended = $request->session()->pull('url.intended');
        $allowed = [route('public.account'), route('public.reports'), route('public.incident-reports.create')];

        return redirect()->to(in_array($intended, $allowed, true) ? $intended : route('public.account'))
            ->with('success', 'Your public account is ready.');
    }

    public function edit(Request $request): View
    {
        return view('public.account.edit', [
            'resident' => $request->user(),
            'barangays' => Barangay::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ResidentSubscriptionService $subscriptions): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validatedProfile($request, $user);
        DB::transaction(function () use ($user, $data, $request, $subscriptions): void {
            $user->update($data + [
                'name' => $data['first_name'].' '.$data['last_name'],
                'receive_flood_alerts' => $request->boolean('receive_flood_alerts'),
                'receive_fire_alerts' => $request->boolean('receive_fire_alerts'),
            ]);
            $subscriptions->sync($user);
        });

        return back()->with('success', 'Your barangay and alert preferences have been updated.');
    }

    public function reports(Request $request): View
    {
        return view('public.account.reports', [
            'reports' => PublicIncidentReport::where('submitted_by', $request->user()->id)
                ->latest('id')->paginate(15),
        ]);
    }

    private function validatedProfile(Request $request, ?User $user = null): array
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'contact_number' => ResidentSubscriptionService::normalizePhone((string) $request->input('contact_number')),
            'first_name' => trim((string) $request->input('first_name')),
            'last_name' => trim((string) $request->input('last_name')),
        ]);
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'barangay_id' => ['required', 'integer', Rule::exists('barangays', 'id')->where('is_active', true)],
            'contact_number' => ['required', 'regex:/^\+639\d{9}$/',
                Rule::unique('users', 'contact_number')->ignore($user?->id),
                Rule::unique('sms_recipients', 'phone_number')->ignore($user?->smsRecipient()->value('id'))],
            'receive_flood_alerts' => ['nullable', 'boolean'],
            'receive_fire_alerts' => ['nullable', 'boolean'],
        ], ['contact_number.regex' => 'Enter a Philippine mobile number such as 09171234567 or +639171234567.']);
    }
}
