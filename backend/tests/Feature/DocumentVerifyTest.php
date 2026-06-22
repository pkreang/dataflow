<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentVerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_token_auto_generated_on_create(): void
    {
        $sub = $this->makeSubmission();

        $this->assertNotEmpty($sub->verify_token);
        $this->assertSame(12, strlen($sub->verify_token));
    }

    public function test_public_verify_page_accessible_without_auth(): void
    {
        $sub = $this->makeSubmission(['reference_no' => 'PO2026-0009']);

        $this->get('/verify/'.$sub->verify_token)
            ->assertOk()
            ->assertSee('PO2026-0009')
            ->assertSee('เอกสารนี้อยู่ในระบบจริง')
            ->assertSee('ใบสั่งซื้อ');
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->get('/verify/doesnotexist123')->assertNotFound();
    }

    private function makeSubmission(array $attrs = []): DocumentFormSubmission
    {
        $form = DocumentForm::create([
            'form_key' => 'verify_t_'.uniqid(),
            'name' => 'ใบสั่งซื้อ',
            'document_type' => 'purchase_order',
            'is_active' => true,
            'layout_columns' => 1,
        ]);

        return DocumentFormSubmission::create(array_merge([
            'form_id' => $form->id,
            'status' => 'submitted',
            'payload' => [],
            'reference_no' => 'PO-1',
        ], $attrs));
    }
}
