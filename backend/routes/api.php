<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardWidgetDataController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DevicePushTokenController;
use App\Http\Controllers\Api\MobileApprovalController;
use App\Http\Controllers\Api\MobileFormController;
use App\Http\Controllers\Api\MobileStatsController;
use App\Http\Controllers\Api\MobileSubmissionController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\InboundController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Inbound webhooks — public endpoint (auth via X-Webhook-Token header, throttle per IP)
Route::post('/inbound/{slug}', [InboundController::class, 'receive'])
    ->name('api.inbound.receive')
    ->middleware('throttle:60,1');

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'sanctum.password'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/password', [AuthController::class, 'changePassword']);

        // Users
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:user_access.read');
        Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:user_access.read');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:user_access.create');
        Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:user_access.update');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:user_access.delete');

        // Roles
        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:role_access.read');
        Route::get('/roles/{id}', [RoleController::class, 'show'])->middleware('permission:role_access.read');
        Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:role_access.create');
        Route::put('/roles/{id}', [RoleController::class, 'update'])->middleware('permission:role_access.update');
        Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->middleware('permission:role_access.delete');

        // Permissions
        Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permission_access.read');

        // Organizations + branches (API path /profile — was /prpfile)
        Route::middleware('permission:manage profile')->group(function () {
            Route::get('/profile', [CompanyController::class, 'index']);
            Route::get('/profile/{company}', [CompanyController::class, 'show']);
            Route::post('/profile', [CompanyController::class, 'store']);
            Route::put('/profile/{company}', [CompanyController::class, 'update']);
            Route::delete('/profile/{company}', [CompanyController::class, 'destroy']);

            Route::get('/profile/{company}/branches', [CompanyController::class, 'branchIndex']);
            Route::post('/profile/{company}/branches', [CompanyController::class, 'branchStore']);
            Route::put('/profile/{company}/branches/{branch}', [CompanyController::class, 'branchUpdate']);
            Route::delete('/profile/{company}/branches/{branch}', [CompanyController::class, 'branchDestroy']);
        });

        // Departments — same as web Settings → Departments (users.is_super_admin only)
        Route::middleware('super-admin')->group(function () {
            Route::get('/departments', [DepartmentController::class, 'index']);
            Route::get('/departments/{department}', [DepartmentController::class, 'show']);
            Route::post('/departments', [DepartmentController::class, 'store']);
            Route::put('/departments/{department}', [DepartmentController::class, 'update']);
            Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
        });

        // Mobile API — forms, submissions, approvals, stats
        Route::prefix('mobile')->group(function () {
            Route::get('/stats',                        [MobileStatsController::class, 'index']);
            Route::get('/forms',                        [MobileFormController::class, 'index']);
            Route::get('/forms/{formKey}',              [MobileFormController::class, 'show'])->where('formKey', '[a-z0-9_]+');
            Route::post('/forms/{formKey}',             [MobileFormController::class, 'submit'])->where('formKey', '[a-z0-9_]+');
            Route::get('/submissions',                  [MobileSubmissionController::class, 'index']);
            Route::get('/submissions/{id}',             [MobileSubmissionController::class, 'show'])->whereNumber('id');
            Route::get('/approvals',                    [MobileApprovalController::class, 'index']);
            Route::get('/approvals/{id}',               [MobileApprovalController::class, 'show'])->whereNumber('id');
            Route::post('/approvals/{id}/act',          [MobileApprovalController::class, 'act'])->whereNumber('id');
        });

        // Device push token registration (FCM)
        Route::post('/devices/push-token',    [DevicePushTokenController::class, 'store']);
        Route::delete('/devices/push-token',  [DevicePushTokenController::class, 'destroy']);

        // Dashboard widget data
        Route::get('/dashboards/{dashboard}/widgets/{widget}/data',
            [DashboardWidgetDataController::class, 'show']
        );
        Route::get('/dashboards/{dashboard}/widgets/{widget}/export',
            [DashboardWidgetDataController::class, 'exportWidget']
        );
        Route::get('/dashboards/{dashboard}/export',
            [DashboardWidgetDataController::class, 'exportDashboard']
        );
    });
});
