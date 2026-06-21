<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\IncomingWebhook;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeForm(): DocumentForm
    {
        $form = DocumentForm::create([
            'form_key' => 'test_inbound',
            'name' => 'Test Inbound Form',
            'document_type' => 'repair_request',
            'is_active' => true,
            'layout_columns' => 1,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'title', 'label' => 'Title',
            'field_type' => 'text', 'is_required' => false, 'sort_order' => 1,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'location', 'label' => 'Location',
            'field_type' => 'text', 'is_required' => false, 'sort_order' => 2,
        ]);

        return $form;
    }

    public function test_valid_token_creates_draft_submission(): void
    {
        $form = $this->makeForm();
        $hook = IncomingWebhook::create([
            'name' => 'test', 'slug' => 'abc-test', 'token' => 'secret-token-1234567890',
            'document_form_id' => $form->id, 'is_active' => true,
        ]);

        $response = $this->withHeaders(['X-Webhook-Token' => $hook->token])
            ->postJson('/api/inbound/abc-test', [
                'title' => 'Pump failure',
                'location' => 'Line 3',
                'unknown_field' => 'ignored',
            ]);

        $response->assertStatus(201)->assertJson([
            'ok' => true,
            'received_keys' => ['title', 'location'],
            'ignored_keys' => ['unknown_field'],
        ]);

        $this->assertDatabaseHas('document_form_submissions', [
            'form_id' => $form->id,
            'user_id' => null,
            'status' => 'draft',
        ]);

        $submission = DocumentFormSubmission::first();
        $this->assertSame(['title' => 'Pump failure', 'location' => 'Line 3'], $submission->payload);

        $hook->refresh();
        $this->assertSame(1, $hook->received_count);
        $this->assertNotNull($hook->last_received_at);
    }

    public function test_wrong_token_returns_401(): void
    {
        $form = $this->makeForm();
        IncomingWebhook::create([
            'name' => 'test', 'slug' => 'abc-test', 'token' => 'real-token-12345678',
            'document_form_id' => $form->id, 'is_active' => true,
        ]);

        $this->withHeaders(['X-Webhook-Token' => 'wrong'])
            ->postJson('/api/inbound/abc-test', ['title' => 'x'])
            ->assertStatus(401);

        $this->assertDatabaseCount('document_form_submissions', 0);
    }

    public function test_inactive_webhook_returns_404(): void
    {
        $form = $this->makeForm();
        IncomingWebhook::create([
            'name' => 'test', 'slug' => 'abc-test', 'token' => 'tok-1234567890',
            'document_form_id' => $form->id, 'is_active' => false,
        ]);

        $this->withHeaders(['X-Webhook-Token' => 'tok-1234567890'])
            ->postJson('/api/inbound/abc-test', ['title' => 'x'])
            ->assertStatus(404);
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->withHeaders(['X-Webhook-Token' => 'whatever'])
            ->postJson('/api/inbound/does-not-exist', ['title' => 'x'])
            ->assertStatus(404);
    }
}
