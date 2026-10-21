<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_name', 'user_role', 'action', 'method', 'route',
        'subject', 'response_status', 'ip_address', 'created_at',
    ];

    protected function casts(): array
    {
        return ['subject' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
