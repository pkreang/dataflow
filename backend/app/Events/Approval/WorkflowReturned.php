<?php

namespace App\Events\Approval;

use App\Models\ApprovalInstance;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when an approver sends a request back instead of approving/rejecting.
 *
 * @property-read string $destination 'requester' | 'previous_step'
 */
class WorkflowReturned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly ApprovalInstance $instance,
        public readonly string $destination,
        public readonly int $actorUserId,
        public readonly string $comment,
    ) {}
}
