<?php

namespace App\Http\Controllers;

use App\Http\Requests\QrVerificationRequest;
use App\Models\Applicant;
use App\Models\AttendanceLog;
use App\Models\SessionApplicant;
use App\Models\TestSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QrCheckinController extends Controller
{
    public function show(string $token): View
    {
        $sessionRecord = $this->findSession($token);

        return view('examinee.checkin', [
            'sessionRecord' => $sessionRecord,
            'isCheckinOpen' => $sessionRecord->isCheckinOpen(),
        ]);
    }

    public function verify(QrVerificationRequest $request, string $token): RedirectResponse
    {
        $sessionRecord = $this->findSession($token);

        if (! $sessionRecord->isCheckinOpen()) {
            throw ValidationException::withMessages([
                'application_number' => 'This check-in QR is only available during the scheduled session time.',
            ]);
        }

        $assignment = $this->findAssignedApplicant($sessionRecord->id, $request->validated());
        $applicant = $assignment->applicant;
        $data = $request->validated();

        if ($assignment->is_present) {
            throw ValidationException::withMessages([
                'application_number' => 'This applicant has already checked in for this session.',
            ]);
        }

        $assignment->update([
            'scanned_at' => now(),
            'is_present' => true,
        ]);

        AttendanceLog::updateOrCreate(
            [
                'test_session_id' => $assignment->test_session_id,
                'applicant_id' => $assignment->applicant_id,
            ],
            [
                'scanned_at' => now(),
                'verified_name' => $applicant->full_name,
                'verified_gender' => $data['gender'],
                'verified_application_number' => $data['application_number'],
                'ip_address' => $request->ip(),
            ]
        );

        session([
            'session_applicant_id' => $assignment->id,
        ]);

        return redirect()
            ->route('checkin.waiting')
            ->with('success', 'Verification complete. Please wait for the staff to start the test.');
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
        return TestSession::where('qr_token', $token)
            ->firstOrFail();
    }

    private function currentAssignment(): ?SessionApplicant
    {
        $assignmentId = session('session_applicant_id');

        if (! $assignmentId) {
            return null;
        }

        return SessionApplicant::with(['applicant', 'testSession'])
            ->find($assignmentId);
    }

    private function normalize(string $value): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($value)));
    }

    private function findAssignedApplicant(int $sessionId, array $data): SessionApplicant
    {
        $applicant = Applicant::query()
            ->whereRaw('LOWER(application_number) = ?', [strtolower($data['application_number'])])
            ->whereHas('genderLookup', function ($query) use ($data): void {
                $query->whereRaw('LOWER(name) = ?', [strtolower($data['gender'])]);
            })
            ->get()
            ->first(function (Applicant $applicant) use ($data): bool {
                return $this->normalize($applicant->full_name) === $this->normalize($data['full_name']);
            });

        if (! $applicant) {
            throw ValidationException::withMessages([
                'full_name' => 'The details entered do not match any applicant record.',
            ]);
        }

        $assignment = SessionApplicant::with(['applicant', 'testSession'])
            ->where('test_session_id', $sessionId)
            ->where('applicant_id', $applicant->id)
            ->first();

        if (! $assignment) {
            throw ValidationException::withMessages([
                'application_number' => 'You are not assigned to this scheduled test session.',
            ]);
        }

        return $assignment;
    }
}
