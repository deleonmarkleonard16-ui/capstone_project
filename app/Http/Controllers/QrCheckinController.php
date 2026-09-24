<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplicant;
use App\Models\Applicant;
use App\Models\AttendanceLog;
use App\Models\SessionApplicant;
use App\Models\TestSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QrCheckinController extends Controller
{
    public function show(string $token): View
    {
        $sessionRecord = $this->findSession($token);
        return view('examinee.checkin', [
            'sessionRecord'  => $sessionRecord,
            'isCheckinOpen'  => $sessionRecord->isCheckinOpen(),
        ]);
    }

    public function verify(Request $request, string $token): RedirectResponse
    {
        $sessionRecord = $this->findSession($token);

        if (! $sessionRecord->isCheckinOpen()) {
            throw ValidationException::withMessages([
                'application_number' => 'Check-in is not open at this time.',
                'last_name'          => 'Check-in is not open at this time.',
            ]);
        }

        // Support both admission applicants (new pipeline) and legacy applicants
        if ($request->filled('application_number') || ($request->filled('full_name') && ! $request->filled('last_name'))) {
            $data = $request->validate([
                'application_number' => ['nullable', 'string', 'max:255'],
                'full_name'          => ['nullable', 'string', 'max:255'],
                'gender'             => ['nullable', 'string', 'max:50'],
            ]);
        } else {
            $data = $request->validate([
                'first_name'  => ['required', 'string', 'max:255'],
                'middle_name' => ['nullable', 'string', 'max:255'],
                'last_name'   => ['required', 'string', 'max:255'],
            ]);
        }

        $assignment = $this->findAssignment($sessionRecord->id, $data);

        if (! $assignment) {
            throw ValidationException::withMessages([
                'application_number' => 'The details you entered do not match any applicant assigned to this session. Please check your details.',
                'last_name'          => 'The details you entered do not match any applicant assigned to this session. Please check your name spelling.',
            ]);
        }

        if ($assignment->is_present ?? false) {
            throw ValidationException::withMessages([
                'application_number' => 'This applicant has already checked in.',
                'last_name'          => 'This applicant has already checked in.',
            ]);
        }

        $assignment->update(['scanned_at' => now(), 'is_present' => true]);

        if ($assignment instanceof SessionApplicant) {
            $applicant = $assignment->applicant;
            AttendanceLog::updateOrCreate(
                ['test_session_id' => $assignment->test_session_id, 'applicant_id' => $assignment->applicant_id],
                [
                    'scanned_at'                  => now(),
                    'verified_name'               => $request->input('full_name') ?? $applicant?->full_name ?? trim(($applicant?->first_name ?? '') . ' ' . ($applicant?->last_name ?? '')),
                    'verified_gender'             => $request->input('gender') ?? $applicant?->gender,
                    'verified_application_number' => $request->input('application_number') ?? $applicant?->application_number,
                    'ip_address'                  => $request->ip(),
                ]
            );
        }

        session(['session_applicant_id' => $assignment->id]);

        return redirect()->route('checkin.waiting')
            ->with('success', 'Verification complete. Please wait for the exam to begin.');
    }

    public function waiting(): View|RedirectResponse
    {
        $assignment = $this->currentAssignment();
        if (! $assignment) {
            return redirect()->route('login');
        }
        return view('examinee.waiting', [
            'assignment' => $assignment->load(['applicant', 'testSession']),
        ]);
    }

    private function findSession(string $token): TestSession
    {
        return TestSession::where('qr_token', $token)->firstOrFail();
    }

    private function currentAssignment(): ?SessionApplicant
    {
        $id = session('session_applicant_id');
        if (! $id) return null;
        return SessionApplicant::with(['applicant', 'testSession'])->find($id);
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($value)));
    }

    private function findAssignment(int $sessionId, array $data): ?SessionApplicant
    {
        // 1. Check by application_number if provided
        if (!empty($data['application_number'])) {
            $legacyApplicant = Applicant::where('application_number', $data['application_number'])->first();
            if ($legacyApplicant) {
                $assignment = SessionApplicant::where('test_session_id', $sessionId)
                    ->where('applicant_id', $legacyApplicant->id)
                    ->first();
                if ($assignment) return $assignment;
            }

            $admApplicant = AdmissionApplicant::where('application_number', $data['application_number'])->first();
            if ($admApplicant) {
                $assignment = SessionApplicant::where('test_session_id', $sessionId)
                    ->where('applicant_id', $admApplicant->id)
                    ->first();
                if ($assignment) return $assignment;
            }
        }

        // 2. Check by full_name if provided
        if (!empty($data['full_name'])) {
            $normFullName = $this->normalize($data['full_name']);
            $match = SessionApplicant::with(['applicant', 'testSession'])
                ->where('test_session_id', $sessionId)
                ->get()
                ->first(function (SessionApplicant $sa) use ($normFullName) {
                    $applicant = $sa->applicant;
                    if (! $applicant) return false;
                    return $this->normalize($applicant->full_name ?? '') === $normFullName
                        || $this->normalize(trim(($applicant->first_name ?? '') . ' ' . ($applicant->last_name ?? ''))) === $normFullName
                        || $this->normalize(trim(($applicant->last_name ?? '') . ', ' . ($applicant->first_name ?? ''))) === $normFullName;
                });
            if ($match) return $match;
        }

        // 3. Check by first_name and last_name
        if (!empty($data['first_name']) && !empty($data['last_name'])) {
            return $this->findAssignedAdmissionApplicant($sessionId, $data)
                ?? $this->findAssignedLegacyApplicant($sessionId, $data);
        }

        return null;
    }

    /**
     * Find an admission applicant (new PSU-CAT pipeline) assigned to this session
     * by matching first_name, last_name, and optionally middle_name.
     */
    private function findAssignedAdmissionApplicant(int $sessionId, array $data): ?SessionApplicant
    {
        $session = TestSession::find($sessionId);
        if (! $session) return null;

        $query = AdmissionApplicant::query()
            ->whereRaw('LOWER(last_name) = ?', [$this->normalize($data['last_name'])])
            ->whereRaw('LOWER(first_name) = ?', [$this->normalize($data['first_name'])])
            ->where('session_label', $session->title);

        if (!empty($data['middle_name'])) {
            $query->whereRaw('LOWER(middle_name) = ?', [$this->normalize($data['middle_name'])]);
        }

        $admApplicant = $query->first();
        if (! $admApplicant) return null;

        return SessionApplicant::where('test_session_id', $sessionId)
            ->where('applicant_id', $admApplicant->id)
            ->first();
    }

    /**
     * Find a legacy applicant assigned to this session by name matching.
     */
    private function findAssignedLegacyApplicant(int $sessionId, array $data): ?SessionApplicant
    {
        return SessionApplicant::with(['applicant', 'testSession'])
            ->where('test_session_id', $sessionId)
            ->get()
            ->first(function (SessionApplicant $sa) use ($data) {
                $applicant = $sa->applicant;
                if (! $applicant) return false;
                $lastMatch  = $this->normalize($applicant->last_name ?? '')  === $this->normalize($data['last_name']);
                $firstMatch = $this->normalize($applicant->first_name ?? '') === $this->normalize($data['first_name']);
                return $lastMatch && $firstMatch;
            });
    }
}

