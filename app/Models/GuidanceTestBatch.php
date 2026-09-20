<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuidanceTestBatch extends Model
{
    protected $primaryKey = 'batch_id';
    protected $guarded = ['batch_id'];
    protected $hidden = ['batch_token'];
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->academic_year) $model->academic_year = GuidanceSetting::valueOf('academic_year');
        });
    }
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'archived_at' => 'datetime'];
    }
    public function appointments(): HasMany
    {
        return $this->hasMany(GuidanceAppointment::class, 'batch_id');
    }
    public function makeupAppointments(): HasMany
    {
        return $this->hasMany(GuidanceAppointment::class, 'source_batch_id');
    }
    public function documentRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'batch_id');
    }
    public function courseLabel(): string
    {
        return \App\Support\CourseCatalog::label($this->course, $this->legacy_course);
    }

    public function readyCount(): int
    {
        return $this->appointments->where('attendance_status', 'Ready')->count();
    }

    public function totalCount(): int
    {
        return $this->appointments->count();
    }

    public function isArchived(): bool
    {
        return $this->status === 'Completed' || $this->archived_at !== null;
    }
}
