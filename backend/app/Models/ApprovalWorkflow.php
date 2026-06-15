<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    use HasAutoCode;
    use HasFactory;

    protected $fillable = [
        'auto_code',
        'name',
        'document_type',
        'description',
        'is_active',
        'allow_requester_as_approver',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allow_requester_as_approver' => 'boolean',
        ];
    }

    public function stages()
    {
        return $this->hasMany(ApprovalWorkflowStage::class, 'workflow_id')->orderBy('step_no');
    }

    protected function autoCodePrefix(): string
    {
        return 'WF';
    }
}
