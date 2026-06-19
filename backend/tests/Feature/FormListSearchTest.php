<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use App\Services\FormSchemaService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormListSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_searchable_defaults_to_false_in_migration(): void
    {
        $this->seedBase();
        $form = DocumentForm::create([
            'form_key' => 'defaults_form',
            'name' => 'Defaults',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        // Create a field without explicitly setting is_searchable — default must be false.
        $field = DocumentFormField::create([
            'form_id' => $form->id,
            'field_key' => 'foo',
            'label' => 'Foo',
            'field_type' => 'text',
            'sort_order' => 1,
        ]);
        $this->assertFalse((bool) $field->fresh()->is_searchable);
    }

    public function test_search_via_dedicated_fdata_table_filters_rows(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeFormWithFields(useDedicatedTable: true);

        $this->createSubmission($form, $user, ['title' => 'A', 'priority' => 'ฉุกเฉิน'], 'URGENT-001');
        $this->createSubmission($form, $user, ['title' => 'B', 'priority' => 'ปกติ'], 'NORMAL-001');

        $response = $this->actingAsWebSession($user)->get('/forms/search_form/submissions?priority='.urlencode('ฉุกเฉิน'));
        $response->assertOk();
        $response->assertSee('URGENT-001');
        $response->assertDontSee('NORMAL-001');
    }

    public function test_search_via_json_payload_filters_rows(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeFormWithFields(useDedicatedTable: false);

        $this->createSubmission($form, $user, ['title' => 'A', 'priority' => 'ฉุกเฉิน'], 'URG-001');
        $this->createSubmission($form, $user, ['title' => 'B', 'priority' => 'ปกติ'], 'NRM-001');

        $response = $this->actingAsWebSession($user)->get('/forms/search_form/submissions?priority='.urlencode('ฉุกเฉิน'));
        $response->assertOk();
        $response->assertSee('URG-001');
        $response->assertDontSee('NRM-001');
    }

    public function test_text_filter_is_case_insensitive_like(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeFormWithFields(useDedicatedTable: false);

        $this->createSubmission($form, $user, ['title' => 'ปั๊มเสียงดัง', 'priority' => 'ปกติ'], 'REF-PUMP');
        $this->createSubmission($form, $user, ['title' => 'Motor noise', 'priority' => 'ปกติ'], 'REF-MOTOR');

        $response = $this->actingAsWebSession($user)->get('/forms/search_form/submissions?title=motor');
        $response->assertOk();
        $response->assertSee('REF-MOTOR');
        $response->assertDontSee('REF-PUMP');
    }

    public function test_empty_filters_return_all_user_submissions(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeFormWithFields(useDedicatedTable: true);

        $this->createSubmission($form, $user, ['title' => 'A', 'priority' => 'ปกติ'], 'REF-A');
        $this->createSubmission($form, $user, ['title' => 'B', 'priority' => 'ฉุกเฉิน'], 'REF-B');

        $response = $this->actingAsWebSession($user)->get('/forms/search_form/submissions');
        $response->assertOk();
        $response->assertSee('REF-A');
        $response->assertSee('REF-B');
    }

    public function test_user_never_sees_other_users_submissions_via_filter(): void
    {
        $this->seedBase();
        [$form, $userA] = $this->makeFormWithFields(useDedicatedTable: true);
        $userB = $this->makeUser();

        $this->createSubmission($form, $userA, ['title' => 'A', 'priority' => 'ฉุกเฉิน'], 'USR-A-SECRET');
        $this->createSubmission($form, $userB, ['title' => 'B', 'priority' => 'ฉุกเฉิน'], 'USR-B-SECRET');

        $response = $this->actingAsWebSession($userB)->get('/forms/search_form/submissions?priority='.urlencode('ฉุกเฉิน'));
        $response->assertOk();
        $response->assertSee('USR-B-SECRET');
        $response->assertDontSee('USR-A-SECRET');
    }

    public function test_reference_no_filter_works_regardless_of_searchable_fields(): void
    {
        $this->seedBase();
        // Create a form with NO searchable fields — reference_no should still work.
        $form = DocumentForm::create([
            'form_key' => 'search_form',
            'name' => 'No Searchable',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'title', 'label' => 'Title',
            'field_type' => 'text', 'sort_order' => 1, 'is_searchable' => false,
        ]);
        $user = $this->makeUser();

        $this->createSubmission($form, $user, ['title' => 'A'], 'MR-2026-001');
        $this->createSubmission($form, $user, ['title' => 'B'], 'PO-2026-999');

        $response = $this->actingAsWebSession($user)->get('/forms/search_form/submissions?reference_no=MR');
        $response->assertOk();
        $response->assertSee('MR-2026-001');
        $response->assertDontSee('PO-2026-999');
    }

    public function test_date_range_filter_via_json_payload(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeFormWithFields(useDedicatedTable: false, withDateField: true);

        $this->createSubmission($form, $user, ['title' => 'e', 'priority' => 'ปกติ', 'doc_date' => '2026-01-10'], 'DATE-EARLY');
        $this->createSubmission($form, $user, ['title' => 'm', 'priority' => 'ปกติ', 'doc_date' => '2026-02-15'], 'DATE-MID');
        $this->createSubmission($form, $user, ['title' => 'l', 'priority' => 'ปกติ', 'doc_date' => '2026-03-20'], 'DATE-LATE');

        $response = $this->actingAsWebSession($user)
            ->get('/forms/search_form/submissions?doc_date_from=2026-02-01&doc_date_to=2026-02-28');
        $response->assertOk();
        $response->assertSee('DATE-MID');
        $response->assertDontSee('DATE-EARLY');
        $response->assertDontSee('DATE-LATE');
    }

    // ── Helpers ─────────────────────────────────────────────

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    /**
     * Create a form with title (text, searchable), priority (select, searchable)
     * and optionally doc_date (date, searchable). useDedicatedTable=true creates
     * an fdata_* table via FormSchemaService.
     */
    private function makeFormWithFields(bool $useDedicatedTable, bool $withDateField = false): array
    {
        $form = DocumentForm::create([
            'form_key' => 'search_form',
            'name' => 'Searchable Form',
            'document_type' => 'generic',
            'is_active' => true,
            'submission_table' => $useDedicatedTable ? 'fdata_search_form' : null,
        ]);

        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'title', 'label' => 'Title',
            'field_type' => 'text', 'sort_order' => 1, 'is_searchable' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'priority', 'label' => 'Priority',
            'field_type' => 'select', 'sort_order' => 2, 'is_searchable' => true,
            'options' => ['ปกติ', 'ฉุกเฉิน'],
        ]);
        if ($withDateField) {
            DocumentFormField::create([
                'form_id' => $form->id, 'field_key' => 'doc_date', 'label' => 'Document Date',
                'field_type' => 'date', 'sort_order' => 3, 'is_searchable' => true,
            ]);
        }

        if ($useDedicatedTable) {
            app(FormSchemaService::class)->createTable($form->load('fields'));
        }

        $user = $this->makeUser();

        return [$form->fresh('fields'), $user];
    }

    private function createSubmission(DocumentForm $form, User $user, array $payload, ?string $referenceNo = null): DocumentFormSubmission
    {
        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'payload' => $payload,
            'status' => 'submitted',
            'reference_no' => $referenceNo,
        ]);

        if ($form->hasDedicatedTable()) {
            $rowId = app(FormSchemaService::class)->insertRow($form, $payload, [
                'user_id' => $user->id,
                'status' => 'submitted',
            ]);
            $submission->update(['fdata_row_id' => $rowId]);
        }

        return $submission;
    }

    private function makeUser(): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'first_name' => 'Test',
            'last_name' => "User{$counter}",
            'email' => "search{$counter}@example.test",
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
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
