<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;
use App\Services\EvaluationFormResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EvaluationController extends Controller
{
    public function create(DocumentFormSubmission $submission): View|RedirectResponse
    {
        $this->authorizeOwnerOfApproved($submission);

        $existing = $submission->evaluations()->first();
        if ($existing) {
            return redirect($this->showRoute($existing))
                ->with('warning', __('common.evaluation_already_submitted'));
        }

        $evalForm = app(EvaluationFormResolver::class)->resolveFor($submission);
        if (! $evalForm) {
            return redirect($this->showRoute($submission))
                ->with('error', __('common.evaluation_no_form'));
        }
        $evalForm->load('fields');

        return view('evaluations.create', [
            'form' => $evalForm,
            'parent' => $submission,
            'storeAction' => route('forms.submission.evaluate.store', $submission),
        ]);
    }

    public function store(Request $request, DocumentFormSubmission $submission): RedirectResponse
    {
        $this->authorizeOwnerOfApproved($submission);
        abort_if($submission->evaluations()->exists(), 422, 'already evaluated');

        $evalForm = app(EvaluationFormResolver::class)->resolveFor($submission);
        if (! $evalForm) {
            return redirect($this->showRoute($submission))
                ->with('error', __('common.evaluation_no_form'));
        }

        $spec = app(DocumentFormSubmissionController::class)->buildPayloadRules($evalForm, (array) $request->input('fields', []));
        $validated = $request->validate($spec['rules'], [], $spec['attributes']);

        $userId = (int) (session('user.id') ?? 0);
        $userOrgUnitId = session('user.org_unit_id') ?? \App\Models\User::find($userId)?->org_unit_id;

        DocumentFormSubmission::create([
            'form_id' => $evalForm->id,
            'user_id' => $userId,
            'org_unit_id' => $userOrgUnitId,
            'parent_submission_id' => $submission->id,
            'payload' => $validated['fields'] ?? [],
            'status' => 'submitted',
        ]);

        return redirect($this->showRoute($submission))
            ->with('success', __('common.evaluation_thank_you'));
    }

    public function indexAdmin(): View
    {
        $forms = DocumentForm::where('document_type', 'evaluation')
            ->withCount('fields')
            ->orderByDesc('id')
            ->get();

        return view('settings.evaluation-forms.index', compact('forms'));
    }

    public function createAdmin(): RedirectResponse
    {
        return redirect()->route('settings.document-forms.create', ['document_type' => 'evaluation']);
    }

    /**
     * Mobile (`/m/*`) and desktop share this controller — pick the matching
     * submission-detail route so a mobile evaluation never ejects the user
     * back into the desktop layout.
     */
    private function showRoute(DocumentFormSubmission $submission): string
    {
        return request()->routeIs('mobile.*')
            ? route('mobile.request.detail', $submission)
            : route('forms.submission.show', $submission);
    }

    private function authorizeOwnerOfApproved(DocumentFormSubmission $submission): void
    {
        $userId = (int) (session('user.id') ?? 0);
        $isSuper = (bool) session('user.is_super_admin', false);

        abort_unless((int) $submission->user_id === $userId || $isSuper, 403);

        $submission->load('instance');
        abort_unless($submission->effective_status === 'approved', 422, __('common.evaluation_not_ready'));
    }
}
