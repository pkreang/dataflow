<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportDashboardWidget extends Model
{
    use HasFactory;

    protected $fillable = [
        'dashboard_id',
        'title',
        'widget_type',
        'data_source',
        'config',
        'col_span',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'col_span' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function dashboard()
    {
        return $this->belongsTo(ReportDashboard::class, 'dashboard_id');
    }
}
