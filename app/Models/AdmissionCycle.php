<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdmissionCycle extends Model
{
    protected $guarded = ['id'];

    public function applicants()
    {
        return $this->hasMany(AdmissionApplicant::class, 'admission_cycle_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'exam_weight' => 'decimal:2',
            'gwa_weight' => 'decimal:2',
            'interview_weight' => 'decimal:2',
            'passing_rate' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public static function active(): ?self
    {
        return static::where('is_active', true)->where('is_archived', false)->first();
    }
}
