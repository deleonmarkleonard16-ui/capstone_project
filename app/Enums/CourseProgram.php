<?php

namespace App\Enums;

enum CourseProgram: string
{
    case BSHM     = 'BSHM';
    case BEED     = 'BEED';
    case BSED_FIL = 'BSED-FIL';
    case BSED_SOC = 'BSED-SOC';
    case BTLEd    = 'BTLEd';
    case BSBA_HRDM = 'BSBA-HRDM';
    case BSBA_FM   = 'BSBA-FM';
    case BSBA_MM   = 'BSBA-MM';
    case BSIT     = 'BSIT';
    case BSOA     = 'BSOA';

    /** Full official program title. */
    public function label(): string
    {
        return match ($this) {
            self::BSHM      => 'Bachelor of Science in Hospitality Management',
            self::BEED      => 'Bachelor of Elementary Education',
            self::BSED_FIL  => 'Bachelor of Secondary Education major in Filipino',
            self::BSED_SOC  => 'Bachelor of Secondary Education major in Social Studies',
            self::BTLEd     => 'Bachelor of Technology and Livelihood Education',
            self::BSBA_HRDM => 'Bachelor of Science in Business Administration major in Human Resource Development Management',
            self::BSBA_FM   => 'Bachelor of Science in Business Administration major in Financial Management',
            self::BSBA_MM   => 'Bachelor of Science in Business Administration major in Marketing Management',
            self::BSIT      => 'Bachelor of Science in Information Technology',
            self::BSOA      => 'Bachelor of Science in Office Administration',
        };
    }

    /**
     * Returns [code => fullLabel] array for <select> dropdowns.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $c) => $c->label(), self::cases())
        );
    }

    /**
     * Resolve a CourseProgram from a code or full label (case-insensitive).
     * Returns null if no match is found.
     */
    public static function fromCode(string $value): ?self
    {
        $value = trim($value);

        // Try direct enum value first
        $direct = self::tryFrom($value);
        if ($direct !== null) {
            return $direct;
        }

        // Try matching by full label
        foreach (self::cases() as $case) {
            if (strcasecmp($value, $case->label()) === 0) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Laravel validation rule — accepts any valid course code.
     *
     * @return array<int, mixed>
     */
    public static function validationRule(): array
    {
        return ['required', 'string', \Illuminate\Validation\Rule::in(array_column(self::cases(), 'value'))];
    }
}
