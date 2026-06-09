<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'department_id',
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

    public function department()
    {
        return $this->belongsTo(Department::class);
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
        if ($this->formSubmission) {
            return route('forms.submission.show', $this->formSubmission);
        }

        $route = match ($this->document_type) {
            'repair_request' => 'repair-requests.show',
            'spare_parts_requisition' => 'spare-parts.requisition.show',
            default => null,
        };

        return $route && \Illuminate\Support\Facades\Route::has($route)
            ? route($route, $this)
            : null;
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
        return $query->where('approval_instances.status', 'pending')
            ->where(function ($rq) use ($userId) {
                $rq->where('approval_instances.requester_user_id', '!=', $userId)
                    ->orWhereHas('workflow', fn ($w) => $w->where('allow_requester_as_approver', true));
            })
            ->whereHas('steps', function ($q) use ($userId, $roleNames, $positionId) {
                $q->where('approval_instance_steps.action', 'pending')
                    ->whereRaw('approval_instance_steps.step_no = approval_instances.current_step_no')
                    ->where(function ($sq) use ($userId, $roleNames, $positionId) {
                        // Single-source steps (approver_rules is null)
                        $sq->where(function ($single) use ($userId, $roleNames, $positionId) {
                            $single->whereNull('approver_rules')
                                ->where(function ($types) use ($userId, $roleNames, $positionId) {
                                    $types->where(fn ($u) => $u->where('approver_type', 'user')->where('approver_ref', (string) $userId));
                                    if (! empty($roleNames)) {
                                        $types->orWhere(fn ($r) => $r->where('approver_type', 'role')->whereIn('approver_ref', $roleNames));
                                    }
                                    if ($positionId) {
                                        $types->orWhere(fn ($p) => $p->where('approver_type', 'position')->where('approver_ref', (string) $positionId));
                                    }
                                });
                        });
                        // Multi-source steps (approver_rules JSON) — use LIKE for cross-DB compat
                        $sq->orWhere(function ($multi) use ($userId, $roleNames, $positionId) {
                            $multi->whereNotNull('approver_rules')
                                ->where(function ($json) use ($userId, $roleNames, $positionId) {
                                    $json->where('approver_rules', 'like', '%"type":"user","ref":"' . $userId . '"}%');
                                    foreach ($roleNames as $role) {
                                        $json->orWhere('approver_rules', 'like', '%"type":"role","ref":"' . addslashes($role) . '"}%');
                                    }
                                    if ($positionId) {
                                        $json->orWhere('approver_rules', 'like', '%"type":"position","ref":"' . $positionId . '"}%');
                                    }
                                });
                        });
                    });
            });
    }
}
