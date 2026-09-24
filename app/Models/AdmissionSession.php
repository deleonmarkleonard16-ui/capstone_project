<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'exam_date'   => 'date',
            'start_time'  => 'string',
            'end_time'    => 'string',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'admission_cycle_id');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(AdmissionApplicant::class, 'admission_session_id');
    }

    public function isCheckinOpen(): bool
    {
        if (!$this->exam_date || !$this->start_time || !$this->end_time) {
            return false;
        }
        $now = now();
        $date = $this->exam_date->format('Y-m-d');
        $open = \Carbon\Carbon::parse("{$date} {$this->start_time}");
        $close = \Carbon\Carbon::parse("{$date} {$this->end_time}");
        return $now->gte($open->subMinutes(30)) && $now->lte($close);
    }
}
