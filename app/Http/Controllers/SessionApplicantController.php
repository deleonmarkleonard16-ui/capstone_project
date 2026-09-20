<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignApplicantsRequest;
use App\Models\Applicant;
use App\Models\SessionApplicant;
use App\Models\TestSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SessionApplicantController extends Controller
{
    public function index(TestSession $session): View
    {
        if (! $session->qr_token || ! $session->qr_code_path) {
            $this->refreshSessionQr($session);
            $session->refresh();
        }

        $assignedApplicantIds = $session->sessionApplicants()->pluck('applicant_id');

        return view('staff.assignments.index', [
            'session' => $session->load(['sessionApplicants.applicant']),
            'availableApplicants' => Applicant::whereNotIn('id', $assignedApplicantIds)
                ->orderBy('last_name')
                ->get(),
        ]);
    }

    public function store(AssignApplicantsRequest $request, TestSession $session): RedirectResponse
    {
        foreach ($request->validated()['applicant_ids'] as $applicantId) {
            SessionApplicant::firstOrCreate(
                [
                    'test_session_id' => $session->id,
                    'applicant_id' => $applicantId,
                ],
                [
                    'scanned_at' => null,
                    'is_present' => false,
                ]
            );
        }

        return redirect()
            ->route(auth()->user()->role.'.sessions.assignments.index', $session)
            ->with('success', 'Applicants assigned to the session successfully.');
    }

    public function destroy(TestSession $session, SessionApplicant $assignment): RedirectResponse
    {
        abort_unless($assignment->test_session_id === $session->id, 404);

        $assignment->delete();

        return redirect()
            ->route(auth()->user()->role.'.sessions.assignments.index', $session)
            ->with('success', 'Assigned applicant removed from session.');
    }

    public function regenerateQr(TestSession $session): RedirectResponse
    {
        $this->refreshSessionQr($session);

        return redirect()
            ->route(auth()->user()->role.'.sessions.assignments.index', $session)
            ->with('success', 'Session QR code regenerated successfully.');
    }

    private function buildQrCodeUrl(string $token): string
    {
        $checkinUrl = rtrim((string) config('app.qr_public_url', config('app.url')), '/')
            .route('checkin.show', $token, false);

        return 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.rawurlencode($checkinUrl);
    }

    private function refreshSessionQr(TestSession $session): void
    {
        $token = Str::uuid()->toString();

        $session->update([
            'qr_token' => $token,
            'qr_code_path' => $this->buildQrCodeUrl($token),
        ]);
    }
}
