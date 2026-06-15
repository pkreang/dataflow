<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;
    use HasAutoCode;

    protected $fillable = [
        'auto_code',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected function autoCodePrefix(): string
    {
        return 'DEPT';
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function workflowBindings()
    {
        return $this->hasMany(DepartmentWorkflowBinding::class);
    }
}
