<?php

namespace App\Http\Controllers;

use App\Models\AdmissionCycle;
use App\Models\AdmissionSession;
use App\Models\GuidanceTestBatch;
use App\Services\DocumentExportService;
use App\Services\ExportReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentExportController extends Controller
{
    public function index(Request $request)
    {
        $modules = ExportReportService::TYPES + ['analytics' => ['summary', 'records']];
        if ($request->user()->role !== 'admin') unset($modules['admission']);

        return view('exports.index', [
            'modules' => $modules,
            'cycles' => $request->user()->role === 'admin' ? AdmissionCycle::orderByDesc('id')->get() : collect(),
            'sessions' => $request->user()->role === 'admin' ? AdmissionSession::with('cycle')->orderByDesc('id')->get() : collect(),
            'batches' => GuidanceTestBatch::orderBy('batch_name')->get(),
            'courses' => \App\Support\CourseCatalog::allOptions(),
        ]);
    }

    public function export(Request $request, string $module)
    {
        abort_unless(isset(ExportReportService::TYPES[$module]), 404);
        abort_if($module === 'admission' && $request->user()->role !== 'admin', 403);
        $admission = $module === 'admission';
        $guidance = in_array($module, ['guidance-testing', 'psychological', 'personality', 'career'], true);
        $filters = $request->validate([
            'format' => ['nullable', Rule::in(['html', 'pdf', 'docx', 'csv'])],
            'type' => ['nullable', Rule::in(ExportReportService::TYPES[$module])],
            'cycle_id' => [$admission ? 'nullable' : 'prohibited', 'integer', 'exists:admission_cycles,id'],
            'session_id' => [$admission ? 'nullable' : 'prohibited', 'integer', 'exists:admission_sessions,id'],
            'batch_id' => [$admission ? 'prohibited' : 'nullable', 'integer', 'exists:guidance_test_batches,batch_id'],
            'batch_name' => [$admission ? 'prohibited' : 'nullable', 'string', 'max:255'],
            'batch_group' => [$admission ? 'nullable' : 'prohibited', 'string', 'max:100'],
            'request_id' => [$admission ? 'prohibited' : 'nullable', 'integer', 'exists:service_requests,id'],
            'appointment_id' => [$guidance ? 'nullable' : 'prohibited', 'integer', 'exists:guidance_appointments,guidance_appointment_id'],
            'submission_id' => [$guidance ? 'nullable' : 'prohibited', 'integer', 'exists:guidance_test_submissions,id'],
            'category' => [$module === 'guidance-testing' ? 'nullable' : 'prohibited', Rule::in(['psychological', 'personality', 'career'])],
            'course' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in($admission ? ['Pending', 'Qualified', 'Not Qualified'] : ($guidance ? array_merge(['Pending', 'Pending Payment', 'Receipt Uploaded', 'Approved', 'In-Progress', 'Completed'], \App\Models\ServiceRequest::STATUSES) : \App\Models\ServiceRequest::STATUSES))],
            'auto_print' => ['nullable', 'boolean'],
        ]);
        $filters['type'] = $filters['type'] ?? ($admission ? 'masterlist' : ($guidance ? 'summary' : 'queue'));
        $format = $filters['format'] ?? 'html';
        $fallback = route($request->user()->role.'.exports.index');
        try {
            if ($filters['type'] === 'session' && empty($filters['session_id'])) {
                throw new \DomainException('Select a session to print its masterlist.');
            }
            if ($filters['type'] === 'individual' && empty($filters['request_id']) && empty($filters['appointment_id']) && empty($filters['submission_id'])) {
                throw new \DomainException('Select a request, appointment or submission for an individual assessment report.');
            }
            if (! empty($filters['appointment_id']) && ! empty($filters['submission_id'])) {
                throw new \DomainException('Select either an appointment or a submission.');
            }
            $document = app(ExportReportService::class)->build($module, $filters);
            $records = $document['records'];
            if ($records->isEmpty()) {
                return redirect($fallback)->with('error', 'No eligible records found for the selected filters.');
            }
            if (! empty($filters['batch_id'])) {
                $document['metadata']['Selected Batch'] = GuidanceTestBatch::find($filters['batch_id'])?->batch_name ?? 'N/A';
            }
            $document['format'] = $format;
            $document['autoPrint'] = $format === 'html' && (bool) ($filters['auto_print'] ?? false);
            $bytes = app(DocumentExportService::class)->render($document, $format);
            $mime = match ($format) {
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'csv' => 'text/csv; charset=UTF-8',
                default => 'text/html; charset=UTF-8',
            };
            $headers = ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
            if ($format !== 'html') {
                $filename = 'DMSGTA-'.Str::slug($document['title']).'-'.now()->format('Y-m-d').'.'.$format;
                $headers['Content-Disposition'] = 'attachment; filename="'.$filename.'"';
            }

            return response($bytes, 200, $headers);
        } catch (\DomainException $e) {
            return redirect($fallback)->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('DMSGTA export failed', ['module' => $module, 'format' => $format, 'exception' => $e]);

            return redirect($fallback)->with('error', 'Unable to generate the document. Please contact the system administrator or try another format.');
        }
    }

    public function printMasterlist(Request $request, string $module)
    {
        $request->merge(['format' => 'html', 'auto_print' => true]);
        if ($module === 'admission') $request->merge(['type' => 'session']);

        return $this->export($request, $module);
    }

    public function archive(Request $request, \App\Services\GuidanceQueueService $queue)
    {
        $filters = $queue->filters($request);
        $format = $request->validate(['format' => ['required', Rule::in(['html', 'csv', 'pdf', 'docx'])]])['format'];
        $fallback = route($request->user()->role.'.exports.index');
        try {
            $records = $queue->query($filters, true)->with(['applicant', 'serviceRequest', 'batch', 'sourceBatch'])->orderBy('guidance_appointment_id')->get();
            if ($records->isEmpty()) return redirect($fallback)->with('error', 'No eligible records found for the selected filters.');
            $document = [
                'title' => 'Guidance Testing Archive Summary', 'office' => 'Guidance and Counseling Office',
                'orientation' => 'landscape', 'format' => $format, 'autoPrint' => false, 'summary' => [], 'certificates' => [],
                'metadata' => ['Date Generated' => now()->timezone('Asia/Manila')->format('F j, Y g:i A').' (Asia/Manila)', 'Active Filters' => collect($filters)->filter()->map(fn ($v, $k) => $k.': '.$v)->implode(' | ') ?: 'All archived individual requests', 'Record Count' => (string) $records->count()],
                'columns' => ['Reference', 'Name', 'Student ID', 'Course', 'Tests', 'Status', 'Appointment', 'Archived'],
                'rows' => $records->map(fn ($r) => [$r->reference ?: '-', trim(($r->last_name ?? '').', '.($r->first_name ?? '').' '.($r->middle_name ?? ''), ' ,') ?: 'N/A', $r->student_number ?? '-', $r->courseLabel(), $r->testLabel(), $r->status ?? '-', $r->appointment_at?->format('Y-m-d H:i') ?? '-', $r->archived_at?->format('Y-m-d H:i') ?? '-']),
            ];
            $bytes = app(DocumentExportService::class)->render($document, $format);
            $mime = match ($format) { 'pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'csv' => 'text/csv; charset=UTF-8', default => 'text/html; charset=UTF-8' };
            if ($format === 'csv') {
                return response()->streamDownload(static function () use ($bytes): void { echo $bytes; }, 'DMSGTA-guidance-archive-'.now()->format('Y-m-d').'.csv', ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store']);
            }

            return response($bytes, 200, ['Content-Type' => $mime, 'Content-Disposition' => ($format === 'html' ? 'inline' : 'attachment').'; filename="DMSGTA-guidance-archive-'.now()->format('Y-m-d').'.'.$format.'"', 'Cache-Control' => 'private, no-store']);
        } catch (\Throwable $e) {
            Log::error('Guidance archive export failed', ['exception' => $e]);

            return redirect($fallback)->with('error', 'Unable to generate the document. Please contact the system administrator or try another format.');
        }
    }

    /** Preserve query parameters used by existing report buttons. */
    public function legacyReport(Request $request, string $module)
    {
        if ($module === 'admission' && $request->filled('batch_id')) {
            $request->merge(['batch_group' => $request->input('batch_id')]);
            $request->query->remove('batch_id');
            $request->request->remove('batch_id');
        }
        if ($module === 'guidance-testing') {
            $request->merge(['category' => $request->input('module', $request->input('category'))]);
        }
        if ($module === 'documents' && in_array($request->input('module'), ['good-moral', 'exit-form'], true)) {
            $module = $request->input('module');
        }

        return $this->export($request, $module);
    }
}
