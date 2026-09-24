<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionApplicant extends Model
{
    public const STATUS_PENDING      = 'Pending';
    public const STATUS_QUALIFIED    = 'Qualified';
    public const STATUS_NOT_QUALIFIED = 'Not Qualified';

    /**
     * Computed / integrity-protected columns that must never be mass-assignable.
     * exam_score, stanine_score, total_score, qualification_status, strike_count,
     * and submitted_at are written only through AdmissionScoringService.
     */
    protected $guarded = [
        'id',
        'exam_score',
        'stanine_score',
        'total_score',
        'qualification_status',
        'strike_count',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'answers'          => 'array',
            'submitted_at'     => 'datetime',
            'exam_score'       => 'decimal:2',
            'gwa'              => 'decimal:2',
            'interview_score'  => 'decimal:2',
            'total_score'      => 'decimal:2',
            'stanine_score'    => 'integer',
            'strike_count'     => 'integer',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AdmissionCycle::class, 'admission_cycle_id');
    }

    public function admissionSession(): BelongsTo
    {
        return $this->belongsTo(AdmissionSession::class, 'admission_session_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        $middle = $this->middle_name ? ' ' . $this->middle_name : '';
        return trim("{$this->last_name}, {$this->first_name}{$middle}");
    }

    /**
     * Backwards-compatible accessor: special_group mirrors 4ps_osy_ip_pwd_sp.
     */
    public function getSpecialGroupAttribute($value): ?string
    {
        return $value ?: ($this->attributes['4ps_osy_ip_pwd_sp'] ?? null);
    }

    public function setSpecialGroupAttribute($value): void
    {
        $this->attributes['special_group']     = $value;
        $this->attributes['4ps_osy_ip_pwd_sp'] = $value;
    }
}
