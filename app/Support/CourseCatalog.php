<?php

namespace App\Support;

use App\Enums\CourseProgram;
use App\Models\Course;
use Illuminate\Support\Facades\Schema;

final class CourseCatalog
{
    public const OPTIONS = [
        'BSHM' => 'Bachelor of Science in Hospitality Management',
        'BEED' => 'Bachelor of Elementary Education',
        'BSED-FIL' => 'Bachelor of Secondary Education major in Filipino',
        'BSED-SOC' => 'Bachelor of Secondary Education major in Social Studies',
        'BTLEd' => 'Bachelor of Technology and Livelihood Education',
        'BSBA-HRDM' => 'Bachelor of Science in Business Administration major in Human Resource Development Management',
        'BSBA-FM' => 'Bachelor of Science in Business Administration major in Financial Management',
        'BSBA-MM' => 'Bachelor of Science in Business Administration major in Marketing Management',
        'BSIT' => 'Bachelor of Science in Information Technology',
        'BSOA' => 'Bachelor of Science in Office Administration',
    ];

    public static function rule(): array
    {
        return ['required', 'string', function ($attribute, $value, $fail) {
            if (!array_key_exists($value, self::activeOptions())) {
                $fail('Select an active program.');
            }
        }];
    }

    /**
     * Active courses only — for registration, student portal, and new imports.
     */
    public static function activeOptions(): array
    {
        try {
            if (Schema::hasTable('courses')) {
                $dbOptions = Course::where('is_active', true)->orderBy('code')->pluck('name', 'code')->toArray();
                if (!empty($dbOptions)) {
                    return $dbOptions;
                }
            }
        } catch (\Throwable $e) {
            // Fallback if DB is unavailable
        }

        return CourseProgram::options();
    }

    /**
     * All courses (including Inactive) — for archives, past applicant rosters, and analytics.
     */
    public static function allOptions(): array
    {
        try {
            if (Schema::hasTable('courses')) {
                $dbOptions = Course::orderBy('code')->pluck('name', 'code')->toArray();
                if (!empty($dbOptions)) {
                    return $dbOptions;
                }
            }
        } catch (\Throwable $e) {
            // Fallback if DB is unavailable
        }

        return CourseProgram::options();
    }

    public static function label(?string $code, ?string $legacy = null): string
    {
        $options = self::allOptions();
        if ($code && isset($options[$code])) {
            return $options[$code] . ' (' . $code . ')';
        }

        return $legacy ?: ($code ?: 'Not provided');
    }

    public static function normalizeLegacy(?string $value): ?string
    {
        $value = trim((string) $value);
        $all = self::allOptions();

        if (isset($all[$value])) {
            return $value;
        }

        foreach ($all as $code => $title) {
            if (strcasecmp($value, $title) === 0 || strcasecmp($value, $title . ' (' . $code . ')') === 0) {
                return $code;
            }
        }

        $fromEnum = CourseProgram::fromCode($value);
        if ($fromEnum !== null) {
            return $fromEnum->value;
        }

        return match (strtoupper($value)) {
            'BS INFORMATION TECHNOLOGY', 'BSIT 3-A', 'BSIT 3A' => 'BSIT',
            default => null,
        };
    }
}
