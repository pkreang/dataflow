<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Branch-level list filtering driven by settings (see Settings → Branch scoping).
 * Super-admins and users without branch_id are not restricted.
 */
final class BranchScopeService
{
    public const MODULE_EQUIPMENT = 'equipment';


    public static function masterEnabled(): bool
    {
        return Setting::getBool('branch_scoping.enabled', false);
    }

    public static function moduleEnabled(string $module): bool
    {
        if (! self::masterEnabled()) {
            return false;
        }

        return Setting::getBool("branch_scoping.{$module}", true);
    }

    public static function applies(?User $user, string $module): bool
    {
        if (! self::moduleEnabled($module)) {
            return false;
        }
        if ($user === null) {
            return false;
        }
        if ($user->is_super_admin) {
            return false;
        }
        if (! $user->branch_id) {
            return false;
        }

        return true;
    }

    /**
     * Rows with null branch_id are treated as organization-wide (visible to all branches).
     *
     * @param  Builder<\App\Models\Equipment>  $query
     */
    public static function constrainEquipmentQuery(Builder $query, ?User $user): void
    {
        if (! self::applies($user, self::MODULE_EQUIPMENT)) {
            return;
        }
        $bid = (int) $user->branch_id;
        $query->where(function ($q) use ($bid) {
            $q->where('branch_id', $bid)->orWhereNull('branch_id');
        });
    }

    public static function userCanAccessEquipment(?User $user, Equipment $equipment): bool
    {
        if (! self::applies($user, self::MODULE_EQUIPMENT)) {
            return true;
        }
        if ($equipment->branch_id === null) {
            return true;
        }

        return (int) $equipment->branch_id === (int) $user->branch_id;
    }

    /**
     * When scoping applies, new records default to the user's branch if none submitted.
     */
    public static function defaultBranchIdForUser(?User $user, string $module): ?int
    {
        if (! self::applies($user, $module)) {
            return null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }

    /**
     * When scoping applies and user has a branch, submitted branch_id must match (or be omitted for default).
     */
    public static function submittedBranchIdValid(?User $user, string $module, mixed $submittedBranchId): bool
    {
        if (! self::applies($user, $module)) {
            return true;
        }
        if ($submittedBranchId === null || $submittedBranchId === '') {
            return true;
        }

        return (int) $submittedBranchId === (int) $user->branch_id;
    }
}
