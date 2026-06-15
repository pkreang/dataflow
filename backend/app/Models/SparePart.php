<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparePart extends Model
{
    use HasAutoCode;
    use HasFactory;

    protected $fillable = [
        'auto_code',
        'code',
        'name',
        'description',
        'unit',
        'equipment_category_id',
        'min_stock',
        'current_stock',
        'unit_cost',
        'company_id',
        'branch_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_stock' => 'decimal:2',
            'current_stock' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SparePartTransaction::class);
    }

    public function requisitionItems(): HasMany
    {
        return $this->hasMany(SparePartRequisitionItem::class);
    }

    protected function autoCodePrefix(): string
    {
        return 'SP';
    }
}
