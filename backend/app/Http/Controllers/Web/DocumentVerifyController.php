<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DocumentFormSubmission;
use Illuminate\Contracts\View\View;

/**
 * Public document verification page (no auth). Scan the QR on a printed/shared
 * document → lands here → confirms the document exists in the system + shows
 * minimal non-sensitive metadata (ref, form, status, date, requester).
 * Lookup is by an opaque per-submission verify_token (non-enumerable).
 */
class DocumentVerifyController extends Controller
{
    public function show(string $token): View
    {
        $submission = DocumentFormSubmission::query()
            ->where('verify_token', $token)
            ->first();

        abort_unless($submission, 404);

        return view('verify.show', ['submission' => $submission]);
    }
}
