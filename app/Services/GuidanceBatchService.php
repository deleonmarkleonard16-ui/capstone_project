<?php

namespace App\Services;

use App\Events\GuidanceBatchStarted;
use App\Models\Applicant;
use App\Models\GuidanceAppointment;
use App\Models\GuidanceTestBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GuidanceBatchService
{
    public function parseRoster(UploadedFile $csv): array
    {
        $path = $csv->getRealPath();
        $sample = @file_get_contents($path, false, null, 0, 4096);
        abort_unless(is_string($sample) && trim($sample) !== '', 422, 'CSV is empty.');

        // Auto-detect delimiter from the header line (commas, semicolons, tabs)
        $firstLine = strtok($sample, "\r\n") ?: '';
        $commaCount = substr_count($firstLine, ',');
        $semiCount = substr_count($firstLine, ';');
        $tabCount = substr_count($firstLine, "\t");
        $delimiter = ',';
        if ($semiCount > $commaCount && $semiCount > $tabCount) {
            $delimiter = ';';
        } elseif ($tabCount > $commaCount && $tabCount > $semiCount) {
            $delimiter = "\t";
        }

        $file = fopen($path, 'r');
        try {
            $rawHeader = fgetcsv($file, 0, $delimiter, '"', '');
            abort_unless(is_array($rawHeader) && !empty($rawHeader), 422, 'CSV is empty.');

            // Strip UTF-8 BOM if present on the first column
            $rawHeader[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeader[0]);

            // Canonicalize column headers (case, spaces, underscores, and common aliases)
            $header = [];
            foreach ($rawHeader as $index => $col) {
                $clean = strtolower(trim((string) $col));
                $clean = preg_replace('/[\x00-\x1F\x7F\xC2\xA0]/u', '', $clean);
                $normalized = preg_replace('/[^a-z0-9]/', '', $clean);

                $canonical = match (true) {
                    in_array($normalized, ['studentid', 'studentnumber', 'idnumber', 'studentno', 'id', 'studid', 'studentnum', 'student'], true) => 'student_id',
                    in_array($normalized, ['firstname', 'first', 'givenname', 'fname', 'firstnames'], true) => 'first_name',
                    in_array($normalized, ['lastname', 'last', 'surname', 'familyname', 'lname', 'lastnames'], true) => 'last_name',
                    in_array($normalized, ['middlename', 'middle', 'middleinitial', 'mi', 'mname', 'minitial'], true) => 'middle_name',
                    default => $clean,
                };

                $header[$index] = $canonical;
            }

            // Remove trailing empty headers (frequent artifact of Excel CSV exports)
            while (!empty($header) && end($header) === '') {
                array_pop($header);
            }

            $required = ['student_id', 'first_name', 'last_name'];
            $missing = array_diff($required, $header);
            abort_if(!empty($missing), 422, 'CSV requires unique student_id, first_name and last_name columns.');

            $headerCount = count($header);
            $rows = []; $seen = [];
            while (($values = fgetcsv($file, 0, $delimiter, '"', '')) !== false) {
                if ($values === [null] || empty(array_filter($values, fn ($v) => trim((string) $v) !== ''))) {
                    continue; // Skip blank or whitespace lines
                }

                // Slice or pad row values to match header count
                if (count($values) > $headerCount) {
                    $values = array_slice($values, 0, $headerCount);
                } elseif (count($values) < $headerCount) {
                    $values = array_pad($values, $headerCount, '');
                }

                $row = array_combine($header, array_map(fn ($v) => trim((string) $v), $values));
                Validator::make($row, ['student_id' => 'required|string|max:100', 'first_name' => 'required|string|max:100', 'middle_name' => 'nullable|string|max:100', 'last_name' => 'required|string|max:100'])->validate();
                $key = mb_strtolower($row['student_id']);
                abort_if(isset($seen[$key]), 422, 'Duplicate student ID in roster.');
                $seen[$key] = true;
                $row['student_id'] = mb_strtoupper($row['student_id']);
                $row['first_name'] = mb_strtoupper($row['first_name']);
                $row['last_name'] = mb_strtoupper($row['last_name']);
                if (!empty($row['middle_name'])) {
                    $row['middle_name'] = mb_strtoupper($row['middle_name']);
                }
                $rows[] = $row;
                abort_if(count($rows) > 500, 422, 'A batch may contain at most 500 students.');
            }
            abort_if(!$rows, 422, 'CSV has no students.');
        } finally { fclose($file); }
        return $rows;
    }

    public function import(array $metadata, UploadedFile $csv): GuidanceTestBatch
    {
        Validator::make($metadata, ['course' => \App\Support\CourseCatalog::rule()])->validate();
        $rows = $this->parseRoster($csv);
        return DB::transaction(function () use ($metadata, $rows) {
            $category = array_search($metadata['test_type'], GuidanceCategories::LABELS, true);
            $batch = GuidanceTestBatch::create($metadata + ['module_type' => $metadata['test_type'], 'batch_token' => bin2hex(random_bytes(32))]);
            foreach ($rows as $row) {
                $applicant = Applicant::create(['application_number' => 'GT-'.strtoupper(bin2hex(random_bytes(12))), 'first_name' => $row['first_name'], 'middle_name' => $row['middle_name'] ?? null, 'last_name' => $row['last_name'], 'status' => 'pending']);
                $batch->appointments()->create(['applicant_id' => $applicant->id, 'student_id_number' => $row['student_id'], 'origin_course' => $batch->course, 'origin_section' => $batch->year_section, 'request_code' => app(GuidanceReferenceService::class)->reserve(), 'test_category' => $category, 'test_type' => GuidanceCategories::TESTS[$category][0], 'test_types' => GuidanceCategories::TESTS[$category], 'student_status' => 'student', 'status' => 'Pending Payment', 'attendance_status' => 'Pending Scan']);
            }
            return $batch;
        }, 3);
    }

    public function verify(GuidanceTestBatch $batch, array $identity): GuidanceAppointment
    {
        abort_unless($batch->status === 'Pending Registration', 409, 'Registration has closed.');
        $studentId = mb_strtoupper(trim((string) ($identity['student_id'] ?? '')));
        $appointment = $batch->appointments()->with('applicant')->where('student_id_number', $studentId)->first();
        $matches = $appointment && mb_strtoupper($appointment->student_id_number) === $studentId && $appointment->attendance_status !== 'Absent';
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            $expected = mb_strtoupper(trim((string) $appointment?->applicant?->$field));
            $provided = mb_strtoupper(trim((string) ($identity[$field] ?? '')));
            $matches = $matches && $expected === $provided;
        }
        if (!$matches) throw ValidationException::withMessages(['student_id' => 'The student ID and full name must exactly match the roster.']);
        return $appointment;
    }

    public function receipt(GuidanceTestBatch $batch, int $appointmentId, UploadedFile $receipt): void
    {
        $path = $receipt->store('guidance-receipts', 'local');
        abort_unless($path, 503, 'Receipt could not be stored.');
        try {
            DB::transaction(function () use ($batch, $appointmentId, $path) {
                $locked = GuidanceTestBatch::whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === 'Pending Registration', 409, 'Registration has closed.');
                $appointment = $locked->appointments()->whereKey($appointmentId)->lockForUpdate()->firstOrFail();
                abort_unless($appointment->attendance_status === 'Pending Scan', 409, 'Receipt already submitted or student marked absent.');
                $appointment->update(['payment_slip_path' => $path, 'status' => 'Receipt Uploaded', 'attendance_status' => 'Ready', 'appointment_at' => now()]);
            }, 3);
        } catch (\Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
    }

    public function start(GuidanceTestBatch $batch, int $staffId): void
    {
        DB::transaction(function () use ($batch, $staffId) {
            $batch = GuidanceTestBatch::whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            if ($batch->status === 'In-Progress') return;
            abort_unless($batch->status === 'Pending Registration', 409, 'Batch is closed.');
            $ready = $batch->appointments()->where('attendance_status', 'Ready')->orderBy('guidance_appointment_id')->lockForUpdate()->get();
            abort_if($ready->isEmpty(), 422, 'At least one student must be ready.');
            $started = now();
            foreach ($ready as $appointment) {
                foreach ($appointment->testTypes() as $test) app(GuidanceTestScoringService::class)->definition($test);
                abort_unless($appointment->payment_slip_path && Storage::disk('local')->exists($appointment->payment_slip_path), 422, 'A ready student has no receipt.');
                $appointment->qrCode()->firstOrCreate([], ['token' => bin2hex(random_bytes(32)), 'is_active' => true]);
                $appointment->update(['status' => 'In-Progress', 'verified_by' => $staffId, 'verified_at' => $started, 'started_at' => $started, 'expires_at' => $started->copy()->addSeconds(GuidanceAssessmentSessionService::DURATION)]);
            }
            $batch->update(['status' => 'In-Progress', 'started_at' => $started]);
            GuidanceBatchStarted::dispatch($batch->getKey(), $started->toIso8601String());
        }, 3);
    }

    public function action(GuidanceTestBatch $batch, string $action, ?int $id): void
    {
        DB::transaction(function () use ($batch, $action, $id) {
            $batch = GuidanceTestBatch::whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            if ($action === 'archive') {
                abort_if($batch->appointments()->where('status', 'In-Progress')->exists(), 409, 'Wait for running assessments to finish before archiving.');
                $batch->appointments()->whereNotIn('attendance_status', ['Completed', 'Absent'])->update(['attendance_status' => 'Absent']);
                $batch->update(['status' => 'Completed', 'archived_at' => now()]);
                return;
            }
            $appointment = $batch->appointments()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($action === 'absent') {
                abort_unless(in_array($appointment->attendance_status, ['Pending Scan', 'Ready']) && !$appointment->started_at, 409, 'A started assessment cannot be marked absent.');
                $appointment->update(['attendance_status' => 'Absent']);
            } else {
                abort_unless($appointment->attendance_status === 'Absent', 409, 'Only absent students can be converted.');
                $appointment->update(['source_batch_id' => $batch->getKey(), 'origin_course' => $batch->course, 'origin_section' => $batch->year_section, 'batch_id' => null, 'attendance_status' => 'Pending Scan', 'is_archived' => false, 'archived_at' => null]);
            }
            $this->completeIfDone($batch);
        }, 3);
    }

    // Call only inside a transaction that holds the batch lock.
    public function completeIfDone(GuidanceTestBatch $batch): void
    {
        if (!$batch->appointments()->whereNotIn('attendance_status', ['Completed', 'Absent'])->exists()) {
            $batch->update(['status' => 'Completed', 'archived_at' => now()]);
        }
    }
}
