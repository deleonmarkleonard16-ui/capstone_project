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
        return app(DocumentExportController::class)->archive($request, $queue);
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

    public function evaluate(Request $request, GuidanceAppointment $appointment)
    {
        $data = $request->validate([
            'remarks' => 'nullable|string|max:5000',
            'recommendations' => 'nullable|string|max:500',
            'action' => ['required', Rule::in(['save', 'move_to_review', 'archive', 'complete_and_archive'])],
        ]);

        DB::transaction(function () use ($request, $appointment, $data) {
            $locked = GuidanceAppointment::whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

            $update = [
                'remarks' => $data['remarks'] ?? $locked->remarks,
                'recommendations' => $data['recommendations'] ?? $locked->recommendations,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ];

            if ($data['action'] === 'move_to_review') {
                abort_unless($locked->status === 'Terminated', 409, 'Only a terminated assessment can be moved to Under Review.');
                $update['status'] = 'Under review';
                $locked->update($update);
            } elseif (in_array($data['action'], ['archive', 'complete_and_archive'], true)) {
                $update['status'] = 'Completed';
                $update['is_archived'] = true;
                $update['archived_at'] = now();
                $update['attendance_status'] = 'Completed';

                $locked->update($update);

                if ($locked->batch_id && $locked->batch) {
                    app(\App\Services\GuidanceBatchService::class)->completeIfDone($locked->batch);
                }

                if ($locked->service_request_id) {
                    $serviceRequest = \App\Models\ServiceRequest::lockForUpdate()->find($locked->service_request_id);
                    if ($serviceRequest) {
                        $hasUnarchived = $serviceRequest->guidanceAppointments()
                            ->where('guidance_appointment_id', '!=', $locked->guidance_appointment_id)
                            ->where(function ($q) {
                                $q->where('is_archived', false)->orWhere('status', '!=', 'Completed');
                            })->exists();
                        if (!$hasUnarchived) {
                            $serviceRequest->update(['status' => 'completed', 'archived_at' => now()]);
                        }
                    }
                }
            } else {
                $locked->update($update);
            }
        });

        $isArchived = in_array($data['action'], ['archive', 'complete_and_archive'], true);
        $movedToReview = $data['action'] === 'move_to_review';
        $message = $isArchived ? 'Evaluation finalized and record moved to Module Archives.' : ($movedToReview ? 'Assessment moved to Under Review.' : 'Remarks and recommendations saved successfully.');

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'status' => $isArchived ? 'Completed' : 'Under review',
                'is_archived' => $isArchived,
            ]);
        }

        return back()->with('success', $message);
    }

    public function resumeTerminated(Request $request, GuidanceAppointment $appointment, \App\Services\GuidanceAssessmentSessionService $sessions)
    {
        $state = $sessions->resumeTerminated($appointment);
        $message = 'Termination removed. The QR pass is active again and the requester can continue from saved answers.';

        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'status' => $state['status']]);
        }

        return back()->with('success', $message);
    }

    public function showResults(GuidanceAppointment $appointment)
    {
        abort_unless($appointment->response && in_array($appointment->status, ['Under review', 'Completed'], true), 404);
        $appointment->load(['applicant', 'response', 'serviceRequest', 'batch', 'sourceBatch']);
        return view('guidance.results', compact('appointment'));
    }


    public function report(Request $request, GuidanceQueueService $queue)
    {
        return app(DocumentExportController::class)->legacyReport($request, 'guidance-testing');
    }
}
