<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'org_unit_id',
        'requester_user_id',
        'document_type',
        'reference_no',
        'payload',
        'current_step_no',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'current_step_no' => 'integer',
        ];
    }

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function steps()
    {
        return $this->hasMany(ApprovalInstanceStep::class)->orderBy('step_no');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    /**
     * When this instance was started from /forms (generic eForm submission).
     */
    public function formSubmission()
    {
        return $this->hasOne(DocumentFormSubmission::class, 'approval_instance_id');
    }

    /**
     * The detail page where this document can be viewed and acted on. Dynamic
     * eForm submissions go to forms.submission.show; legacy CMMS instances to
     * their type-specific show route. Returns null when no detail route exists
     * (e.g. purchase_request, whose web routes are intentionally unregistered)
     * so callers can omit the link rather than error.
     */
    public function detailUrl(): ?string
    {
        // Every document type is an eForm now — its detail lives at the linked
        // submission. No linked submission ⇒ no detail page (caller omits link).
        if ($this->formSubmission) {
            return route('forms.submission.show', $this->formSubmission);
        }

        return null;
    }

    /**
     * "Pending approvals waiting for this user right now" — the canonical
     * predicate shared by /approvals/my, the sidebar badge, the form-list merge,
     * and the mobile home KPIs so every surface reports the same set/count.
     *
     * Includes requester-exclusion: a requester never sees their own document in
     * their approval queue unless the workflow explicitly allows it
     * (allow_requester_as_approver).
     *
     * @param  array<int, string>  $roleNames
     */
    public function scopePendingForApprover(Builder $query, int $userId, array $roleNames, ?int $positionId): Builder
    {
        // รวม id ของ principal ที่ user นี้เป็น active substitute ให้ — list ต้อง parity
        // กับ canUserActOnStep ซึ่ง honor substitution เฉพาะ single-source user step
        $userRefs = array_map('strval', array_merge(
            [$userId],
            UserSubstitution::activePrincipalsFor($userId, now())
        ));

        return $query->where('approval_instances.status', 'pending')
            ->where(function ($rq) use ($userId) {
                $rq->where('approval_instances.requester_user_id', '!=', $userId)
                    ->orWhereHas('workflow', fn ($w) => $w->where('allow_requester_as_approver', true));
            })
            ->whereHas('steps', function ($q) use ($userId, $userRefs, $roleNames, $positionId) {
                $q->where('approval_instance_steps.action', 'pending')
                    ->whereRaw('approval_instance_steps.step_no = approval_instances.current_step_no')
                    ->where(function ($sq) use ($userId, $userRefs, $roleNames, $positionId) {
                        // Single-source steps (approver_rules is null)
                        $sq->where(function ($single) use ($userRefs, $roleNames, $positionId) {
                            $single->whereNull('approver_rules')
                                ->where(function ($types) use ($userRefs, $roleNames, $positionId) {
                                    $types->where(fn ($u) => $u->where('approver_type', 'user')->whereIn('approver_ref', $userRefs));
                                    if (! empty($roleNames)) {
                                        $types->orWhere(fn ($r) => $r->where('approver_type', 'role')->whereIn('approver_ref', $roleNames));
                                    }
                                    if ($positionId) {
                                        $types->orWhere(fn ($p) => $p->where('approver_type', 'position')->where('approver_ref', (string) $positionId));
                                    }
                                });
                        });
                        // Multi-source steps: check primary columns (parity with canUserActOnStep) AND rules JSON
                        $sq->orWhere(function ($multi) use ($userRefs, $userId, $roleNames, $positionId) {
                            $multi->whereNotNull('approver_rules')
                                ->where(function ($any) use ($userRefs, $userId, $roleNames, $positionId) {
                                    // Primary approver columns (same logic as single-source branch)
                                    $any->where(fn ($u) => $u->where('approver_type', 'user')->whereIn('approver_ref', $userRefs));
                                    if (! empty($roleNames)) {
                                        $any->orWhere(fn ($r) => $r->where('approver_type', 'role')->whereIn('approver_ref', $roleNames));
                                    }
                                    if ($positionId) {
                                        $any->orWhere(fn ($p) => $p->where('approver_type', 'position')->where('approver_ref', (string) $positionId));
                                    }
                                    // AND-source rules JSON
                                    // MySQL JSON column adds spaces after ":" on read-back; SQLite stores verbatim.
                                    // Use two OR-LIKE per field to handle both formats.
                                    $any->orWhere(fn ($j) => $j
                                        ->where(fn ($t) => $t->where('approver_rules', 'like', '%"type":"user"%')->orWhere('approver_rules', 'like', '%"type": "user"%'))
                                        ->where(fn ($r) => $r->where('approver_rules', 'like', '%"ref":"'.$userId.'"%')->orWhere('approver_rules', 'like', '%"ref": "'.$userId.'"%')));
                                    foreach ($roleNames as $role) {
                                        $esc = addslashes($role);
                                        $any->orWhere(fn ($j) => $j
                                            ->where(fn ($t) => $t->where('approver_rules', 'like', '%"type":"role"%')->orWhere('approver_rules', 'like', '%"type": "role"%'))
                                            ->where(fn ($r) => $r->where('approver_rules', 'like', '%"ref":"'.$esc.'"%')->orWhere('approver_rules', 'like', '%"ref": "'.$esc.'"%')));
                                    }
                                    if ($positionId) {
                                        $any->orWhere(fn ($j) => $j
                                            ->where(fn ($t) => $t->where('approver_rules', 'like', '%"type":"position"%')->orWhere('approver_rules', 'like', '%"type": "position"%'))
                                            ->where(fn ($r) => $r->where('approver_rules', 'like', '%"ref":"'.$positionId.'"%')->orWhere('approver_rules', 'like', '%"ref": "'.$positionId.'"%')));
                                    }
                                });
                        });
                    });
            });
    }
}
