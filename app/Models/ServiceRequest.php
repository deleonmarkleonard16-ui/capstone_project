<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'student_number' => '',
        'purpose' => '',
    ];

    protected function casts(): array
    {
        return [
            'tests' => 'array',
            'birthdate' => 'date',
            'scheduled_at' => 'datetime',
            'expires_at' => 'datetime',
            'archived_at' => 'datetime',
            'or_date' => 'date',
            'claimed_at' => 'datetime',
        ];
    }

    public const SERVICES = ['testing' => 'Testing Request', 'good-moral' => 'Good Moral Request', 'exit-form' => 'Exit Form'];
    public const STATUSES = ['pending', 'approved', 'proof_review', 'processing', 'ready', 'scheduled', 'completed', 'declined', 'cancelled', 'void'];

    /**
     * Statuses that represent an in-flight / active request.
     * A new submission from the same student for the same service is blocked
     * while any prior request is in one of these statuses.
     */
    public const ACTIVE_STATUSES = ['pending', 'proof_review', 'approved', 'processing', 'ready', 'scheduled'];

    /**
     * Check whether the given student already has an active request for
     * the specified service (good-moral, exit-form, or testing).
     *
     * @param  string  $studentNumber  Normalised student ID (e.g. "23-SC-4143").
     * @param  string  $service        Service key from self::SERVICES.
     */
    public static function hasActiveRequest(string $studentNumber, string $service): bool
    {
        if ($studentNumber === '') {
            return false; // Cannot reliably deduplicate without a student number
        }

        return static::where('student_number', $studentNumber)
            ->where('service', $service)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNull('archived_at')
            ->exists();
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->academic_year) $model->academic_year = GuidanceSetting::valueOf('academic_year');
            if (! $model->expires_at) {
                $model->expires_at = now()->addDays(5);
            }
        });
        static::created(function (self $model): void {
            if ($model->batch_id) return;
            $modules = $model->service === 'testing' ? ($model->tests ?: []) : [$model->service];
            foreach (array_intersect($modules, ['psychological', 'personality', 'career', 'good-moral', 'exit-form']) as $module) {
                GuidanceRequestNotification::create(['service_request_id' => $model->getKey(), 'module' => $module]);
            }
        });
    }

    public function testSubmissions()
    {
        return $this->hasMany(GuidanceTestSubmission::class);
    }

    public function guidanceAppointments()
    {
        return $this->hasMany(GuidanceAppointment::class);
    }

    public function batch()
    {
        return $this->belongsTo(GuidanceTestBatch::class, 'batch_id');
    }
    public function courseLabel(): string
    {
        return \App\Support\CourseCatalog::label($this->course, $this->legacy_course);
    }

    public function getOfficialFeeAttribute(): ?float
    {
        $fee = \App\Support\RequestFees::total($this->service, $this->tests ?? [], $this->copies ?? 1);
        return $fee !== null ? (float) $fee : null;
    }

    public function getOfficialFeeFormattedAttribute(): string
    {
        return $this->official_fee !== null ? '₱' . number_format($this->official_fee, 2) : 'Free';
    }

    public function checkAndApplyExpiration(): bool
    {
        if ($this->guidanceAppointments()->exists()) return false;
        if ($this->status === 'void') {
            return true;
        }

        // Only void if it is still pending action / proof upload / pending approval and 5 days elapsed
        if (in_array($this->status, ['pending', 'approved'], true) && $this->expires_at && now()->greaterThan($this->expires_at)) {
            $this->update([
                'status' => 'void',
                'staff_message' => 'Request voided automatically: Required action was not completed within the 5-day window.',
                'archived_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    public static function expirePendingRequests(): int
    {
        return static::whereIn('status', ['pending', 'approved'])
            ->whereDoesntHave('guidanceAppointments')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update([
                'status' => 'void',
                'staff_message' => 'Request voided automatically: Required action was not completed within the 5-day window.',
                'archived_at' => now(),
            ]);
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    public function timeRemainingText(): string
    {
        if ($this->isVoid()) {
            return 'Void (Expired)';
        }

        if (! $this->expires_at || ! in_array($this->status, ['pending', 'approved'], true)) {
            return 'Active';
        }

        if (now()->greaterThan($this->expires_at)) {
            return 'Expired';
        }

        $diff = now()->diff($this->expires_at);
        if ($diff->days > 0) {
            return "{$diff->days} day(s) {$diff->h} hr(s) left";
        }

        return "{$diff->h} hr(s) {$diff->i} min(s) left";
    }

    public static function newReference(string $service): string
    {
        $prefix = match ($service) {
            'good-moral' => 'GM-',
            'exit-form' => 'EF-',
            default => 'TR-',
        };

        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $reference = $prefix;
            for ($i = 0; $i < 8; $i++) {
                $reference .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public static function referenceRules(): array
    {
        return ['required', 'string', 'max:36', 'regex:/^(?:G-[A-Z0-9]{4}|(?:TR|GM|EF)-[A-Z2-9]{8}|TR-[a-f0-9]{32}|[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$/i'];
    }

    public function isPsychological(): bool
    {
        return $this->service === 'testing' && in_array('psychological', $this->tests ?? [], true);
    }

    public function hasReceipt(): bool
    {
        if ($this->attributes['receipt_data'] ?? null) {
            return true;
        }

        if (!empty($this->proof_path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($this->proof_path)) {
            return true;
        }

        return $this->guidanceAppointments()->where(function ($q) {
            $q->whereNotNull('receipt_data')->orWhereNotNull('payment_slip_path');
        })->exists();
    }

    public function getReceiptDataAttribute(): ?string
    {
        if ($this->attributes['receipt_data'] ?? null) {
            return $this->attributes['receipt_data'];
        }

        if (!empty($this->proof_path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($this->proof_path)) {
            return \Illuminate\Support\Facades\Storage::disk('local')->get($this->proof_path);
        }

        $appointment = $this->guidanceAppointments()
            ->where(fn ($q) => $q->whereNotNull('receipt_data')->orWhereNotNull('payment_slip_path'))
            ->first();

        return $appointment?->receipt_data;
    }

    public function getReceiptMimeTypeAttribute(): ?string
    {
        if ($this->attributes['receipt_mime_type'] ?? null) {
            return $this->attributes['receipt_mime_type'];
        }

        if (!empty($this->proof_path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($this->proof_path)) {
            return \Illuminate\Support\Facades\Storage::disk('local')->mimeType($this->proof_path) ?: 'application/octet-stream';
        }

        $appointment = $this->guidanceAppointments()
            ->where(fn ($q) => $q->whereNotNull('receipt_data')->orWhereNotNull('payment_slip_path'))
            ->first();

        return $appointment?->receipt_mime_type ?? 'application/octet-stream';
    }

    public function getReceiptOriginalNameAttribute(): ?string
    {
        if ($this->attributes['receipt_original_name'] ?? null) {
            return $this->attributes['receipt_original_name'];
        }

        if (!empty($this->proof_path)) {
            return basename($this->proof_path);
        }

        $appointment = $this->guidanceAppointments()
            ->where(fn ($q) => $q->whereNotNull('receipt_data')->orWhereNotNull('payment_slip_path'))
            ->first();

        return $appointment?->receipt_original_name ?? 'receipt';
    }
}
