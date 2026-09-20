<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionApplicant extends Model
{
    protected $guarded = ['id', 'exam_score', 'stanine_score', 'total_score', 'qualification_status', 'strike_count', 'submitted_at'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'submitted_at' => 'datetime', 'exam_score' => 'decimal:2', 'total_score' => 'decimal:2'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'admission_cycle_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->last_name}, {$this->first_name} {$this->middle_name}");
    }
}
