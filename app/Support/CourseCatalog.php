<?php

namespace App\Support;

use Illuminate\Validation\Rule;

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
            if (!array_key_exists($value, self::activeOptions())) $fail('Select an active program.');
        }];
    }

    public static function activeOptions(): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('courses')) return self::OPTIONS;
        return \App\Models\Course::where('is_active', true)->orderBy('name')->pluck('name', 'code')->all();
    }

    public static function allOptions(): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('courses')) return self::OPTIONS;
        return \App\Models\Course::orderBy('name')->pluck('name', 'code')->all();
    }

    public static function label(?string $code, ?string $legacy = null): string
    {
        $options = self::allOptions();
        if ($code && isset($options[$code])) return $options[$code].' ('.$code.')';
        return $legacy ?: ($code ?: 'Not provided');
    }

    public static function normalizeLegacy(?string $value): ?string
    {
        $value = trim((string) $value);
        if (isset(self::OPTIONS[$value])) return $value;
        foreach (self::OPTIONS as $code => $title) {
            if (strcasecmp($value, $title) === 0 || strcasecmp($value, $title.' ('.$code.')') === 0) return $code;
        }
        return match (strtoupper($value)) {
            'BS INFORMATION TECHNOLOGY', 'BSIT 3-A', 'BSIT 3A' => 'BSIT',
            default => null,
        };
    }
}
