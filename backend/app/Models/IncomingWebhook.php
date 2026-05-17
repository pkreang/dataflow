<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IncomingWebhook extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'token',
        'document_form_id',
        'is_active',
        'last_received_at',
        'last_payload',
        'received_count',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'last_received_at' => 'datetime',
        'last_payload' => 'array',
        'received_count' => 'int',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(DocumentForm::class, 'document_form_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function generateSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'webhook';
        return $base.'-'.Str::lower(Str::random(6));
    }
}
