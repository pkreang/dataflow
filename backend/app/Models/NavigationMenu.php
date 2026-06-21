<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class NavigationMenu extends Model
{
    use HasAutoCode;

    protected $fillable = [
        'auto_code',
        'parent_id', 'label', 'label_en', 'label_th', 'icon', 'route',
        'permission', 'document_form_id', 'sort_order', 'is_active',
    ];

    /** Map standard labels to common.* translation keys */
    protected static array $labelKeyMap = [
        'Dashboard' => 'dashboard',
        'Settings' => 'settings',
        'Users' => 'users',
        'Roles' => 'roles',
        'Permissions' => 'permissions',
        'Password Policy' => 'password_policy',
        'Menu Manager' => 'menu_manager',
        'Companies' => 'companies',
        'Organizations' => 'companies',
        'Branding' => 'branding',
        'Reports' => 'reports',
        'Repair Request' => 'repair_request',
        'My Repair Jobs' => 'my_repair_jobs',
        'Report Repair' => 'repair_request',
        'Assign Repair Jobs' => 'assign_repair_jobs',
        'Evaluate Repair Jobs' => 'evaluate_repair_jobs',
        'Repair History Report' => 'repair_history_report',
        'My Approvals' => 'my_approvals',
        'Auto Assign Settings' => 'auto_assign_settings',
        'Spare Parts' => 'spare_parts',
        'Spare Parts Stock' => 'spare_parts_stock',
        'Spare Parts Withdrawal History' => 'spare_parts_withdrawal_history',
        'Equipment Registry' => 'equipment_registry',
        'Equipment List' => 'equipment_list',
        'Equipment Machinery Locations' => 'equipment_locations',
        'Positions' => 'positions',
        'Workflow' => 'workflow',
        'Approval Routing' => 'approval_routing',
        'Document Forms' => 'document_forms',
        'Equipment' => 'equipment',
        'Notifications' => 'notifications',
        'Equipment Locations' => 'equipment_locations',
        'Activity History' => 'activity_history',
        'Authentication & SSO' => 'authentication_sso',
        'Branch scoping' => 'branch_scoping_title',
    ];

    public function getTranslatedLabelAttribute(): string
    {
        $locale = \Illuminate\Support\Facades\App::getLocale();

        if ($locale === 'th' && $this->label_th !== null && $this->label_th !== '') {
            return $this->label_th;
        }
        if ($locale !== 'th' && $this->label_en !== null && $this->label_en !== '') {
            return $this->label_en;
        }

        $key = self::$labelKeyMap[$this->label] ?? null;
        if ($key) {
            $commonKey = 'common.'.$key;
            if (\Illuminate\Support\Facades\Lang::has($commonKey)) {
                return __($commonKey);
            }
            $companyKey = 'company.'.$key;
            if (\Illuminate\Support\Facades\Lang::has($companyKey)) {
                return __($companyKey);
            }
        }

        return $this->label ?? '';
    }

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ── Relations ─────────────────────────────────────────

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function allChildren(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function documentForm(): BelongsTo
    {
        return $this->belongsTo(DocumentForm::class, 'document_form_id');
    }

    // ── Scopes ────────────────────────────────────────────

    public function scopeRootMenus(Builder $query): Builder
    {
        return $query->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    // ── Helpers ───────────────────────────────────────────

    public function isActive(): bool
    {
        return self::routeMatchesPath($this->route, request()->path());
    }

    /**
     * Whether a stored menu `route` (a URI path) covers the given request path —
     * exact match, or a path-segment prefix (so `/cmms/pm` covers `/cmms/pm/x`).
     * Shared by sidebar highlighting and the menu-permission route gate.
     */
    public static function routeMatchesPath(?string $route, string $currentPath): bool
    {
        if ($route === null || $route === '') {
            return false;
        }

        $routePath = ltrim($route, '/');
        $currentPath = trim($currentPath, '/');

        if ($routePath === '') {
            return false;
        }

        return $currentPath === $routePath || str_starts_with($currentPath.'/', $routePath.'/');
    }

    public function hasActiveChild(): bool
    {
        return $this->children->contains(fn (self $child) => $child->isActive());
    }

    // ── Cache invalidation ────────────────────────────────

    protected static function booted(): void
    {
        $clear = function (): void {
            Cache::forget('navigation_menus_tree');
            Cache::forget('navigation_route_permissions');
        };

        static::saved($clear);
        static::deleted($clear);
    }

    protected function autoCodePrefix(): string
    {
        return 'NAV';
    }
}
