<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * PSU-CAT Admission Session Venue Check-In Controller
 *
 * Handles examinee check-in when scanning /admission/checkin/{session_token}:
 * 1. Displays Attendance Verification form (First Name, Middle Name [Optional], Last Name)
 * 2. Validates credentials against the Session Roster
 * 3. On successful match:
 *    - If session is In-Progress: redirects directly to the secure digital lockdown exam page (/admission/take/{token})
 *    - If session is Scheduled: redirects to the waiting room which refreshes automatically
 */
class AdmissionCheckinController extends Controller
{
    private function normalize(string $s): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    }

    /**
     * Display the Attendance Verification Form for the given Session.
     */
    public function show(string $session_token)
    {
        $session = AdmissionSession::with('cycle')->where('qr_token', $session_token)->firstOrFail();
        $isCheckinOpen = $session->isOpen();

        // Check if user already checked in for this session in their current browser session
        $checkedInApplicantId = session('admission_checkin_applicant_id');
        $checkedInSessionId   = session('admission_checkin_session_id');

        if ($checkedInApplicantId && $checkedInSessionId === $session->id) {
            $applicant = AdmissionApplicant::find($checkedInApplicantId);
            if ($applicant && !$applicant->submitted_at) {
                if ($session->isInProgress() || $session->status === 'In-Progress') {
                    return redirect()->route('admission.take', ['token' => $applicant->exam_token]);
                }
                return redirect()->route('admission.checkin.waiting', ['session_token' => $session_token]);
            }
        }

        return view('admission.checkin', compact('session', 'isCheckinOpen'));
    }

    /**
     * Validate examinee credentials against session roster and redirect to digital lockdown exam or waiting room.
     */
    public function verify(Request $request, string $session_token)
    {
        $session = AdmissionSession::with('cycle')->where('qr_token', $session_token)->firstOrFail();

        $data = $request->validate([
            'first_name'  => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'last_name'   => ['required', 'string', 'max:120'],
        ]);

        $first  = trim($data['first_name']);
        $last   = trim($data['last_name']);
        $middle = trim((string) ($data['middle_name'] ?? ''));

        // Check if session check-in is permitted
        if ($session->isCompleted()) {
            throw ValidationException::withMessages([
                'last_name' => 'This admission test session has already concluded and is marked as Completed.',
            ]);
        }

        // Search assigned applicants in this session roster
        $applicants = $session->applicants()
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [$this->normalize($last)])
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [$this->normalize($first)])
            ->get();

        $applicant = null;

        if ($applicants->count() === 1) {
            $candidate = $applicants->first();
            if ($middle !== '' && !empty($candidate->middle_name)) {
                $candMid = $this->normalize($candidate->middle_name);
                $inpMid  = $this->normalize($middle);
                if ($candMid !== $inpMid && substr($candMid, 0, 1) !== substr($inpMid, 0, 1)) {
                    throw ValidationException::withMessages([
                        'middle_name' => 'The provided middle name does not match the admission roster record.',
                    ]);
                }
            }
            $applicant = $candidate;
        } elseif ($applicants->count() > 1) {
            if ($middle !== '') {
                $inpMid = $this->normalize($middle);
                $applicant = $applicants->first(function (AdmissionApplicant $app) use ($inpMid) {
                    $candMid = $this->normalize($app->middle_name ?? '');
                    return $candMid === $inpMid || (strlen($candMid) > 0 && substr($candMid, 0, 1) === substr($inpMid, 0, 1));
                });
            }
            if (!$applicant) {
                $applicant = $applicants->first();
            }
        }

        // If not found in this session roster, check if assigned to another session
        if (!$applicant) {
            $other = AdmissionApplicant::where('admission_cycle_id', $session->admission_cycle_id)
                ->whereRaw('LOWER(TRIM(last_name)) = ?', [$this->normalize($last)])
                ->whereRaw('LOWER(TRIM(first_name)) = ?', [$this->normalize($first)])
                ->first();

            if ($other && $other->admission_session_id && $other->admission_session_id !== $session->id) {
                $otherSessionName = $other->admissionSession?->session_name ?? 'another session';
                throw ValidationException::withMessages([
                    'last_name' => "The details entered match an examinee scheduled under '{$otherSessionName}'. Please proceed to your designated session/venue.",
                ]);
            }

            throw ValidationException::withMessages([
                'last_name' => 'The details entered do not match any applicant record.',
            ]);
        }

        // Prevent taking the exam again if already submitted
        if ($applicant->submitted_at) {
            throw ValidationException::withMessages([
                'last_name' => 'This examinee has already submitted their examination answers.',
            ]);
        }

        // Generate examination token if not already created
        if (empty($applicant->exam_token)) {
            $applicant->forceFill(['exam_token' => Str::random(64)])->save();
        }

        // Store verification details in session
        session([
            'admission_checkin_applicant_id' => $applicant->id,
            'admission_checkin_session_id'   => $session->id,
        ]);

        // If session is already In-Progress, go straight to digital lockdown exam
        if ($session->isInProgress() || $session->status === 'In-Progress') {
            return redirect()->route('admission.take', ['token' => $applicant->exam_token]);
        }

        // If session is Scheduled, show waiting room
        return redirect()->route('admission.checkin.waiting', ['session_token' => $session_token])
            ->with('success', 'Attendance verified! Please wait for the admin to start the exam.');
    }

    /**
     * Display the Waiting Room while awaiting session start (Image 3).
     */
    public function waiting(string $session_token)
    {
        $session = AdmissionSession::with('cycle')->where('qr_token', $session_token)->firstOrFail();

        $applicantId = session('admission_checkin_applicant_id');
        if (!$applicantId) {
            return redirect()->route('admission.checkin.show', ['session_token' => $session_token]);
        }

        $applicant = AdmissionApplicant::findOrFail($applicantId);

        // If session is already In-Progress, redirect to lockdown exam
        if ($session->isInProgress() || $session->status === 'In-Progress') {
            return redirect()->route('admission.take', ['token' => $applicant->exam_token]);
        }

        return view('admission.waiting', compact('session', 'applicant'));
    }
}
