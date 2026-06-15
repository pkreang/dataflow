<?php

namespace Tests\Feature\Settings;

use App\Models\RunningNumberConfig;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class RunningNumbersCrudTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_create_running_number(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->post(route('settings.running-numbers.store'), [
            'document_type' => 'repair_request',
            'prefix' => 'RR',
            'digit_count' => 5,
            'reset_mode' => 'yearly',
            'include_year' => 1,
            'is_active' => 1,
        ])->assertRedirect(route('settings.running-numbers.index'));

        $config = RunningNumberConfig::firstWhere('document_type', 'repair_request');
        $this->assertNotNull($config);
        $this->assertSame('RR', $config->prefix);
        $this->assertSame(5, $config->digit_count);
    }

    public function test_super_admin_can_update_running_number(): void
    {
        $admin = $this->makeSuperAdmin();
        $config = RunningNumberConfig::create([
            'document_type' => 'memo',
            'prefix' => 'MEM',
            'digit_count' => 4,
            'reset_mode' => 'none',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->put(route('settings.running-numbers.update', $config), [
            'document_type' => 'memo',
            'prefix' => 'M',
            'digit_count' => 6,
            'reset_mode' => 'monthly',
            'is_active' => 1,
        ])->assertRedirect(route('settings.running-numbers.index'));

        $config->refresh();
        $this->assertSame('M', $config->prefix);
        $this->assertSame(6, $config->digit_count);
        $this->assertSame('monthly', $config->reset_mode);
    }

    public function test_super_admin_can_reset_running_number(): void
    {
        $admin = $this->makeSuperAdmin();
        $config = RunningNumberConfig::create([
            'document_type' => 'memo',
            'prefix' => 'MEM',
            'digit_count' => 4,
            'reset_mode' => 'none',
            'last_number' => 99,
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->post(route('settings.running-numbers.reset', $config))
            ->assertRedirect(route('settings.running-numbers.index'));

        $this->assertSame(0, $config->fresh()->last_number);
    }

    public function test_super_admin_can_destroy_running_number(): void
    {
        $admin = $this->makeSuperAdmin();
        $config = RunningNumberConfig::create([
            'document_type' => 'tmp',
            'prefix' => 'T',
            'digit_count' => 3,
            'reset_mode' => 'none',
            'is_active' => true,
        ]);

        $this->actingAsWebSession($admin)->delete(route('settings.running-numbers.destroy', $config))
            ->assertRedirect(route('settings.running-numbers.index'));

        $this->assertNull($config->fresh());
    }

    public function test_validation_rejects_invalid_reset_mode(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAsWebSession($admin)->post(route('settings.running-numbers.store'), [
            'document_type' => 'x',
            'prefix' => 'X',
            'digit_count' => 3,
            'reset_mode' => 'daily',
        ])->assertSessionHasErrors('reset_mode');
    }
}
