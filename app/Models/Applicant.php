<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Applicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_number',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'email',
        'contact_number',
        'status',
    ];

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

    public function admissionEvaluation()
    {
        return $this->hasOne(AdmissionEvaluation::class);
    }

    public function genderLookup(): BelongsTo
    {
        return $this->belongsTo(Gender::class, 'gender_id');
    }

    public function statusLookup(): BelongsTo
    {
        return $this->belongsTo(ApplicantStatus::class, 'applicant_status_id');
    }

    public function getAttribute($key)
    {
        if ($key === 'gender') {
            return $this->genderLookup?->name;
        }

        if ($key === 'status') {
            return $this->statusLookup?->slug ?? $this->statusLookup?->name;
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'gender') {
            $gender = is_numeric($value)
                ? Gender::find($value)
                : Gender::query()
                    ->where('slug', strtolower((string) $value))
                    ->orWhere('name', $value)
                    ->first();

            $this->attributes['gender_id'] = $gender?->id;

            return $this;
        }

        if ($key === 'status') {
            $status = is_numeric($value)
                ? ApplicantStatus::find($value)
                : ApplicantStatus::query()
                    ->where('slug', strtolower((string) $value))
                    ->orWhere('name', $value)
                    ->first();

            $this->attributes['applicant_status_id'] = $status?->id;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function getFullNameAttribute(): string
    {
        $givenNames = collect([$this->first_name, $this->middle_name])
            ->filter()
            ->implode(', ');

        $full = $givenNames !== ''
            ? ($this->last_name ? "{$this->last_name}, {$givenNames}" : $givenNames)
            : ($this->last_name ?? '');

        return mb_strtoupper($full);
    }
}
