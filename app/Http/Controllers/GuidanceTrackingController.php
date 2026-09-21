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
            return response()->json(['status' => ucfirst($entry->status), 'passes' => [], 'qr_image' => null, 'direct_test_link' => null,
                'details_html' => view('portal.stub', ['entry' => $entry, 'tracking' => true])->render()]);
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
        return response()->json(['status' => $status, 'passes' => $passes,
            'qr_image' => $passes->count() === 1 ? $passes[0]['qr_image'] : null,
            'direct_test_link' => $passes->count() === 1 ? $passes[0]['direct_test_link'] : null,
            'receipt_uploaded' => $entry ? (bool) $entry->proof_path : (bool) $appointments->first()->payment_slip_path,
            'details_html' => $entry ? view('portal.stub', ['entry' => $entry, 'tracking' => true])->render() : null]);
    }

    public function uploadReceipt(Request $request, \App\Services\GuidancePortalService $portal)
    {
        if (!$request->has('reference')) $request->merge(['reference' => $request->input('request_code')]);
        if (is_string($request->input('reference'))) $request->merge(['reference' => strtoupper(trim($request->input('reference')))]);
        if ($request->hasFile('proof') && !$request->hasFile('payment_slip')) {
            $request->files->set('payment_slip', $request->file('proof'));
        }
        $data = $request->validate([
            'reference' => ['required', 'string', 'regex:/^(?:G-[A-Z0-9]{4}|(?:TR|GT)-[A-F0-9]{32})$/D'],
            'or_number' => 'required|string|max:50',
            'or_date'   => 'required|date|before_or_equal:today',
            'payment_slip' => 'required|image|max:5120',
        ]);
        $reference = strtoupper($data['reference']);
        $reference = GuidanceAppointment::whereNull('batch_id')->where('request_code', $reference)->first()?->serviceRequest?->reference ?? $reference;
        $portal->uploadReceipt($reference, $request->file('payment_slip'), $data['or_number'], $data['or_date']);
        if ($request->expectsJson()) return response()->json(['status' => 'Receipt Uploaded', 'message' => 'Receipt uploaded. Please wait for staff verification.']);
        return redirect('/portal?service=testing#track')->with('tracking_reference', $reference)->with('portal_notice', 'Receipt uploaded. Please wait for staff verification.');
    }
}
