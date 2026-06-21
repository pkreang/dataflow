<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    use HasAutoCode;
    use HasFactory;

    protected $table = 'equipment';

    public const CRITICALITY_LEVELS = ['A', 'B', 'C'];

    protected $fillable = [
        'auto_code',
        'name',
        'code',
        'serial_number',
        'manufacturer',
        'model',
        'equipment_category_id',
        'equipment_location_id',
        'company_id',
        'branch_id',
        'status',
        'criticality',
        'installed_date',
        'purchase_date',
        'warranty_expiry',
        'runtime_hours',
        'specifications',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'specifications' => 'array',
            'installed_date' => 'date',
            'purchase_date' => 'date',
            'warranty_expiry' => 'date',
            'runtime_hours' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    public function location()
    {
        return $this->belongsTo(EquipmentLocation::class, 'equipment_location_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    protected function autoCodePrefix(): string
    {
        return 'EQ';
    }
}
