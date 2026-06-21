<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalInstanceStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_instance_id',
        'step_no',
        'stage_name',
        'approver_type',
        'approver_ref',
        'approver_rules',
        'min_approvals',
        'require_signature',
        'escalation_after_days',
        'escalation_notified_at',
        'approved_by',
        'acted_by_user_id',
        'action',
        'comment',
        'signature_image',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'step_no' => 'integer',
            'min_approvals' => 'integer',
            'require_signature' => 'boolean',
            'escalation_after_days' => 'integer',
            'escalation_notified_at' => 'datetime',
            'approved_by' => 'array',
            'acted_at' => 'datetime',
            'approver_rules' => 'array',
        ];
    }

    public function approvalInstance()
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }

    public function satisfiedSourcesCount(): int
    {
        $approvedBy = $this->approved_by ?? [];
        if (empty($this->approver_rules)) {
            return count($approvedBy);
        }
        $allRules = array_merge(
            [['type' => $this->approver_type, 'ref' => $this->approver_ref]],
            $this->approver_rules ?? []
        );
        $satisfied = 0;
        foreach ($allRules as $rule) {
            foreach ($approvedBy as $ab) {
                $u = User::find($ab['user_id']);
                $matches = match ($rule['type'] ?? '') {
                    'user' => (int) ($rule['ref'] ?? 0) === (int) $ab['user_id'],
                    'position' => $u && $u->position_id && (string) $u->position_id === (string) ($rule['ref'] ?? ''),
                    'role' => $u && $u->hasRole($rule['ref'] ?? ''),
                    default => false,
                };
                if ($matches) {
                    $satisfied++;
                    break;
                }
            }
        }

        return $satisfied;
    }

    public function totalSourcesCount(): int
    {
        return empty($this->approver_rules) ? 1 : 1 + count($this->approver_rules);
    }
}
