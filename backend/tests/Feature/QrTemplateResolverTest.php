<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use App\Support\QrTemplateResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrTemplateResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_ref_no_token(): void
    {
        $submission = $this->makeSubmission(['ref' => 'MR-2026-0042']);
        $this->assertSame(
            'verify/MR-2026-0042',
            QrTemplateResolver::resolve('verify/{ref_no}', $submission)
        );
    }

    public function test_resolves_url_token_to_absolute_route(): void
    {
        $submission = $this->makeSubmission();
        $resolved = QrTemplateResolver::resolve('{url}', $submission);
        $this->assertSame(route('forms.submission.show', $submission), $resolved);
        // sanity: includes scheme + path
        $this->assertStringStartsWith('http', $resolved);
    }

    public function test_resolves_field_token_from_payload(): void
    {
        $submission = $this->makeSubmission([], ['equipment_id' => 'EQ-007', 'qty' => 3]);
        $this->assertSame(
            'EQ-007 / 3',
            QrTemplateResolver::resolve('{field:equipment_id} / {field:qty}', $submission)
        );
    }

    public function test_unknown_tokens_left_unchanged(): void
    {
        $submission = $this->makeSubmission(['ref' => 'X']);
        // {made_up} is not a known token; survive untouched
        $this->assertSame(
            'X-{made_up}-end',
            QrTemplateResolver::resolve('{ref_no}-{made_up}-end', $submission)
        );
    }

    public function test_array_field_value_joins_with_comma(): void
    {
        // multi_select stores arrays — the resolver must serialise them safely
        $submission = $this->makeSubmission([], ['hazards' => ['fire', 'chemical']]);
        $this->assertSame(
            'fire, chemical',
            QrTemplateResolver::resolve('{field:hazards}', $submission)
        );
    }

    public function test_draft_without_reference_no_falls_back_to_id_marker(): void
    {
        $submission = $this->makeSubmission(['ref' => null]);
        $resolved = QrTemplateResolver::resolve('{ref_no}', $submission);
        $this->assertSame('#'.$submission->id, $resolved);
    }

    public function test_resolves_date_to_iso_format(): void
    {
        $submission = $this->makeSubmission();
        $resolved = QrTemplateResolver::resolve('day:{date}', $submission);
        // Pattern day:YYYY-MM-DD
        $this->assertMatchesRegularExpression('/^day:\d{4}-\d{2}-\d{2}$/', $resolved);
    }

    public function test_empty_template_returns_empty_string(): void
    {
        $submission = $this->makeSubmission();
        $this->assertSame('', QrTemplateResolver::resolve('', $submission));
    }

    /**
     * @param  array{ref?:string|null}  $opts
     * @param  array<string, mixed>  $payload
     */
    private function makeSubmission(array $opts = [], array $payload = []): DocumentFormSubmission
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);

        $form = DocumentForm::firstOrCreate(['form_key' => 'qr_test_form'], [
            'name' => 'QR Test Form',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        $user = User::create([
            'first_name' => 'QR', 'last_name' => 'Test',
            'email' => 'qr_test_'.uniqid().'@example.test',
            'password' => 'password', 'is_active' => true, 'is_super_admin' => false,
        ]);

        return DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'reference_no' => array_key_exists('ref', $opts) ? $opts['ref'] : 'REF-1',
            'payload' => $payload,
            'status' => 'submitted',
        ]);
    }
}
