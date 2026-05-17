<?php

namespace Tests\Feature;

use App\Models\Webhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithSettingsAuth;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use InteractsWithSettingsAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_index_requires_super_admin(): void
    {
        $regular = $this->makeRegularUser();
        $this->actingAsWebSession($regular)
            ->get(route('settings.webhooks.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_index(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAsWebSession($admin)
            ->get(route('settings.webhooks.index'))
            ->assertSuccessful()
            ->assertSee(__('common.integrations'));
    }

    public function test_create_form_renders_with_suggested_secret(): void
    {
        $admin = $this->makeSuperAdmin();
        $response = $this->actingAsWebSession($admin)->get(route('settings.webhooks.create'));
        $response->assertSuccessful();
        $response->assertSee('form.submitted');
        $response->assertSee('approval.completed');
    }

    public function test_store_creates_webhook_with_events(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)
            ->post(route('settings.webhooks.store'), [
                'name' => 'ERP Sync',
                'url' => 'https://erp.example.test/hook',
                'secret' => str_repeat('a', 32),
                'events' => ['form.submitted', 'approval.completed'],
                'is_active' => 1,
            ])
            ->assertRedirect(route('settings.webhooks.index'));

        $this->assertDatabaseHas('webhooks', [
            'name' => 'ERP Sync',
            'url' => 'https://erp.example.test/hook',
        ]);

        $webhook = Webhook::first();
        $this->assertSame(['form.submitted', 'approval.completed'], $webhook->events);
    }

    public function test_store_rejects_unknown_event(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)
            ->from(route('settings.webhooks.create'))
            ->post(route('settings.webhooks.store'), [
                'name' => 'Bad',
                'url' => 'https://example.test/hook',
                'events' => ['not_a_real_event'],
            ])
            ->assertSessionHasErrors('events.0');
    }

    public function test_test_send_pings_endpoint_and_persists_status(): void
    {
        Http::fake([
            'https://erp.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $admin = $this->makeSuperAdmin();
        $webhook = Webhook::create([
            'name' => 'ERP',
            'url' => 'https://erp.example.test/hook',
            'secret' => str_repeat('s', 32),
            'events' => ['form.submitted'],
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAsWebSession($admin)
            ->post(route('settings.webhooks.test', $webhook));

        $response->assertSuccessful();
        $response->assertJson(['ok' => true, 'status' => 200]);

        $webhook->refresh();
        $this->assertSame(200, $webhook->last_response_status);
        $this->assertNotNull($webhook->last_triggered_at);
    }

    public function test_test_send_handles_failed_endpoint(): void
    {
        Http::fake([
            'https://broken.example.test/*' => Http::response('boom', 500),
        ]);

        $admin = $this->makeSuperAdmin();
        $webhook = Webhook::create([
            'name' => 'Broken',
            'url' => 'https://broken.example.test/hook',
            'secret' => str_repeat('s', 32),
            'events' => [],
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAsWebSession($admin)
            ->post(route('settings.webhooks.test', $webhook));

        $response->assertSuccessful();
        $response->assertJson(['ok' => false, 'status' => 500]);
    }

    public function test_store_persists_field_allowlists(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)
            ->post(route('settings.webhooks.store'), [
                'name' => 'ERP With Allowlist',
                'url' => 'https://erp.example.test/hook',
                'secret' => str_repeat('a', 32),
                'events' => ['form.submitted'],
                'field_allowlists' => [
                    'nteq_maintenance' => ['priority', 'problem_type', 'description'],
                    'school_eform_abc' => ['reference_no', 'applicant_name'],
                ],
                'is_active' => 1,
            ])
            ->assertRedirect(route('settings.webhooks.index'));

        $webhook = Webhook::first();
        $this->assertSame([
            'nteq_maintenance' => ['priority', 'problem_type', 'description'],
            'school_eform_abc' => ['reference_no', 'applicant_name'],
        ], $webhook->field_allowlists);
    }

    public function test_store_normalizes_empty_allowlists_to_null(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAsWebSession($admin)
            ->post(route('settings.webhooks.store'), [
                'name' => 'Empty Allowlist',
                'url' => 'https://erp.example.test/hook',
                'secret' => str_repeat('a', 32),
                'events' => ['form.submitted'],
                'field_allowlists' => [
                    'nteq_maintenance' => [],  // empty array → drop
                ],
                'is_active' => 1,
            ])
            ->assertRedirect(route('settings.webhooks.index'));

        $webhook = Webhook::first();
        $this->assertNull($webhook->field_allowlists);
    }

    public function test_update_clears_field_allowlists(): void
    {
        $admin = $this->makeSuperAdmin();
        $webhook = Webhook::create([
            'name' => 'With Allowlist',
            'url' => 'https://erp.example.test/hook',
            'secret' => str_repeat('a', 32),
            'events' => ['form.submitted'],
            'field_allowlists' => ['nteq_maintenance' => ['priority']],
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAsWebSession($admin)
            ->put(route('settings.webhooks.update', $webhook), [
                'name' => 'Cleared',
                'url' => 'https://erp.example.test/hook',
                'secret' => str_repeat('a', 32),
                'events' => ['form.submitted'],
                // no field_allowlists key sent → cleared
                'is_active' => 1,
            ])
            ->assertRedirect(route('settings.webhooks.edit', $webhook));

        $webhook->refresh();
        $this->assertNull($webhook->field_allowlists);
    }

    public function test_destroy_removes_webhook(): void
    {
        $admin = $this->makeSuperAdmin();
        $webhook = Webhook::create([
            'name' => 'Tmp',
            'url' => 'https://x.example.test/hook',
            'secret' => str_repeat('s', 32),
            'events' => [],
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAsWebSession($admin)
            ->delete(route('settings.webhooks.destroy', $webhook))
            ->assertRedirect(route('settings.webhooks.index'));

        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    }
}
