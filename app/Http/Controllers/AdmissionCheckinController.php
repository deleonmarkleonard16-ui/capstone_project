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
 * 3. On successful match, redirects to the secure digital lockdown exam page (/admission/take/{token})
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

        return view('admission.checkin', compact('session', 'isCheckinOpen'));
    }

    /**
     * Validate examinee credentials against session roster and redirect to digital lockdown exam.
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
            // If examinee supplied middle name, and candidate record has a non-empty middle name, verify
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
            // Multiple examinees share first & last name; disambiguate with middle name
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

        // If not found in this session roster, check if assigned to a different session in the cycle
        if (!$applicant) {
            $other = AdmissionApplicant::where('admission_cycle_id', $session->admission_cycle_id)
                ->whereRaw('LOWER(TRIM(last_name)) = ?', [$this->normalize($last)])
                ->whereRaw('LOWER(TRIM(first_name)) = ?', [$this->normalize($first)])
                ->first();

            if ($other && $other->admission_session_id && $other->admission_session_id !== $session->id) {
                $otherSessionName = $other->admissionSession?->session_name ?? 'another session';
                throw ValidationException::withMessages([
                    'last_name' => "Examinee '{$first} {$last}' is scheduled under {$otherSessionName}. Please proceed to your designated venue.",
                ]);
            }

            throw ValidationException::withMessages([
                'last_name' => 'No examinee matching the entered name was found in this session roster. Please verify your spelling.',
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

        // Redirect to the secure digital lockdown answer page
        return redirect()->route('admission.take', ['token' => $applicant->exam_token]);
    }
}
