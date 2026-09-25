<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionSession extends Model
{
    public const STATUS_SCHEDULED   = 'Scheduled';
    public const STATUS_IN_PROGRESS = 'In-Progress';
    public const STATUS_COMPLETED   = 'Completed';

    public const STATUSES = [
        self::STATUS_SCHEDULED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_time'   => 'datetime',
            'start_number' => 'integer',
            'end_number'   => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'admission_cycle_id');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(AdmissionApplicant::class, 'admission_session_id');
    }

    // ── Lifecycle & Status Inspection ─────────────────────────────────────────

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function allApplicantsSubmitted(): bool
    {
        $total = $this->applicants()->count();
        if ($total === 0) {
            return false;
        }

        return $this->applicants()->whereNull('submitted_at')->count() === 0;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED || $this->allApplicantsSubmitted();
    }

    public function isOpen(): bool
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return false;
        }

        if ($this->allApplicantsSubmitted()) {
            return false;
        }

        if (!$this->start_time) {
            return false;
        }

        return now()->gte($this->start_time->subMinutes(30));
    }

    public function isCheckinOpen(): bool
    {
        return $this->isOpen();
    }

    public function submittedCount(): int
    {
        return $this->applicants()->whereNotNull('submitted_at')->count();
    }

    public function totalAssigned(): int
    {
        return $this->applicants()->count();
    }

    public function submissionPercentage(): float
    {
        $total = $this->totalAssigned();
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->submittedCount() / $total) * 100, 1);
    }

    public function checkinUrl(): string
    {
        $baseUrl = rtrim((string) config('app.qr_public_url', config('app.url')), '/');
        $path = route('admission.checkin.show', $this->qr_token, false);

        return $baseUrl . $path;
    }

    public function qrImageUrl(): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='
            . rawurlencode($this->checkinUrl());
    }
}
