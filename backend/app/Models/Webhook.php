<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    public const EVENTS = [
        'form.submitted',
        'form.draft.saved',
        'approval.started',
        'approval.completed',
        'approval.rejected',
        'repair.created',
        'repair.completed',
    ];

    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'field_allowlists',
        'is_active',
        'last_triggered_at',
        'last_response_status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'field_allowlists' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
            'last_response_status' => 'integer',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateSecret(): string
    {
        return bin2hex(random_bytes(24));
    }
}
