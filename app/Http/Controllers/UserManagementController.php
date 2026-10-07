<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class UserManagementController extends Controller
{
    /**
     * Display the user-management page.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search'));
        $roleId = $request->input('role_id');
        $status = $request->input('status');

        $users = User::query()
            ->with('role')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(
                filled($roleId),
                fn ($query) => $query->where('role_id', $roleId)
            )
            ->when(
                $status === 'active',
                fn ($query) => $query->where('is_active', true)
            )
            ->when(
                $status === 'inactive',
                fn ($query) => $query->where('is_active', false)
            )
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $roles = Role::query()
            ->orderBy('name')
            ->get();

        return view('users.index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'role_id' => $roleId,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Show the form for creating a user.
     */
    public function create(): View
    {
        $roles = Role::query()
            ->orderBy('name')
            ->get();

        return view('users.create', [
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'role_id' => [
                'required',
                'integer',
                'exists:roles,id',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        try {
            DB::transaction(function () use ($validated): void {
                [$firstName, $lastName] = $this->splitFullName(
                    $validated['name']
                );

                $user = new User();

                $user->first_name = $firstName;
                $user->last_name = $lastName;
                $user->name = $validated['name'];
                $user->email = strtolower($validated['email']);
                $user->role_id = $validated['role_id'];
                $user->password = Hash::make($validated['password']);
                $user->is_active = (bool) ($validated['is_active'] ?? true);

                /*
                 * Users created by an administrator are automatically
                 * approved and email-verified.
                 */
                $user->approved_at = now();
                $user->email_verified_at = now();

                $user->save();
            });

            return redirect()
                ->route('users.index')
                ->with('success', 'User account created successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except([
                    'password',
                    'password_confirmation',
                ]))
                ->with('error', 'The user account could not be created.');
        }
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(User $user): View
    {
        /*
         * Read the relationship explicitly instead of using $user->role.
         * Some existing production databases still contain a legacy
         * users.role attribute, which can shadow the role() relationship
         * and cause a 500 error when the edit page reads ->name or ->slug.
         */
        $assignedRole = $user->role()->first();

        $roles = Role::query()
            ->orderBy('name')
            ->get();

        $isAdministratorAccount =
            $assignedRole?->slug === 'administrator';

        $roleChangeAvailableAt = $user->role_changed_at
            ?->copy()
            ->addMonthsNoOverflow(3);

        $roleChangeOnCooldown = $roleChangeAvailableAt
            && now()->lt($roleChangeAvailableAt);

        $canChangeRole = ! $isAdministratorAccount
            && ! $roleChangeOnCooldown;

        $roleChangeRestrictionMessage = null;

        if ($isAdministratorAccount) {
            $roleChangeRestrictionMessage =
                'Administrator accounts have a protected role and cannot change roles.';
        } elseif ($roleChangeOnCooldown) {
            $roleChangeRestrictionMessage = sprintf(
                'This role was changed recently. It can be changed again on %s.',
                $roleChangeAvailableAt->format('M d, Y')
            );
        }

        return view('users.edit', [
            'user' => $user,
            'roles' => $roles,
            'assignedRole' => $assignedRole,
            'canChangeRole' => $canChangeRole,
            'roleChangeAvailableAt' => $roleChangeAvailableAt,
            'roleChangeRestrictionMessage' =>
                $roleChangeRestrictionMessage,
        ]);
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role_id' => [
                'required',
                'integer',
                'exists:roles,id',
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        /*
         * Prevent the currently logged-in administrator from
         * deactivating their own account through the edit form.
         */
        if (
            auth()->id() === $user->id
            && array_key_exists('is_active', $validated)
            && ! (bool) $validated['is_active']
        ) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    'You cannot deactivate your own account.'
                );
        }

        $assignedRole = $user->role()->first();

        $roleIsChanging =
            (int) $validated['role_id'] !== (int) $user->role_id;

        if ($roleIsChanging) {
            if ($assignedRole?->slug === 'administrator') {
                return back()
                    ->withInput()
                    ->withErrors([
                        'role_id' =>
                            'Administrator accounts have a protected role and cannot change roles.',
                    ]);
            }

            $roleChangeAvailableAt = $user->role_changed_at
                ?->copy()
                ->addMonthsNoOverflow(3);

            if (
                $roleChangeAvailableAt
                && now()->lt($roleChangeAvailableAt)
            ) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'role_id' => sprintf(
                            'A user role can only be changed once every 3 months. This role can be changed again on %s.',
                            $roleChangeAvailableAt->format('M d, Y')
                        ),
                    ]);
            }
        }

        try {
            DB::transaction(function () use (
                $validated,
                $user,
                $roleIsChanging
            ): void {
                [$firstName, $lastName] = $this->splitFullName(
                    $validated['name']
                );

                $user->first_name = $firstName;
                $user->last_name = $lastName;
                $user->name = $validated['name'];
                $user->email = strtolower($validated['email']);

                if ($roleIsChanging) {
                    $user->role_id = $validated['role_id'];
                    $user->role_changed_at = now();
                }

                $user->is_active = (bool) ($validated['is_active'] ?? false);

                if (! empty($validated['password'])) {
                    $user->password = Hash::make($validated['password']);
                }

                $user->save();
            });

            return redirect()
                ->route('users.index')
                ->with('success', 'User account updated successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except([
                    'password',
                    'password_confirmation',
                ]))
                ->with('error', 'The user account could not be updated.');
        }
    }

    /**
     * Activate or deactivate a user.
     */
    public function updateStatus(
        Request $request,
        User $user
    ): RedirectResponse {
        $validated = $request->validate([
            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $newStatus = (bool) $validated['is_active'];

        if (auth()->id() === $user->id && ! $newStatus) {
            return back()->with(
                'error',
                'You cannot deactivate your own account.'
            );
        }

        try {
            $user->is_active = $newStatus;
            $user->save();

            $message = $newStatus
                ? 'User account activated successfully.'
                : 'User account deactivated successfully.';

            return back()->with('success', $message);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'The account status could not be updated.'
            );
        }
    }

    /**
     * Reset a user's password.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        $temporaryPassword = $this->generateTemporaryPassword();

        try {
            $user->password = Hash::make($temporaryPassword);
            $user->save();

            return back()
                ->with(
                    'success',
                    'Password reset successfully. Give the temporary password to the user securely.'
                )
                ->with('temporary_password', $temporaryPassword)
                ->with('password_user_name', $user->name);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'The password could not be reset.'
            );
        }
    }

    /**
     * Delete a user account.
     */
    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return back()->with(
                'error',
                'You cannot delete your own account.'
            );
        }

        try {
            $user->delete();

            return redirect()
                ->route('users.index')
                ->with('success', 'User account deleted successfully.');
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'The user account could not be deleted. It may still be connected to other system records.'
            );
        }
    }

    /**
     * Generate a temporary password containing uppercase,
     * lowercase, numbers, and a special character.
     */
    private function generateTemporaryPassword(): string
    {
        return 'Maps@'
            . strtoupper(substr(bin2hex(random_bytes(2)), 0, 2))
            . strtolower(substr(bin2hex(random_bytes(2)), 0, 2))
            . random_int(1000, 9999);
    }
    /**
     * Split a full name into values required by the users table.
     */
    private function splitFullName(string $fullName): array
    {
        $normalizedName = preg_replace('/\\s+/', ' ', trim($fullName));
        $normalizedName = $normalizedName ?: trim($fullName);

        $parts = explode(' ', $normalizedName);

        if (count($parts) === 1) {
            return [
                $parts[0],
                $parts[0],
            ];
        }

        $lastName = array_pop($parts);
        $firstName = implode(' ', $parts);

        return [
            $firstName,
            $lastName,
        ];
    }

}