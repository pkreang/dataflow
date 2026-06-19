<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentFormField;
use App\Models\DocumentFormSubmission;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_effective_status_returns_draft_when_submission_is_draft(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeForm();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'payload' => ['title' => 'x'],
            'status' => 'draft',
        ]);

        $this->assertSame('draft', $submission->effective_status);
    }

    public function test_effective_status_falls_back_to_submitted_when_no_instance(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeForm();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'payload' => ['title' => 'x'],
            'status' => 'submitted',
        ]);

        $this->assertSame('submitted', $submission->effective_status);
    }

    public function test_preview_returns_first_searchable_field_value(): void
    {
        $this->seedBase();
        $form = DocumentForm::create([
            'form_key' => 'prev_form',
            'name' => 'Preview Form',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'code', 'label' => 'Code',
            'field_type' => 'text', 'sort_order' => 1, 'is_searchable' => false,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'title', 'label' => 'Title',
            'field_type' => 'text', 'sort_order' => 2, 'is_searchable' => true,
        ]);
        $user = $this->makeUser();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'payload' => ['code' => 'C1', 'title' => 'ปั๊มน้ำเสียงดัง'],
            'status' => 'draft',
        ]);

        // title is searchable, so it wins even though code has lower sort_order
        $this->assertSame('ปั๊มน้ำเสียงดัง', $submission->fresh('form.fields')->preview);
    }

    public function test_action_plan_for_draft_shows_edit_primary_and_delete_menu(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeForm();
        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'payload' => [],
            'status' => 'draft',
        ]);

        $plan = $submission->actionPlan($this->viewerFor($user));

        // No primary button — all actions live in the kebab menu.
        $this->assertNull($plan['primary']);
        $menuLabels = array_column($plan['menu'], 'label');
        $this->assertContains(__('common.view'), $menuLabels);
        $this->assertContains(__('common.edit'), $menuLabels);
        $this->assertContains(__('common.action_duplicate'), $menuLabels);
        $this->assertContains(__('common.action_delete_draft'), $menuLabels);
    }

    public function test_action_plan_for_non_owner_without_approval_permission_is_empty(): void
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();
        $other = $this->makeUser();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => [],
            'status' => 'submitted',
        ]);

        $plan = $submission->actionPlan($this->viewerFor($other));
        $this->assertNull($plan['primary']);
        $this->assertEmpty($plan['menu']);
    }

    public function test_print_route_returns_200_for_owner(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeForm();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'payload' => ['title' => 'x'],
            'status' => 'submitted',
            'reference_no' => 'R-1',
        ]);

        $response = $this->actingAsWebSession($user)->get(route('forms.submission.print', $submission));
        $response->assertOk();
        $response->assertSee('R-1');
    }

    public function test_print_route_forbidden_for_other_user(): void
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();
        $other = $this->makeUser();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => ['title' => 'x'],
            'status' => 'submitted',
            'reference_no' => 'R-2',
        ]);

        $response = $this->actingAsWebSession($other)->get(route('forms.submission.print', $submission));
        $response->assertForbidden();
    }

    public function test_print_route_404_for_draft(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeForm();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'payload' => [],
            'status' => 'draft',
        ]);

        $response = $this->actingAsWebSession($user)->get(route('forms.submission.print', $submission));
        $response->assertNotFound();
    }

    public function test_duplicate_creates_new_draft_with_nulled_metadata(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeForm();

        $original = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $user->id,
            'payload' => ['title' => 'orig'],
            'status' => 'submitted',
            'reference_no' => 'R-100',
            'approval_instance_id' => null,
        ]);

        $response = $this->actingAsWebSession($user)
            ->post(route('forms.submission.duplicate', $original));

        $copy = DocumentFormSubmission::where('user_id', $user->id)
            ->where('id', '!=', $original->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($copy);
        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->reference_no);
        $this->assertNull($copy->approval_instance_id);
        $this->assertSame(['title' => 'orig'], $copy->payload);
        $response->assertRedirect(route('forms.draft.edit', $copy));
    }

    public function test_duplicate_forbidden_for_non_owner(): void
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();
        $other = $this->makeUser();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => [],
            'status' => 'submitted',
        ]);

        $response = $this->actingAsWebSession($other)
            ->post(route('forms.submission.duplicate', $submission));
        $response->assertForbidden();
    }

    // ── Return-to-draft ─────────────────────────────────────

    public function test_owner_can_return_rejected_submission_to_draft(): void
    {
        [$submission, $owner, $instance] = $this->makeRejectedSubmission();

        $response = $this->actingAsWebSession($owner)
            ->post(route('forms.submission.return-to-draft', $submission));

        $response->assertRedirect(route('forms.draft.edit', $submission));

        $submission->refresh();
        $this->assertSame('draft', $submission->status);
        $this->assertSame($instance->id, $submission->approval_instance_id, 'instance link must be preserved for audit trail');
        $this->assertNotNull($submission->reference_no);
    }

    public function test_non_owner_cannot_return_to_draft(): void
    {
        [$submission] = $this->makeRejectedSubmission();
        $other = $this->makeUser();

        $response = $this->actingAsWebSession($other)
            ->post(route('forms.submission.return-to-draft', $submission));

        $response->assertForbidden();
        $this->assertSame('submitted', $submission->fresh()->status);
    }

    public function test_cannot_return_draft_submission(): void
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => ['title' => 'x'],
            'status' => 'draft',
        ]);

        $response = $this->actingAsWebSession($owner)
            ->post(route('forms.submission.return-to-draft', $submission));

        $response->assertForbidden();
    }

    public function test_cannot_return_pending_submission(): void
    {
        [$submission, $owner] = $this->makeSubmissionWithInstance('pending');

        $response = $this->actingAsWebSession($owner)
            ->post(route('forms.submission.return-to-draft', $submission));

        $response->assertForbidden();
        $this->assertSame('submitted', $submission->fresh()->status);
    }

    public function test_cannot_return_approved_submission(): void
    {
        [$submission, $owner] = $this->makeSubmissionWithInstance('approved');

        $response = $this->actingAsWebSession($owner)
            ->post(route('forms.submission.return-to-draft', $submission));

        $response->assertForbidden();
    }

    public function test_return_logs_activity(): void
    {
        [$submission, $owner, $instance] = $this->makeRejectedSubmission();

        $this->actingAsWebSession($owner)
            ->post(route('forms.submission.return-to-draft', $submission));

        $log = \App\Models\SubmissionActivityLog::where('submission_id', $submission->id)
            ->where('action', 'returned_to_draft')
            ->first();
        $this->assertNotNull($log, 'activity log entry must be recorded');
        $this->assertSame($instance->id, $log->meta['from_approval_instance_id'] ?? null);
    }

    public function test_action_plan_offers_return_button_for_rejected_owner(): void
    {
        [$submission, $owner] = $this->makeRejectedSubmission();

        $plan = $submission->actionPlan($this->viewerFor($owner));

        // No primary button — return-to-draft action lives in the kebab menu.
        $this->assertNull($plan['primary']);

        $returnAction = collect($plan['menu'])->firstWhere('label', __('common.action_return_to_draft'));
        $this->assertNotNull($returnAction, 'return-to-draft should appear in the kebab menu for rejected owner');
        $this->assertSame('POST', $returnAction['method']);
        $this->assertSame(
            route('forms.submission.return-to-draft', $submission),
            $returnAction['action']
        );
    }

    public function test_action_plan_hides_return_button_from_non_owner(): void
    {
        [$submission] = $this->makeRejectedSubmission();
        $other = $this->makeUser();

        $plan = $submission->actionPlan($this->viewerFor($other));

        // Non-owner without approval permissions: no primary action at all (no return-to-draft).
        // If a primary is shown (e.g., view link for an approver), it must never be the
        // return-to-draft POST — that's owner-only.
        $primaryMethod = $plan['primary']['method'] ?? null;
        $this->assertNotSame('POST', $primaryMethod);
    }

    // ── Soft delete / restore ───────────────────────────────

    public function test_destroy_draft_soft_deletes_main_and_records_cancelled(): void
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => ['title' => 'x'],
            'status' => 'draft',
        ]);

        $response = $this->actingAsWebSession($owner)
            ->delete(route('forms.draft.destroy', $submission));
        $response->assertRedirect();

        $trashed = DocumentFormSubmission::withTrashed()->find($submission->id);
        $this->assertNotNull($trashed);
        $this->assertTrue($trashed->trashed());
        $this->assertSame($owner->id, (int) $trashed->deleted_by);

        $log = \App\Models\SubmissionActivityLog::where('submission_id', $submission->id)
            ->where('action', 'cancelled')
            ->first();
        $this->assertNotNull($log);
    }

    public function test_global_scope_hides_trashed_submissions(): void
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();

        $sub = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => [],
            'status' => 'draft',
        ]);
        $sub->delete();

        $this->assertSame(0, DocumentFormSubmission::count(), 'default scope hides trashed');
        $this->assertSame(1, DocumentFormSubmission::withTrashed()->count());
    }

    public function test_effective_status_returns_cancelled_when_trashed(): void
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();

        $sub = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => [],
            'status' => 'draft',
        ]);
        $sub->delete();
        $sub = DocumentFormSubmission::withTrashed()->find($sub->id);

        $this->assertSame('cancelled', $sub->effective_status);
    }

    public function test_super_admin_can_restore_cancelled_submission(): void
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();

        $sub = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => ['title' => 'x'],
            'status' => 'draft',
        ]);
        $sub->delete();

        $admin = $this->makeSuperAdmin();
        $response = $this->actingAsWebSession($admin)
            ->post(route('forms.submission.restore', $sub->id));
        $response->assertRedirect();

        $restored = DocumentFormSubmission::find($sub->id);
        $this->assertNotNull($restored);
        $this->assertFalse($restored->trashed());
        $this->assertNull($restored->deleted_by);

        $log = \App\Models\SubmissionActivityLog::where('submission_id', $sub->id)
            ->where('action', 'restored')
            ->first();
        $this->assertNotNull($log);
    }

    public function test_non_super_admin_cannot_restore(): void
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();

        $sub = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => [],
            'status' => 'draft',
        ]);
        $sub->delete();

        $response = $this->actingAsWebSession($owner)
            ->post(route('forms.submission.restore', $sub->id));
        $response->assertForbidden();

        $this->assertTrue(DocumentFormSubmission::withTrashed()->find($sub->id)->trashed(), 'must still be trashed');
    }

    public function test_action_plan_shows_restore_primary_for_super_admin_on_trashed(): void
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();

        $sub = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => [],
            'status' => 'draft',
        ]);
        $sub->delete();
        $sub = DocumentFormSubmission::withTrashed()->find($sub->id);

        $admin = $this->makeSuperAdmin();
        $plan = $sub->actionPlan([
            'id' => $admin->id,
            'can_approve' => false,
            'is_super_admin' => true,
        ]);

        // Restore moved from primary button to the kebab menu (all actions consolidated).
        $this->assertNull($plan['primary']);
        $restoreAction = collect($plan['menu'])->firstWhere('label', __('common.action_restore'));
        $this->assertNotNull($restoreAction, 'restore action should appear in the menu for super-admin on a trashed submission');
        $this->assertSame('POST', $restoreAction['method']);
        $this->assertSame(route('forms.submission.restore', $sub), $restoreAction['action']);
    }

    // ── Submission history ──────────────────────────────────

    public function test_history_page_accessible_to_owner(): void
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => ['title' => 'x'],
            'status' => 'draft',
        ]);
        \App\Models\SubmissionActivityLog::record($submission->id, $owner->id, 'created');
        \App\Models\SubmissionActivityLog::record($submission->id, $owner->id, 'updated');

        $response = $this->actingAsWebSession($owner)
            ->get(route('forms.submission.history', $submission));

        $response->assertOk();
        $response->assertSee(__('common.activity_created'));
        $response->assertSee(__('common.activity_updated'));
    }

    public function test_history_page_forbidden_for_non_owner_without_approval_perm(): void
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();
        $stranger = $this->makeUser();

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => [],
            'status' => 'draft',
        ]);

        $response = $this->actingAsWebSession($stranger)
            ->get(route('forms.submission.history', $submission));
        $response->assertForbidden();
    }

    public function test_action_plan_includes_history_menu_for_viewer(): void
    {
        [$submission, $owner] = $this->makeRejectedSubmission();

        $plan = $submission->actionPlan($this->viewerFor($owner));

        $historyItem = collect($plan['menu'])->firstWhere('href', route('forms.submission.history', $submission));
        $this->assertNotNull($historyItem, 'history item must be in menu');
        $this->assertSame(__('common.action_history'), $historyItem['label']);
    }

    // ── Conditional required + group repeater ───────────────

    public function test_conditional_required_passes_when_rule_does_not_apply(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeConditionalRequiredForm();
        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id, 'user_id' => $user->id,
            'payload' => [], 'status' => 'draft',
        ]);

        $this->actingAsWebSession($user)
            ->put(route('forms.draft.update', $submission), [
                'fields' => ['status' => 'on_time'],
            ])
            ->assertRedirect(route('forms.draft.edit', $submission));

        $this->assertSame('on_time', $submission->fresh()->payload['status']);
    }

    public function test_conditional_required_fires_when_rule_true_and_field_empty(): void
    {
        $this->seedBase();
        [$form, $user] = $this->makeConditionalRequiredForm();
        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id, 'user_id' => $user->id,
            'payload' => [], 'status' => 'draft',
        ]);

        // status=late triggers reason as required; reason left empty → 422
        $this->actingAsWebSession($user)
            ->put(route('forms.draft.update', $submission), [
                'fields' => ['status' => 'late', 'reason' => ''],
            ])
            ->assertSessionHasErrors('fields.reason');
    }

    public function test_conditional_required_skipped_when_field_hidden_by_visibility(): void
    {
        $this->seedBase();
        $form = DocumentForm::create([
            'form_key' => 'cv_form',
            'name' => 'Conditional Visibility Form',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'gate', 'label' => 'Gate',
            'field_type' => 'text', 'sort_order' => 1,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'trigger', 'label' => 'Trigger',
            'field_type' => 'text', 'sort_order' => 2,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'note', 'label' => 'Note',
            'field_type' => 'text', 'sort_order' => 3,
            'is_required' => false,
            'visibility_rules' => [['field' => 'gate', 'operator' => 'equals', 'value' => 'show']],
            'required_rules' => [['field' => 'trigger', 'operator' => 'equals', 'value' => 'yes']],
        ]);
        $user = $this->makeUser();
        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id, 'user_id' => $user->id,
            'payload' => [], 'status' => 'draft',
        ]);

        // gate=hide → note hidden → required_rules ignored even though trigger=yes
        $this->actingAsWebSession($user)
            ->put(route('forms.draft.update', $submission), [
                'fields' => ['gate' => 'hide', 'trigger' => 'yes', 'note' => ''],
            ])
            ->assertRedirect(route('forms.draft.edit', $submission));
    }

    public function test_group_repeater_validates_min_rows_required(): void
    {
        $this->seedBase();
        $form = DocumentForm::create([
            'form_key' => 'gr_form',
            'name' => 'Group Form',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'beneficiaries', 'label' => 'Beneficiaries',
            'field_type' => 'group', 'sort_order' => 1, 'is_required' => false,
            'options' => [
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'col_span' => 0],
                    ['key' => 'pct',  'label' => 'Pct',  'type' => 'number', 'required' => false, 'col_span' => 0],
                ],
                'min_rows' => 2,
                'max_rows' => 5,
                'layout_columns' => 2,
                'label_singular' => 'Person',
            ],
        ]);
        $user = $this->makeUser();
        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id, 'user_id' => $user->id,
            'payload' => [], 'status' => 'draft',
        ]);

        // Fewer than min_rows → fails
        $this->actingAsWebSession($user)
            ->put(route('forms.draft.update', $submission), [
                'fields' => ['beneficiaries' => [['name' => 'A', 'pct' => 100]]],
            ])
            ->assertSessionHasErrors('fields.beneficiaries');

        // Inner required field missing → fails
        $this->actingAsWebSession($user)
            ->put(route('forms.draft.update', $submission), [
                'fields' => ['beneficiaries' => [
                    ['name' => 'A', 'pct' => 60],
                    ['name' => '',  'pct' => 40],
                ]],
            ])
            ->assertSessionHasErrors('fields.beneficiaries.1.name');

        // Two rows with name filled → passes
        $this->actingAsWebSession($user)
            ->put(route('forms.draft.update', $submission), [
                'fields' => ['beneficiaries' => [
                    ['name' => 'A', 'pct' => 60],
                    ['name' => 'B', 'pct' => 40],
                ]],
            ])
            ->assertRedirect(route('forms.draft.edit', $submission));
    }

    // ── filterPayloadForAssignee coverage ───────────────────

    public function test_assignee_write_filter_drops_non_granted_fields(): void
    {
        $this->seedBase();
        $owner = $this->makeUser();
        $assignee = $this->makeUser();

        $form = DocumentForm::create([
            'form_key' => 'assignee_filter_form',
            'name' => 'Assignee Filter Form',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'open_field', 'label' => 'Open',
            'field_type' => 'text', 'sort_order' => 1,
            'editable_by' => ['requester'],
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'shared_field', 'label' => 'Shared',
            'field_type' => 'text', 'sort_order' => 2,
            'editable_by' => ['requester', 'user:'.$assignee->id],
        ]);

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => ['open_field' => 'original_open', 'shared_field' => 'original_shared'],
            'status' => 'draft',
            'assigned_editor_user_ids' => [$assignee->id],
        ]);

        // Assignee tampers — sends both fields. Server filter should keep
        // only shared_field; open_field stays at the original value.
        $this->actingAsWebSession($assignee)
            ->put(route('forms.draft.update', $submission), [
                'fields' => [
                    'open_field' => 'tampered_open',
                    'shared_field' => 'updated_shared',
                ],
            ])
            ->assertRedirect(route('forms.draft.edit', $submission));

        $fresh = $submission->fresh();
        $this->assertSame('original_open', $fresh->payload['open_field']);
        $this->assertSame('updated_shared', $fresh->payload['shared_field']);
    }

    public function test_owner_writes_all_fields_unchanged_by_filter(): void
    {
        $this->seedBase();
        $owner = $this->makeUser();
        $assignee = $this->makeUser();

        $form = DocumentForm::create([
            'form_key' => 'owner_filter_form',
            'name' => 'Owner Filter Form',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'open_field', 'label' => 'Open',
            'field_type' => 'text', 'sort_order' => 1,
            'editable_by' => ['requester'],
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'shared_field', 'label' => 'Shared',
            'field_type' => 'text', 'sort_order' => 2,
            'editable_by' => ['requester', 'user:'.$assignee->id],
        ]);

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => ['open_field' => 'old_open', 'shared_field' => 'old_shared'],
            'status' => 'draft',
            'assigned_editor_user_ids' => [$assignee->id],
        ]);

        // Owner is exempt from the filter — both writes must land.
        $this->actingAsWebSession($owner)
            ->put(route('forms.draft.update', $submission), [
                'fields' => [
                    'open_field' => 'new_open',
                    'shared_field' => 'new_shared',
                ],
            ])
            ->assertRedirect(route('forms.draft.edit', $submission));

        $fresh = $submission->fresh();
        $this->assertSame('new_open', $fresh->payload['open_field']);
        $this->assertSame('new_shared', $fresh->payload['shared_field']);
    }

    public function test_assignee_cannot_write_field_with_only_role_tokens(): void
    {
        $this->seedBase();
        $owner = $this->makeUser();
        $assignee = $this->makeUser();

        $form = DocumentForm::create([
            'form_key' => 'role_only_filter_form',
            'name' => 'Role-Only Filter Form',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        // Step-only token, no requester, no user:{id} — assignee has no grant.
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'restricted_field', 'label' => 'Restricted',
            'field_type' => 'text', 'sort_order' => 1,
            'editable_by' => ['step_1'],
        ]);
        // A field the assignee can write — confirms the request didn't fail at
        // the route level (would mask the actual filter behavior we're testing).
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'allowed_field', 'label' => 'Allowed',
            'field_type' => 'text', 'sort_order' => 2,
            'editable_by' => ['requester', 'user:'.$assignee->id],
        ]);

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => ['restricted_field' => 'locked', 'allowed_field' => 'before'],
            'status' => 'draft',
            'assigned_editor_user_ids' => [$assignee->id],
        ]);

        $this->actingAsWebSession($assignee)
            ->put(route('forms.draft.update', $submission), [
                'fields' => [
                    'restricted_field' => 'TAMPERED',
                    'allowed_field' => 'after',
                ],
            ])
            ->assertRedirect(route('forms.draft.edit', $submission));

        $fresh = $submission->fresh();
        $this->assertSame('locked', $fresh->payload['restricted_field']);
        $this->assertSame('after', $fresh->payload['allowed_field']);
    }

    // ── Form builder roundtrip + combined-rule coverage ────

    public function test_form_builder_persists_group_options_roundtrip(): void
    {
        $this->seedBase();
        \App\Models\DocumentType::updateOrCreate(['code' => 'generic'], [
            'label_en' => 'Generic', 'label_th' => 'ทั่วไป', 'is_active' => true,
        ]);
        $admin = $this->makeSuperAdmin();

        $groupOpts = [
            'fields' => [
                ['key' => 'name', 'label_th' => 'ชื่อ', 'type' => 'text', 'required' => true, 'col_span' => 2],
                ['key' => 'pct',  'label_th' => 'สัดส่วน', 'type' => 'number', 'required' => false, 'col_span' => 1],
            ],
            'min_rows' => 1,
            'max_rows' => 5,
            'layout_columns' => 2,
            'label_singular' => 'ผู้รับผลประโยชน์',
        ];

        $this->actingAsWebSession($admin)
            ->post(route('settings.document-forms.store'), [
                'form_key' => 'group_rt_form',
                'name' => 'Group Roundtrip Form',
                'document_type' => 'generic',
                'layout_columns' => 1,
                'table_name' => 'group_rt_form',
                'fields' => [[
                    'field_key' => 'beneficiaries',
                    'label' => 'Beneficiaries',
                    'field_type' => 'group',
                    'group_options' => json_encode($groupOpts),
                ]],
            ])
            ->assertRedirect(route('settings.document-forms.index'));

        $field = DocumentForm::where('form_key', 'group_rt_form')->firstOrFail()->fields->first();
        $this->assertNotNull($field->options);
        $this->assertSame('beneficiaries', $field->field_key);
        $this->assertSame(1, $field->options['min_rows']);
        $this->assertSame(5, $field->options['max_rows']);
        $this->assertSame('ผู้รับผลประโยชน์', $field->options['label_singular']);
        $this->assertCount(2, $field->options['fields']);
        $this->assertSame('name', $field->options['fields'][0]['key']);
        $this->assertTrue($field->options['fields'][0]['required']);
    }

    public function test_form_builder_persists_qr_options_roundtrip(): void
    {
        $this->seedBase();
        \App\Models\DocumentType::updateOrCreate(['code' => 'generic'], [
            'label_en' => 'Generic', 'label_th' => 'ทั่วไป', 'is_active' => true,
        ]);
        $admin = $this->makeSuperAdmin();

        $qrOpts = [
            'template' => 'https://app.local/verify/{ref_no}',
            'size' => 192,
            'label_position' => 'above',
        ];

        $this->actingAsWebSession($admin)
            ->post(route('settings.document-forms.store'), [
                'form_key' => 'qr_rt_form',
                'name' => 'QR Roundtrip Form',
                'document_type' => 'generic',
                'layout_columns' => 1,
                'table_name' => 'qr_rt_form',
                'fields' => [[
                    'field_key' => 'verify_qr',
                    'label' => 'Verification QR',
                    'field_type' => 'qr_code',
                    'qr_options' => json_encode($qrOpts),
                ]],
            ])
            ->assertRedirect(route('settings.document-forms.index'));

        $field = DocumentForm::where('form_key', 'qr_rt_form')->firstOrFail()->fields->first();
        $this->assertNotNull($field->options);
        $this->assertSame($qrOpts['template'], $field->options['template']);
        $this->assertSame(192, $field->options['size']);
        $this->assertSame('above', $field->options['label_position']);
    }

    public function test_update_draft_records_field_level_diff(): void
    {
        $this->seedBase();
        $form = DocumentForm::create([
            'form_key' => 'diff_form', 'name' => 'Diff Form',
            'document_type' => 'generic', 'is_active' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'amount', 'label' => 'Amount',
            'field_type' => 'number', 'sort_order' => 1,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'note', 'label' => 'Note',
            'field_type' => 'text', 'sort_order' => 2,
        ]);
        $user = $this->makeUser();
        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id, 'user_id' => $user->id,
            'payload' => ['amount' => 5000, 'note' => 'pre'],
            'status' => 'draft',
        ]);

        $this->actingAsWebSession($user)
            ->put(route('forms.draft.update', $submission), [
                'fields' => ['amount' => 50000, 'note' => 'pre'],
            ])
            ->assertRedirect(route('forms.draft.edit', $submission));

        $log = \App\Models\SubmissionActivityLog::where('submission_id', $submission->id)
            ->where('action', 'updated')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($log, 'updated activity log row should exist');
        $changes = $log->meta['changed_fields'] ?? [];
        $this->assertArrayHasKey('amount', $changes);
        $this->assertArrayNotHasKey('note', $changes, 'unchanged fields should not be in diff');
        $this->assertSame('5000', (string) $changes['amount']['from']);
        $this->assertSame('50000', (string) $changes['amount']['to']);
    }

    public function test_required_rules_apply_when_visibility_passes(): void
    {
        // Companion to test_conditional_required_skipped_when_field_hidden_by_visibility
        // Same form layout; this case proves the POSITIVE half of the visibility
        // gate — when visibility holds AND required_rules are true, the field
        // really is required (422 fires).
        $this->seedBase();
        $form = DocumentForm::create([
            'form_key' => 'cv_form_pos',
            'name' => 'Conditional Visibility Form (positive)',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'gate', 'label' => 'Gate',
            'field_type' => 'text', 'sort_order' => 1,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'trigger', 'label' => 'Trigger',
            'field_type' => 'text', 'sort_order' => 2,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'note', 'label' => 'Note',
            'field_type' => 'text', 'sort_order' => 3,
            'is_required' => false,
            'visibility_rules' => [['field' => 'gate', 'operator' => 'equals', 'value' => 'show']],
            'required_rules' => [['field' => 'trigger', 'operator' => 'equals', 'value' => 'yes']],
        ]);
        $user = $this->makeUser();
        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id, 'user_id' => $user->id,
            'payload' => [], 'status' => 'draft',
        ]);

        // gate=show (visibility passes) AND trigger=yes → note required
        $this->actingAsWebSession($user)
            ->put(route('forms.draft.update', $submission), [
                'fields' => ['gate' => 'show', 'trigger' => 'yes', 'note' => ''],
            ])
            ->assertSessionHasErrors('fields.note');
    }

    private function makeConditionalRequiredForm(): array
    {
        $form = DocumentForm::create([
            'form_key' => 'cr_form',
            'name' => 'Conditional Required Form',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'status', 'label' => 'Status',
            'field_type' => 'text', 'sort_order' => 1, 'is_required' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'reason', 'label' => 'Reason',
            'field_type' => 'text', 'sort_order' => 2,
            'is_required' => false,
            'required_rules' => [
                ['field' => 'status', 'operator' => 'equals', 'value' => 'late'],
            ],
        ]);
        $user = $this->makeUser();

        return [$form->fresh('fields'), $user];
    }

    // ── Helpers ─────────────────────────────────────────────

    private function seedBase(): void
    {
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    private function makeForm(): array
    {
        $form = DocumentForm::create([
            'form_key' => 'af_form',
            'name' => 'Actions Form',
            'document_type' => 'generic',
            'is_active' => true,
        ]);
        DocumentFormField::create([
            'form_id' => $form->id, 'field_key' => 'title', 'label' => 'Title',
            'field_type' => 'text', 'sort_order' => 1, 'is_searchable' => true,
        ]);
        $user = $this->makeUser();

        return [$form->fresh('fields'), $user];
    }

    private function viewerFor(User $user): array
    {
        return [
            'id' => $user->id,
            'can_approve' => $user->getAllPermissions()->contains('name', 'approval.approve'),
            'is_super_admin' => (bool) $user->is_super_admin,
        ];
    }

    private function makeUser(): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'first_name' => 'Test',
            'last_name' => "Actions{$counter}",
            'email' => "actions{$counter}@example.test",
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => false,
        ]);
    }

    private function makeSuperAdmin(): User
    {
        static $counter = 0;
        $counter++;

        return User::create([
            'first_name' => 'Super',
            'last_name' => "Admin{$counter}",
            'email' => "super{$counter}@example.test",
            'password' => 'password',
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    /**
     * Build (submission, owner, instance) with instance.status = rejected and
     * submission.status = 'submitted' — the state that `returnToDraft` is
     * designed to unlock.
     */
    private function makeRejectedSubmission(): array
    {
        return $this->makeSubmissionWithInstance('rejected');
    }

    /**
     * Build a submission linked to an approval instance with the requested
     * status (pending / approved / rejected). Submission itself stays
     * `status = 'submitted'` — effective_status is derived from the instance.
     */
    private function makeSubmissionWithInstance(string $instanceStatus): array
    {
        $this->seedBase();
        [$form, $owner] = $this->makeForm();

        $workflow = \App\Models\ApprovalWorkflow::create([
            'document_type' => $form->document_type,
            'name' => 'Minimal workflow',
            'is_active' => true,
        ]);

        $instance = \App\Models\ApprovalInstance::create([
            'workflow_id' => $workflow->id,
            'requester_user_id' => $owner->id,
            'document_type' => $form->document_type,
            'reference_no' => 'REF-'.uniqid(),
            'payload' => [],
            'current_step_no' => 1,
            'status' => $instanceStatus,
        ]);

        $submission = DocumentFormSubmission::create([
            'form_id' => $form->id,
            'user_id' => $owner->id,
            'payload' => ['title' => 'original'],
            'status' => 'submitted',
            'approval_instance_id' => $instance->id,
            'reference_no' => $instance->reference_no,
        ]);

        return [$submission, $owner, $instance];
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
