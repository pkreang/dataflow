<?php

namespace Tests\Feature\Settings;

use App\Models\ApprovalWorkflow;
use App\Models\DocumentForm;
use App\Models\Setting;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        $form = DocumentForm::factory()->create(['document_type' => 'repair_request']);
        $workflow = ApprovalWorkflow::create(['name' => 'Test WF', 'document_type' => 'repair_request', 'is_active' => true]);

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'defaults' => [(string) $form->id => (string) $workflow->id],
            'allow_requester_override' => '1',
        ])->assertRedirect(route('settings.approval-routing'));

        $this->assertDatabaseHas('document_form_workflow_policies', [
            'form_id' => $form->id,
            'org_unit_id' => null,
            'position_id' => null,
            'workflow_id' => $workflow->id,
        ]);
        $this->assertTrue(Setting::getBool('approval.allow_requester_override'));
    }

    public function test_approval_routing_deletes_policy_when_workflow_id_empty(): void
    {
        $admin = $this->makeSuperAdmin();
        $form = DocumentForm::factory()->create(['document_type' => 'repair_request']);
        $workflow = ApprovalWorkflow::create(['name' => 'Test WF2', 'document_type' => 'repair_request', 'is_active' => true]);
        \App\Models\DocumentFormWorkflowPolicy::create([
            'form_id' => $form->id,
            'org_unit_id' => null,
            'position_id' => null,
            'workflow_id' => $workflow->id,
            'use_amount_condition' => false,
        ]);

        $this->actingAsWebSession($admin)->post(route('settings.approval-routing.save'), [
            'defaults' => [(string) $form->id => ''],
        ])->assertRedirect(route('settings.approval-routing'));

        $this->assertDatabaseMissing('document_form_workflow_policies', [
            'form_id' => $form->id,
            'org_unit_id' => null,
            'position_id' => null,
        ]);
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
        $this->assertSame('0', Setting::get('line_messaging.enabled'));
    }

    public function test_super_admin_can_save_line_messaging_credentials(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)->put(route('settings.notifications.update'), [
            'mail_mailer' => 'log',
            'line_messaging_channel_access_token' => 'real-token-abc-very-long-string',
            'line_messaging_channel_id' => '1234567890',
            'toggle' => [
                'line_messaging.enabled' => '1',
            ],
        ])->assertRedirect();

        $this->assertSame('real-token-abc-very-long-string', Setting::get('line_messaging.channel_access_token'));
        $this->assertSame('1234567890', Setting::get('line_messaging.channel_id'));
        $this->assertSame('1', Setting::get('line_messaging.enabled'));
    }

    public function test_test_line_send_pushes_message_to_admin_line_user_id(): void
    {
        $admin = $this->makeSuperAdmin();
        $admin->update(['line_user_id' => 'Uadmin1234567890']);
        Setting::set('line_messaging.channel_access_token', 'super-token-xyz');

        Http::fake(['api.line.me/v2/bot/message/push' => Http::response('', 200)]);

        $this->actingAsWebSession($admin)
            ->post(route('settings.notifications.test-line'))
            ->assertRedirect();

        Http::assertSent(function ($req) {
            return $req->url() === 'https://api.line.me/v2/bot/message/push'
                && $req->method() === 'POST'
                && $req->hasHeader('Authorization', 'Bearer super-token-xyz')
                && ($req->data()['to'] ?? null) === 'Uadmin1234567890';
        });
    }

    public function test_test_line_send_errors_when_token_missing(): void
    {
        $admin = $this->makeSuperAdmin();
        $admin->update(['line_user_id' => 'Uadmin1234567890']);
        Setting::set('line_messaging.channel_access_token', '');
        Http::fake();

        $this->actingAsWebSession($admin)
            ->post(route('settings.notifications.test-line'))
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_test_line_send_errors_when_admin_has_no_line_user_id(): void
    {
        $admin = $this->makeSuperAdmin();
        Setting::set('line_messaging.channel_access_token', 'super-token-xyz');
        Http::fake();

        $this->actingAsWebSession($admin)
            ->post(route('settings.notifications.test-line'))
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNothingSent();
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
                'branch_scoping.enabled' => '1',
            ],
        ])->assertRedirect(route('settings.branch-scoping'));

        $this->assertSame('1', Setting::get('branches.enabled'));
        $this->assertSame('1', Setting::get('branch_scoping.enabled'));
    }
}
