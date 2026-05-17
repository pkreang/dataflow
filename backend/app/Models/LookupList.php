<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class LookupList extends Model
{
    use HasAutoCode;

    protected $fillable = [
        'auto_code',
        'key',
        'label_en',
        'label_th',
        'description',
        'is_system',
        'required_permission',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(LookupListItem::class, 'list_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function booted(): void
    {
        $clear = fn () => Cache::forget('lookup_registry_sources');
        static::saved($clear);
        static::deleted($clear);
    }

    protected function autoCodePrefix(): string
    {
        return 'LKLIST';
    }
}
