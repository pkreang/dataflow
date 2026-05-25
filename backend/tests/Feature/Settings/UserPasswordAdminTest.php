<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

/**
 * Admin-side password helpers — two paths admins can use to get a new user
 * into the system:
 *
 *  - resetPassword: regenerate a random password and surface it ONCE in the
 *    session so the admin can copy + share manually (UAT-friendly, works
 *    without email infra)
 *  - sendPasswordResetLink: trigger Laravel's password broker so the user
 *    gets a normal reset email (production path)
 */
class UserPasswordAdminTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_reset_user_password(): void
    {
        $admin = $this->makeSuperAdmin();
        $user = $this->makeRegularUser('alice@example.test');
        $oldHash = $user->password;

        $response = $this->actingAsWebSession($admin)
            ->post(route('users.password.reset', $user));

        $response->assertRedirect(route('users.edit', $user));
        $response->assertSessionHas('temp_password');

        $fresh = $user->fresh();
        $this->assertNotSame($oldHash, $fresh->password);
        $this->assertTrue((bool) $fresh->password_must_change);
        $this->assertNotNull($fresh->password_changed_at);
    }

    public function test_super_admin_can_send_password_reset_email(): void
    {
        Notification::fake();
        $admin = $this->makeSuperAdmin();
        $user = $this->makeRegularUser('bob@example.test');

        $response = $this->actingAsWebSession($admin)
            ->post(route('users.password.send-link', $user));

        $response->assertRedirect(route('users.edit', $user));
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_non_super_admin_cannot_reset_password(): void
    {
        $actor = $this->makeRegularUser('not-admin@example.test');
        $target = $this->makeRegularUser('target@example.test');

        $this->actingAsWebSession($actor)
            ->post(route('users.password.reset', $target))
            ->assertStatus(403);
    }

    public function test_non_super_admin_cannot_send_reset_link(): void
    {
        Notification::fake();
        $actor = $this->makeRegularUser('not-admin2@example.test');
        $target = $this->makeRegularUser('target2@example.test');

        $this->actingAsWebSession($actor)
            ->post(route('users.password.send-link', $target))
            ->assertStatus(403);

        Notification::assertNothingSentTo($target);
    }
}
