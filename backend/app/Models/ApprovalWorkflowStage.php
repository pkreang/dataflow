<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'step_no',
        'name',
        'approver_type',
        'approver_ref',
        'approver_rules',
        'min_approvals',
        'require_signature',
        'allow_requester_override',
        'escalation_after_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'step_no' => 'integer',
            'min_approvals' => 'integer',
            'require_signature' => 'boolean',
            'allow_requester_override' => 'boolean',
            'escalation_after_days' => 'integer',
            'is_active' => 'boolean',
            'approver_rules' => 'array',
        ];
    }

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }
}
