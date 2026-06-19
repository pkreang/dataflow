<?php

namespace App\Providers;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflowStage;
use App\Models\DocumentType;
use App\Models\Position;
use App\Models\Setting;
use App\Models\User;
use App\Observers\DocumentTypeObserver;
use App\Observers\PermissionObserver;
use App\Observers\PositionObserver;
use App\Observers\RoleObserver;
use App\Observers\SettingObserver;
use App\Observers\UserObserver;
use App\Observers\WorkflowStageObserver;
use App\Policies\RolePolicy;
use App\Services\ApproverIdentity;
use App\Services\Auth\PasswordCapabilityService;
use App\Services\EvaluationFormResolver;
use App\Services\Mail\ApplyDatabaseMailConfig;
use App\Services\NavigationService;
use App\Support\OrganizationTranslations;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NavigationService::class);
        $this->app->singleton(EvaluationFormResolver::class);
    }

    public function boot(): void
    {
        ApplyDatabaseMailConfig::apply();

        OrganizationTranslations::registerLoaderPath();

        Gate::policy(Role::class, RolePolicy::class);

        ResetPassword::createUrlUsing(function ($notifiable, $token) {
            return config('app.url').'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });

        // System change-log observers — see SystemChangeLog + app/Observers/.
        // Observers swallow exceptions internally, so a logging failure can
        // never block the underlying admin save.
        Setting::observe(SettingObserver::class);
        ApprovalWorkflowStage::observe(WorkflowStageObserver::class);
        DocumentType::observe(DocumentTypeObserver::class);
        Role::observe(RoleObserver::class);
        Permission::observe(PermissionObserver::class);
        User::observe(UserObserver::class);
        Position::observe(PositionObserver::class);

        Gate::before(function ($user, $ability) {
            if ($user?->is_super_admin ?? false) {
                return true;
            }
        });

        View::composer('layouts.app', function ($view) {
            if (session('api_token')) {
                $perms = session('user_permissions', []);
                $isSuperAdmin = session('user.is_super_admin', false);
                $userOrgUnitId = session('user.org_unit_id');
                $userId = (int) (session('user.id') ?? 0);
                $navService = app(NavigationService::class);
                $menus = $navService->getMenus($perms, $isSuperAdmin, $userOrgUnitId);
                $view->with('navigationMenus', $menus);
                $view->with('pinnedMenus', $userId > 0 ? $navService->getPinnedMenus($userId, $menus) : collect());

                // Sidebar badges: route => count. Generic map so future menus can
                // opt in without re-plumbing. Today only the approval inbox is
                // badged with the user's pending-approval count (same predicate as
                // /approvals/my). 60s TTL keeps it cheap; nav-badge staleness ≤60s
                // is acceptable — no cross-user event invalidation needed.
                $menuBadges = [];
                if ($userId > 0) {
                    $identity = app(ApproverIdentity::class)->fromSession();
                    $menuBadges['/approvals/my'] = Cache::remember(
                        "pending_approvals_count:{$userId}",
                        60,
                        fn () => ApprovalInstance::query()
                            ->pendingForApprover($identity['userId'], $identity['roles'], $identity['positionId'])
                            ->count()
                    );
                }
                $view->with('menuBadges', $menuBadges);
            } else {
                $view->with('navigationMenus', collect());
                $view->with('pinnedMenus', collect());
                $view->with('menuBadges', []);
            }

            $layoutUser = session('user', []);
            $canChangePassword = true;
            if (array_key_exists('can_change_password', $layoutUser)) {
                $canChangePassword = (bool) $layoutUser['can_change_password'];
            } elseif (session('api_token')) {
                $tokenUser = PersonalAccessToken::findToken(session('api_token'))?->tokenable;
                $canChangePassword = PasswordCapabilityService::canChangePasswordInApp($tokenUser);
            }
            $view->with('layoutCanChangePassword', $canChangePassword);
            $view->with('authPasswordHelpUrl', trim((string) Setting::get('auth_password_help_url', '')));
        });
    }
}
