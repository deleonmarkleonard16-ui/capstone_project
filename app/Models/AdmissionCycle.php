<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdmissionCycle extends Model
{
    // ── Status constants ──────────────────────────────────────────────────────
    public const STATUS_DRAFT       = 'Draft';
    public const STATUS_ACTIVE      = 'Active';
    public const STATUS_COMPLETED   = 'Completed';
    public const STATUS_MAINTENANCE = 'Maintenance';  // Spec Req §1: maintenance mode
    public const STATUS_ARCHIVED    = 'Archived';     // Spec alias for Completed

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
        self::STATUS_MAINTENANCE,
        self::STATUS_ARCHIVED,
    ];

    protected $guarded = ['id'];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function applicants()
    {
        return $this->hasMany(AdmissionApplicant::class, 'admission_cycle_id');
    }

    public function sessions()
    {
        return $this->hasMany(AdmissionSession::class, 'admission_cycle_id');
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'is_active'       => 'boolean',
            'is_archived'     => 'boolean',
            'exam_weight'     => 'decimal:2',
            'gwa_weight'      => 'decimal:2',
            'interview_weight'=> 'decimal:2',
            'passing_rate'    => 'decimal:2',
            'passing_stanine' => 'integer',
            'start_date'      => 'date',
            'end_date'        => 'date',
        ];
    }

    // ── Accessors / Mutators ──────────────────────────────────────────────────

    /** Unified display name regardless of which column was populated. */
    public function getDisplayNameAttribute(): string
    {
        return $this->cycle_name ?: ($this->name ?: "Cycle #{$this->id}");
    }

    /** Keep name and cycle_name in sync automatically. */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value;
        if (empty($this->attributes['cycle_name'])) {
            $this->attributes['cycle_name'] = $value;
        }
    }

    public function setCycleNameAttribute($value): void
    {
        $this->attributes['cycle_name'] = $value;
        if (empty($this->attributes['name'])) {
            $this->attributes['name'] = $value;
        }
    }

    public function getNameAttribute($value): string
    {
        return $value ?: ($this->attributes['cycle_name'] ?? '');
    }

    // ── Scopes & Static Queries ───────────────────────────────────────────────

    /**
     * Return the single active cycle, or null if none exists.
     * Uses is_active as the single source of truth; activate() still sets
     * status='Active' for display, but gating does not require it.
     */
    public static function active(): ?self
    {
        return static::where('is_active', true)
            ->where('is_archived', false)
            ->first();
    }

    // ── State Predicates ──────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return (bool) $this->is_active
            && $this->status === self::STATUS_ACTIVE
            && !$this->is_archived;
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_ARCHIVED], true)
            || (bool) $this->is_archived;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT
            && !$this->is_active
            && !$this->is_archived;
    }

    public function isMaintenance(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    // ── Lifecycle Actions ─────────────────────────────────────────────────────

    /**
     * Atomically set this cycle as the ONE active cycle.
     * Any previously Active cycle is moved to Completed.
     */
    public function activate(): void
    {
        abort_if($this->isCompleted(), 409, 'A completed/archived cycle cannot be reactivated.');

        DB::transaction(function () {
            static::query()->where('id', '!=', $this->id)->update([
                'is_active'   => false,
                'status'      => DB::raw("CASE WHEN status = 'Active' THEN 'Completed' ELSE status END"),
                'is_archived' => DB::raw("CASE WHEN status = 'Active' THEN 1 ELSE is_archived END"),
            ]);

            $this->update([
                'is_active'   => true,
                'is_archived' => false,
                'status'      => self::STATUS_ACTIVE,
            ]);
        });
    }

    /**
     * Toggle Maintenance mode on/off. Cycle remains visible but is locked
     * from new applicant encoding while in maintenance.
     */
    public function toggleMaintenance(): void
    {
        abort_if($this->isCompleted(), 409, 'Cannot toggle maintenance on a completed cycle.');

        $newStatus = $this->isMaintenance() ? self::STATUS_ACTIVE : self::STATUS_MAINTENANCE;
        $this->update([
            'status'    => $newStatus,
            'is_active' => $newStatus === self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Complete and archive this cycle (locks edits while preserving historical data).
     */
    public function completeAndArchive(): void
    {
        $this->update([
            'is_active'   => false,
            'is_archived' => true,
            'status'      => self::STATUS_COMPLETED,
        ]);
    }
}
