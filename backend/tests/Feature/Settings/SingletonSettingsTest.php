<?php

namespace Tests\Feature\Settings;

use App\Models\DocumentType;
use App\Models\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class SingletonSettingsTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_super_admin_can_save_password_policy(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->post(route('settings.password-policy.save'), [
            'password_min_length' => 8,
            'password_max_length' => 64,
            'password_expires_days' => 90,
            'password_prevent_reuse' => 5,
            'lockout_max_attempts' => 5,
            'lockout_duration_minutes' => 15,
            'password_require_uppercase' => 1,
            'password_require_number' => 1,
        ])->assertRedirect(route('settings.password-policy'));

        $this->assertSame('8', Setting::get('password_min_length'));
        $this->assertSame('5', Setting::get('password_prevent_reuse'));
        $this->assertSame('1', Setting::get('password_require_uppercase'));
        $this->assertSame('0', Setting::get('password_require_special'));
    }

    public function test_password_policy_validation_rejects_negative_lockout(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAsWebSession($admin)->post(route('settings.password-policy.save'), [
            'password_min_length' => 8,
            'password_max_length' => 64,
            'password_expires_days' => 90,
            'password_prevent_reuse' => 5,
            'lockout_max_attempts' => -1,
            'lockout_duration_minutes' => 15,
        ])->assertSessionHasErrors('lockout_max_attempts');
    }

    public function test_super_admin_can_save_branding_color(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->post(route('settings.branding.save'), [
            'login_background_color' => '#123456',
        ])->assertRedirect(route('settings.branding'));

        $this->assertSame('#123456', Setting::get('login_background_color'));
    }

    public function test_super_admin_can_save_auth_settings(): void
    {
        $admin = $this->makeSuperAdmin();
        $role = Role::firstWhere('name', 'admin') ?? Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $this->actingAsWebSession($admin)->post(route('settings.auth.save'), [
            'auth_default_role' => $role->name,
            'ldap_port' => 389,
            'ldap_user_filter' => '(uid=%s)',
            'ldap_user_create_validation' => 'disabled',
        ])->assertRedirect(route('settings.auth'));

        $this->assertSame($role->name, Setting::get('auth_default_role'));
        $this->assertSame('389', Setting::get('ldap_port'));
    }

    public function test_auth_settings_rejects_unknown_role(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAsWebSession($admin)->post(route('settings.auth.save'), [
            'auth_default_role' => 'no-such-role',
            'ldap_port' => 389,
            'ldap_user_create_validation' => 'disabled',
        ])->assertSessionHasErrors('auth_default_role');
    }

    public function test_super_admin_can_save_approval_routing(): void
    {
        $admin = $this->makeSuperAdmin();
        $type = DocumentType::create([
            'code' => 'sample_routing',
            'label_en' => 'Sample',
            'label_th' => 'ตัวอย่าง',
            'sort_order' => 0,
            'is_active' => true,
            'routing_mode' => 'hybrid',
        ]);

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'routing_modes' => [
                $type->code => 'department_scoped',
            ],
        ])->assertRedirect(route('settings.approval-routing'));

        $this->assertSame('department_scoped', $type->fresh()->routing_mode);
    }

    public function test_approval_routing_rejects_invalid_mode(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'routing_modes' => [
                'something' => 'bogus_mode',
            ],
        ])->assertSessionHasErrors('routing_modes.something');
    }

    public function test_super_admin_can_save_notifications(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->put(route('settings.notifications.update'), [
            'mail_mailer' => 'log',
            'toggle' => [
                'notifications.email_enabled' => '1',
                'notifications.approval_pending_email' => '1',
            ],
        ])->assertRedirect();

        $this->assertSame('1', Setting::get('notifications.email_enabled'));
        $this->assertSame('1', Setting::get('notifications.approval_pending_email'));
        $this->assertSame('0', Setting::get('notifications.line_enabled'));
    }

    public function test_notifications_rejects_invalid_mailer(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAsWebSession($admin)->put(route('settings.notifications.update'), [
            'mail_mailer' => 'pigeon',
        ])->assertSessionHasErrors('mail_mailer');
    }

    public function test_super_admin_can_save_branch_scoping(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->put(route('settings.branch-scoping.update'), [
            'toggle' => [
                'branches.enabled' => '1',
                'branch_scoping.equipment' => '1',
            ],
        ])->assertRedirect(route('settings.branch-scoping'));

        $this->assertSame('1', Setting::get('branches.enabled'));
        $this->assertSame('1', Setting::get('branch_scoping.equipment'));
        $this->assertSame('0', Setting::get('branch_scoping.spare_parts'));
    }
}
