<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileExtendedTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_saves_phone_and_locale(): void
    {
        $this->seedBase();
        [$user, $position] = $this->makeUserWithPosition();

        $response = $this->actingAsWebSession($user)->put(route('profile.update'), [
            'first_name' => 'New',
            'last_name' => 'Name',
            'department' => 'Ops',
            'email' => $user->email,
            'position_id' => $position->id,
            'phone' => '081-234-5678',
            'locale' => 'en',
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertSame('081-234-5678', $user->phone);
        $this->assertSame('en', $user->locale);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->seedBase();
        [$user, $position] = $this->makeUserWithPosition();

        $response = $this->actingAsWebSession($user)->put(route('profile.update'), [
            'first_name' => 'X',
            'last_name' => 'Y',
            'department' => 'Ops',
            'email' => $user->email,
            'position_id' => $position->id,
            'locale' => 'zz',
        ]);
        $response->assertSessionHasErrors('locale');
    }

    public function test_avatar_upload_stores_file_and_sets_user_avatar(): void
    {
        Storage::fake('public');
        $this->seedBase();
        [$user, $position] = $this->makeUserWithPosition();

        $file = UploadedFile::fake()->image('me.png', 200, 200);

        $response = $this->actingAsWebSession($user)->put(route('profile.update'), [
            'first_name' => 'X',
            'last_name' => 'Y',
            'department' => 'Ops',
            'email' => $user->email,
            'position_id' => $position->id,
            'avatar' => $file,
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertStringContainsString('avatars/', $user->avatar);

        // File exists in fake disk
        $storagePrefix = Storage::disk('public')->url('');
        $relative = ltrim(substr($user->avatar, strlen($storagePrefix)), '/');
        Storage::disk('public')->assertExists($relative);
    }

    public function test_remove_avatar_deletes_file_and_clears_column(): void
    {
        Storage::fake('public');
        $this->seedBase();
        [$user, $position] = $this->makeUserWithPosition();

        Storage::disk('public')->put('avatars/existing.png', 'x');
        $user->update(['avatar' => Storage::disk('public')->url('avatars/existing.png')]);

        $response = $this->actingAsWebSession($user)->put(route('profile.update'), [
            'first_name' => 'X',
            'last_name' => 'Y',
            'department' => 'Ops',
            'email' => $user->email,
            'position_id' => $position->id,
            'remove_avatar' => '1',
        ]);

        $response->assertRedirect();
        $this->assertNull($user->fresh()->avatar);
        Storage::disk('public')->assertMissing('avatars/existing.png');
    }

    public function test_notification_preferences_are_saved(): void
    {
        $this->seedBase();
        [$user, $_position] = $this->makeUserWithPosition();

        $response = $this->actingAsWebSession($user)->put(route('profile.notifications.update'), [
            'notifications' => [
                'approval_pending' => ['mail' => '1'],
                // line intentionally off for approval_pending
                'workflow_approved' => ['mail' => '1', 'line' => '1'],
                // workflow_rejected + stock_low: all off
            ],
        ]);

        $response->assertRedirect();
        $prefs = NotificationPreference::where('user_id', $user->id)->get()->keyBy(fn ($p) => $p->event_type.'|'.$p->channel);

        $this->assertTrue($prefs['approval_pending|mail']->enabled);
        $this->assertFalse($prefs['approval_pending|line']->enabled);
        $this->assertTrue($prefs['workflow_approved|line']->enabled);
        $this->assertFalse($prefs['workflow_rejected|mail']->enabled);
        $this->assertFalse($prefs['stock_low|line']->enabled);
    }

    public function test_edit_page_shows_quick_stats(): void
    {
        $this->seedBase();
        [$user, $_position] = $this->makeUserWithPosition();

        $response = $this->actingAsWebSession($user)->get(route('profile.edit'));
        $response->assertOk();
        // All stats default to 0 for a fresh user
        $response->assertSee(__('common.my_drafts'));
        $response->assertSee(__('common.my_submissions_count'));
        $response->assertSee(__('common.my_pending_approvals'));
    }

    public function test_department_id_cannot_be_changed_via_post(): void
    {
        $this->seedBase();
        [$user, $position] = $this->makeUserWithPosition();

        $originalDept = \App\Models\Department::create(['code' => 'OG', 'name' => 'Original']);
        $elevatedDept = \App\Models\Department::create(['code' => 'EL', 'name' => 'Elevated']);
        $user->update(['department_id' => $originalDept->id]);

        $this->actingAsWebSession($user)->put(route('profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'department_id' => $elevatedDept->id,    // attempt to reassign self
            'phone' => '080-0000-000',
        ]);

        $this->assertSame($originalDept->id, $user->fresh()->department_id);
    }

    public function test_position_id_cannot_be_changed_via_post(): void
    {
        $this->seedBase();
        [$user, $originalPosition] = $this->makeUserWithPosition();
        $elevatedPosition = Position::create(['code' => 'MGR', 'name' => 'Manager', 'is_active' => true]);

        $this->actingAsWebSession($user)->put(route('profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'position_id' => $elevatedPosition->id,  // attempt to escalate
        ]);

        $this->assertSame($originalPosition->id, $user->fresh()->position_id);
    }

    public function test_sso_user_cannot_change_name_via_post(): void
    {
        $this->seedBase();
        [$user, $position] = $this->makeUserWithPosition();
        $user->update(['auth_provider' => 'entra', 'external_id' => 'abc-123']);

        $this->actingAsWebSession($user)->put(route('profile.update'), [
            'first_name' => 'Hacker',
            'last_name' => 'Spoof',
            // email intentionally omitted — SSO user can't edit email anyway
            'phone' => '081-1111-111',
        ]);

        $user->refresh();
        $this->assertNotSame('Hacker', $user->first_name);
        $this->assertNotSame('Spoof', $user->last_name);
        $this->assertSame('081-1111-111', $user->phone); // other fields still save
    }

    public function test_local_user_can_still_change_name(): void
    {
        $this->seedBase();
        [$user, $position] = $this->makeUserWithPosition();

        $this->actingAsWebSession($user)->put(route('profile.update'), [
            'first_name' => 'NewFirst',
            'last_name' => 'NewLast',
            'email' => $user->email,
            'phone' => '',
        ]);

        $user->refresh();
        $this->assertSame('NewFirst', $user->first_name);
        $this->assertSame('NewLast', $user->last_name);
    }

    public function test_local_user_cannot_change_own_email_via_profile_update(): void
    {
        $this->seedBase();
        [$user, $position] = $this->makeUserWithPosition();
        $originalEmail = $user->email;

        $this->actingAsWebSession($user)->put(route('profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => 'attacker-'.uniqid().'@evil.test',
            'position_id' => $position->id,
        ]);

        $this->assertSame(
            $originalEmail,
            $user->fresh()->email,
            'Local user email must not be editable via profile.update (login credential is immutable)'
        );
    }

    public function test_admin_cannot_change_local_user_email_via_users_update(): void
    {
        $this->seedBase();
        [$target, $position] = $this->makeUserWithPosition();
        $dept = \App\Models\Department::create([
            'code' => 'EMAILTST',
            'name' => 'Email Lock Test Dept',
            'is_active' => true,
        ]);
        $target->update(['department_id' => $dept->id]);
        $originalEmail = $target->fresh()->email;

        $admin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
            'position_id' => $position->id,
            'department_id' => $dept->id,
        ]);

        $response = $this->actingAsWebSession($admin)->put(route('users.update', $target->id), [
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => 'admin-changed-'.uniqid().'@evil.test',
            'department_id' => $dept->id,
            'position_id' => $position->id,
            'phone' => '081-000-0000', // distinct change to prove the update ran
        ]);

        $response->assertSessionHasNoErrors(); // confirm validation passed (not a redirect-back-with-errors)
        $response->assertRedirect();
        $fresh = $target->fresh();
        $this->assertSame('081-000-0000', $fresh->phone, 'Update must have actually executed');
        $this->assertSame(
            $originalEmail,
            $fresh->email,
            'Admin must not be able to change a user email via users.update (login credential is immutable)'
        );
    }

    // ── Helpers ─────────────────────────────────────────────

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeUserWithPosition(): array
    {
        $position = Position::create([
            'code' => 'TST',
            'name' => 'Tester',
            'is_active' => true,
        ]);

        $user = User::create([
            'first_name' => 'Profile',
            'last_name' => 'User',
            'email' => 'profile-'.uniqid().'@example.test',
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
            'position_id' => $position->id,
        ]);

        return [$user, $position];
    }

    private function actingAsWebSession(User $user): self
    {
        $token = $user->createToken('phpunit-web')->plainTextToken;

        return $this->withSession([
            'api_token' => $token,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => trim($user->first_name.' '.$user->last_name) ?: $user->email,
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
                'department_id' => $user->department_id,
                'can_change_password' => true,
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }
}
