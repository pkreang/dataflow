<?php

namespace App\Services;

use App\Models\DocumentFormSubmission;

class LeaveValidationService
{
    /**
     * Throw a RuntimeException if the user already has a pending/approved leave
     * submission for the same form that overlaps the requested date range.
     *
     * Error key is a bare string matching a common.php translation key so the
     * controller can do __('common.' . $e->getMessage()).
     *
     * @throws \RuntimeException with key 'leave_date_range_invalid' or 'leave_overlap_error'
     */
    public function checkOverlap(
        int $userId,
        int $formId,
        string $dateFrom,
        string $dateTo,
        ?int $excludeId = null
    ): void {
        if ($dateFrom > $dateTo) {
            throw new \RuntimeException('leave_date_range_invalid');
        }

        // Only block on submissions that are still active (submitted status) and
        // whose approval instance has not been rejected or returned.
        $exists = DocumentFormSubmission::query()
            ->where('user_id', $userId)
            ->where('form_id', $formId)
            ->where('status', 'submitted')
            ->whereHas('instance', fn ($q) => $q->whereIn('status', ['pending', 'approved']))
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->whereRaw(
                "json_extract(payload, '$.date_from') <= ? AND json_extract(payload, '$.date_to') >= ?",
                [$dateTo, $dateFrom]
            )
            ->exists();

        if ($exists) {
            throw new \RuntimeException('leave_overlap_error');
        }
    }
}
