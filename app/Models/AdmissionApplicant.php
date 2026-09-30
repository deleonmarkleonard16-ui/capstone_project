<?php

namespace App\Models;

use App\Support\CourseCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'checked_in_at'    => 'datetime',
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

    public function securityLogs(): HasMany
    {
        return $this->hasMany(GuidanceTestSecurityLog::class, 'applicant_id');
    }

    // ── Attendance & Monitor Helpers ──────────────────────────────────────────

    public function getComputedAttendanceStatusAttribute(): string
    {
        if ($this->submitted_at) {
            return 'Submitted';
        }
        $session = $this->admissionSession;
        if ($session && $session->status === AdmissionSession::STATUS_IN_PROGRESS) {
            if ($this->attendance_status === 'In-Progress' || $this->checked_in_at) {
                return 'In-Progress';
            }
        }
        if ($this->attendance_status === 'Ready' || $this->checked_in_at) {
            return 'Ready';
        }
        return 'Absent';
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

    public function getFirstCourseChoiceAttribute(): ?string
    {
        return $this->attributes['course_choice'] ?? null;
    }

    public function setFirstCourseChoiceAttribute($value): void
    {
        $this->attributes['course_choice'] = $value;
    }

    /**
     * CAT Score Percentage = (Exam Score / Cycle Total Items) * 100
     */
    public function getCatScorePercentageAttribute(): ?float
    {
        if ($this->exam_score === null) {
            return null;
        }
        $cycle = $this->cycle ?? $this->admissionSession?->cycle;
        $totalItems = max(1, (int) ($cycle?->total_items ?: 80));
        return round(((float) $this->exam_score / $totalItems) * 100, 2);
    }

    /**
     * TOTAL (%) = (CAT Score % * Cycle Exam Weight / 100) + (GWA * Cycle GWA Weight / 100) + (Interview Score * Cycle Interview Weight / 100)
     */
    public function getCalculatedTotalAttribute(): ?float
    {
        if ($this->total_score !== null) {
            return (float) $this->total_score;
        }

        $cycle = $this->cycle ?? $this->admissionSession?->cycle;
        if (!$cycle) {
            return null;
        }

        $catPercent = $this->cat_score_percentage;
        if ($catPercent === null || $this->gwa === null || $this->interview_score === null) {
            return null;
        }

        $examWeight = (float) ($cycle->exam_weight ?: 60);
        $gwaWeight = (float) ($cycle->gwa_weight ?: 20);
        $interviewWeight = (float) ($cycle->interview_weight ?: 20);

        return round(
            ($catPercent * ($examWeight / 100)) +
            ((float) $this->gwa * ($gwaWeight / 100)) +
            ((float) $this->interview_score * ($interviewWeight / 100)),
            2
        );
    }

    /**
     * Auto-Qualifying Rules & Dual Choice Conflict Handling:
     * - Non-Board Programs: Stanine == 3 -> Passed
     * - Board & Non-Board Programs: Stanine >= 4 -> Passed
     * - 1st Choice Priority: Always assign 1st Choice if qualified.
     * - Dual Choice Conflict Handling (Stanine == 3):
     *   * If 1st Choice is Board and 2nd Choice is Non-Board:
     *     Mark 1st Choice in soft light red (Not Qualified) and highlight 2nd Choice in soft green (Qualified).
     *     Remarks = "Passed (2nd Choice)".
     *   * If both 1st and 2nd Choices are Board Programs and Stanine == 3: Remarks = "Failed".
     */
    public function getQualificationEvaluationAttribute(): array
    {
        $cycle = $this->cycle ?? $this->admissionSession?->cycle;
        $boardCutoff = $cycle ? $cycle->getBoardCutoff() : 4;
        $nonBoardCutoff = $cycle ? $cycle->getNonBoardCutoff() : 3;

        $c1 = $this->course_choice;
        $c2 = $this->second_course_choice;

        $isC1Board = CourseCatalog::isBoardProgram($c1);
        $isC2Board = CourseCatalog::isBoardProgram($c2);

        $stanine = $this->stanine_score;

        if (!$this->submitted_at && $stanine === null) {
            $session = $this->admissionSession;
            $label = ($session && $session->status === 'Scheduled') ? 'Scheduled' : 'Pending';
            return [
                'status'           => 'Pending',
                'remarks'          => $label,
                'c1_status'        => 'neutral',
                'c2_status'        => 'neutral',
                'qualified_choice' => null,
                'qualified_course' => null,
                'badge'            => 'bg-secondary',
            ];
        }

        if ($stanine === null) {
            return [
                'status'           => 'Pending',
                'remarks'          => 'Pending',
                'c1_status'        => 'neutral',
                'c2_status'        => 'neutral',
                'qualified_choice' => null,
                'qualified_course' => null,
                'badge'            => 'bg-secondary',
            ];
        }

        // Stanine >= Board Cutoff (e.g. >= 4): Passed both Board and Non-Board
        if ($stanine >= $boardCutoff) {
            return [
                'status'           => 'Passed',
                'remarks'          => 'Passed (1st Choice)',
                'c1_status'        => 'qualified',    // Soft green highlight
                'c2_status'        => 'neutral',
                'qualified_choice' => 1,
                'qualified_course' => $c1,
                'badge'            => 'bg-success',
            ];
        }

        // Stanine == Non-Board Cutoff (e.g. == 3): Passes Non-Board only
        if ($stanine >= $nonBoardCutoff) {
            if (!$isC1Board) {
                // 1st choice is Non-Board -> Qualified for 1st choice!
                return [
                    'status'           => 'Passed',
                    'remarks'          => 'Passed (1st Choice)',
                    'c1_status'        => 'qualified',    // Soft green highlight
                    'c2_status'        => 'neutral',
                    'qualified_choice' => 1,
                    'qualified_course' => $c1,
                    'badge'            => 'bg-success',
                ];
            }

            // 1st choice is Board (Not Qualified for 1st choice at Stanine 3)
            if ($c2 && !$isC2Board) {
                // Dual Choice Conflict Handling: 1st choice Board (soft red), 2nd choice Non-Board (soft green)
                return [
                    'status'           => 'Passed',
                    'remarks'          => 'Passed (2nd Choice)',
                    'c1_status'        => 'not_qualified', // Soft light red
                    'c2_status'        => 'qualified',     // Soft green highlight
                    'qualified_choice' => 2,
                    'qualified_course' => $c2,
                    'badge'            => 'bg-success',
                ];
            }

            // Both 1st and 2nd Choices are Board Programs and Stanine == 3 -> Failed
            return [
                'status'           => 'Failed',
                'remarks'          => 'Failed',
                'c1_status'        => 'not_qualified',
                'c2_status'        => $c2 ? 'not_qualified' : 'neutral',
                'qualified_choice' => null,
                'qualified_course' => null,
                'badge'            => 'bg-danger',
            ];
        }

        // Stanine < 3: Failed
        return [
            'status'           => 'Failed',
            'remarks'          => 'Failed',
            'c1_status'        => 'not_qualified',
            'c2_status'        => $c2 ? 'not_qualified' : 'neutral',
            'qualified_choice' => null,
            'qualified_course' => null,
            'badge'            => 'bg-danger',
        ];
    }

    /**
     * Dynamic Certificate Remarks:
     * - Stanine == 3: "QUALIFIED FOR NON-BOARD PROGRAMS"
     * - Stanine >= 4: "QUALIFIED FOR BOARD AND NON-BOARD PROGRAMS"
     */
    public function getCertificateRemarksAttribute(): string
    {
        $cycle = $this->cycle ?? $this->admissionSession?->cycle;
        $boardCutoff = $cycle ? $cycle->getBoardCutoff() : 4;
        $nonBoardCutoff = $cycle ? $cycle->getNonBoardCutoff() : 3;

        $stanine = $this->stanine_score;
        if ($stanine === null) {
            return 'EXAM PENDING';
        }

        if ($stanine >= $boardCutoff) {
            return 'QUALIFIED FOR BOARD AND NON-BOARD PROGRAMS';
        }

        if ($stanine >= $nonBoardCutoff) {
            return 'QUALIFIED FOR NON-BOARD PROGRAMS';
        }

        return 'NOT QUALIFIED';
    }
}
