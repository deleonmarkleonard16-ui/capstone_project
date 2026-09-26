<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GuidanceAppointment extends Model
{
    public const STATUSES = ['Pending Payment', 'Receipt Uploaded', 'Approved', 'In-Progress', 'Completed'];
    protected $primaryKey = 'guidance_appointment_id';
    protected $guarded = ['guidance_appointment_id'];

    protected $attributes = [
        'student_status' => 'student',
    ];

    public function securityIncidents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GuidanceSecurityIncident::class, 'guidance_appointment_id');
    }
    public function securityLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GuidanceTestSecurityLog::class, 'guidance_appointment_id');
    }
    protected function casts(): array
    {
        return ['strike_count' => 'integer', 'terminated_at' => 'datetime', 'appointment_at' => 'datetime', 'verified_at' => 'datetime', 'started_at' => 'datetime', 'expires_at' => 'datetime', 'test_types' => 'array', 'draft_answers' => 'array', 'section_history' => 'array', 'section_index' => 'integer', 'is_archived' => 'boolean', 'archived_at' => 'datetime', 'or_date' => 'date'];
    }
    public function batch(): BelongsTo { return $this->belongsTo(GuidanceTestBatch::class, 'batch_id'); }
    public function sourceBatch(): BelongsTo { return $this->belongsTo(GuidanceTestBatch::class, 'source_batch_id'); }
    public function testTypes(): array
    {
        return $this->test_types ?: [$this->test_type];
    }
    public function awaitsScoringConfiguration(): bool
    {
        foreach ($this->testTypes() as $test) {
            if (!in_array($test, ['dass21', 'phq9', 'gad7'], true) && config("guidance.$test.items", 0) < 1) return true;
        }
        return false;
    }
    public function testLabel(): string
    {
        if ($this->test_category === 'psychological' || in_array($this->test_type, ['dass21', 'phq9', 'gad7'], true)) return 'Psychological Assessment';
        return implode(' / ', array_map(fn ($test) => \App\Services\GuidanceTestScoringService::LABELS[$test] ?? strtoupper($test), $this->testTypes()));
    }
    public function categoryLabel(): string
    {
        return \App\Services\GuidanceCategories::LABELS[$this->test_category]
            ?? \App\Services\GuidanceCategories::LABELS[$this->test_type]
            ?? $this->testLabel();
    }
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }
    public function qrCode(): HasOne
    {
        return $this->hasOne(GuidanceTestQrCode::class, 'guidance_appointment_id');
    }
    public function response(): HasOne
    {
        return $this->hasOne(GuidanceTestResponse::class, 'guidance_appointment_id');
    }
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getOfficialFeeAttribute(): float
    {
        if ($this->serviceRequest) {
            return $this->serviceRequest->official_fee;
        }
        $category = $this->test_category ?? $this->test_type ?? 'psychological';
        return (float) (\App\Support\RequestFees::PESOS[$category] ?? 60.00);
    }

    public function getOfficialFeeFormattedAttribute(): string
    {
        return '₱' . number_format($this->official_fee, 2);
    }

    public function getReferenceAttribute(): string
    {
        return $this->attributes['request_code'] ?? ($this->serviceRequest?->reference ?? '');
    }

    public function courseLabel(): string
    {
        $course = $this->origin_course ?? $this->batch?->course ?? $this->sourceBatch?->course ?? $this->serviceRequest?->course;
        return $course ? (\App\Support\CourseCatalog::allOptions()[$course] ?? $course) : 'Not specified';
    }

    public function getFirstNameAttribute(): string
    {
        return $this->applicant?->first_name ?? ($this->serviceRequest?->first_name ?? '');
    }

    public function getLastNameAttribute(): string
    {
        return $this->applicant?->last_name ?? ($this->serviceRequest?->last_name ?? '');
    }

    public function getMiddleNameAttribute(): ?string
    {
        return $this->applicant?->middle_name ?? ($this->serviceRequest?->middle_name ?? null);
    }

    public function getStudentNumberAttribute(): ?string
    {
        return $this->attributes['student_id_number'] ?? ($this->serviceRequest?->student_number ?? null);
    }

    public function getPurposeAttribute(): string
    {
        return $this->serviceRequest?->purpose ?? ($this->batch?->reason_for_request ?? 'Guidance Assessment');
    }

    public function getTestsAttribute(): array
    {
        return $this->testTypes();
    }

    public function hasReceipt(): bool
    {
        return $this->receipt_data !== null || $this->serviceRequest?->hasReceipt();
    }

    public function getReceiptDataAttribute(): ?string
    {
        return $this->attributes['receipt_data'] ?? $this->serviceRequest?->receipt_data;
    }

    public function getReceiptMimeTypeAttribute(): ?string
    {
        return $this->attributes['receipt_mime_type'] ?? $this->serviceRequest?->receipt_mime_type;
    }

    public function getReceiptOriginalNameAttribute(): ?string
    {
        return $this->attributes['receipt_original_name'] ?? $this->serviceRequest?->receipt_original_name;
    }
}
