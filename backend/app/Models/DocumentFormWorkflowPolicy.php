<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Position;

class DocumentFormWorkflowPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'department_id',
        'org_unit_id',
        'position_id',
        'use_amount_condition',
        'amount_field_key',
        'field_conditions',
        'workflow_id',
    ];

    protected function casts(): array
    {
        return [
            'use_amount_condition' => 'boolean',
            'field_conditions'     => 'array',
        ];
    }

    public function form()
    {
        return $this->belongsTo(DocumentForm::class, 'form_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function ranges()
    {
        return $this->hasMany(DocumentFormWorkflowRange::class, 'policy_id')->orderBy('sort_order');
    }
}
