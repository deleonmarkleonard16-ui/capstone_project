<?php

namespace App\Services;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Models\AdmissionSession;
use App\Models\GuidanceAppointment;
use App\Models\GuidanceTestSubmission;
use App\Models\ServiceRequest;

class ExportReportService
{
    public const TYPES = [
        'admission' => ['masterlist', 'qualified', 'not-qualified', 'summary', 'session'],
        'guidance-testing' => ['individual', 'batch', 'completion', 'summary'],
        'psychological' => ['individual', 'batch', 'completion', 'summary'],
        'personality' => ['individual', 'batch', 'completion', 'summary'],
        'career' => ['individual', 'batch', 'completion', 'summary'],
        'good-moral' => ['certificate', 'queue'],
        'exit-form' => ['certificate', 'queue'],
        'documents' => ['queue'],
    ];

    public function build(string $module, array $filters): array
    {
        $document = [
            'office' => $module === 'admission' ? 'Testing and Admission Office' : 'Guidance and Counseling Office',
            'metadata' => ['Selected Batch' => $filters['batch_name'] ?? 'All batches / Individual requests', 'Date Generated' => now()->timezone('Asia/Manila')->format('F j, Y g:i A').' (Asia/Manila)'],
            'summary' => [], 'certificates' => [], 'orientation' => 'landscape',
        ];
        $data = match (true) {
            $module === 'admission' => $this->admission($filters),
            in_array($module, ['good-moral', 'exit-form', 'documents'], true) => $this->documents($module, $filters),
            default => $this->guidance($module, $filters),
        };
        $document = array_replace($document, $data);
        $active = collect($filters)->except(['format', 'auto_print'])->filter(fn ($value) => $value !== null && $value !== '');
        $document['metadata']['Active Filters'] = $active->map(fn ($value, $key) => str_replace('_', ' ', $key).': '.$value)->implode(' | ') ?: 'All records';
        $document['metadata']['Record Count'] = (string) $document['records']->count();

        return $document;
    }

    private function admission(array $f): array
    {
        $session = isset($f['session_id']) ? AdmissionSession::with('cycle')->find($f['session_id']) : null;
        $cycle = isset($f['cycle_id']) ? AdmissionCycle::find($f['cycle_id']) : ($session?->cycle ?? AdmissionCycle::active());
        if (! $cycle) throw new \DomainException('No admission cycle is available. Select an admission cycle first.');
        if ($session && (int) $session->admission_cycle_id !== (int) $cycle->id) throw new \DomainException('The session does not belong to the selected admission cycle.');
        $type = $f['type'];
        $records = AdmissionApplicant::where('admission_cycle_id', $cycle->id)
            ->when($f['session_id'] ?? null, fn ($q, $id) => $q->where('admission_session_id', $id))
            ->when($f['course'] ?? null, fn ($q, $course) => $q->where('course_choice', $course))
            ->when($f['batch_group'] ?? null, fn ($q, $batch) => $q->where('batch_group', $batch))
            ->when($f['status'] ?? null, fn ($q, $status) => $q->where('qualification_status', $status))
            ->when(in_array($type, ['qualified', 'not-qualified'], true), fn ($q) => $q->where('qualification_status', $type === 'qualified' ? 'Qualified' : 'Not Qualified'))
            ->when($type !== 'session', fn ($q) => $q->orderBy('course_choice')->orderByDesc('total_score'))
            ->orderBy('last_name')->orderBy('first_name')->get();
        $columns = ['Application Number', 'Name', 'Sex', 'Course Choice', 'GWA'];
        $columns = array_merge($columns, $type === 'session' ? ['Proctor Verification'] : ['Exam Score', 'Stanine', 'Interview', 'Total Score', 'Status']);
        $rows = $records->map(function ($r) use ($type) {
            $row = [$r->application_number ?? '-', $r->full_name ?? 'N/A', $r->sex ?? '-', $r->course_choice ?? 'N/A', $r->gwa ?? '-'];

            return array_merge($row, $type === 'session' ? ['________________'] : [$r->exam_score ?? '-', $r->stanine_score ?? '-', $r->interview_score ?? '-', $r->total_score ?? '-', $r->qualification_status ?? 'Pending']);
        });

        return [
            'title' => match ($type) {
                'qualified' => 'Official Roster of Qualified Applicants',
                'not-qualified' => 'Official Roster of Non-Qualified Applicants',
                'summary' => 'Final Admission Evaluation Summary Report',
                'session' => 'Official Admission Test Session Masterlist',
                default => 'Admission Masterlist',
            },
            'records' => $records, 'columns' => $columns, 'rows' => $rows,
            'metadata' => ['Admission Cycle' => $cycle->display_name, 'Academic Year' => $cycle->academic_year ?? 'N/A', 'Session' => $session?->session_name ?? 'All sessions', 'Room' => $session?->room ?? '-', 'Schedule' => $session?->start_time?->timezone('Asia/Manila')->format('F j, Y g:i A') ?? '-', 'Date Generated' => now()->timezone('Asia/Manila')->format('F j, Y g:i A').' (Asia/Manila)'],
            'summary' => ['Applicants' => $records->count(), 'Qualified' => $records->where('qualification_status', 'Qualified')->count(), 'Non-Qualified' => $records->where('qualification_status', 'Not Qualified')->count(), 'Pending' => $records->where('qualification_status', 'Pending')->count()],
        ];
    }

