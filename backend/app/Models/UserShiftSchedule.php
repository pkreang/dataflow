<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserShiftSchedule extends Model
{
    protected $fillable = ['user_id', 'shift_id', 'work_days', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'work_days' => 'array',
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }
}
