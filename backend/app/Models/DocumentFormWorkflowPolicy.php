<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentFormWorkflowPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'department_id',
        'use_amount_condition',
        'amount_field_key',
        'workflow_id',
    ];

    protected function casts(): array
    {
        return [
            'use_amount_condition' => 'boolean',
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

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    public function ranges()
    {
        return $this->hasMany(DocumentFormWorkflowRange::class, 'policy_id')->orderBy('sort_order');
    }
}
