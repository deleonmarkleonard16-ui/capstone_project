<?php

namespace App\Http\Controllers;

use App\Models\GuidanceAppointment;
use App\Services\GuidanceTestScoringService;
use App\Services\GuidanceQueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminGuidanceController extends Controller
{
    public function index(Request $request, GuidanceQueueService $queue)
    {
        app(\App\Services\GuidanceAssessmentSessionService::class)->expireDue();
        $filters = $queue->filters($request);
        $archived = $request->routeIs(auth()->user()->role.'.guidance-appointments.archive');
        $appointments = $queue->query($filters, $archived)->with(['applicant', 'response', 'serviceRequest', 'securityIncidents'])
            ->orderByRaw("CASE WHEN status = 'Receipt Uploaded' THEN 0 WHEN status = 'Pending Payment' THEN 1 ELSE 2 END")
            ->latest('guidance_appointment_id')->paginate(25)->withQueryString();
        $data = compact('appointments', 'filters', 'archived');
        if ($request->expectsJson()) return response()->json(['html' => view('guidance.queue', $data)->render()]);
        return view('guidance.dashboard', $data);
    }

    public function archiveSelected(Request $request, GuidanceQueueService $queue)
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'required|integer|distinct', 'archive' => 'required|boolean']);
        $queue->archive($data['ids'], (bool) $data['archive']);
        return back()->with('success', $data['archive'] ? 'Selected assessments archived.' : 'Selected assessments restored. Completed QR passes remain inactive.');
    }

    public function export(Request $request, GuidanceQueueService $queue)
    {
        $filters = $queue->filters($request);
        $format = $request->validate(['format' => ['required', Rule::in(['csv', 'pdf'])]])['format'];
        $query = $queue->query($filters, true)->with(['applicant', 'serviceRequest'])->orderBy('guidance_appointment_id');

        if ($format === 'pdf') {
            abort_if((clone $query)->count() > 500, 422, 'PDF exports support up to 500 requests. Narrow your filters or export CSV.');

            try {
                $pdf = app(\App\Services\GuidanceArchivePdfService::class)->render($query->get());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Guidance Archive PDF Export Error', [
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);
                return redirect()->back()
                    ->with('error', 'PDF export failed: ' . $e->getMessage());
            }

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="guidance-archive-' . now()->format('Y-m-d') . '.pdf"',
            ]);
        }

        return response()->streamDownload(function () use ($query) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, ['Reference', 'Name', 'Student ID', 'Tests', 'Status', 'Appointment (Asia/Manila)', 'Archived (Asia/Manila)']);
            foreach ($query->lazy(200) as $entry) {
                $row = [
                    $entry->serviceRequest?->reference ?? $entry->request_code ?? '-',
                    $entry->applicant->full_name ?? 'N/A',
                    $entry->serviceRequest?->student_number ?? '',
                    $entry->testLabel() ?? '-',
                    $entry->status ?? '-',
                    $entry->appointment_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s') ?? '',
                    $entry->archived_at?->timezone('Asia/Manila')->format('Y-m-d H:i:s') ?? '',
                ];
                // Prevent spreadsheet formula injection in exported user-supplied cells.
                fputcsv($file, array_map(
                    fn ($cell) => preg_match('/^[\s]*[=+@\-]/u', (string) $cell) ? "'" . $cell : $cell,
                    $row
                ));
            }
            fclose($file);
        }, 'guidance-archive-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function verify(Request $request, GuidanceAppointment $appointment, GuidanceTestScoringService $scoring)
    {
        abort_if($appointment->batch_id, 409, 'Use Start Batch Assessment for batch students.');
        $allowed = $appointment->test_category === 'psychological' ? ['dass21', 'phq9', 'gad7'] : [$appointment->test_type];
        $data = $request->validate(['test_type' => ['nullable', Rule::in($allowed)]]);
        DB::transaction(function () use ($request, $appointment, $data, $scoring) {
            if ($appointment->service_request_id) {
                \App\Models\ServiceRequest::whereKey($appointment->service_request_id)->lockForUpdate()->firstOrFail();
            }
            $locked = GuidanceAppointment::whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['Approved', 'In-Progress'])) return;
            abort_if($locked->status === 'Pending Payment', 422, 'Upload a payment receipt before verification.');
            abort_unless($locked->status === 'Receipt Uploaded', 409, 'This request cannot be verified in its current state.');
            $test = $data['test_type'] ?? $locked->test_type;
            foreach ($locked->test_types ?: [$test] as $instrument) $scoring->definition($instrument);

            abort_unless($locked->hasReceipt(), 422, 'Receipt has not been uploaded.');

            $locked->qrCode()->create(['token' => bin2hex(random_bytes(32)), 'is_active' => true]);
            $locked->update(['test_type' => $test, 'status' => 'Approved', 'verified_by' => $request->user()->id, 'verified_at' => now()]);
            $locked->serviceRequest?->update(['status' => 'processing']);

            /*
            $receiptPath = null;
            abort_unless($locked->hasReceipt(), 422, 'Receipt has not been uploaded.');

            // In cloud environments (e.g. Render ephemeral storage / container restart),
            // the physical file may have been wiped on a container reboot.
            // Restore a 1×1 pixel placeholder stub so any subsequent image-streaming
            // endpoints do not 404 on the next request. We trust the DB path column
            // as the source of truth for whether a receipt was previously uploaded —
            // never block admin verification over a transient filesystem state.
            if (false && ! Storage::disk('local')->exists($receiptPath)) {
                if (! app()->environment('testing')) {
                    Storage::disk('local')->put(
                        $receiptPath,
                        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHfoAAAAASUVORK5CYII=')
                    );
                }
            }

            // The receipt is stored in the database and was validated above.
            $locked->qrCode()->create(['token' => bin2hex(random_bytes(32)), 'is_active' => true]);
            $locked->update(['test_type' => $test, 'status' => 'Approved', 'verified_by' => $request->user()->id, 'verified_at' => now()]);
            $locked->serviceRequest?->update(['status' => 'processing']);
            */
        });

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            $role = $request->user()->role;
            $fallback = match ($appointment->test_category) {
                'career', 'psychological', 'personality' => route($role.'.'.$appointment->test_category.'.index'),
                default => route($role.'.guidance.index'),
            };
            $previous = url()->previous();
            $redirectUrl = (! empty($previous) && ! str_contains($previous, '/notifications')) ? $previous : $fallback;

            return response()->json([
                'success' => true,
                'message' => 'Receipt verified. The QR pass is now available.',
                'redirect' => $redirectUrl,
            ]);
        }

        $role = $request->user()->role;
        $fallback = match ($appointment->test_category) {
            'career', 'psychological', 'personality' => route($role.'.'.$appointment->test_category.'.index'),
            default => route($role.'.guidance.index'),
        };
        $previous = url()->previous();
        $redirectUrl = (! empty($previous) && ! str_contains($previous, '/notifications')) ? $previous : $fallback;

        return redirect()->to($redirectUrl)->with('success', 'Receipt verified. The QR pass is available in student tracking.');
    }

    public function review(GuidanceAppointment $appointment, \App\Services\GuidanceQrService $qr)
    {
        app(\App\Services\GuidanceAssessmentSessionService::class)->expireDue();
        $appointment->refresh()->load(['applicant', 'serviceRequest', 'qrCode', 'response']);
        $active = in_array($appointment->status, ['Approved', 'In-Progress'], true) && $appointment->qrCode?->is_active;
        $testUrl = $appointment->qrCode ? route('guidance.take', $appointment->qrCode->token) : null;
        return response()->json(['html' => view('guidance.review-frame', [
            'appointment' => $appointment, 'testUrl' => $testUrl,
            'qrImage' => $testUrl ? $qr->dataUri($testUrl) : null,
        ])->render()]);
    }

    public function receipt(GuidanceAppointment $appointment)
    {
        return \App\Services\ReceiptStorage::response($appointment);
    }

    public function results()
    {
        app(\App\Services\GuidanceAssessmentSessionService::class)->expireDue();
        return response()->json(GuidanceAppointment::with('response')->where('status', 'Completed')->latest('updated_at')->limit(50)->get()->map(fn ($entry) => [
            'id' => $entry->getKey(), 'status' => $entry->status,
            'score_summary' => $entry->response?->score_summary,
            'completed_at' => $entry->response?->created_at?->toIso8601String(),
            'results_url' => route(auth()->user()->role.'.guidance-appointments.show-results', $entry),
        ]));
    }

    public function completed()
    {
        app(\App\Services\GuidanceAssessmentSessionService::class)->expireDue();
        return view('guidance.results-index', ['appointments'=>GuidanceAppointment::with(['applicant','response','serviceRequest'])->where('status','Completed')->latest('updated_at')->paginate(25)]);
    }

    public function showResults(GuidanceAppointment $appointment)
    {
        abort_unless($appointment->status === 'Completed', 404);
        $appointment->load(['applicant', 'response', 'serviceRequest']);
        return view('guidance.results', compact('appointment'));
    }


    public function report(Request $request, GuidanceQueueService $queue)
    {
        $batchId = $request->query('batch_id');
        $module  = $request->query('module') ?? $request->query('category');
        $format  = $request->query('format', 'html');

        $query = GuidanceAppointment::with(['applicant', 'serviceRequest', 'response', 'batch']);

        if ($batchId) {
            $batch = \App\Models\GuidanceTestBatch::find($batchId);
            if (!$batch) {
                return redirect()->back()->with('error', 'The requested test batch was not found.');
            }
            $query->where(function ($q) use ($batchId) {
                $q->where('batch_id', $batchId)->orWhere('source_batch_id', $batchId);
            });
        }

        if ($module) {
            $label = \App\Services\GuidanceCategories::LABELS[$module] ?? $module;
            $query->where(function ($q) use ($module, $label) {
                $q->where('test_category', $module)
                  ->orWhere('test_type', $module)
                  ->orWhere('test_type', $label);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('course')) {
            $query->whereHas('applicant', fn ($q) => $q->where('course', $request->query('course')));
        }

        $records = $query->orderBy('guidance_appointment_id')->get();

        if (in_array($format, ['docx', 'pdf'], true) && $records->isEmpty()) {
            return redirect()->back()->with('error', 'Cannot export report: No active guidance test records found for the selected filters.');
        }

        if ($format === 'docx') {
            try {
                if (!extension_loaded('zip') && !class_exists(\ZipArchive::class)) {
                    throw new \RuntimeException('PHP ZipArchive extension is not enabled on this server.');
                }
                $phpWord = new \PhpOffice\PhpWord\PhpWord();
                $phpWord->setDefaultFontName('Arial');
                $phpWord->setDefaultFontSize(10);
                $section = $phpWord->addSection([
                    'orientation' => 'landscape',
                    'marginTop' => 720, 'marginBottom' => 720, 'marginLeft' => 720, 'marginRight' => 720
                ]);
                $section->addText('PANGASINAN STATE UNIVERSITY – SAN CARLOS CAMPUS', ['bold' => true, 'size' => 11, 'color' => '0D1B3E'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                $section->addText('Guidance and Counseling Services Office — Guidance Testing Summary', ['bold' => true, 'size' => 12], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                $section->addText('Generated: ' . now()->timezone('Asia/Manila')->format('F j, Y g:i A'), ['size' => 9, 'italic' => true], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                $section->addTextBreak(1);

                $table = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 50, 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::CENTER]);
                $table->addRow(300, ['tblHeader' => true, 'cantSplit' => true]);
                foreach (['#', 'Reference', 'Student Name', 'Student ID', 'Course', 'Assessment', 'Status', 'Date'] as $h) {
                    $table->addCell(1300, ['bgColor' => '0D1B3E', 'valign' => 'center'])->addText($h, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                }
                $i = 1;
                foreach ($records as $entry) {
                    $table->addRow(260, ['cantSplit' => true]);
                    $table->addCell(500)->addText((string)$i++, ['size' => 8.5], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                    $table->addCell(1400)->addText((string)($entry->serviceRequest?->reference ?? $entry->request_code ?? '-'), ['size' => 8.5], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                    $table->addCell(2200)->addText((string)($entry->applicant?->full_name ?? 'N/A'), ['size' => 8.5, 'bold' => true]);
                    $table->addCell(1200)->addText((string)($entry->serviceRequest?->student_number ?? $entry->applicant?->student_id ?? '-'), ['size' => 8.5], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                    $table->addCell(1100)->addText((string)($entry->applicant?->course ?? $entry->serviceRequest?->course ?? 'N/A'), ['size' => 8.5], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                    $table->addCell(1600)->addText((string)($entry->testLabel() ?? '-'), ['size' => 8.5], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                    $table->addCell(1200)->addText((string)($entry->status ?? 'Unassigned'), ['size' => 8.5, 'bold' => true], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                    $table->addCell(1400)->addText((string)($entry->appointment_at?->timezone('Asia/Manila')->format('Y-m-d') ?? $entry->created_at?->timezone('Asia/Manila')->format('Y-m-d') ?? '-'), ['size' => 8.5], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
                }

                $tempFile = tempnam(sys_get_temp_dir(), 'guidance_docx_');
                try {
                    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
                    $writer->save($tempFile);
                    $bytes = file_get_contents($tempFile);
                    return response($bytes, 200, [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'Content-Disposition' => 'attachment; filename="guidance-test-report-' . now()->format('Y-m-d') . '.docx"',
                    ]);
                } finally {
                    if ($tempFile && file_exists($tempFile)) @unlink($tempFile);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Guidance DOCX Export Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                return redirect()->back()->with('error', 'DOCX generation failed: ' . $e->getMessage());
            }
        }

        if ($format === 'pdf') {
            try {
                $pdf = app(\App\Services\GuidanceArchivePdfService::class)->render($records);
                return response($pdf, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="guidance-test-report-' . now()->format('Y-m-d') . '.pdf"',
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Guidance PDF Export Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                return redirect()->back()->with('error', 'PDF generation failed: ' . $e->getMessage());
            }
        }

        return view('guidance.archive-print', ['appointments' => $records]);
    }
}