<?php

namespace App\Support;

use App\Models\ApprovalWorkflow;
use App\Models\DocumentType;
use Illuminate\Support\Collection;

/**
 * Document types shown for org_unit ↔ workflow bindings.
 * Union of active master document types and active workflows (with document_type).
 */
final class WorkflowDocumentTypes
{
    public static function forBindings(): Collection
    {
        $fromMaster = DocumentType::allActive()->pluck('code');

        $fromWorkflows = ApprovalWorkflow::query()
            ->where('is_active', true)
            ->whereNotNull('document_type')
            ->where('document_type', '!=', '')
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type');

        return $fromMaster
            ->merge($fromWorkflows)
            ->unique()
            ->filter()
            ->sort()
            ->values();
    }
}
