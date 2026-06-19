<?php

namespace Tests\Feature\Settings;

use App\Models\Position;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class PositionsCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_position(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->post(route('settings.positions.store'), [
            'name' => 'Technician',
            'code' => 'tech',
            'description' => 'Field tech',
            'is_active' => 1,
        ])->assertRedirect(route('settings.positions.index'));

        $position = Position::where('code', 'TECH')->firstOrFail();
        $this->assertSame('Technician', $position->name);
    }

    public function test_super_admin_can_update_position(): void
    {
        $admin = $this->makeSuperAdmin();
        $position = Position::create([
            'name' => 'Old',
            'code' => 'POS',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->put(route('settings.positions.update', $position), [
            'name' => 'New',
            'code' => 'POS',
            'description' => 'updated',
            'is_active' => 1,
        ])->assertRedirect(route('settings.positions.index'));

        $this->assertSame('New', $position->fresh()->name);
    }

    public function test_super_admin_can_destroy_unused_position(): void
    {
        $admin = $this->makeSuperAdmin();
        $position = Position::create([
            'name' => 'Removable',
            'code' => 'RM',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.positions.destroy', $position))
            ->assertRedirect(route('settings.positions.index'));

        $this->assertNull($position->fresh());
    }

    public function test_position_with_users_cannot_be_destroyed(): void
    {
        $admin = $this->makeSuperAdmin();
        $position = Position::create([
            'name' => 'Inuse',
            'code' => 'INU',
            'is_active' => true,
        ]);
        User::create([
            'first_name' => 'Holder',
            'last_name' => 'X',
            'email' => 'position-holder@example.test',
            'password' => 'pw',
            'is_active' => true,
            'position_id' => $position->id,
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.positions.destroy', $position))
            ->assertRedirect(route('settings.positions.index'));

        $this->assertNotNull($position->fresh());
    }

    public function test_validation_rejects_empty_name(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAsWebSession($admin)->post(route('settings.positions.store'), [
            'code' => 'X',
        ])->assertSessionHasErrors('name');
    }
}
