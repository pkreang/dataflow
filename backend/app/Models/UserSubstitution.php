<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserSubstitution extends Model
{
    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'starts_at',
        'ends_at',
        'reason',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * ตรวจว่า toUserId เป็น active substitute ของ fromUserId ณ เวลาที่กำหนด
     */
    public static function activeSubstituteFor(int $fromUserId, int $toUserId, Carbon $at): bool
    {
        return static::query()
            ->where('from_user_id', $fromUserId)
            ->where('to_user_id', $toUserId)
            ->where('is_active', true)
            ->where('starts_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at))
            ->exists();
    }

    /**
     * คืน to_user_id ของ active substitute ของ fromUserId ณ เวลาที่กำหนด
     */
    public static function findActiveSubstitute(int $fromUserId, Carbon $at): ?int
    {
        $sub = static::query()
            ->where('from_user_id', $fromUserId)
            ->where('is_active', true)
            ->where('starts_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at))
            ->first();

        return $sub?->to_user_id;
    }
}
