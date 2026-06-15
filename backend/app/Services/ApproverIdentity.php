<?php

namespace App\Services;

use App\Models\User;

/**
 * Resolves the current actor's approver identity — (userId, roleNames, positionId)
 * — from the web session, with a DB fallback for position_id when it isn't
 * cached in the session payload.
 *
 * Centralized so every approver-matching call site (ApprovalController,
 * MobileController, DocumentFormSubmissionController, the sidebar badge composer)
 * feeds ApprovalInstance::scopePendingForApprover identical inputs and can't drift.
 */
class ApproverIdentity
{
    /**
     * @return array{userId: int, roles: array<int, string>, positionId: int|null}
     */
    public function fromSession(): array
    {
        $userId = (int) (session('user.id') ?? 0);

        $roles = collect(session('user.roles') ?? [])
            ->map(fn ($r) => is_array($r) ? ($r['name'] ?? '') : $r)
            ->filter()
            ->values()
            ->all();

        $positionId = session('user.position_id')
            ?? ($userId ? User::query()->whereKey($userId)->value('position_id') : null);

        return [
            'userId' => $userId,
            'roles' => $roles,
            'positionId' => $positionId ? (int) $positionId : null,
        ];
    }
}
