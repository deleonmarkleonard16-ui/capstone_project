<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidanceTestResponse extends Model
{
    protected $primaryKey = 'guidance_test_response_id';
    protected $guarded = ['guidance_test_response_id'];
    protected function casts(): array
    {
        return ['answers' => 'array', 'score_summary' => 'array'];
    }
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
    public function testSummaries(): array
    {
        $summary = $this->score_summary ?? [];
        $tests = $summary['tests'] ?? (isset($summary['test_type']) ? [$summary['test_type'] => $summary] : []);
        if (isset($tests['phq9']['scores']['total']) && !isset($tests['phq9']['interpretation']['severity'])) {
            $tests['phq9']['interpretation']['severity'] = app(\App\Services\GuidanceTestScoringService::class)->phqSeverity((int) $tests['phq9']['scores']['total']);
        }
        return $tests;
    }
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(GuidanceAppointment::class, 'guidance_appointment_id');
    }
}
