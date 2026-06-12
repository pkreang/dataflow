<?php

namespace App\Models;

use App\Models\Concerns\HasAutoCode;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasAutoCode, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'auto_code',
        'first_name',
        'last_name',
        'email',
        'locale',
        'theme',
        'density',
        'auth_provider',
        'external_id',
        'ldap_dn',
        'company_id',
        'branch_id',
        'password',
        'password_changed_at',
        'password_must_change',
        'avatar',
        'signature_path',
        'department_id',
        'position_id',
        'manager_id',
        'org_unit_id',
        'phone',
        'line_notify_token',
        'line_user_id',
        'remark',
        'is_active',
        'is_super_admin',
        'last_active_at',
        'home_dashboard_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'line_user_id',
    ];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password_must_change' => 'boolean',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * Public URL for the user's saved signature image, or null when none.
     * `signature_path` stores the absolute URL (matching the avatar
     * convention) so this accessor just normalises empty → null.
     */
    public function getSignatureUrlAttribute(): ?string
    {
        return ! empty($this->signature_path) ? $this->signature_path : null;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Master position (Settings → Positions). The `position` string column is kept in sync for display/API. */
    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function shiftSchedules()
    {
        return $this->hasMany(UserShiftSchedule::class);
    }

    /** The shift assigned to this user on the given date (default: today). */
    public function currentShift(?\Carbon\Carbon $at = null): ?Shift
    {
        $date = ($at ?? now())->toDateString();

        return $this->shiftSchedules()
            ->with('shift')
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->first()?->shift;
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function substitutions(): HasMany
    {
        return $this->hasMany(UserSubstitution::class, 'from_user_id');
    }

    public function homeDashboard(): BelongsTo
    {
        return $this->belongsTo(ReportDashboard::class, 'home_dashboard_id');
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(UserPasswordHistory::class);
    }

    public function canChangePasswordInApp(): bool
    {
        return \App\Services\Auth\PasswordCapabilityService::canChangePasswordInApp($this);
    }

    /**
     * Locale for queued mail / database notifications (queue workers have no session).
     */
    public function preferredLocale(): ?string
    {
        $l = $this->locale;
        if (is_string($l) && $l !== '' && in_array($l, ['th', 'en'], true)) {
            return $l;
        }

        $fallback = (string) config('app.locale', 'th');

        return in_array($fallback, ['th', 'en'], true) ? $fallback : 'th';
    }

    protected function autoCodePrefix(): string
    {
        return 'USER';
    }
}
