<?php

namespace App\Http\Controllers;

use App\Models\GuidanceAppointment;
use App\Models\ServiceRequest;
use App\Services\GuidanceQrService;
use App\Services\GuidanceTestScoringService;
use Illuminate\Http\Request;

class GuidanceTrackingController extends Controller
{
    public function __invoke(Request $request, GuidanceQrService $qr)
    {
        app(\App\Services\GuidanceAssessmentSessionService::class)->expireDue();
        if (!$request->has('reference')) $request->merge(['reference' => $request->input('request_code')]);
        $data = $request->validate(['reference' => 'required|string|max:64']);
        $reference = strtoupper(trim($data['reference']));
        $lookup = preg_match('/^[A-F0-9]{8}-/D', $reference) ? strtolower($reference) : $reference;
        $entry = ServiceRequest::where('reference', $lookup)->first();
        if (!$entry) $entry = GuidanceAppointment::whereNull('batch_id')->where('request_code', $reference)->first()?->serviceRequest;
        $appointments = $entry
            ? $entry->guidanceAppointments()->with('qrCode')->get()
            : GuidanceAppointment::with('qrCode')->whereNull('batch_id')->where('request_code', $reference)->get();
        abort_if(! $entry && $appointments->isEmpty(), 404, 'No request matches that tracking reference.');
        if ($appointments->isEmpty()) {
            $entry->checkAndApplyExpiration();
            $statusLabel = match ($entry->status) {
                'proof_review' => 'Receipt Uploaded / Pending Verification',
                'pending' => 'Awaiting payment / paid stub',
                'approved' => 'Approved — Paid/stamped stub required',
                'processing' => 'Payment verified — processing document',
                'ready' => 'Ready for pick-up at Guidance Office',
                'scheduled' => 'Verified — scheduled for testing',
                'completed' => 'Completed',
                'declined' => 'Declined',
                'cancelled' => 'Cancelled',
                'void' => 'Void (5-day window expired)',
                default => ucfirst($entry->status),
            };
            return response()->json([
                'success' => true,
                'status' => ucfirst($entry->status),
                'display_status' => $statusLabel,
                'raw_status' => $entry->status,
                'passes' => [],
                'qr_image' => null,
                'direct_test_link' => null,
                'details_html' => view('portal.stub', ['entry' => $entry, 'tracking' => true])->render(),
            ]);
        }
        $passes = $appointments->map(function ($appointment) use ($qr) {
            $active = in_array($appointment->status, ['Approved', 'In-Progress'], true) && $appointment->qrCode?->is_active;
            $url = $active ? route('guidance.take', $appointment->qrCode->token) : null;
            return ['test' => $appointment->testLabel(), 'category_label' => $appointment->categoryLabel(), 'tests' => $appointment->testTypes(), 'category' => $appointment->test_category === 'bundle' ? null : $appointment->test_category,
                'status' => $appointment->status, 'appointment_at' => $appointment->appointment_at?->toIso8601String(),
                'qr_image' => $url ? $qr->dataUri($url) : null, 'direct_test_link' => $url];
        });
        $statuses = $appointments->pluck('status');
        $status = $statuses->every(fn ($status) => $status === 'Completed') ? 'Completed'
            : ($statuses->contains('In-Progress') || $statuses->contains('Completed') ? 'In-Progress'
                : ($statuses->every(fn ($status) => $status === 'Approved') ? 'Approved'
                    : ($statuses->contains('Receipt Uploaded') ? 'Receipt Uploaded' : 'Pending Payment')));
        return response()->json([
            'success' => true,
            'status' => $status,
            'display_status' => $status === 'Receipt Uploaded' ? 'Receipt Uploaded / Pending Verification' : $status,
            'passes' => $passes,
            'qr_image' => $passes->count() === 1 ? $passes[0]['qr_image'] : null,
            'direct_test_link' => $passes->count() === 1 ? $passes[0]['direct_test_link'] : null,
            'receipt_uploaded' => $entry ? (bool) $entry->proof_path : (bool) $appointments->first()->payment_slip_path,
            'details_html' => $entry ? view('portal.stub', ['entry' => $entry, 'tracking' => true])->render() : null,
        ]);
    }

    public function uploadReceipt(\App\Http\Requests\ReceiptUploadRequest $request, \App\Services\GuidancePortalService $portal)
    {
        $data = $request->validated();
        $reference = strtoupper($data['reference']);
        $reference = GuidanceAppointment::whereNull('batch_id')->where('request_code', $reference)->first()?->serviceRequest?->reference ?? $reference;
        $portal->uploadReceipt($reference, $request->file('payment_slip'), $data['or_number'] ?? null, $data['or_date'] ?? null);
        $message = 'Receipt uploaded successfully! Your payment is now pending verification by Guidance Staff.';
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'status' => 'Receipt Uploaded / Pending Verification',
                'message' => $message,
            ]);
        }
        return redirect('/portal?service=testing#track')->with('tracking_reference', $reference)->with('portal_notice', $message);
    }
}
