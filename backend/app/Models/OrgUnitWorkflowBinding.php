<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * binding org_unit → workflow ต่อ document_type (org-model consolidation).
  * org_unit ↔ workflow binding ต่อ document_type (resolveWorkflowId อ่านผ่าน resolveOrgUnitBindingWorkflowId).
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
