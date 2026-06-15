<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Models\Setting;
use App\Models\User;
use App\Services\ApproverIdentity;
use App\Services\Auth\AuthModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Mobile App surface (`/m/*`) — separate URLs from desktop for a dedicated
 * mobile experience with bottom-nav + top app bar. Reuses existing models +
 * partials where sensible (e.g. `approvals.my-approvals` for the approval list).
 */
class MobileController extends Controller
{
    /**
     * Find the primary "repair" form visible to the current user — used as
     * the target of the home hero card + global FAB. Picks the first active
     * form whose document_type starts with "repair" or "maintenance"; null
     * if none exists or none is visible to the user's department.
     */
    private function primaryRepairForm(): ?DocumentForm
    {
        $userDepartmentId = (int) (session('user.department_id') ?? 0);

        return DocumentForm::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('document_type', 'like', 'repair%')
                    ->orWhere('document_type', 'like', 'maintenance%');
            })
            ->visibleToUser($userDepartmentId)
            ->orderBy('id')
            ->first();
    }

    public function home(ApproverIdentity $approverIdentity): View
    {
        $identity = $approverIdentity->fromSession();
        $userId = $identity['userId'];

        // Pending approvals waiting for this user — identical predicate to
        // /approvals/my and the sidebar badge (incl. requester-exclusion).
        $pendingApprovalsCount = ApprovalInstance::query()
            ->pendingForApprover($userId, $identity['roles'], $identity['positionId'])
            ->count();

        $myDraftsCount = DocumentFormSubmission::query()
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->count();

        $submittedTodayCount = DocumentFormSubmission::query()
            ->where('user_id', $userId)
            ->where('status', 'submitted')
            ->whereDate('created_at', Carbon::today())
            ->count();

        $repairRequestsOpenCount = ApprovalInstance::query()
            ->where('document_type', 'repair_request')
            ->where('status', 'pending')
            ->count();

        // Mockup-aligned KPIs: pending / approved / forms / reports
        $approvedCount = ApprovalInstance::where('status', 'approved')->count();
        $formsCount = DocumentForm::where('is_active', true)->count();
        $reportsCount = \App\Models\ReportDashboard::where('is_active', true)->count();

        // Recent activity for the user (last 5 submissions)
        $recentSubmissions = DocumentFormSubmission::query()
            ->where('user_id', $userId)
            ->with('form')
            ->latest()
            ->limit(5)
            ->get();

        $userDepartmentId = (int) (session('user.department_id') ?? 0);
        $quickForms = DocumentForm::query()
            ->where('is_active', true)
            ->visibleToUser($userDepartmentId)
            ->orderBy('name')
            ->limit(3)
            ->get();

        // Top 3 pending approvals (preview list on home)
        $pendingPreview = ApprovalInstance::query()
            ->with(['steps', 'requester', 'workflow', 'formSubmission.form'])
            ->pendingForApprover($userId, $identity['roles'], $identity['positionId'])
            ->latest()
            ->limit(3)
            ->get();

        $latestApproval = ApprovalInstance::query()
            ->where('status', 'approved')
            ->with(['formSubmission.form', 'requester'])
            ->latest('updated_at')
            ->first();

        return view('mobile.home', [
            'kpis' => [
                'pending_approvals' => $pendingApprovalsCount,
                'approved_count' => $approvedCount,
                'forms_count' => $formsCount,
                'reports_count' => $reportsCount,
                'my_drafts' => $myDraftsCount,
                'submitted_today' => $submittedTodayCount,
                'repair_open' => $repairRequestsOpenCount,
            ],
            'recentSubmissions' => $recentSubmissions,
            'quickForms' => $quickForms,
            'pendingPreview' => $pendingPreview,
            'latestApproval' => $latestApproval,
            'primaryRepairForm' => $this->primaryRepairForm(),
        ]);
    }

    public function approvals(ApproverIdentity $approverIdentity): View
    {
        // Mirror the query from ApprovalController::myApprovals so the same
        // permission logic applies, but render the mobile.approvals view
        // directly (which uses mobile layout + glass cards + inline action form).
        $identity = $approverIdentity->fromSession();
        $userId = $identity['userId'];

        $mySignatureDataUrl = $userId ? User::query()->whereKey($userId)->value('signature_path') : null;

        $instances = ApprovalInstance::query()
            ->with(['steps', 'workflow', 'requester', 'formSubmission'])
            ->pendingForApprover($userId, $identity['roles'], $identity['positionId'])
            ->latest()
            ->get();

        return view('mobile.approvals', [
            'instances' => $instances,
            'mySignatureDataUrl' => $mySignatureDataUrl,
            'primaryRepairForm' => $this->primaryRepairForm(),
        ]);
    }

    public function forms(): View
    {
        $userDepartmentId = (int) (session('user.department_id') ?? 0);

        $forms = DocumentForm::query()
            ->where('is_active', true)
            ->visibleToUser($userDepartmentId)
            ->orderBy('name')
            ->get();

        return view('mobile.forms', [
            'forms' => $forms,
            'primaryRepairForm' => $this->primaryRepairForm(),
        ]);
    }

    public function requests(): View
    {
        $userId = (int) (session('user.id') ?? 0);

        $submissions = DocumentFormSubmission::query()
            ->where('user_id', $userId)
            ->with(['form', 'instance.steps', 'evaluations'])
            ->latest()
            ->limit(30)
            ->get();

        return view('mobile.requests', [
            'submissions' => $submissions,
            'primaryRepairForm' => $this->primaryRepairForm(),
        ]);
    }

    public function me(): View
    {
        $userId = (int) (session('user.id') ?? 0);
        $user = $userId ? User::with(['jobPosition', 'department'])->find($userId) : null;

        return view('mobile.me', compact('user'));
    }

    /**
     * Public mobile login page — sets intended=/m/ so post-login lands at mobile home.
     * POST goes to /login (same backend as desktop).
     */
    public function loginShow(): View|RedirectResponse
    {
        if (session('api_token')) {
            return redirect()->route('mobile.home');
        }

        // Ensure post-login lands at /m/ (AuthController honors session('intended'))
        session(['intended' => url('/m')]);

        $authLocalEnabled = AuthModeService::isLocalEnabled();
        $authEntraEnabled = AuthModeService::isEntraEnabled() && AuthModeService::entraConfigured();
        $authLdapEnabled = AuthModeService::isLdapEnabled() && AuthModeService::ldapConfigured() && extension_loaded('ldap');
        $authConfigured = AuthModeService::anyMethodEnabled()
            && ($authLocalEnabled || $authEntraEnabled || $authLdapEnabled);

        return view('mobile.login', compact('authLocalEnabled', 'authEntraEnabled', 'authLdapEnabled', 'authConfigured'));
    }

    /**
     * Mobile form filler — reuses `forms.create` view but swaps in the mobile layout
     * via the `$layout` variable. POST submit still goes through `forms.draft.store`.
     */
    public function formCreate(DocumentForm $documentForm): View
    {
        abort_if(! $documentForm->is_active, 404);
        $documentForm->load('fields');

        return view('forms.create', [
            'form' => $documentForm,
            'layout' => 'layouts.mobile',
        ]);
    }

    /**
     * Mobile submission detail — delegates to the existing controller and view,
     * just swaps the layout to mobile.
     */
    public function requestDetail(DocumentFormSubmission $submission): View
    {
        $view = app(DocumentFormSubmissionController::class)->showSubmission($submission);
        return $view->with('layout', 'layouts.mobile');
    }

    /**
     * Mobile evaluation form — wraps EvaluationController::create with mobile layout.
     */
    public function evaluateForm(DocumentFormSubmission $submission): \Illuminate\Contracts\Support\Renderable|\Illuminate\Http\RedirectResponse
    {
        $response = app(EvaluationController::class)->create($submission);
        if ($response instanceof \Illuminate\Http\RedirectResponse) {
            return $response;
        }
        return $response
            ->with('layout', 'layouts.mobile')
            ->with('storeAction', route('mobile.request.evaluate.store', $submission));
    }

    /**
     * Mobile reports list — delegates to ReportController::index with mobile layout.
     */
    public function reports(): View
    {
        $view = app(ReportController::class)->index();
        return $view->with('layout', 'layouts.mobile');
    }
}
