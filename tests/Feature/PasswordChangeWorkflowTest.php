<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\PasswordChangeRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\PasswordChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug = 'flood-analyst', array $attributes = []): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $slug, 'is_active' => true]);

        return User::factory()->create($attributes + ['role_id' => $role->id, 'is_active' => true, 'password' => 'OldPassword123!']);
    }

    private function payload(string $password = 'NewPassword456!'): array
    {
        return ['current_password' => 'OldPassword123!', 'password' => $password, 'password_confirmation' => $password];
    }

    private function submit(User $user): PasswordChangeRequest
    {
        $this->actingAs($user)->withSession(['password_version' => $user->password_version])
            ->post(route('password.request'), $this->payload())->assertRedirect(route('profile'));

        return PasswordChangeRequest::where('user_id', $user->id)->firstOrFail();
    }

    public function test_all_roles_can_request_without_changing_their_active_password(): void
    {
        foreach (['administrator', 'operations-manager', 'fire-responder', 'flood-analyst', 'system-viewer', 'public-resident'] as $slug) {
            $user = $this->account($slug);
            $this->actingAs($user)->get(route('profile'))->assertOk()->assertSee('My Profile');
            $change = $this->submit($user);
            $this->assertSame('Pending', $change->status);
            $this->assertTrue(Hash::check('NewPassword456!', $change->password_hash));
            $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
            $this->assertFalse(Hash::check('NewPassword456!', $user->fresh()->password));
            $this->assertArrayNotHasKey('password_hash', $change->toArray());
            $this->get(route('profile'))->assertSee('Pending')->assertDontSee('name="password"', false);
            $this->assertStringNotContainsString('NewPassword456!', json_encode($change->getAttributes()));
        }
    }

    public function test_approval_activates_exact_hash_and_exposes_no_passwords_in_ui_or_logs(): void
    {
        $user = $this->account();
        $change = $this->submit($user);
        $hash = $change->password_hash;
        $admin = $this->account('administrator');
        $this->actingAs($admin)->get(route('users.index'))->assertOk()->assertSee('Account Change Requests');
        $this->get(route('users.password-requests'))->assertOk()->assertSee($user->email)
            ->assertDontSee($hash, false)->assertDontSee('NewPassword456!');
        $this->post(route('users.password-requests.review', $change), ['decision' => 'Approved'])->assertRedirect();
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame(1, $user->fresh()->password_version);
        $this->assertSame('Approved', $change->fresh()->status);
        $this->assertNull($change->fresh()->password_hash);
        $this->assertSame($admin->id, $change->fresh()->reviewed_by);
        $logs = ActivityLog::all()->toJson();
        foreach (['OldPassword123!', 'NewPassword456!', $hash] as $secret) {
            $this->assertStringNotContainsString($secret, $logs);
        }
        $this->post(route('users.password-requests.review', $change), ['decision' => 'Rejected'])->assertSessionHasErrors('request');
        $this->assertSame($hash, $user->fresh()->password);
        // The submitting browser's old session must sign in again.
        $token = $user->fresh()->remember_token;
        $this->actingAs($user->fresh())->withSession(['password_version' => 0])->get(route('profile'))->assertRedirect(route('login'));
        $this->assertSame($token, $user->fresh()->remember_token);
        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'OldPassword123!'])->assertSessionHasErrors('email');
        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'NewPassword456!'])->assertRedirect(route('dashboard'));
    }

    public function test_rejection_leaves_password_active_and_allows_a_new_request(): void
    {
        $user = $this->account();
        $change = $this->submit($user);
        $this->actingAs($this->account('administrator'))->post(route('users.password-requests.review', $change), ['decision' => 'Rejected'])->assertRedirect();
        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
        $this->assertSame(0, $user->fresh()->password_version);
        $this->assertSame('Rejected', $change->fresh()->status);
        $this->assertNull($change->fresh()->password_hash);
        $this->actingAs($user)->post(route('password.request'), $this->payload('AnotherPassword789!'))->assertRedirect(route('profile'));
        $this->assertDatabaseCount('password_change_requests', 2);
    }

    public function test_requests_validate_current_password_confirmation_length_and_pending_duplicates(): void
    {
        $user = $this->account();
        $this->actingAs($user)->post(route('password.request'), ['current_password' => 'Wrong'] + $this->payload())->assertSessionHasErrors('current_password');
        $this->post(route('password.request'), ['password_confirmation' => 'Mismatch'] + $this->payload())->assertSessionHasErrors('password');
        $this->post(route('password.request'), $this->payload('short'))->assertSessionHasErrors('password');
        $this->post(route('password.request'), $this->payload('OldPassword123!'))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('password_change_requests', 0);
        $this->post(route('password.request'), $this->payload())->assertRedirect(route('profile'));
        // Avoid endpoint throttling while exercising the duplicate guard.
        $this->travel(2)->minutes();
        $this->post(route('password.request'), $this->payload('AnotherPassword789!'))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('password_change_requests', 1);
        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input.current_password'));
    }

    public function test_only_admins_can_review_or_reset_accounts(): void
    {
        $user = $this->account();
        $change = $this->submit($user);
        foreach (['operations-manager', 'fire-responder', 'flood-analyst', 'system-viewer', 'public-resident'] as $slug) {
            $this->actingAs($this->account($slug))->get(route('users.password-requests'))->assertForbidden();
            $this->post(route('users.password-requests.review', $change), ['decision' => 'Approved'])->assertForbidden();
            $this->patch(route('users.reset-password', $user))->assertForbidden();
        }
        $this->assertSame('Pending', $change->fresh()->status);
        Auth::logout();
        $this->post(route('password.request'), $this->payload())->assertRedirect(route('login'));
    }

    public function test_reset_cancels_requests_and_requires_new_password_before_any_other_action(): void
    {
        $user = $this->account();
        $change = $this->submit($user);
        $this->actingAs($this->account('administrator'))->patch(route('users.reset-password', $user))->assertRedirect()->assertSessionHas('temporary_password');
        $temporary = session('temporary_password');
        $this->assertTrue(Hash::check($temporary, $user->fresh()->password));
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertSame('Cancelled', $change->fresh()->status);
        $this->assertNull($change->fresh()->password_hash);
        $this->post(route('users.password-requests.review', $change), ['decision' => 'Approved'])->assertSessionHasErrors('request');
        $this->actingAs($user->fresh())->withSession(['password_version' => 0])->get(route('profile'))->assertRedirect(route('login'));
        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => $temporary])->assertRedirect(route('password.required'));
        $this->get(route('password.required'))->assertOk()->assertSee('Set your new password');
        foreach (['dashboard', 'profile', 'public.portal'] as $route) {
            $this->get(route($route))->assertRedirect(route('password.required'));
        }
        $this->post(route('password.request'), $this->payload())->assertRedirect(route('password.required'));
        $this->postJson(route('prediction.citywide'), ['forecast_hours' => 24])->assertForbidden()->assertJsonPath('redirect', route('password.required'));
        $this->withHeader('Accept', 'text/html');
        $this->put(route('password.complete'), ['password' => $temporary, 'password_confirmation' => $temporary])->assertSessionHasErrors('password');
        $this->put(route('password.complete'), ['password' => 'PermanentPassword789!', 'password_confirmation' => 'Mismatch'])->assertSessionHasErrors('password');
        $this->put(route('password.complete'), ['password' => 'PermanentPassword789!', 'password_confirmation' => 'PermanentPassword789!'])->assertRedirect(route('dashboard'));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertSame(2, $user->fresh()->password_version);
        $this->assertTrue(Hash::check('PermanentPassword789!', $user->fresh()->password));
        $this->assertDatabaseCount('password_change_requests', 1);
        // Reload the auth user as a new HTTP request would.
        $this->actingAs($user->fresh())->get(route('profile'))->assertOk();
        $this->put(route('password.complete'), ['password' => 'BypassPassword789!', 'password_confirmation' => 'BypassPassword789!'])->assertStatus(409);
    }

    public function test_forced_change_persists_after_logout_and_works_for_public_and_admin_accounts(): void
    {
        foreach (['public-resident', 'administrator'] as $slug) {
            $user = $this->account($slug);
            app(PasswordChangeService::class)->reset($user, 'Temporary123!');
            Auth::logout();
            $this->withSession(['password_version' => 0])->post(route('login.attempt'), ['email' => $user->email, 'password' => 'Temporary123!'])->assertRedirect(route('password.required'));
            $this->post(route('logout'))->assertRedirect();
            $this->assertTrue($user->fresh()->must_change_password);
            $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'Temporary123!'])->assertRedirect(route('password.required'));
            $this->put(route('password.complete'), ['password' => 'PermanentPassword789!', 'password_confirmation' => 'PermanentPassword789!'])->assertRedirect(route($slug === 'public-resident' ? 'public.account' : 'dashboard'));
            $this->assertFalse($user->fresh()->must_change_password);
            $this->assertDatabaseCount('password_change_requests', 0);
        }
    }

    public function test_admin_self_reset_can_set_new_password_without_becoming_locked_out(): void
    {
        $admin = $this->account('administrator');
        $this->actingAs($admin)->patch(route('users.reset-password', $admin))->assertRedirect(route('password.required'));
        $this->actingAs($admin->fresh())->get(route('password.required'))->assertOk();
        $this->put(route('password.complete'), ['password' => 'NewAdminPassword789!', 'password_confirmation' => 'NewAdminPassword789!'])->assertRedirect(route('dashboard'));
        $this->assertTrue(Hash::check('NewAdminPassword789!', $admin->fresh()->password));
    }

    public function test_admin_edit_cannot_bypass_password_workflow_and_inactive_users_cannot_request(): void
    {
        $user = $this->account();
        $this->actingAs($this->account('administrator'))->put(route('users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id, 'is_active' => true,
            'password' => 'BypassPassword789!', 'password_confirmation' => 'BypassPassword789!',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
        $user->update(['is_active' => false]);
        $this->actingAs($user)->post(route('password.request'), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('password_change_requests', 0);
    }

    public function test_request_cannot_target_another_account_or_disable_reset_protection(): void
    {
        $user = $this->account();
        $other = $this->account('fire-responder');
        $this->actingAs($user)->post(route('password.request'), $this->payload() + [
            'user_id' => $other->id, 'must_change_password' => false, 'password_version' => 999,
        ])->assertRedirect(route('profile'));
        $this->assertSame($user->id, PasswordChangeRequest::firstOrFail()->user_id);
        $this->assertSame(0, $user->fresh()->password_version);
        $this->assertTrue(Hash::check('OldPassword123!', $other->fresh()->password));
        $this->put(route('password.complete'), $this->payload())->assertStatus(409);
    }

    public function test_old_remember_cookie_is_invalidated_and_a_current_cookie_can_resume_login(): void
    {
        $user = $this->account();
        $cookie = Auth::guard()->getRecallerName();
        $oldCookie = $user->id.'|'.$user->remember_token.'|'.$user->password;
        $change = $this->submit($user);
        $this->actingAs($this->account('administrator'))->post(route('users.password-requests.review', $change), ['decision' => 'Approved'])->assertRedirect();
        $user->refresh();
        $this->assertNotSame($oldCookie, $user->id.'|'.$user->remember_token.'|'.$user->password);
        Auth::forgetGuards();
        $this->flushSession();
        $this->withCookie($cookie, $oldCookie)->get(route('profile'))->assertRedirect(route('login'));
        $this->assertGuest();
        Auth::forgetGuards();
        $this->withCookie($cookie, $user->id.'|'.$user->remember_token.'|'.$user->password)->get(route('profile'))->assertOk();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->password_version, session('password_version'));
    }
}
