<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportDashboard extends Model
{
    use HasAutoCode;
    use HasFactory;

    protected $fillable = [
        'auto_code',
        'name',
        'description',
        'layout_columns',
        'visibility',
        'required_permission',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'layout_columns' => 'integer',
        ];
    }

    public function widgets()
    {
        return $this->hasMany(ReportDashboardWidget::class, 'dashboard_id')->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function autoCodePrefix(): string
    {
        return 'DASH';
    }

    /**
     * Restrict the query to dashboards a given user is allowed to view. Mirrors
     * the rule used in `ReportDashboard::canBeAccessedBy()` so the list page,
     * the home-dashboard picker, and the single-dashboard guard agree.
     *
     * Rule: super-admin sees everything; everyone else sees `visibility=all` +
     * `visibility=permission` rows whose `required_permission` they hold.
     * Guests (`$user === null`) only see `visibility=all`.
     */
    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        if ($user && ($user->is_super_admin ?? false)) {
            return $query;
        }

        $permissions = $user
            ? $user->getAllPermissions()->pluck('name')->all()
            : [];

        return $query->where(function ($q) use ($permissions) {
            $q->where('visibility', 'all')
                ->orWhere(function ($q2) use ($permissions) {
                    $q2->where('visibility', 'permission')
                        ->whereIn('required_permission', $permissions);
                });
        });
    }

    /**
     * Whether a single dashboard instance is visible to the user. Used by the
     * profile picker to refuse setting a dashboard as "home" the user can't
     * actually open, and by the dashboard show page to gate access.
     */
    public function canBeAccessedBy(?User $user): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->visibility === 'all') {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->is_super_admin ?? false) {
            return true;
        }

        if ($this->visibility === 'permission' && $this->required_permission) {
            return $user->hasPermissionTo($this->required_permission);
        }

        return true;
    }
}
