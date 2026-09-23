<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestSessionRequest;
use App\Models\TestSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TestSessionController extends Controller
{
    public function index(): View
    {
        return view('staff.sessions.index', [
            'sessions' => TestSession::latest('exam_date')->latest()->paginate(10),
        ]);
    }

    public function create(): View
    {
        return view('staff.sessions.form', [
            'session' => new TestSession(),
            'formAction' => route(auth()->user()->role.'.sessions.store'),
            'method' => 'POST',
        ]);
    }

    public function store(TestSessionRequest $request): RedirectResponse
    {
        $token = Str::uuid()->toString();

        $data = $request->validated();
        $startNum = $data['start_number'] ?? null;
        $endNum = $data['end_number'] ?? null;
        unset($data['start_number'], $data['end_number']);

        $session = TestSession::create([
            ...$data,
            'qr_token' => $token,
            'qr_code_path' => $this->buildQrCodeUrl($token),
        ]);

        // Range-Based Test Session Generation (e.g., Applicants 1 to 20)
        if ($startNum && $endNum && (int)$endNum >= (int)$startNum) {
            $start = (int)$startNum;
            $end = (int)$endNum;
            $limit = ($end - $start) + 1;

            $cycle = \App\Models\AdmissionCycle::active();
            if ($cycle) {
                $apps = $cycle->applicants()
                    ->orderBy('course_choice')
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->skip($start - 1)
                    ->take($limit)
                    ->get();

                foreach ($apps as $app) {
                    $app->update(['session_label' => $session->title]);
                }
            }

            $legacyApps = \App\Models\Applicant::orderBy('last_name')
                ->skip($start - 1)
                ->take($limit)
                ->get();

            foreach ($legacyApps as $legacyApp) {
                \App\Models\SessionApplicant::firstOrCreate([
                    'test_session_id' => $session->id,
                    'applicant_id' => $legacyApp->id,
                ]);
            }
        }

        return redirect()
            ->route(auth()->user()->role.'.sessions.assignments.index', $session)
            ->with('success', 'Test session created and range allocation processed successfully.');
    }

    public function edit(TestSession $session): View
    {
        return view('staff.sessions.form', [
            'session' => $session,
            'formAction' => route(auth()->user()->role.'.sessions.update', $session),
            'method' => 'PUT',
        ]);
    }

    public function update(TestSessionRequest $request, TestSession $session): RedirectResponse
    {
        $token = $session->qr_token ?: Str::uuid()->toString();

        $session->update([
            ...$request->validated(),
            'qr_token' => $token,
            'qr_code_path' => $session->qr_code_path ?: $this->buildQrCodeUrl($token),
        ]);

        return redirect()
            ->route(auth()->user()->role.'.sessions.index')
            ->with('success', 'Test session updated successfully.');
    }

    public function destroy(TestSession $session): RedirectResponse
    {
        $session->delete();

        return redirect()
            ->route(auth()->user()->role.'.sessions.index')
            ->with('success', 'Test session deleted successfully.');
    }

    private function buildQrCodeUrl(string $token): string
    {
        $checkinUrl = rtrim((string) config('app.qr_public_url', config('app.url')), '/')
            .route('checkin.show', $token, false);

        return 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='
            .rawurlencode($checkinUrl);
    }
}
