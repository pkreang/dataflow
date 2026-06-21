<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Company;
use App\Models\LookupList;
use App\Models\LookupListItem;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class LookupRegistry
{
    /**
     * Built-in hardcoded sources (tied to Eloquent models).
     * Kept separate from DB-driven sources so we can protect their keys from
     * admin-defined name collisions.
     */
    public static function builtInSources(): array
    {
        return [
            'user' => [
                'model' => User::class,
                'value' => 'id',
                'display' => 'name',
                'label_en' => 'User',
                'label_th' => 'ผู้ใช้',
                'has_active' => true,
            ],
            'company' => [
                'model' => Company::class,
                'value' => 'id',
                'display' => 'name',
                'label_en' => 'Organization',
                'label_th' => 'องค์กร',
                'has_active' => true,
            ],
            'branch' => [
                'model' => Branch::class,
                'value' => 'id',
                'display' => 'name',
                'label_en' => 'Branch',
                'label_th' => 'สาขา',
                'has_active' => true,
            ],
            'position' => [
                'model' => Position::class,
                'value' => 'id',
                'display' => 'name',
                'label_en' => 'Position',
                'label_th' => 'ตำแหน่ง',
                'has_active' => true,
            ],
        ];
    }

    /**
     * All queryable sources = built-in + admin-defined DB lists (cached per-request).
     */
    public static function sources(): array
    {
        return Cache::remember('lookup_registry_sources', 3600, fn () => array_merge(
            static::builtInSources(),
            static::dbDrivenSources()
        ));
    }

    /**
     * Build source configs from the lookup_lists table. Missing table (fresh app
     * before migrations) → return empty array so existing callers stay safe.
     */
    protected static function dbDrivenSources(): array
    {
        if (! Schema::hasTable('lookup_lists')) {
            return [];
        }

        $builtInKeys = array_keys(static::builtInSources());

        return LookupList::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->reject(fn ($list) => in_array($list->key, $builtInKeys, true))
            ->mapWithKeys(fn ($list) => [
                $list->key => [
                    'model' => null,
                    'source_type' => 'db',
                    'list_id' => $list->id,
                    'value' => 'value',
                    'display' => 'label',
                    'label_en' => $list->label_en,
                    'label_th' => $list->label_th,
                    'has_active' => true,
                    'is_system' => (bool) $list->is_system,
                    'required_permission' => $list->required_permission,
                ],
            ])
            ->all();
    }

    /**
     * Filter sources by current user's permissions. Sources without required_permission
     * are visible to everyone. Super-admin bypasses. Cached separately since it depends
     * on the current user.
     */
    public static function accessibleSources(array $userPermissions, bool $isSuperAdmin): array
    {
        return collect(static::sources())
            ->filter(function ($config) use ($userPermissions, $isSuperAdmin) {
                $required = $config['required_permission'] ?? null;
                if ($required === null || $required === '') {
                    return true;
                }
                if ($isSuperAdmin) {
                    return true;
                }

                return in_array($required, $userPermissions, true);
            })
            ->all();
    }

    /**
     * Keys of the registered built-in source namespace (used by admin validator
     * to block name collisions when creating a DB-driven list).
     */
    public static function builtInSourceKeys(): array
    {
        return array_keys(static::builtInSources());
    }

    /**
     * Get the list of valid source keys.
     */
    public static function sourceKeys(): array
    {
        return array_keys(static::sources());
    }

    /**
     * Get items for a given source, optionally filtered.
     *
     * @return Collection<int, array{value: mixed, display: string}>
     */
    public static function getItems(string $source, ?array $filters = null): Collection
    {
        $config = static::sources()[$source] ?? null;

        if (! $config) {
            return collect();
        }

        if (($config['source_type'] ?? 'model') === 'db') {
            return static::dbItems($config, $filters);
        }

        $query = $config['model']::query()->orderBy('name');

        if ($config['has_active']) {
            $query->where('is_active', true);
        }

        if ($filters) {
            foreach ($filters as $column => $val) {
                if (preg_match('/^[a-z_]+$/', $column) && $val !== null && $val !== '') {
                    $query->where($column, $val);
                }
            }
        }

        $displayField = $config['display'];

        return $query->get()->map(function ($item) use ($config, $displayField) {
            $display = $displayField === 'code_name'
                ? "[{$item->code}] {$item->name}"
                : $item->{$displayField};

            return [
                'value' => $item->{$config['value']},
                'display' => $display,
            ];
        });
    }

    /**
     * Pull active items from a DB-driven lookup list. Labels follow the current
     * app locale with graceful fallback between th/en.
     */
    protected static function dbItems(array $config, ?array $filters): Collection
    {
        $locale = App::getLocale();
        $query = LookupListItem::query()
            ->where('list_id', $config['list_id'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($filters && isset($filters['parent_id']) && filled($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        return $query->get()->map(function (LookupListItem $item) use ($locale) {
            $display = $locale === 'th'
                ? ($item->label_th ?: $item->label_en)
                : ($item->label_en ?: $item->label_th);

            return [
                'value' => $item->value,
                'display' => $display ?: $item->value,
            ];
        });
    }

    /**
     * Known foreign key relationships for cascading lookups.
     */
    public static function cascadingRelations(): array
    {
        return [
            'branch' => ['company' => 'company_id'],
            'user' => [
                'company' => 'company_id',
                'branch' => 'branch_id',
            ],
        ];
    }

    /**
     * Suggest the foreign key when a child source depends on a parent source.
     */
    public static function suggestForeignKey(string $childSource, string $parentSource): ?string
    {
        return static::cascadingRelations()[$childSource][$parentSource] ?? null;
    }
}
