<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Barangay;
use App\Models\PasswordChangeRequest;
use App\Models\ProfilePhoto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileAccountChangesTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug = 'flood-analyst', array $attributes = []): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $slug, 'is_active' => true]);

        return User::factory()->create($attributes + ['role_id' => $role->id, 'is_active' => true, 'password' => 'OldPassword123!']);
    }

    private function photo(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aVJkAAAAASUVORK5CYII='));
    }

    private function requestLogin(User $user, array $changes = []): PasswordChangeRequest
    {
        $this->actingAs($user)->withSession(['password_version' => $user->password_version])
            ->post(route('password.request'), $changes + ['current_password' => 'OldPassword123!'])->assertRedirect(route('profile'));

        return PasswordChangeRequest::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    public function test_profile_popup_shows_role_and_assigned_barangay_without_navigation(): void
    {
        $barangay = Barangay::create(['name' => 'Hulo', 'district' => 1, 'is_active' => true]);
        $user = $this->account(attributes: ['barangay_id' => $barangay->id]);
        $this->actingAs($user)->get(route('profile'))->assertOk()->assertSee('data-profile-open', false)
            ->assertSee('data-profile-dialog', false)->assertSee('aria-labelledby="user-profile-title"', false);
        $this->get(route('profile', ['modal' => 1]))->assertOk()->assertSee($user->name)->assertSee($user->email)
            ->assertSee('flood-analyst')->assertSee('Hulo')->assertSee('Profile picture')->assertSee('Save profile')
            ->assertDontSee('<!DOCTYPE', false)->assertDontSee('OldPassword123!');
        $other = $this->account('fire-responder');
        $this->actingAs($other)->get(route('profile', ['modal' => 1]))->assertSee('Not assigned')->assertDontSee($user->email);
    }

    public function test_all_roles_can_save_their_names_and_pictures_without_changing_access_or_login(): void
    {
        $barangay = Barangay::create(['name' => 'Hulo', 'district' => 1, 'is_active' => true]);
        foreach (['administrator', 'operations-manager', 'fire-responder', 'flood-analyst', 'system-viewer', 'public-resident'] as $index => $slug) {
            $user = $this->account($slug, ['barangay_id' => $barangay->id, 'contact_number' => '+63917123450'.$index]);
            $hash = $user->password;
            $photo = $this->photo('avatar.png');
            $this->actingAs($user)->from(route('profile'))->put(route('profile.update'), [
                'first_name' => ' Paul Randolf ', 'last_name' => ' Bañoc ', 'photo' => $photo, '_profile_modal' => '1',
                'user_id' => 9999, 'role_id' => 9999, 'barangay_id' => 9999, 'email' => 'ignored@example.test',
                'password' => 'IgnoredPassword123!', 'must_change_password' => false,
            ])->assertRedirect(route('profile'))->assertSessionHas('open_profile', true);
            $fresh = $user->fresh();
            $this->assertSame('Paul Randolf Bañoc', $fresh->name);
            $this->assertSame($user->email, $fresh->email);
            $this->assertSame($hash, $fresh->password);
            $this->assertSame($user->role_id, $fresh->role_id);
            $this->assertSame($barangay->id, $fresh->barangay_id);
            $this->assertSame(0, $fresh->password_version);
            $stored = ProfilePhoto::where('user_id', $user->id)->firstOrFail();
            $this->get(route('profile.photo'))->assertOk()->assertHeader('Content-Type', 'image/png')
                ->assertHeader('Cache-Control', 'no-store, private')->assertContent(base64_decode($stored->image_data));
            $this->assertArrayNotHasKey('image_data', $stored->toArray());
            $this->assertStringNotContainsString($stored->image_data, ActivityLog::all()->toJson());
            if ($slug === 'public-resident') {
                $this->assertSame('Paul Randolf Bañoc', $fresh->smsRecipient()->value('full_name'));
                $this->get(route('public.account'))->assertOk()->assertSee('data-profile-open', false);
            }
        }
        $this->assertDatabaseCount('password_change_requests', 0);
    }

    public function test_photo_validation_is_private_and_replacement_does_not_create_extra_records(): void
    {
        $user = $this->account();
        $data = ['first_name' => 'Paul', 'last_name' => 'Bañoc'];
        $this->actingAs($user)->put(route('profile.update'), $data + ['photo' => UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('photo');
        $this->put(route('profile.update'), $data + ['photo' => $this->photo('large.png')->size(3000)])->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('profile_photos', 0);
        $this->assertSame($user->name, $user->fresh()->name);
        $this->put(route('profile.update'), $data + ['photo' => $this->photo('first.png')])->assertRedirect();
        $this->put(route('profile.update'), $data + ['photo' => $this->photo('second.png')])->assertRedirect();
        $this->assertDatabaseCount('profile_photos', 1);
        $this->assertSame('image/png', ProfilePhoto::first()->mime_type);
        $this->actingAs($this->account())->get(route('profile.photo'))->assertNotFound();
        Auth::logout();
        $this->get(route('profile.photo'))->assertRedirect(route('login'));
    }

    public function test_username_only_request_keeps_password_and_old_email_until_admin_approval(): void
    {
        $user = $this->account();
        $hash = $user->password;
        $change = $this->requestLogin($user, ['email' => ' New.User@Example.test ']);
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertSame('new.user@example.test', $change->requested_email);
        $this->assertFalse($change->changes_password);
        $this->assertNull($change->password_hash);
        $this->actingAs($this->account('administrator'))->get(route('users.password-requests'))->assertOk()->assertSee('new.user@example.test');
        $this->post(route('users.password-requests.review', $change), ['decision' => 'Approved'])->assertRedirect();
        $fresh = $user->fresh();
        $this->assertSame('new.user@example.test', $fresh->email);
        $this->assertSame($hash, $fresh->password);
        $this->assertSame(1, $fresh->password_version);
        $this->actingAs($fresh)->withSession(['password_version' => 0])->get(route('profile'))->assertRedirect(route('login'));
        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'OldPassword123!'])->assertSessionHasErrors('email');
        $this->post(route('login.attempt'), ['email' => $fresh->email, 'password' => 'OldPassword123!'])->assertRedirect(route('dashboard'));
    }

    public function test_combined_request_is_private_and_email_collision_prevents_both_changes(): void
    {
        $user = $this->account();
        $change = $this->requestLogin($user, ['email' => 'wanted@example.test', 'password' => 'NewPassword456!', 'password_confirmation' => 'NewPassword456!']);
        $this->assertTrue($change->changes_password);
        $this->assertTrue(Hash::check('NewPassword456!', $change->password_hash));
        $other = $this->account(attributes: ['email' => 'wanted@example.test']);
        $this->actingAs($this->account('administrator'))->get(route('users.password-requests'))->assertDontSee('NewPassword456!')->assertDontSee($change->password_hash, false);
        $this->post(route('users.password-requests.review', $change), ['decision' => 'Approved'])->assertSessionHasErrors('email');
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
        $this->assertSame('Pending', $change->fresh()->status);
        $other->update(['email' => 'other@example.test']);
        $this->post(route('users.password-requests.review', $change), ['decision' => 'Approved'])->assertRedirect();
        $this->assertSame('wanted@example.test', $user->fresh()->email);
        $this->assertTrue(Hash::check('NewPassword456!', $user->fresh()->password));
        $this->assertNull($change->fresh()->password_hash);
        $this->assertStringNotContainsString('NewPassword456!', ActivityLog::all()->toJson());
    }

    public function test_rejection_and_stale_username_requests_cannot_overwrite_admin_changes(): void
    {
        $user = $this->account();
        $change = $this->requestLogin($user, ['email' => 'requested@example.test']);
        $user->update(['email' => 'admin-updated@example.test']);
        $this->actingAs($this->account('administrator'))->post(route('users.password-requests.review', $change), ['decision' => 'Approved'])->assertSessionHasErrors('request');
        $this->assertSame('admin-updated@example.test', $user->fresh()->email);
        $this->post(route('users.password-requests.review', $change), ['decision' => 'Rejected'])->assertRedirect();
        $this->assertSame('Rejected', $change->fresh()->status);
        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
    }

    public function test_request_validation_prevents_invalid_duplicate_and_unchanged_emails(): void
    {
        $user = $this->account();
        $other = $this->account();
        $this->actingAs($user)->post(route('password.request'), ['email' => $other->email, 'current_password' => 'OldPassword123!'])->assertSessionHasErrors('email');
        $this->post(route('password.request'), ['email' => $user->email, 'current_password' => 'OldPassword123!'])->assertSessionHasErrors('email');
        $this->post(route('password.request'), ['email' => 'invalid', 'current_password' => 'OldPassword123!'])->assertSessionHasErrors('email');
        $this->post(route('password.request'), ['email' => 'valid@example.test', 'current_password' => 'Wrong'])->assertSessionHasErrors('current_password');
        $this->post(route('password.request'), ['current_password' => 'OldPassword123!'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('password_change_requests', 0);
    }

    public function test_modal_requests_return_to_origin_and_reset_blocks_profile_changes(): void
    {
        $user = $this->account();
        $this->actingAs($user)->from(route('profile'))->post(route('password.request'), [
            'email' => 'requested@example.test', 'current_password' => 'OldPassword123!', '_profile_modal' => '1',
        ])->assertRedirect(route('profile'))->assertSessionHas('open_profile', true);
        $change = PasswordChangeRequest::firstOrFail();
        $this->actingAs($this->account('administrator'))->patch(route('users.reset-password', $user))->assertRedirect();
        $this->assertSame('Cancelled', $change->fresh()->status);
        $this->actingAs($user->fresh())->withSession(['password_version' => $user->fresh()->password_version])
            ->put(route('profile.update'), ['first_name' => 'Bypass', 'last_name' => 'User'])->assertRedirect(route('password.required'));
        $this->get(route('profile', ['modal' => 1]))->assertRedirect(route('password.required'));
        $this->assertSame($user->name, $user->fresh()->name);
    }
}
