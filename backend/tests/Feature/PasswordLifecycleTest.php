<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\PasswordLifecycleService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_blocks_protected_routes_when_password_change_required(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $user->update(['password_must_change' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/users')
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'password_change_required' => true,
            ]);
    }

    public function test_api_me_is_allowed_and_reports_must_change_password(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $user->update(['password_must_change' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.must_change_password', true);
    }

    public function test_api_change_password_clears_must_change_and_restores_access(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $user->update(['password_must_change' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'Newpass1!x',
                'password_confirmation' => 'Newpass1!x',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertFalse((bool) $user->password_must_change);

        $this->withToken($token)
            ->getJson('/api/v1/users')
            ->assertOk();
    }

    public function test_expired_password_requires_change_when_policy_days_set(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Setting::set('password_expires_days', '30');

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $user->update([
            'password_must_change' => false,
            'password_changed_at' => now()->subDays(31),
        ]);

        $this->assertTrue(PasswordLifecycleService::requiresPasswordChange($user->fresh()));
    }
}