    private function guidance(string $module, array $f): array
    {
        $category = $module === 'guidance-testing' ? ($f['category'] ?? null) : $module;
        $tests = $category ? GuidanceCategories::TESTS[$category] : array_merge(...array_values(GuidanceCategories::TESTS));
        $appointments = GuidanceAppointment::with(['applicant', 'serviceRequest', 'response', 'batch', 'sourceBatch'])
            ->when($f['submission_id'] ?? null, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($f['appointment_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($f['request_id'] ?? null, fn ($q, $id) => $q->where('service_request_id', $id))
            ->when($f['batch_id'] ?? null, fn ($q, $id) => $q->where(fn ($q) => $q->where('batch_id', $id)->orWhere('source_batch_id', $id)))
            ->when($f['batch_name'] ?? null, fn ($q, $name) => $q->where(fn ($q) => $q->whereHas('batch', fn ($b) => $b->where('batch_name', $name))->orWhereHas('sourceBatch', fn ($b) => $b->where('batch_name', $name))))
            ->when($f['status'] ?? null, fn ($q, $status) => $q->whereIn('status', match (strtolower($status)) {
                'pending' => ['Pending', 'Pending Payment'],
                'proof_review' => ['Receipt Uploaded'],
                'processing' => ['In-Progress'],
                default => [ucfirst($status)],
            }))
            ->when($f['type'] === 'completion', fn ($q) => $q->where('status', 'Completed')->whereHas('response'))
            ->when($f['course'] ?? null, fn ($q, $course) => $q->whereRaw('COALESCE(origin_course, (SELECT course FROM guidance_test_batches WHERE batch_id = guidance_appointments.batch_id), (SELECT course FROM guidance_test_batches WHERE batch_id = guidance_appointments.source_batch_id), (SELECT course FROM service_requests WHERE id = guidance_appointments.service_request_id)) = ?', [$course]))
            ->orderBy('guidance_appointment_id')->get();
        $rows = collect();
        $seen = [];
        foreach ($appointments as $r) {
            $summaries = $r->response?->testSummaries() ?? [];
            // Results exports never present an uncompleted assessment as a scored result.
            foreach (array_intersect(array_keys($summaries), $tests) as $test) {
                $summary = $summaries[$test] ?? [];
                $rows->push([$r->reference ?: '-', $this->name($r), $r->student_number ?? '-', $r->courseLabel(), $r->batch?->batch_name ?? $r->sourceBatch?->batch_name ?? '-', GuidanceTestScoringService::LABELS[$test] ?? $test, $this->flatten($summary['scores'] ?? []), $this->flatten($summary['interpretation'] ?? []), $r->status ?? '-', $r->response?->created_at?->format('Y-m-d') ?? '-']);
                $seen[($r->service_request_id ?? 'appointment-'.$r->getKey()).':'.$test] = true;
            }
        }
        // Preserve assessment results encoded through the older submission workflow.
        if (empty($f['appointment_id'])) {
            $submissions = GuidanceTestSubmission::with('serviceRequest.batch')->whereNotNull('completed_at')->whereIn('test_type', $tests)
                ->when($f['submission_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
                ->whereHas('serviceRequest', function ($q) use ($f) {
                    $q->where('service', 'testing')
                        ->when($f['request_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
                        ->when($f['course'] ?? null, fn ($q, $course) => $q->where('course', $course))
                        ->when($f['status'] ?? null, fn ($q, $status) => $q->where('status', match ($status) {
                            'In-Progress' => 'processing', 'Pending Payment' => 'pending', 'Receipt Uploaded' => 'proof_review',
                            default => strtolower($status),
                        }))
                        ->when($f['type'] === 'completion', fn ($q) => $q->where('status', 'completed'))
                        ->when($f['batch_id'] ?? null, fn ($q, $id) => $q->where('batch_id', $id))
                        ->when($f['batch_name'] ?? null, fn ($q, $name) => $q->whereHas('batch', fn ($b) => $b->where('batch_name', $name)));
                })->get();
            foreach ($submissions as $r) {
                if (isset($seen[$r->service_request_id.':'.$r->test_type])) continue;
                $request = $r->serviceRequest;
                $rows->push([$request?->reference ?? '-', $this->name($request), $request?->student_number ?? '-', $request?->courseLabel() ?? 'N/A', $request?->batch?->batch_name ?? '-', GuidanceTestScoringService::LABELS[$r->test_type] ?? $r->test_type, $this->flatten($r->scores ?? []), $this->flatten($r->interpretation ?? []), $request?->status ?? '-', $r->completed_at?->format('Y-m-d') ?? '-']);
            }
        }

        return ['title' => ($category ? GuidanceCategories::LABELS[$category].' - ' : 'Guidance Testing - ').match ($f['type']) {
            'individual' => 'Individual Assessment Results and Score Summary',
            'completion' => 'Official Completion Report',
            'batch' => 'Batch Assessment Summary',
            default => 'Assessment Score Summary',
        }, 'records' => $rows, 'rows' => $rows, 'columns' => ['Reference', 'Name', 'Student Number', 'Course', 'Batch', 'Assessment', 'Scores', 'Interpretation', 'Status', 'Completed'], 'summary' => ['Assessments' => $rows->count(), 'Students' => $rows->pluck(0)->unique()->count()]];
    }

    private function documents(string $module, array $f): array
    {
        $certificate = $f['type'] === 'certificate';
        $records = ServiceRequest::with('batch')->whereIn('service', $module === 'documents' ? ['good-moral', 'exit-form'] : [$module])
            ->when($f['request_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($f['course'] ?? null, fn ($q, $course) => $q->where('course', $course))
            ->when($f['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($f['batch_id'] ?? null, fn ($q, $id) => $q->where('batch_id', $id))
            ->when($f['batch_name'] ?? null, fn ($q, $name) => $q->whereHas('batch', fn ($b) => $b->where('batch_name', $name)))
            ->when($certificate, fn ($q) => $q->whereIn('status', $module === 'good-moral' ? ['approved', 'processing', 'ready', 'completed'] : ['completed']))
            ->orderBy('last_name')->orderBy('first_name')->get();
        $certificates = [];
        if ($certificate) {
            foreach ($records as $r) {
                $certificates[] = [
                    'name' => mb_strtoupper(preg_replace('/\s+/u', ' ', trim(($r->first_name ?? '').' '.($r->middle_name ?? '').' '.($r->last_name ?? '')))) ?: 'N/A',
                    'student_number' => $r->student_number ?: 'N/A',
                    'course' => $r->courseLabel(),
                    'purpose' => $r->purpose ?: 'Not provided',
                    'issued_at' => now()->timezone('Asia/Manila')->format('F j, Y'),
                    'statement' => $module === 'good-moral'
                        ? 'has been issued a certificate of good moral character.'
                        : 'has completed the Exit Form clearance process.',
                ];
            }
        }

        return ['title' => $certificate ? ($module === 'good-moral' ? 'Certificate of Good Moral Character' : 'Exit Form Clearance Certificate') : 'Document Request Queue Summary', 'records' => $records, 'certificates' => $certificates, 'orientation' => $certificate ? 'portrait' : 'landscape', 'columns' => $certificate ? [] : ['Reference', 'Name', 'Student Number', 'Course', 'Batch', 'Document', 'Purpose', 'Status', 'Requested'], 'rows' => $certificate ? collect() : $records->map(fn ($r) => [$r->reference ?? '-', $this->name($r), $r->student_number ?: '-', $r->courseLabel(), $r->batch?->batch_name ?? '-', ServiceRequest::SERVICES[$r->service] ?? 'Document', $r->purpose ?: '-', $r->status ?? '-', $r->created_at?->format('Y-m-d') ?? '-'])];
    }

    private function name($record): string
    {
        return trim(($record->last_name ?? '').', '.($record->first_name ?? '').' '.($record->middle_name ?? ''), ' ,') ?: 'N/A';
    }

    private function flatten(array $values, string $prefix = ''): string
    {
        $parts = [];
        foreach ($values as $key => $value) {
            $label = $prefix.str_replace('_', ' ', (string) $key);
            $parts[] = is_array($value) ? $this->flatten($value, $label.' / ') : $label.': '.(string) ($value ?? '-');
        }

        return implode('; ', $parts) ?: '-';
    }
}
