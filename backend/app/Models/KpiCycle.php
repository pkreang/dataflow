<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One KPI evaluation cycle — a bundle of evaluations against one form during
 * a defined period. Created in `draft`, opened to spawn drafts for every
 * assignment, closed when the period ends.
 */
class KpiCycle extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name',
        'form_id',
        'period_start',
        'period_end',
        'status',
        'opened_at',
        'closed_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DocumentForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(DocumentForm::class, 'form_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<KpiCycleAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(KpiCycleAssignment::class, 'cycle_id');
    }
}
