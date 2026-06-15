<?php

namespace App\Models;

use App\Support\WorkdayCalculator;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['date', 'name', 'is_active'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => WorkdayCalculator::forgetCache());
        static::deleted(fn () => WorkdayCalculator::forgetCache());
    }
}
