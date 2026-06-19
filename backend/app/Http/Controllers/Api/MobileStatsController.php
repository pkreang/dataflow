<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalInstance;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileStatsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $roles = $user->getRoleNames()->all();
        $positionId = $user->position_id;

        $pendingApprovals = ApprovalInstance::query()
            ->pendingForApprover($userId, $roles, $positionId)
            ->count();

        $mySubmissionsTotal = DocumentFormSubmission::query()
            ->where('user_id', $userId)
            ->count();

        $mySubmissionsPending = DocumentFormSubmission::query()
            ->where('user_id', $userId)
            ->where('status', 'submitted')
            ->count();

        $myDrafts = DocumentFormSubmission::query()
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->count();

        $formsAvailable = DocumentForm::query()
            ->where('is_active', true)
            ->visibleToUser($user->org_unit_id)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'pending_approvals'    => $pendingApprovals,
                'my_submissions_total' => $mySubmissionsTotal,
                'my_submissions_pending' => $mySubmissionsPending,
                'my_drafts'            => $myDrafts,
                'forms_available'      => $formsAvailable,
            ],
        ]);
    }
}
