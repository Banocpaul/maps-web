<?php

namespace App\Services;

use App\Models\PasswordChangeRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PasswordChangeService
{
    public function submit(User $actor, string $current, ?string $password, ?string $email = null): void
    {
        DB::transaction(function () use ($actor, $current, $password, $email): void {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($current, $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }
            if ($user->must_change_password) {
                throw ValidationException::withMessages(['password' => 'Complete the required password change first.']);
            }
            if ($password !== null) {
                $this->ensureDifferent($user, $password);
            }
            if ($email !== null) {
                if (strcasecmp($email, $user->email) === 0) {
                    throw ValidationException::withMessages(['email' => 'Choose a different username email.']);
                }
                $this->validateEmail($user, $email);
            }
            if ($password === null && $email === null) {
                throw ValidationException::withMessages(['email' => 'Enter a new username email or password.']);
            }
            if (PasswordChangeRequest::where('user_id', $user->id)->where('status', 'Pending')->exists()) {
                throw ValidationException::withMessages(['password' => 'You already have an account change awaiting approval.']);
            }
            PasswordChangeRequest::create([
                'user_id' => $user->id, 'password_hash' => $password !== null ? Hash::make($password) : null,
                'current_email' => $user->email, 'requested_email' => $email, 'changes_password' => $password !== null,
                'password_version' => $user->password_version, 'status' => 'Pending',
            ]);
        });
    }

    public function review(PasswordChangeRequest $change, User $admin, string $decision): void
    {
        try {
            DB::transaction(function () use ($change, $admin, $decision): void {
                $user = User::whereKey($change->user_id)->lockForUpdate()->firstOrFail();
                $change = PasswordChangeRequest::whereKey($change->id)->lockForUpdate()->firstOrFail();
                if ($change->status !== 'Pending') {
                    throw ValidationException::withMessages(['request' => 'This account change has already been processed.']);
                }
                if ($user->must_change_password || $change->password_version !== $user->password_version) {
                    throw ValidationException::withMessages(['request' => 'This request is no longer valid after a password reset.']);
                }
                if ($decision === 'Approved') {
                    if ($change->requested_email !== null) {
                        if ($change->current_email !== $user->email) {
                            throw ValidationException::withMessages(['request' => 'The username changed since this request. Reject it and ask the user to submit again.']);
                        }
                        $this->validateEmail($user, $change->requested_email);
                    }
                    $updates = [
                        'password_version' => $user->password_version + 1,
                        'remember_token' => Str::random(60),
                    ];
                    if ($change->changes_password) {
                        $updates['password'] = $change->password_hash;
                    }
                    if ($change->requested_email !== null) {
                        $updates['email'] = $change->requested_email;
                    }
                    $user->forceFill($updates)->save();
                }
                $change->update([
                    'status' => $decision, 'password_hash' => null,
                    'reviewed_by' => $admin->id, 'reviewed_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => 'The requested username email is already in use. Reject this request and ask the user to submit another.']);
        }
    }

    public function reset(User $target, string $temporaryPassword): User
    {
        return DB::transaction(function () use ($target, $temporaryPassword): User {
            $user = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $user->forceFill([
                'password' => Hash::make($temporaryPassword), 'must_change_password' => true,
                'password_version' => $user->password_version + 1, 'remember_token' => Str::random(60),
            ])->save();
            PasswordChangeRequest::where('user_id', $user->id)->where('status', 'Pending')->update([
                'status' => 'Cancelled', 'password_hash' => null, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);

            return $user;
        });
    }

    public function completeReset(User $actor, string $password): User
    {
        return DB::transaction(function () use ($actor, $password): User {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->must_change_password && $user->password_version === $actor->password_version, 409);
            $this->ensureDifferent($user, $password);
            $user->forceFill([
                'password' => Hash::make($password), 'must_change_password' => false,
                'password_version' => $user->password_version + 1, 'remember_token' => Str::random(60),
            ])->save();

            return $user;
        });
    }

    private function ensureDifferent(User $user, string $password): void
    {
        if (Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => 'Choose a password different from your current password.']);
        }
    }

    private function validateEmail(User $user, string $email): void
    {
        validator(['email' => $email], ['email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)]])->validate();
    }
}
