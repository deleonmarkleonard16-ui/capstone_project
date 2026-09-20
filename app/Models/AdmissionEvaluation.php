<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionEvaluation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'exam_score' => 'decimal:2',
            'exam_percentage' => 'decimal:2',
            'gwa' => 'decimal:2',
            'gwa_percentage' => 'decimal:2',
            'interview_score' => 'decimal:2',
            'interview_percentage' => 'decimal:2',
            'total_marks' => 'decimal:2',
            'rank' => 'integer',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }
}
