<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * binding org_unit → workflow ต่อ document_type (org-model consolidation).
 * คู่ขนานกับ DepartmentWorkflowBinding จนกว่า resolveWorkflowId จะย้ายมาอ่านตัวนี้ (Phase 2a).
 */
class OrgUnitWorkflowBinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'org_unit_id',
        'document_type',
        'workflow_id',
    ];

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }
}
