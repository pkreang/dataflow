<?php

use App\Http\Controllers\Web\ActivityHistoryController;
use App\Http\Controllers\Web\ApprovalController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BranchScopingController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DepartmentController;
use App\Http\Controllers\Web\DocumentFormController;
use App\Http\Controllers\Web\DocumentFormSubmissionController;
use App\Http\Controllers\Web\DocumentFormWorkflowPolicyController;
use App\Http\Controllers\Web\DocumentTypeController;
use App\Http\Controllers\Web\EquipmentController;
use App\Http\Controllers\Web\EquipmentLocationController;
use App\Http\Controllers\Web\HolidayController;
use App\Http\Controllers\Web\LookupController;
use App\Http\Controllers\Web\LookupListController;
use App\Http\Controllers\Web\MaintenanceController;
use App\Http\Controllers\Web\MobileController;
use App\Http\Controllers\Web\MyReportController;
use App\Http\Controllers\Web\NavigationMenuController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\NotificationSettingController;
use App\Http\Controllers\Web\OrgUnitController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\PermissionController;
use App\Http\Controllers\Web\PocSchemaFirstController;
use App\Http\Controllers\Web\PositionController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\PurchaseOrderController;
use App\Http\Controllers\Web\PurchaseRequestController;
use App\Http\Controllers\Web\RepairRequestController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\ReportDashboardController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\RunningNumberController;
use App\Http\Controllers\Web\SettingController;
use App\Http\Controllers\Web\ShiftController;
use App\Http\Controllers\Web\SparePartsController;
use App\Http\Controllers\Web\SystemChangeLogController;
use App\Http\Controllers\Web\ThailandAddressSearchController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\UserPinnedMenuController;
use App\Http\Controllers\Web\UserSubstitutionController;
use App\Http\Controllers\Web\WebhookController;
use App\Http\Controllers\Web\WorkflowController;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

/*
 * Deploy escape-hatch สำหรับ host ที่เข้าได้แค่ FTP/File Manager (ไม่มี shell/artisan).
 * ปิดสนิทเมื่อไม่ได้ตั้ง DEPLOY_TOKEN ใน .env (config('app.deploy_token') = null → 404 เสมอ).
 * ใช้ครั้งเดียวหลัง deploy: เปิด /__deploy/<token>/link เพื่อสร้าง public/storage symlink
 * (และ /clear ล้าง cache). ดู doc/deploy-cpanel.md.
 */
Route::get('/__deploy/{token}/{cmd}', function (string $token, string $cmd) {
    $expected = config('app.deploy_token');
    abort_unless(filled($expected) && is_string($expected) && hash_equals($expected, $token), 404);

    $command = [
        'link' => 'storage:link',
        'clear' => 'optimize:clear',
        'migrate' => 'migrate',
    ][$cmd] ?? abort(404);

    Artisan::call($command, $command === 'migrate' ? ['--force' => true] : []);

    return response('<pre>'.e(Artisan::output()).'</pre>');
})->name('deploy.hatch');

Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['th', 'en'], true)) {
        session(['locale' => $locale]);
        $userId = session('user.id');
        if ($userId) {
            User::query()->whereKey($userId)->update(['locale' => $locale]);
        }
    }

    return redirect()->back();
})->name('lang.switch');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/m/login', [MobileController::class, 'loginShow'])->name('mobile.login');
Route::get('/auth/entra/redirect', [AuthController::class, 'redirectToEntra'])->name('auth.entra.redirect');
Route::get('/auth/entra/callback', [AuthController::class, 'entraCallback'])->name('auth.entra.callback');
Route::post('/auth/ldap/login', [AuthController::class, 'loginLdap'])->name('auth.ldap.login');

