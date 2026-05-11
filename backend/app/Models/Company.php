<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use App\Models\Concerns\HasStructuredAddress;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasAutoCode;
    use HasFactory;
    use HasStructuredAddress;

    protected $fillable = [
        'auto_code',
        'name',
        'code',
        'tax_id',
        'business_type',
        'logo',
        'address',
        'address_no',
        'address_building',
        'address_soi',
        'address_street',
        'address_subdistrict',
        'address_district',
        'address_province',
        'address_postal_code',
        'phone',
        'fax',
        'website',
        'description',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    protected function autoCodePrefix(): string
    {
        return 'COMP';
    }
}
