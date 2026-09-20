<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionApplicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_session_id',
        'applicant_id',
        'scanned_at',
        'is_present',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'is_present' => 'boolean',
        ];
    }

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}
