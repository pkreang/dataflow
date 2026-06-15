<?php

namespace App\Services;

use App\Models\DocumentForm;
use App\Models\DocumentFormSubmission;

/**
 * Resolves which evaluation form applies to a parent submission.
 *
 * Priority: an active evaluation form whose `target_document_types` contains
 * the parent's `document_type` → fallback to the `evaluation_default` form.
 * Returns null when no usable (active) evaluation form exists — callers must
 * handle that case instead of assuming a form is always available.
 *
 * Registered as a singleton so the per-document-type cache is shared across a
 * request — list pages render many rows of the same parent document type.
 */
class EvaluationFormResolver
{
    /** @var array<string, DocumentForm|null> */
    private array $cache = [];

    public function resolveFor(DocumentFormSubmission $submission): ?DocumentForm
    {
        $docType = $submission->form?->document_type;
        $key = $docType ?? '__none__';

        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->lookup($docType);
        }

        return $this->cache[$key];
    }

    public function hasFormFor(DocumentFormSubmission $submission): bool
    {
        return $this->resolveFor($submission) instanceof DocumentForm;
    }

    private function lookup(?string $docType): ?DocumentForm
    {
        if ($docType) {
            $specific = DocumentForm::query()
                ->where('document_type', 'evaluation')
                ->where('is_active', true)
                ->whereJsonContains('target_document_types', $docType)
                ->first();

            if ($specific) {
                return $specific;
            }
        }

        return DocumentForm::query()
            ->where('form_key', 'evaluation_default')
            ->where('is_active', true)
            ->first();
    }
}
