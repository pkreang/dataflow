<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasAutoCode;
    use HasFactory;

    protected $fillable = [
        'auto_code',
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected function autoCodePrefix(): string
    {
        return 'POS';
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Resolve FK + denormalized name for the users.position column (API / display).
     *
     * @return array{id: ?int, name: ?string}
     */
    public static function labelsForUser(mixed $positionId): array
    {
        if ($positionId === null || $positionId === '') {
            return ['id' => null, 'name' => null];
        }

        $p = static::query()->find((int) $positionId);

        return [
            'id' => $p?->id,
            'name' => $p?->name,
        ];
    }
}
