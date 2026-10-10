<?php

namespace App\Services;

use App\Models\PasswordChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordChangeService
{
    public function submit(User $actor, string $current, string $password): void
    {
        DB::transaction(function () use ($actor, $current, $password): void {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($current, $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }
            if ($user->must_change_password) {
                throw ValidationException::withMessages(['password' => 'Complete the required password change first.']);
            }
            $this->ensureDifferent($user, $password);
            if (PasswordChangeRequest::where('user_id', $user->id)->where('status', 'Pending')->exists()) {
                throw ValidationException::withMessages(['password' => 'You already have a password request awaiting approval.']);
            }
            PasswordChangeRequest::create([
                'user_id' => $user->id, 'password_hash' => Hash::make($password),
                'password_version' => $user->password_version, 'status' => 'Pending',
            ]);
        });
    }

    public function review(PasswordChangeRequest $change, User $admin, string $decision): void
    {
        DB::transaction(function () use ($change, $admin, $decision): void {
            $user = User::whereKey($change->user_id)->lockForUpdate()->firstOrFail();
            $change = PasswordChangeRequest::whereKey($change->id)->lockForUpdate()->firstOrFail();
            if ($change->status !== 'Pending') {
                throw ValidationException::withMessages(['request' => 'This password request has already been processed.']);
            }
            if ($user->must_change_password || $change->password_version !== $user->password_version) {
                throw ValidationException::withMessages(['request' => 'This request is no longer valid after a password reset.']);
            }
            if ($decision === 'Approved') {
                $user->forceFill([
                    'password' => $change->password_hash,
                    'password_version' => $user->password_version + 1,
                    'remember_token' => Str::random(60),
                ])->save();
            }
            $change->update([
                'status' => $decision, 'password_hash' => null,
                'reviewed_by' => $admin->id, 'reviewed_at' => now(),
            ]);
        });
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
}
