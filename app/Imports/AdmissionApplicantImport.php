<?php

namespace App\Imports;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Services\AdmissionScoringService;
use App\Services\AdmissionSpreadsheetReader;
use App\Support\CourseCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class AdmissionApplicantImport
{
    protected AdmissionSpreadsheetReader $reader;
    protected AdmissionScoringService $scoring;

    public function __construct(
        ?AdmissionSpreadsheetReader $reader = null,
        ?AdmissionScoringService $scoring = null
    ) {
        $this->reader  = $reader ?? app(AdmissionSpreadsheetReader::class);
        $this->scoring = $scoring ?? app(AdmissionScoringService::class);
    }

    /**
     * Import applicants from CSV or XLSX.
     *
     * Expected CSV Headers:
     * last_name, first_name, middle_name, course_choice_1, course_choice_2, sex, 4ps_osy_ip_pwd_sp, cmfl, gwa
     * (Also supports legacy variations: course, second_course_choice, application_number, etc.)
     *
     * @param string|UploadedFile $file
     * @param AdmissionCycle $cycle
     * @param string|null $batchGroup
     * @return array{imported: int, errors: array<string>}
     */
    public function import(string|UploadedFile $file, AdmissionCycle $cycle, ?string $batchGroup = null): array
    {
        if ($file instanceof UploadedFile) {
            $path = $file->getRealPath();
            $ext  = strtolower($file->getClientOriginalExtension());
        } else {
            $path = $file;
            $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        }

        $rows = $this->reader->rows($path, $ext);
        if (empty($rows)) {
            throw new RuntimeException('The uploaded spreadsheet contains no data.');
        }

        $rawHeader = array_shift($rows) ?: [];
        $header = array_map(function ($col) {
            $cleaned = strtolower(trim(ltrim((string) $col, "\xEF\xBB\xBF")));
            return preg_replace('/\s+/', '_', $cleaned);
        }, $rawHeader);

        // Map column variations to standard keys
        $colMap = $this->resolveHeaderMap($header);

        if (!isset($colMap['last_name']) || !isset($colMap['first_name']) || (!isset($colMap['course_choice_1']) && !isset($colMap['course']))) {
            throw new RuntimeException('Missing required headers. CSV must contain at minimum: last_name, first_name, course_choice_1 (or course), sex, 4ps_osy_ip_pwd_sp, cmfl, gwa.');
        }

        $courses = CourseCatalog::activeOptions();
        $importedCount = 0;
        $errors = [];

        foreach ($rows as $rowIndex => $values) {
            // Skip empty rows
            if (!array_filter($values, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }

            $rowNum = $rowIndex + 2; // +1 for 1-based, +1 for header row

            $getVal = function ($key) use ($colMap, $values) {
                if (isset($colMap[$key]) && isset($values[$colMap[$key]])) {
                    return trim((string) $values[$colMap[$key]]);
                }
                return '';
            };

            $lastName   = $getVal('last_name');
            $firstName  = $getVal('first_name');
            $middleName = $getVal('middle_name') ?: null;
            $rawCourse1 = $getVal('course_choice_1') ?: $getVal('course');
            $rawCourse2 = $getVal('course_choice_2') ?: ($getVal('second_course_choice') ?: $getVal('course_2'));
            $sex        = $getVal('sex') ?: null;
            $special    = $getVal('4ps_osy_ip_pwd_sp') ?: ($getVal('special_group') ?: null);
            $cmfl       = $getVal('cmfl') ?: null;
            $gwaRaw     = $getVal('gwa');
            $appNumRaw  = $getVal('application_number');

            if ($lastName === '' || $firstName === '') {
                $errors[] = "Row {$rowNum}: Last name and First name are required.";
                continue;
            }

            // Normalize Course Choice 1 (Required)
            $course1 = CourseCatalog::normalizeLegacy($rawCourse1) ?? $rawCourse1;
            if ($course1 === '' || (!isset($courses[$course1]) && !array_key_exists($course1, CourseCatalog::OPTIONS))) {
                $errors[] = "Row {$rowNum}: Invalid or missing Course Choice 1 ('{$rawCourse1}').";
                continue;
            }

            // Normalize Course Choice 2 (Optional - allowed to be blank, N/A, None)
            $course2 = null;
            if ($rawCourse2 !== '' && strcasecmp($rawCourse2, 'N/A') !== 0 && strcasecmp($rawCourse2, 'None') !== 0) {
                $norm2 = CourseCatalog::normalizeLegacy($rawCourse2) ?? $rawCourse2;
                if (isset($courses[$norm2]) || array_key_exists($norm2, CourseCatalog::OPTIONS)) {
                    $course2 = $norm2;
                } else {
                    $errors[] = "Row {$rowNum}: Warning - Unrecognized Course Choice 2 ('{$rawCourse2}') was set to None.";
                }
            }

            // Validate GWA
            if ($gwaRaw !== '' && (!is_numeric($gwaRaw) || (float) $gwaRaw < 75 || (float) $gwaRaw > 100)) {
                $errors[] = "Row {$rowNum}: Invalid GWA '{$gwaRaw}'. Must be numeric between 75 and 100.";
                continue;
            }
            $gwa = $gwaRaw !== '' ? (float) $gwaRaw : null;

            // Application Number
            $appNum = $appNumRaw;
            if ($appNum === '') {
                $yearPrefix = date('y');
                $appNum = "CAT-{$yearPrefix}-" . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                while (AdmissionApplicant::where('application_number', $appNum)->exists()) {
                    $appNum = "CAT-{$yearPrefix}-" . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                }
            } else {
                // Ensure doesn't belong to another cycle
                if (AdmissionApplicant::where('application_number', $appNum)->where('admission_cycle_id', '!=', $cycle->id)->exists()) {
                    $errors[] = "Row {$rowNum}: Application number '{$appNum}' already belongs to another admission cycle.";
                    continue;
                }
            }

            // Clean sex
            if ($sex !== null) {
                if (stripos($sex, 'm') === 0) {
                    $sex = 'Male';
                } elseif (stripos($sex, 'f') === 0) {
                    $sex = 'Female';
                }
            }

            AdmissionApplicant::updateOrCreate(
                ['application_number' => $appNum],
                [
                    'admission_cycle_id'   => $cycle->id,
                    'batch_group'          => $batchGroup ?: null,
                    'student_id'           => null,
                    'first_name'           => $firstName,
                    'middle_name'          => $middleName,
                    'last_name'            => $lastName,
                    'course_choice_1'      => $course1,
                    'course_choice'        => $course1,
                    'course_choice_2'      => $course2,
                    'second_course_choice' => $course2,
                    'sex'                  => $sex,
                    'special_group'        => $special,
                    '4ps_osy_ip_pwd_sp'    => $special,
                    'cmfl'                 => $cmfl,
                    'gwa'                  => $gwa,
                ]
            );

            $importedCount++;
        }

        if ($importedCount > 0) {
            $this->scoring->evaluate($cycle);
        }

        return [
            'imported' => $importedCount,
            'errors'   => $errors,
        ];
    }

    /**
     * Map fuzzy header column names to normalized keys.
     *
     * @param array<int, string> $headers
     * @return array<string, int>
     */
    protected function resolveHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $h) {
            $h = strtolower(trim($h));

            if (in_array($h, ['last_name', 'lastname', 'surname', 'last'])) {
                $map['last_name'] = $idx;
            } elseif (in_array($h, ['first_name', 'firstname', 'given_name', 'givenname', 'first'])) {
                $map['first_name'] = $idx;
            } elseif (in_array($h, ['middle_name', 'middlename', 'middle', 'mi'])) {
                $map['middle_name'] = $idx;
            } elseif (in_array($h, ['course_choice_1', 'course_1', 'course_choice', 'course_(1st_choice)', '1st_course_choice', 'course', 'program_1', '1st_choice'])) {
                $map['course_choice_1'] = $idx;
            } elseif (in_array($h, ['course_choice_2', 'course_2', 'second_course_choice', 'course_(2nd_choice)', '2nd_course_choice', 'program_2', '2nd_choice'])) {
                $map['course_choice_2'] = $idx;
            } elseif (in_array($h, ['sex', 'gender'])) {
                $map['sex'] = $idx;
            } elseif (in_array($h, ['4ps_osy_ip_pwd_sp', '4ps/osy/ip/pwd/sp', 'special_group', 'special_groups', 'ip_pwd_sp'])) {
                $map['4ps_osy_ip_pwd_sp'] = $idx;
            } elseif (in_array($h, ['cmfl', 'family_income', 'income', 'monthly_income'])) {
                $map['cmfl'] = $idx;
            } elseif (in_array($h, ['gwa', 'general_weighted_average', 'average', 'grade'])) {
                $map['gwa'] = $idx;
            } elseif (in_array($h, ['application_number', 'app_number', 'app_no', 'applicant_number', 'application_no'])) {
                $map['application_number'] = $idx;
            }
        }
        return $map;
    }
}
