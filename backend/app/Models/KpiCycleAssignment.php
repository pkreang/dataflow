<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One assignment within a KPI cycle: which evaluator evaluates which target,
 * with what role label. `submission_id` is filled when the cycle opens —
 * it points to the draft DocumentFormSubmission the evaluator must complete.
 */
class KpiCycleAssignment extends Model
{
    use HasFactory;

    public const ROLE_SELF = 'self';
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_PEER = 'peer';

    protected $fillable = [
        'cycle_id',
        'target_user_id',
        'evaluator_user_id',
        'role',
        'submission_id',
    ];

    /** @return BelongsTo<KpiCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(KpiCycle::class, 'cycle_id');
    }

    /** @return BelongsTo<User, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_user_id');
    }

    /** @return BelongsTo<DocumentFormSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(DocumentFormSubmission::class, 'submission_id');
    }
}
