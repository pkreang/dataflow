<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class OrgUnit extends Model
{
    use HasAutoCode, HasFactory;

    protected $fillable = [
        'auto_code',
        'parent_id',
        'name',
        'type',
        'head_user_id',
        'branch_id',
        'sort_order',
        'is_active',
    ];

    protected function autoCodePrefix(): string
    {
        return 'ORG';
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(OrgUnit::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'org_unit_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Walk up the parent chain, returns collection from root to direct parent (not including self). */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $unit = $this->parent;
        while ($unit) {
            $ancestors->prepend($unit);
            $unit = $unit->parent;
        }

        return $ancestors;
    }

    /** Walk up n levels, returns the OrgUnit found or null. */
    public function nthAncestor(int $n): ?OrgUnit
    {
        $unit = $this;
        for ($i = 0; $i < $n; $i++) {
            $unit = $unit->parent;
            if ($unit === null) {
                return null;
            }
        }

        return $unit;
    }
}
