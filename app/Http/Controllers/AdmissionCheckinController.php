<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionSession;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Applicant Verification & Check-In (Spec §6)
 *
 * When a session QR link is scanned or opened, this controller presents
 * the verification form and validates the applicant against the session roster
 * before granting access to the exam waiting screen.
 *
 * Routes:
 *   GET  /admission/check-in/{qrToken}        → show
 *   POST /admission/check-in/{qrToken}/verify → verify
 */
class AdmissionCheckinController extends Controller
{
    private function normalize(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    }

    public function show(string $qrToken)
    {
        $session = AdmissionSession::where('qr_token', $qrToken)->firstOrFail();
        return view('admission.checkin', compact('session'));
    }

    public function verify(Request $request, string $qrToken)
    {
        $session = AdmissionSession::where('qr_token', $qrToken)->firstOrFail();

        $data = $request->validate([
            'first_name'  => 'required|string|max:120',
            'middle_name' => 'nullable|string|max:120',
            'last_name'   => 'required|string|max:120',
        ]);

        // Match against the session roster using fuzzy name comparison
        $applicant = $session->applicants()->get()->first(function (AdmissionApplicant $app) use ($data) {
            $firstMatch  = $this->normalize($app->first_name) === $this->normalize($data['first_name']);
            $lastMatch   = $this->normalize($app->last_name)  === $this->normalize($data['last_name']);
            $middleMatch = empty($data['middle_name']) || $this->normalize($app->middle_name ?? '') === $this->normalize($data['middle_name']);
            return $firstMatch && $lastMatch && $middleMatch;
        });

        if (!$applicant) {
            throw ValidationException::withMessages([
                'first_name' => 'No matching applicant found in this session roster. Please check your name.',
            ]);
        }

        if ($applicant->submitted_at) {
            throw ValidationException::withMessages([
                'first_name' => 'This applicant has already submitted their examination.',
            ]);
        }

        // Store in session so the waiting page can read it
        session([
            'admission_checkin_applicant_id' => $applicant->id,
            'admission_checkin_session_id'   => $session->id,
        ]);

        return redirect()->route('admission.checkin.waiting')
            ->with('success', 'Verification complete! Please wait for the proctor to start the exam.');
    }

    public function waiting(Request $request)
    {
        $applicantId = session('admission_checkin_applicant_id');
        $sessionId   = session('admission_checkin_session_id');

        if (!$applicantId || !$sessionId) {
            return redirect()->route('portal.index');
        }

        $applicant    = AdmissionApplicant::findOrFail($applicantId);
        $examSession  = AdmissionSession::findOrFail($sessionId);

        return view('admission.waiting', compact('applicant', 'examSession'));
    }
}
