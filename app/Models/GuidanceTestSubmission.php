<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidanceTestSubmission extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'scores' => 'array',
            'interpretation' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }
}
