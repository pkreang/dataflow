<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class DocumentType extends Model
{
    use HasAutoCode;

    protected $fillable = [
        'auto_code',
        'code',
        'label_en',
        'label_th',
        'description',
        'icon',
        'is_active',
        'routing_mode',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * All active document types, cached for dropdown use.
     */
    public static function allActive(): Collection
    {
        return Cache::remember('document_types_active', 3600, function () {
            return static::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get();
        });
    }

    /**
     * Localized label based on current app locale.
     */
    public function label(): string
    {
        return app()->getLocale() === 'th' ? $this->label_th : $this->label_en;
    }

    /**
     * Look up an active document type by code (cached via allActive()).
     */
    public static function resolveByCode(?string $code): ?self
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::allActive()->firstWhere('code', $code);
    }

    /**
     * Icon name for a document type code, or null if not found / unset.
     */
    public static function iconFor(?string $code): ?string
    {
        return self::resolveByCode($code)?->icon;
    }

    /**
     * Flush cached types when saving/deleting.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('document_types_active'));
        static::deleted(fn () => Cache::forget('document_types_active'));
    }

    protected function autoCodePrefix(): string
    {
        return 'DOCTYPE';
    }
}
