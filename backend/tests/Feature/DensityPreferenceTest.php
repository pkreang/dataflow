<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DensityPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_density_preference_is_saved_to_database(): void
    {
        $this->seedBase();
        [$user, $position] = $this->makeUserWithPosition();

        $response = $this->actingAsWebSession($user)->put(route('profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'position_id' => $position->id,
            'density' => 'compact',
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertSame('compact', $user->density);
    }

    public function test_density_preference_can_switch_back_to_comfortable(): void
    {
        $this->seedBase();
        [$user, $position] = $this->makeUserWithPosition();
        $user->update(['density' => 'compact']);

        $this->actingAsWebSession($user)->put(route('profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'position_id' => $position->id,
            'density' => 'comfortable',
        ]);

        $this->assertSame('comfortable', $user->fresh()->density);
    }

    public function test_invalid_density_is_rejected(): void
    {
        $this->seedBase();
        [$user, $position] = $this->makeUserWithPosition();

        $response = $this->actingAsWebSession($user)->put(route('profile.update'), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'position_id' => $position->id,
            'density' => 'tiny',
        ]);

        $response->assertSessionHasErrors('density');
    }

    public function test_density_meta_tag_renders_when_compact(): void
    {
        $this->seedBase();
        [$user, $_position] = $this->makeUserWithPosition();
        $user->update(['density' => 'compact']);

        $response = $this->actingAsWebSession($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('<meta name="user-density" content="compact">', false);
    }

    public function test_density_meta_tag_absent_when_comfortable(): void
    {
        $this->seedBase();
        [$user, $_position] = $this->makeUserWithPosition();
        // default 'comfortable' from migration

        $response = $this->actingAsWebSession($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertDontSee('name="user-density"', false);
    }

    // ── Helpers (mirror ProfileExtendedTest) ──────────────────

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
            'first_name' => 'Density',
            'last_name' => 'Tester',
            'email' => 'density-'.uniqid().'@example.test',
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
                'can_change_password' => true,
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }
}
