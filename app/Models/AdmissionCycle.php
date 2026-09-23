<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdmissionCycle extends Model
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_COMPLETED = 'Completed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_COMPLETED,
    ];

    protected $guarded = ['id'];

    public function applicants()
    {
        return $this->hasMany(AdmissionApplicant::class, 'admission_cycle_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
            'exam_weight' => 'decimal:2',
            'gwa_weight' => 'decimal:2',
            'interview_weight' => 'decimal:2',
            'passing_rate' => 'decimal:2',
            'passing_stanine' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Get the display name for the cycle, supporting both cycle_name and name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->cycle_name ?: ($this->name ?: "Cycle #{$this->id}");
    }

    /**
     * Keep name and cycle_name in sync automatically.
     */
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

    /**
     * Get the single active admission cycle.
     */
    public static function active(): ?self
    {
        return static::where('is_active', true)
            ->where(function ($q) {
                $q->where('status', self::STATUS_ACTIVE)
                  ->orWhere(function ($inner) {
                      $inner->whereNull('status')->where('is_archived', false);
                  });
            })
            ->first();
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active && $this->status === self::STATUS_ACTIVE && !$this->is_archived;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED || (bool) $this->is_archived;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT && !$this->is_active && !$this->is_archived;
    }

    /**
     * Atomically set this cycle as the ONE active cycle.
     */
    public function activate(): void
    {
        abort_if($this->isCompleted(), 409, 'A completed/archived cycle cannot be reactivated.');

        DB::transaction(function () {
            // Deactivate and archive any previously active cycles so the new cycle starts fresh
            static::query()->where('id', '!=', $this->id)->update([
                'is_active' => false,
                'status' => DB::raw("CASE WHEN status = 'Active' THEN 'Completed' ELSE status END"),
                'is_archived' => DB::raw("CASE WHEN status = 'Active' THEN 1 ELSE is_archived END"),
            ]);

            $this->update([
                'is_active' => true,
                'is_archived' => false,
                'status' => self::STATUS_ACTIVE,
            ]);
        });
    }

    /**
     * Complete and archive this cycle (locks edits while preserving historical data).
     */
    public function completeAndArchive(): void
    {
        $this->update([
            'is_active' => false,
            'is_archived' => true,
            'status' => self::STATUS_COMPLETED,
        ]);
    }
}
