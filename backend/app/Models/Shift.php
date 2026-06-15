<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = ['code', 'name', 'start_time', 'end_time', 'break_minutes', 'color', 'is_active'];

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** end < start means the shift runs past midnight (night shift). */
    public function crossesMidnight(): bool
    {
        return $this->end_time < $this->start_time;
    }

    public function schedules()
    {
        return $this->hasMany(UserShiftSchedule::class);
    }
}
