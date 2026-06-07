<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentFormSubmission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'form_id',
        'user_id',
        'department_id',
        'payload',
        'status',
        'approval_instance_id',
        'parent_submission_id',
        'reference_no',
        'fdata_row_id',
        'deleted_by',
        'assigned_editor_user_ids',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'assigned_editor_user_ids' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * True when the given user id is in the assigned-editors list.
     * Owner is NOT auto-included — callers compose owner OR assignee checks
     * explicitly so the two scopes stay distinguishable.
     */
    public function isAssignedEditor(?int $userId): bool
    {
        if (! $userId) {
            return false;
        }
        $ids = $this->assigned_editor_user_ids ?? [];
        return in_array($userId, array_map('intval', $ids), true);
    }

    /**
     * Capture the session user as `deleted_by` on soft-delete. Runs only when
     * the trait is performing a soft-delete (not forceDelete), so compliance
     * data is preserved even if the user closed the session mid-request.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $submission) {
            if ($submission->isForceDeleting()) {
                return;
            }
            if ($submission->deleted_by !== null) {
                return;
            }
            $userId = (int) (session('user.id') ?? 0);
            if ($userId > 0) {
                $submission->deleted_by = $userId;
                $submission->saveQuietly();
            }
        });
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function form()
    {
        return $this->belongsTo(DocumentForm::class, 'form_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function instance()
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }

    /**
     * Parent submission — set when this row is an evaluation/feedback for
     * another submission. NULL for normal first-class submissions.
     */
    public function originalSubmission()
    {
        return $this->belongsTo(self::class, 'parent_submission_id');
    }

    /**
     * Child submissions that reference this one as their parent.
     * For approved work items, this surfaces the requester's evaluation.
     */
    public function evaluations()
    {
        return $this->hasMany(self::class, 'parent_submission_id');
    }

    /**
     * Most recent activity log entry for this submission. Used on the list
     * page so admins can scan "who did what when" without opening each row.
     * Uses Laravel's latestOfMany() so a single query per page fetches one
     * row per submission (no N+1).
     */
    public function latestActivity()
    {
        return $this->hasOne(SubmissionActivityLog::class, 'submission_id')->latestOfMany('created_at');
    }

    /**
     * First-time-submitted activity (action='submitted'). Used as the canonical
     * "submit date" on list pages. Returns null for drafts never submitted.
     */
    public function submittedActivity()
    {
        return $this->hasOne(SubmissionActivityLog::class, 'submission_id')
            ->where('action', 'submitted')
            ->oldestOfMany('created_at');
    }

    /**
     * 'draft' | 'pending' | 'approved' | 'rejected' | 'submitted'
     * draft comes from the submission itself; post-submit statuses come from the
     * approval_instance so the UI tracks workflow outcome, not just submission state.
     */
    public function getEffectiveStatusAttribute(): string
    {
        if ($this->trashed()) {
            return 'cancelled';
        }
        if ($this->status === 'draft') {
            return 'draft';
        }

        return $this->instance?->status ?? 'submitted';
    }

    /**
     * First scalar value from payload (searchable fields first, then by sort_order)
     * used as a row-level "subject line" so users can identify submissions without
     * opening each one.
     */
    public function getPreviewAttribute(): ?string
    {
        $fields = $this->form?->fields;
        if (! $fields || $fields->isEmpty()) {
            return null;
        }

        $ordered = $fields
            ->sortBy('sort_order')
            ->sortByDesc(fn ($f) => (int) ($f->is_searchable ?? 0))
            ->values();

        foreach ($ordered as $field) {
            $val = $this->payload[$field->field_key] ?? null;
            if (is_scalar($val) && $val !== '' && $val !== null) {
                return (string) $val;
            }
        }

        return null;
    }

    /**
     * Compute the row's action plan (primary / secondary / menu) for a given viewer.
     *
     * @param  array{id:int,can_approve:bool,is_super_admin:bool}  $viewer
     * @return array{primary:?array,secondary:array,menu:array}
     */
    public function actionPlan(array $viewer): array
    {
        $isOwner = (int) $this->user_id === (int) $viewer['id'];
        $isAssignee = $this->isAssignedEditor((int) $viewer['id']);
        $canView = $isOwner || $isAssignee || ($viewer['is_related_approver'] ?? false) || $viewer['is_super_admin'];
        $canEditDraft = ($isOwner || $isAssignee) && $this->status === 'draft';
        $canDeleteDraft = $isOwner && $this->status === 'draft';
        $canDuplicate = $isOwner;
        $canPrint = $canView && $this->status !== 'draft';

        $status = $this->effective_status;

        $viewUrl = route('forms.submission.show', $this);
        $editUrl = route('forms.draft.edit', $this);
        $printUrl = route('forms.submission.print', $this);
        $duplicateUrl = route('forms.submission.duplicate', $this);
        $deleteUrl = route('forms.draft.destroy', $this);
        $returnToDraftUrl = route('forms.submission.return-to-draft', $this);
        $historyUrl = route('forms.submission.history', $this);
        $restoreUrl = route('forms.submission.restore', $this);

        $primary = null;
        $secondary = [];
        $menu = [];

        // Cancelled (soft-deleted) submissions: everything in the kebab menu —
        // no primary button. Row remains clickable (via $rowHref in view) to open view.
        if ($this->trashed()) {
            if ($canView) {
                $menu[] = [
                    'label' => __('common.view'),
                    'href' => $viewUrl,
                    'icon' => 'view',
                ];
            }
            if ($viewer['is_super_admin']) {
                $menu[] = [
                    'label' => __('common.action_restore'),
                    'action' => $restoreUrl,
                    'method' => 'POST',
                    'confirm' => __('common.confirm_restore'),
                ];
            }
            if ($canView) {
                $menu[] = [
                    'label' => __('common.action_history'),
                    'href' => $historyUrl,
                    'icon' => 'clock',
                ];
            }

            return compact('primary', 'secondary', 'menu');
        }

        // All actions live in the kebab menu — no primary button.
        // Row remains clickable via $rowHref in the view to open the submission.
        // Secondary stays empty for backward-compat with views still iterating the key.

        // Menu — ordered by usage frequency, view first, history pinned to the bottom.
        if ($canView) {
            $menu[] = [
                'label' => __('common.view'),
                'href' => $viewUrl,
                'icon' => 'view',
            ];
        }
        if ($canEditDraft) {
            $menu[] = [
                'label' => __('common.edit'),
                'href' => $editUrl,
                'icon' => 'edit',
            ];
        }
        if ($status === 'rejected' && $isOwner) {
            $menu[] = [
                'label' => __('common.action_return_to_draft'),
                'action' => $returnToDraftUrl,
                'method' => 'POST',
                'confirm' => __('common.confirm_return_to_draft'),
            ];
        }
        if ($canPrint) {
            $menu[] = [
                'label' => __('common.action_print'),
                'href' => $printUrl,
                'icon' => 'print',
            ];
        }
        if ($canDuplicate) {
            $menu[] = [
                'label' => __('common.action_duplicate'),
                'action' => $duplicateUrl,
                'method' => 'POST',
                'icon' => 'duplicate',
            ];
        }
        // Post-action evaluation — owner of an approved submission rates the work.
        // Hide for evaluation submissions themselves (avoid evaluate-the-evaluation)
        // and only when the form has evaluation_enabled=true (admin opt-in per form).
        if ($status === 'approved' && $isOwner && $this->parent_submission_id === null
            && (bool) ($this->form?->evaluation_enabled ?? false)
            && app(\App\Services\EvaluationFormResolver::class)->hasFormFor($this)) {
            $existingEval = $this->evaluations()->first();
            $menu[] = [
                'label' => $existingEval ? __('common.view_evaluation') : __('common.action_evaluate'),
                'href' => $existingEval
                    ? route('forms.submission.show', $existingEval)
                    : route('forms.submission.evaluate', $this),
                'icon' => 'view',
            ];
        }
        if ($canDeleteDraft) {
            $menu[] = [
                'label' => __('common.action_delete_draft'),
                'action' => $deleteUrl,
                'method' => 'DELETE',
                'icon' => 'delete',
                'class' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30',
                'confirm' => __('common.confirm_delete'),
            ];
        }
        // History last — low-frequency action, pinned to bottom for predictable location.
        if ($canView) {
            $menu[] = [
                'label' => __('common.action_history'),
                'href' => $historyUrl,
                'icon' => 'clock',
            ];
        }

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'menu' => $menu,
        ];
    }
}
