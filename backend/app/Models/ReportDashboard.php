<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportDashboard extends Model
{
    use HasAutoCode;
    use HasFactory;

    protected $fillable = [
        'auto_code',
        'name',
        'description',
        'layout_columns',
        'visibility',
        'required_permission',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'layout_columns' => 'integer',
        ];
    }

    public function widgets()
    {
        return $this->hasMany(ReportDashboardWidget::class, 'dashboard_id')->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function autoCodePrefix(): string
    {
        return 'DASH';
    }
}
