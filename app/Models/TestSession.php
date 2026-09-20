<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'exam_date',
        'start_time',
        'end_time',
        'qr_token',
        'qr_code_path',
        'started_at',
        'duration_minutes',
        'room',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'started_at' => 'datetime',
        ];
    }

    public function sessionApplicants(): HasMany
    {
        return $this->hasMany(SessionApplicant::class);
    }

    public function answerSheets(): HasMany
    {
        return $this->hasMany(AnswerSheet::class);
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function answerKey(): HasOne
    {
        return $this->hasOne(AnswerKey::class);
    }

    public function statusLookup(): BelongsTo
    {
        return $this->belongsTo(TestSessionStatus::class, 'test_session_status_id');
    }

    public function getAttribute($key)
    {
        if ($key === 'status') {
            return $this->statusLookup?->slug ?? $this->statusLookup?->name;
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'status') {
            $status = is_numeric($value)
                ? TestSessionStatus::find($value)
                : TestSessionStatus::query()
                    ->where('slug', strtolower((string) $value))
                    ->orWhere('name', $value)
                    ->first();

            $this->attributes['test_session_status_id'] = $status?->id;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function examStartsAt(): Carbon
    {
        if ($this->started_at) {
            return $this->started_at->copy();
        }

        return Carbon::parse($this->exam_date->format('Y-m-d').' '.$this->start_time);
    }

    public function scheduledStartsAt(): Carbon
    {
        return Carbon::parse($this->exam_date->format('Y-m-d').' '.$this->start_time);
    }

    public function scheduledEndsAt(): Carbon
    {
        return Carbon::parse($this->exam_date->format('Y-m-d').' '.$this->end_time);
    }

    public function examEndsAt(): Carbon
    {
        return $this->examStartsAt()->copy()->addMinutes($this->duration_minutes);
    }

    public function isStarted(): bool
    {
        return $this->started_at !== null && $this->status === 'in_progress';
    }

    public function isFinished(): bool
    {
        return $this->status === 'completed' || now()->greaterThanOrEqualTo($this->examEndsAt());
    }

    public function finalizeExpiredAnswerSheets(): void
    {
        if (now()->lt($this->examEndsAt())) {
            return;
        }

        $submittedAt = $this->examEndsAt();

        foreach ($this->sessionApplicants()->where('is_present', true)->get() as $assignment) {
            $this->answerSheets()->firstOrCreate(
                [
                    'test_session_id' => $this->id,
                    'applicant_id' => $assignment->applicant_id,
                ],
                [
                    'started_at' => $this->started_at ?? $this->examStartsAt(),
                ]
            );
        }

        $this->answerSheets()
            ->where(function ($query): void {
                $query->whereNull('submitted_at')
                    ->orWhere('is_locked', false);
            })
            ->get()
            ->each(function (AnswerSheet $sheet) use ($submittedAt): void {
                $sheet->forceFill([
                    'submitted_at' => $sheet->submitted_at ?? $submittedAt,
                    'is_locked' => true,
                ])->save();
            });

        if ($this->status !== 'completed') {
            $this->update(['status' => 'completed']);
        }
    }

    public function canBeStarted(): bool
    {
        return ! $this->isStarted() && $this->status !== 'completed';
    }

    public function isCheckinOpen(): bool
    {
        $now = now();

        return $now->betweenIncluded($this->exam_date->copy()->startOfDay(), $this->scheduledEndsAt());
    }

    public function checkinUrl(): string
    {
        $baseUrl = rtrim((string) config('app.qr_public_url', config('app.url')), '/');
        $path = route('checkin.show', $this->qr_token, false);

        return $baseUrl.$path;
    }

    public function qrImageUrl(): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='
            .rawurlencode($this->checkinUrl());
    }
}