// LINE Login (account linking — requires authenticated user)
Route::middleware('auth.web')->group(function () {
    Route::get('/auth/line/redirect', [ProfileController::class, 'lineLinkRedirect'])->name('auth.line.redirect');
    Route::get('/auth/line/callback', [ProfileController::class, 'lineLinkCallback'])->name('auth.line.callback');
    Route::post('/auth/line/unlink', [ProfileController::class, 'lineUnlink'])->name('auth.line.unlink');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
// Graceful GET fallback: typing /logout in the address bar (a GET) should log
// the user out instead of showing a 405. The named POST route above stays the
// canonical target for the logout button/forms (route('logout') -> POST).
Route::get('/logout', [AuthController::class, 'logout']);

Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

Route::middleware(['auth.web', 'password.enforced', 'menu.permission'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Mobile App surface — dedicated /m/* URLs with bottom-nav layout (for pitch demo + future mobile use)
    Route::prefix('m')->name('mobile.')->group(function () {
        Route::get('/', [MobileController::class, 'home'])->name('home');
        Route::get('/approvals', [MobileController::class, 'approvals'])->name('approvals');
        Route::get('/forms', [MobileController::class, 'forms'])->name('forms');
        Route::get('/write', [MobileController::class, 'forms'])->name('write'); // alias for "เขียนฟอร์ม" tab
        Route::get('/forms/{documentForm:form_key}', [MobileController::class, 'formCreate'])->name('form.create');
        Route::get('/requests/{submission}', [MobileController::class, 'requestDetail'])->name('request.detail')->withTrashed();
        Route::get('/requests/{submission}/evaluate', [MobileController::class, 'evaluateForm'])->name('request.evaluate');
        Route::post('/requests/{submission}/evaluate', [\App\Http\Controllers\Web\EvaluationController::class, 'store'])->name('request.evaluate.store');
        Route::get('/reports', [MobileController::class, 'reports'])->name('reports');
        Route::get('/requests', [MobileController::class, 'requests'])->name('requests');
        Route::get('/me', [MobileController::class, 'me'])->name('me');
    });
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/dashboards/{dashboard}', [ReportController::class, 'showDashboard'])->name('reports.dashboards.show');
    Route::get('/reports/evaluations', [\App\Http\Controllers\Web\EvaluationReportController::class, 'index'])
        ->middleware('permission:manage_settings')
        ->name('reports.evaluations');

    // Self-service report builder — user-scoped dashboards (created_by = $userId)
    Route::resource('my-reports', MyReportController::class)
        ->names('my-reports')
        ->parameters(['my-reports' => 'dashboard'])
        ->except(['show']);

    // Document Form Submissions (user-facing)
    Route::get('/forms', [DocumentFormSubmissionController::class, 'index'])->name('forms.index');
    Route::get('/forms/calendar', [\App\Http\Controllers\Web\DocumentFormCalendarController::class, 'index'])->name('forms.calendar');
    Route::get('/forms/calendar/events', [\App\Http\Controllers\Web\DocumentFormCalendarController::class, 'events'])->name('forms.calendar.events');
    Route::get('/forms/my-submissions', [DocumentFormSubmissionController::class, 'mySubmissions'])->name('forms.my-submissions');
    Route::get('/forms/drafts/{submission}', [DocumentFormSubmissionController::class, 'editDraft'])->name('forms.draft.edit');
    Route::put('/forms/drafts/{submission}', [DocumentFormSubmissionController::class, 'updateDraft'])->name('forms.draft.update');
    Route::delete('/forms/drafts/{submission}', [DocumentFormSubmissionController::class, 'destroyDraft'])->name('forms.draft.destroy');
    Route::post('/forms/drafts/{submission}/submit', [DocumentFormSubmissionController::class, 'submit'])->name('forms.draft.submit');
    Route::get('/forms/submissions/{submission}', [DocumentFormSubmissionController::class, 'showSubmission'])->name('forms.submission.show')->withTrashed();
    Route::get('/forms/{documentForm:form_key}/submissions', [DocumentFormSubmissionController::class, 'listByForm'])->name('forms.list-by-form');
    Route::get('/forms/submissions/{submission}/print', [DocumentFormSubmissionController::class, 'print'])->name('forms.submission.print');
    Route::get('/forms/submissions/{submission}/pdf', [DocumentFormSubmissionController::class, 'downloadPdf'])->name('forms.submission.pdf');
    Route::post('/forms/submissions/{submission}/duplicate', [DocumentFormSubmissionController::class, 'duplicate'])->name('forms.submission.duplicate');
    Route::post('/forms/submissions/{submission}/return-to-draft', [DocumentFormSubmissionController::class, 'returnToDraft'])->name('forms.submission.return-to-draft');
    Route::post('/forms/submissions/{submission}/send-back', [DocumentFormSubmissionController::class, 'sendBack'])->name('forms.submission.send-back')->middleware('permission:approval.approve');
    Route::get('/forms/submissions/{submission}/history', [DocumentFormSubmissionController::class, 'history'])->name('forms.submission.history')->withTrashed();
    Route::get('/forms/submissions/{submission}/evaluate', [\App\Http\Controllers\Web\EvaluationController::class, 'create'])->name('forms.submission.evaluate');
    Route::post('/forms/submissions/{submission}/evaluate', [\App\Http\Controllers\Web\EvaluationController::class, 'store'])->name('forms.submission.evaluate.store');
    Route::post('/forms/submissions/{submission}/restore', [DocumentFormSubmissionController::class, 'restore'])->name('forms.submission.restore');
    Route::post('/forms/submissions/{submission}/assigned-editors', [DocumentFormSubmissionController::class, 'updateAssignedEditors'])->name('forms.submission.assigned-editors.update');
    Route::post('/forms/submissions/bulk-delete-drafts', [DocumentFormSubmissionController::class, 'bulkDeleteDrafts'])->name('forms.submissions.bulk-delete-drafts');
    Route::get('/forms/{documentForm:form_key}', [DocumentFormSubmissionController::class, 'create'])->name('forms.create');
    Route::post('/forms/{documentForm:form_key}/drafts', [DocumentFormSubmissionController::class, 'storeDraft'])->name('forms.draft.store');

    // CMMS — repair requests (`document_type` = repair_request)
    Route::get('/repair-requests/my-jobs', [RepairRequestController::class, 'myJobs'])->name('repair-requests.my-jobs');
    Route::get('/repair-requests/assign', [RepairRequestController::class, 'assign'])->name('repair-requests.assign');
    Route::get('/repair-requests/evaluate', [RepairRequestController::class, 'evaluate'])->name('repair-requests.evaluate');
    Route::get('/repair-requests', [RepairRequestController::class, 'index'])->name('repair-requests.index');
    Route::post('/repair-requests', [RepairRequestController::class, 'submit'])->name('repair-requests.submit');
    Route::get('/repair-requests/{instance}', [RepairRequestController::class, 'show'])
        ->name('repair-requests.show')
        ->whereNumber('instance');

    // CMMS — maintenance (`document_type` = pm_am_plan)
    Route::get('/maintenance/auto-assign', [MaintenanceController::class, 'autoAssign'])->name('maintenance.auto-assign');
    Route::get('/maintenance/create-plan', [MaintenanceController::class, 'createPlan'])->name('maintenance.create-plan');
    Route::post('/maintenance', [MaintenanceController::class, 'submitPlan'])->name('maintenance.create-plan.submit');
    Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('/maintenance/{instance}', [MaintenanceController::class, 'show'])
        ->name('maintenance.show')
        ->whereNumber('instance');

    // CMMS — spare parts (`document_type` = spare_parts_requisition)
    Route::get('/spare-parts/stock', [SparePartsController::class, 'stock'])->name('spare-parts.stock');
    Route::get('/spare-parts/withdrawal-history', [SparePartsController::class, 'withdrawalHistory'])->name('spare-parts.withdrawal-history');
    Route::get('/spare-parts/requisitions/create', [SparePartsController::class, 'requisitionCreate'])->name('spare-parts.requisition.create');
    Route::post('/spare-parts/requisitions', [SparePartsController::class, 'requisitionSubmit'])->name('spare-parts.requisition.submit');
    Route::get('/spare-parts/requisitions', [SparePartsController::class, 'requisitionIndex'])->name('spare-parts.requisition.index');
    Route::get('/spare-parts/requisitions/{instance}', [SparePartsController::class, 'requisitionShow'])
        ->name('spare-parts.requisition.show')
        ->whereNumber('instance');
    Route::post('/spare-parts/requisitions/{instance}/issue', [SparePartsController::class, 'issueItems'])
        ->name('spare-parts.requisition.issue')
        ->whereNumber('instance');

    Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])->name('purchase-requests.index');
    Route::get('/purchase-requests/create', [PurchaseRequestController::class, 'create'])->name('purchase-requests.create');
    Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])->name('purchase-requests.store');
    Route::get('/purchase-requests/{instance}', [PurchaseRequestController::class, 'show'])->name('purchase-requests.show')->whereNumber('instance');
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
    Route::get('/purchase-orders/{instance}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show')->whereNumber('instance');

    Route::get('/approvals/my', [ApprovalController::class, 'myApprovals'])
        ->middleware('permission:approval.approve')
        ->name('approvals.my');
    Route::post('/approvals/{instance}/act', [ApprovalController::class, 'act'])
        ->middleware('permission:approval.approve')
        ->name('approvals.act');
    Route::patch('/approval-instances/{instance}/fields', [ApprovalController::class, 'updateFields'])
        ->middleware('permission:approval.approve')
        ->name('approvals.update-fields');
    Route::get('/addresses/thailand/subdistricts', [ThailandAddressSearchController::class, 'subdistricts'])
        ->name('addresses.thailand.subdistricts');
    Route::get('/lookup', [LookupController::class, 'index'])->name('lookup.index');
    // Legacy /companies → organizations at /profile
    Route::get('/companies', fn () => redirect()->to('/profile'.(request()->getQueryString() ? '?'.request()->getQueryString() : ''), 301));
    Route::get('/companies/{path}', function (string $path) {
        $target = '/profile/'.$path;
        if ($qs = request()->getQueryString()) {
            $target .= '?'.$qs;
        }

        return redirect($target, 301);
    })->where('path', '.*');
    // Legacy /prpfile → /profile
    Route::get('/prpfile', fn () => redirect()->to('/profile'.(request()->getQueryString() ? '?'.request()->getQueryString() : ''), 301));
    Route::get('/prpfile/{path}', function (string $path) {
        $target = '/profile/'.$path;
        if ($qs = request()->getQueryString()) {
            $target .= '?'.$qs;
        }

        return redirect($target, 301);
    })->where('path', '.*');

    // User account (not organizations)
    Route::get('/myprofile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/myprofile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/myprofile/password', [ProfileController::class, 'showPasswordForm'])->name('profile.password');
    Route::put('/myprofile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::put('/myprofile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications.update');
    Route::get('/myprofile/login-history', [ProfileController::class, 'loginHistory'])->name('profile.login-history');
    Route::get('/myprofile/activity', [ProfileController::class, 'activity'])->name('profile.activity');
    Route::post('/myprofile/pinned-menus/toggle', [UserPinnedMenuController::class, 'toggle'])->name('profile.pinned-menus.toggle');
    Route::patch('/myprofile/home-dashboard', [ProfileController::class, 'updateHomeDashboard'])->name('profile.home-dashboard.update');
    Route::get('/myprofile/sessions', [ProfileController::class, 'activeSessions'])->name('profile.sessions');
    Route::delete('/myprofile/sessions/{tokenId}', [ProfileController::class, 'revokeSession'])->name('profile.sessions.revoke');
    Route::delete('/myprofile/sessions-others', [ProfileController::class, 'revokeOtherSessions'])->name('profile.sessions.revoke-others');
    Route::get('/myprofile/api-tokens', [ProfileController::class, 'apiTokens'])->name('profile.api-tokens');
    Route::post('/myprofile/api-tokens', [ProfileController::class, 'createApiToken'])->name('profile.api-tokens.create');
    Route::delete('/myprofile/api-tokens/{tokenId}', [ProfileController::class, 'revokeApiToken'])->name('profile.api-tokens.revoke');

    // Legacy user-profile URLs (before /profile meant organizations)
    Route::get('/userprofile', fn () => redirect('/myprofile', 301));
    Route::put('/userprofile', fn () => abort(410));
    Route::get('/userprofile/password', fn () => redirect('/myprofile/password', 301));
    Route::put('/userprofile/password', fn () => abort(410));
    Route::get('/profile/password', fn () => redirect('/myprofile/password', 301));

    // Organizations (companies) — route names still companies.*
    Route::resource('profile', CompanyController::class)
        ->names('companies')
        ->parameters(['profile' => 'company']);
    Route::post('profile/{company}/branches', [CompanyController::class, 'storeBranch'])->name('companies.branches.store');
    Route::put('profile/{company}/branches/{branch}', [CompanyController::class, 'updateBranch'])->name('companies.branches.update');
    Route::delete('profile/{company}/branches/{branch}', [CompanyController::class, 'destroyBranch'])->name('companies.branches.destroy');
    Route::get('/users/import', [UserController::class, 'importForm'])->name('users.import');
    Route::post('/users/import', [UserController::class, 'import'])->name('users.import.store');
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.password.reset');
    Route::post('/users/{user}/send-password-link', [UserController::class, 'sendPasswordResetLink'])->name('users.password.send-link');
    Route::get('roles/overview', [RoleController::class, 'overview'])->name('roles.overview');
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::match(['GET', 'POST'], '/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');

    Route::get('/settings/password-policy', [SettingController::class, 'passwordPolicy'])->name('settings.password-policy');
    Route::post('/settings/password-policy', [SettingController::class, 'savePasswordPolicy'])->name('settings.password-policy.save');

    // CMMS: Equipment Registry (operational — not super-admin-only)
    Route::get('/equipment-registry', [\App\Http\Controllers\Web\EquipmentRegistryController::class, 'index'])->name('equipment-registry.index');
    Route::get('/equipment-registry/create', [\App\Http\Controllers\Web\EquipmentRegistryController::class, 'create'])->name('equipment-registry.create');
    Route::post('/equipment-registry', [\App\Http\Controllers\Web\EquipmentRegistryController::class, 'store'])->name('equipment-registry.store');
    Route::get('/equipment-registry/{equipment}/edit', [\App\Http\Controllers\Web\EquipmentRegistryController::class, 'edit'])->name('equipment-registry.edit');
    Route::put('/equipment-registry/{equipment}', [\App\Http\Controllers\Web\EquipmentRegistryController::class, 'update'])->name('equipment-registry.update');
    Route::delete('/equipment-registry/{equipment}', [\App\Http\Controllers\Web\EquipmentRegistryController::class, 'destroy'])->name('equipment-registry.destroy');

    // CMMS: PM Plans (Phase 2A)
    Route::prefix('cmms/pm')->name('cmms.pm.')->group(function () {
        Route::get('plans', [\App\Http\Controllers\Web\Cmms\PmPlanController::class, 'index'])->name('plans.index')
            ->middleware('permission:pm.view');
        Route::get('plans/create', [\App\Http\Controllers\Web\Cmms\PmPlanController::class, 'create'])->name('plans.create')
            ->middleware('permission:pm.plan');
        Route::post('plans', [\App\Http\Controllers\Web\Cmms\PmPlanController::class, 'store'])->name('plans.store')
            ->middleware('permission:pm.plan');
        Route::get('plans/{plan}/edit', [\App\Http\Controllers\Web\Cmms\PmPlanController::class, 'edit'])->name('plans.edit')
            ->middleware('permission:pm.plan');
        Route::put('plans/{plan}', [\App\Http\Controllers\Web\Cmms\PmPlanController::class, 'update'])->name('plans.update')
            ->middleware('permission:pm.plan');
        Route::delete('plans/{plan}', [\App\Http\Controllers\Web\Cmms\PmPlanController::class, 'destroy'])->name('plans.destroy')
            ->middleware('permission:pm.plan');
        Route::post('plans/{plan}/generate-work-order', [\App\Http\Controllers\Web\Cmms\PmPlanController::class, 'generateWorkOrder'])->name('plans.generate-wo')
            ->middleware('permission:pm.plan');

        Route::get('work-orders', [\App\Http\Controllers\Web\Cmms\PmWorkOrderController::class, 'index'])->name('work-orders.index')
            ->middleware('permission:pm.view');
        Route::get('work-orders/{workOrder}', [\App\Http\Controllers\Web\Cmms\PmWorkOrderController::class, 'show'])->name('work-orders.show')
            ->middleware('permission:pm.view');
        Route::post('work-orders/{workOrder}/start', [\App\Http\Controllers\Web\Cmms\PmWorkOrderController::class, 'start'])->name('work-orders.start')
            ->middleware('permission:pm.execute');
        Route::post('work-orders/{workOrder}/complete', [\App\Http\Controllers\Web\Cmms\PmWorkOrderController::class, 'complete'])->name('work-orders.complete')
            ->middleware('permission:pm.execute');
        Route::post('work-orders/{workOrder}/cancel', [\App\Http\Controllers\Web\Cmms\PmWorkOrderController::class, 'cancel'])->name('work-orders.cancel')
            ->middleware('permission:pm.plan');
    });

    Route::middleware('super-admin')->group(function () {
        Route::get('/settings/branding', [SettingController::class, 'branding'])->name('settings.branding');
        Route::post('/settings/branding', [SettingController::class, 'saveBranding'])->name('settings.branding.save');
        Route::get('/settings/departments', [DepartmentController::class, 'index'])->name('settings.departments.index');
        Route::get('/settings/departments/import', [DepartmentController::class, 'importForm'])->name('settings.departments.import');
        Route::post('/settings/departments/import', [DepartmentController::class, 'import'])->name('settings.departments.import.store');
        Route::get('/settings/departments/import/template', [DepartmentController::class, 'downloadTemplate'])->name('settings.departments.import.template');
        Route::get('/settings/department-workflow-bindings', fn () => redirect()->route('settings.approval-routing'))->name('settings.department-workflow-bindings.index');
        Route::post('/settings/department-workflow-bindings', fn () => redirect()->route('settings.approval-routing'))->name('settings.department-workflow-bindings.bulk');
        Route::get('/settings/departments/create', [DepartmentController::class, 'create'])->name('settings.departments.create');
        Route::post('/settings/departments', [DepartmentController::class, 'store'])->name('settings.departments.store');
        Route::get('/settings/departments/{department}/edit', [DepartmentController::class, 'edit'])->name('settings.departments.edit');
        Route::put('/settings/departments/{department}', [DepartmentController::class, 'update'])->name('settings.departments.update');
        Route::delete('/settings/departments/{department}', [DepartmentController::class, 'destroy'])->name('settings.departments.destroy');
        Route::post('/settings/departments/{department}/bindings', [DepartmentController::class, 'bindWorkflow'])->name('settings.departments.bindings.store');
        Route::get('/settings/positions', [PositionController::class, 'index'])->name('settings.positions.index');
        Route::get('/settings/positions/import', [PositionController::class, 'importForm'])->name('settings.positions.import');
        Route::post('/settings/positions/import', [PositionController::class, 'import'])->name('settings.positions.import.store');
        Route::get('/settings/positions/import/template', [PositionController::class, 'downloadTemplate'])->name('settings.positions.import.template');
        Route::get('/settings/positions/create', [PositionController::class, 'create'])->name('settings.positions.create');
        Route::post('/settings/positions', [PositionController::class, 'store'])->name('settings.positions.store');
        Route::get('/settings/positions/{position}/edit', [PositionController::class, 'edit'])->name('settings.positions.edit');
        Route::put('/settings/positions/{position}', [PositionController::class, 'update'])->name('settings.positions.update');
        Route::delete('/settings/positions/{position}', [PositionController::class, 'destroy'])->name('settings.positions.destroy');

        // Holiday calendar (org-wide)
        Route::get('/settings/holidays', [HolidayController::class, 'index'])->name('settings.holidays.index');
        Route::post('/settings/holidays', [HolidayController::class, 'store'])->name('settings.holidays.store');
        Route::patch('/settings/holidays/{holiday}/toggle', [HolidayController::class, 'toggleActive'])->name('settings.holidays.toggle');
        Route::delete('/settings/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('settings.holidays.destroy');

        // Shifts (master + per-user assignment)
        Route::get('/settings/shifts', [ShiftController::class, 'index'])->name('settings.shifts.index');
        Route::post('/settings/shifts', [ShiftController::class, 'store'])->name('settings.shifts.store');
        Route::patch('/settings/shifts/{shift}/toggle', [ShiftController::class, 'toggleActive'])->name('settings.shifts.toggle');
        Route::delete('/settings/shifts/{shift}', [ShiftController::class, 'destroy'])->name('settings.shifts.destroy');
        Route::post('/settings/shifts/assignments', [ShiftController::class, 'assign'])->name('settings.shifts.assign');
        Route::delete('/settings/shifts/assignments/{schedule}', [ShiftController::class, 'destroyAssignment'])->name('settings.shifts.assignments.destroy');

        // Approval Substitutions
        Route::get('/settings/substitutions', [UserSubstitutionController::class, 'index'])->name('settings.substitutions.index');
        Route::get('/settings/substitutions/create', [UserSubstitutionController::class, 'create'])->name('settings.substitutions.create');
        Route::post('/settings/substitutions', [UserSubstitutionController::class, 'store'])->name('settings.substitutions.store');
        Route::patch('/settings/substitutions/{substitution}/toggle', [UserSubstitutionController::class, 'toggleActive'])->name('settings.substitutions.toggle');
        Route::delete('/settings/substitutions/{substitution}', [UserSubstitutionController::class, 'destroy'])->name('settings.substitutions.destroy');

        // Org Units (hierarchical org chart)
        Route::get('/settings/org-units', [OrgUnitController::class, 'index'])->name('settings.org-units.index');
        Route::get('/settings/org-units/import', [OrgUnitController::class, 'importForm'])->name('settings.org-units.import');
        Route::post('/settings/org-units/import', [OrgUnitController::class, 'import'])->name('settings.org-units.import.store');
        Route::get('/settings/org-units/import/template', [OrgUnitController::class, 'downloadTemplate'])->name('settings.org-units.import.template');
        Route::get('/settings/org-units/tree', [OrgUnitController::class, 'treeJson'])->name('settings.org-units.tree');
        Route::get('/settings/org-units/create', [OrgUnitController::class, 'create'])->name('settings.org-units.create');
        Route::post('/settings/org-units', [OrgUnitController::class, 'store'])->name('settings.org-units.store');
        Route::get('/settings/org-units/{orgUnit}/edit', [OrgUnitController::class, 'edit'])->name('settings.org-units.edit');
        Route::put('/settings/org-units/{orgUnit}', [OrgUnitController::class, 'update'])->name('settings.org-units.update');
        Route::delete('/settings/org-units/{orgUnit}', [OrgUnitController::class, 'destroy'])->name('settings.org-units.destroy');

        // Equipment Categories
        Route::get('/settings/equipment', [EquipmentController::class, 'index'])->name('settings.equipment.index');
        Route::get('/settings/equipment/create', [EquipmentController::class, 'create'])->name('settings.equipment.create');
        Route::post('/settings/equipment', [EquipmentController::class, 'store'])->name('settings.equipment.store');
        Route::get('/settings/equipment/{equipmentCategory}/edit', [EquipmentController::class, 'edit'])->name('settings.equipment.edit');
        Route::put('/settings/equipment/{equipmentCategory}', [EquipmentController::class, 'update'])->name('settings.equipment.update');
        Route::delete('/settings/equipment/{equipmentCategory}', [EquipmentController::class, 'destroy'])->name('settings.equipment.destroy');

        // Equipment Locations
        Route::get('/settings/equipment-locations', [EquipmentLocationController::class, 'index'])->name('settings.equipment-locations.index');
        Route::get('/settings/equipment-locations/create', [EquipmentLocationController::class, 'create'])->name('settings.equipment-locations.create');
        Route::post('/settings/equipment-locations', [EquipmentLocationController::class, 'store'])->name('settings.equipment-locations.store');
        Route::get('/settings/equipment-locations/{equipmentLocation}/edit', [EquipmentLocationController::class, 'edit'])->name('settings.equipment-locations.edit');
        Route::put('/settings/equipment-locations/{equipmentLocation}', [EquipmentLocationController::class, 'update'])->name('settings.equipment-locations.update');
        Route::delete('/settings/equipment-locations/{equipmentLocation}', [EquipmentLocationController::class, 'destroy'])->name('settings.equipment-locations.destroy');

        Route::get('/settings/workflow', [WorkflowController::class, 'index'])->name('settings.workflow.index');
        Route::get('/settings/workflow/create', [WorkflowController::class, 'create'])->name('settings.workflow.create');
        Route::post('/settings/workflow', [WorkflowController::class, 'store'])->name('settings.workflow.store');
        Route::get('/settings/workflow/{workflow}/edit', [WorkflowController::class, 'edit'])->name('settings.workflow.edit');
        Route::put('/settings/workflow/{workflow}', [WorkflowController::class, 'update'])->name('settings.workflow.update');
        Route::delete('/settings/workflow/{workflow}', [WorkflowController::class, 'destroy'])->name('settings.workflow.destroy');
        Route::post('/settings/workflow/{workflow}/stages', [WorkflowController::class, 'addStage'])->name('settings.workflow.stages.store');
        Route::get('/settings/approval-routing', [SettingController::class, 'approvalRouting'])->name('settings.approval-routing');
        Route::post('/settings/approval-routing', [SettingController::class, 'saveApprovalRouting'])->name('settings.approval-routing.save');
        Route::get('/settings/system-change-log', [SystemChangeLogController::class, 'index'])->name('settings.system-change-log');

        // KPI evaluation cycles — bundle of evaluations against one form across a period.
        Route::get('/settings/kpi-cycles', [\App\Http\Controllers\Web\KpiCycleController::class, 'index'])->name('settings.kpi-cycles.index');
        Route::get('/settings/kpi-cycles/create', [\App\Http\Controllers\Web\KpiCycleController::class, 'create'])->name('settings.kpi-cycles.create');
        Route::post('/settings/kpi-cycles', [\App\Http\Controllers\Web\KpiCycleController::class, 'store'])->name('settings.kpi-cycles.store');
        Route::get('/settings/kpi-cycles/{kpiCycle}/edit', [\App\Http\Controllers\Web\KpiCycleController::class, 'edit'])->name('settings.kpi-cycles.edit');
        Route::put('/settings/kpi-cycles/{kpiCycle}', [\App\Http\Controllers\Web\KpiCycleController::class, 'update'])->name('settings.kpi-cycles.update');
        Route::delete('/settings/kpi-cycles/{kpiCycle}', [\App\Http\Controllers\Web\KpiCycleController::class, 'destroy'])->name('settings.kpi-cycles.destroy');
        Route::post('/settings/kpi-cycles/{kpiCycle}/open', [\App\Http\Controllers\Web\KpiCycleController::class, 'open'])->name('settings.kpi-cycles.open');
        Route::post('/settings/kpi-cycles/{kpiCycle}/close', [\App\Http\Controllers\Web\KpiCycleController::class, 'close'])->name('settings.kpi-cycles.close');
        Route::get('/settings/kpi-cycles/{kpiCycle}/report', [\App\Http\Controllers\Web\KpiCycleReportController::class, 'show'])->name('settings.kpi-cycles.report');
        Route::get('/settings/authentication', [SettingController::class, 'authSettings'])->name('settings.auth');
        Route::post('/settings/authentication', [SettingController::class, 'saveAuthSettings'])->name('settings.auth.save');
        Route::get('/settings/document-types', [DocumentTypeController::class, 'index'])->name('settings.document-types.index');
        Route::get('/settings/document-types/create', [DocumentTypeController::class, 'create'])->name('settings.document-types.create');
        Route::post('/settings/document-types', [DocumentTypeController::class, 'store'])->name('settings.document-types.store');
        Route::get('/settings/document-types/{documentType}/edit', [DocumentTypeController::class, 'edit'])->name('settings.document-types.edit');
        Route::put('/settings/document-types/{documentType}', [DocumentTypeController::class, 'update'])->name('settings.document-types.update');
        Route::delete('/settings/document-types/{documentType}', [DocumentTypeController::class, 'destroy'])->name('settings.document-types.destroy');
        Route::get('/settings/lookups', [LookupListController::class, 'index'])->name('settings.lookups.index');
        Route::get('/settings/lookups/create', [LookupListController::class, 'create'])->name('settings.lookups.create');
        Route::post('/settings/lookups', [LookupListController::class, 'store'])->name('settings.lookups.store');
        Route::get('/settings/lookups/{lookup}/edit', [LookupListController::class, 'edit'])->name('settings.lookups.edit');
        Route::put('/settings/lookups/{lookup}', [LookupListController::class, 'update'])->name('settings.lookups.update');
        Route::delete('/settings/lookups/{lookup}', [LookupListController::class, 'destroy'])->name('settings.lookups.destroy');
        Route::get('/settings/lookups/{lookup}/export', [LookupListController::class, 'exportCsv'])->name('settings.lookups.export');
        Route::post('/settings/lookups/{lookup}/import', [LookupListController::class, 'importCsv'])->name('settings.lookups.import');

        Route::get('/settings/document-forms', [DocumentFormController::class, 'index'])->name('settings.document-forms.index');
        Route::get('/settings/document-forms/create', [DocumentFormController::class, 'create'])->name('settings.document-forms.create');
        Route::post('/settings/document-forms', [DocumentFormController::class, 'store'])->name('settings.document-forms.store');
        Route::get('/settings/document-forms/{documentForm}/edit', [DocumentFormController::class, 'edit'])->name('settings.document-forms.edit');
        Route::put('/settings/document-forms/{documentForm}', [DocumentFormController::class, 'update'])->name('settings.document-forms.update');
        Route::delete('/settings/document-forms/{documentForm}', [DocumentFormController::class, 'destroy'])->name('settings.document-forms.destroy');
        Route::post('/settings/document-forms/{documentForm}/clone', [DocumentFormController::class, 'clone'])->name('settings.document-forms.clone');
        Route::post('/settings/document-forms/{documentForm}/create-report', [DocumentFormController::class, 'createReport'])->name('settings.document-forms.create-report');
        Route::get('/settings/document-forms/{documentForm}/policy', [DocumentFormWorkflowPolicyController::class, 'edit'])->name('settings.document-forms.policy.edit');
        Route::put('/settings/document-forms/{documentForm}/policy', [DocumentFormWorkflowPolicyController::class, 'update'])->name('settings.document-forms.policy.update');
        Route::get('/settings/notifications', [NotificationSettingController::class, 'index'])->name('settings.notifications.index');
        Route::put('/settings/notifications', [NotificationSettingController::class, 'update'])->name('settings.notifications.update');
        Route::post('/settings/notifications/test-line', [NotificationSettingController::class, 'testLineSend'])->name('settings.notifications.test-line');
        Route::get('/settings/branch-scoping', [BranchScopingController::class, 'edit'])->name('settings.branch-scoping');
        Route::put('/settings/branch-scoping', [BranchScopingController::class, 'update'])->name('settings.branch-scoping.update');
        Route::get('/settings/running-numbers', [RunningNumberController::class, 'index'])->name('settings.running-numbers.index');
        Route::get('/settings/running-numbers/create', [RunningNumberController::class, 'create'])->name('settings.running-numbers.create');
        Route::post('/settings/running-numbers', [RunningNumberController::class, 'store'])->name('settings.running-numbers.store');
        Route::get('/settings/running-numbers/{runningNumberConfig}/edit', [RunningNumberController::class, 'edit'])->name('settings.running-numbers.edit');
        Route::put('/settings/running-numbers/{runningNumberConfig}', [RunningNumberController::class, 'update'])->name('settings.running-numbers.update');
        Route::delete('/settings/running-numbers/{runningNumberConfig}', [RunningNumberController::class, 'destroy'])->name('settings.running-numbers.destroy');
        Route::post('/settings/running-numbers/{runningNumberConfig}/reset', [RunningNumberController::class, 'reset'])->name('settings.running-numbers.reset');
        Route::get('/settings/activity-history', [ActivityHistoryController::class, 'index'])->name('settings.activity-history.index');
        Route::get('/settings/activity-history/export', [ActivityHistoryController::class, 'export'])->name('settings.activity-history.export');
        Route::get('/settings/navigation', [NavigationMenuController::class, 'index'])->name('settings.navigation.index');
        Route::get('/settings/navigation/create', [NavigationMenuController::class, 'create'])->name('settings.navigation.create');
        Route::post('/settings/navigation', [NavigationMenuController::class, 'store'])->name('settings.navigation.store');
        Route::get('/settings/navigation/{navigation}/edit', [NavigationMenuController::class, 'edit'])->name('settings.navigation.edit');
        Route::put('/settings/navigation/{navigation}', [NavigationMenuController::class, 'update'])->name('settings.navigation.update');
        Route::delete('/settings/navigation/{navigation}', [NavigationMenuController::class, 'destroy'])->name('settings.navigation.destroy');
        Route::patch('/settings/navigation/reorder', [NavigationMenuController::class, 'reorder'])->name('settings.navigation.reorder');
        Route::patch('/settings/navigation/{navigation}/toggle', [NavigationMenuController::class, 'toggle'])->name('settings.navigation.toggle');

        // Dashboard designer
        Route::resource('settings/dashboards', ReportDashboardController::class)
            ->names('settings.dashboards')
            ->except(['show']);

        // Integrations — webhook hub
        Route::resource('settings/integrations', WebhookController::class)
            ->parameters(['integrations' => 'webhook'])
            ->names('settings.webhooks')
            ->except(['show']);
        Route::post('settings/integrations/{webhook}/test', [WebhookController::class, 'testSend'])
            ->name('settings.webhooks.test');

        // Integrations — inbound (external systems POST data to us)
        Route::resource('settings/inbound-webhooks', \App\Http\Controllers\Web\IncomingWebhookController::class)
            ->parameters(['inbound-webhooks' => 'inbound_webhook'])
            ->names('settings.inbound-webhooks')
            ->except(['show']);
        Route::post('settings/inbound-webhooks/{inbound_webhook}/test', [\App\Http\Controllers\Web\IncomingWebhookController::class, 'testReceive'])
            ->name('settings.inbound-webhooks.test');

        // Evaluation forms — list page (filtered by document_type='evaluation').
        // Edit/Delete go through the existing settings.document-forms.* routes.
        Route::get('settings/evaluation-form', [\App\Http\Controllers\Web\EvaluationController::class, 'indexAdmin'])
            ->name('settings.evaluation-form');
        Route::get('settings/evaluation-form/create', [\App\Http\Controllers\Web\EvaluationController::class, 'createAdmin'])
            ->name('settings.evaluation-form.create');

        // PoC — Schema-first form builder (annotate columns of existing tables, render form, submit)
        Route::prefix('poc/schema-first')->name('poc.schema-first.')->group(function () {
            Route::get('{table}', [PocSchemaFirstController::class, 'show'])->name('show');
            Route::post('{table}/submit', [PocSchemaFirstController::class, 'submit'])->name('submit');
            Route::get('{table}/annotate', [PocSchemaFirstController::class, 'annotate'])->name('annotate');
            Route::post('{table}/annotate', [PocSchemaFirstController::class, 'saveAnnotations'])->name('annotate.save');
            Route::post('{table}/bootstrap', [PocSchemaFirstController::class, 'bootstrap'])->name('bootstrap');
        });
    });
});
