<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EquipmentCategory extends Model
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
        return 'EQCAT';
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function equipment()
    {
        return $this->hasMany(Equipment::class);
    }
}
