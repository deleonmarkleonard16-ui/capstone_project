<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\GuidanceAppointment;
use App\Models\GuidanceTestQrCode;
use App\Services\GuidanceTestScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GuidanceAppointmentController extends Controller
{
    public function create()
    {
        return redirect('/portal?service=testing#request');
    }

    public function store(Request $request, GuidanceTestScoringService $scoring)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'student_status' => ['required', Rule::in(['student', 'alumni'])],
            'test_type' => ['required', Rule::in(array_merge(array_keys(\App\Services\GuidanceCategories::LABELS), ['dass21', 'phq9', 'gad7', 'bfpi']))],
            'payment_slip' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:max_width=8000,max_height=8000',
            'consent' => 'accepted',
        ]);
        $category = array_key_exists($data['test_type'], \App\Services\GuidanceCategories::TESTS) ? $data['test_type'] : null;
        $instruments = $category ? \App\Services\GuidanceCategories::TESTS[$category] : [$data['test_type']];
        foreach ($instruments as $instrument) $scoring->definition($instrument);
        $receipt = \App\Services\ReceiptStorage::payload($request->file('payment_slip'));
        $appointment = DB::transaction(function () use ($data, $receipt, $category, $instruments) {
                // Public requests never attach themselves to an existing applicant by guessed ID.
                $applicant = Applicant::create([
                    'application_number' => 'GT-'.strtoupper(bin2hex(random_bytes(12))),
                    'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
                    'email' => $data['email'] ?? null, 'status' => 'pending',
                ]);
                return GuidanceAppointment::create([
                    'applicant_id' => $applicant->id,
                    'request_code' => app(\App\Services\GuidanceReferenceService::class)->reserve(),
                    'test_type' => $instruments[0], 'test_category' => $category, 'test_types' => $category ? $instruments : null, 'student_status' => $data['student_status'],
                    ...$receipt, 'status' => 'Receipt Uploaded', 'payment_slip_path' => null, 'appointment_at' => now(),
                ]);
            });
        return redirect('/portal?service=good-moral#track')->with('guidance_request_code', $appointment->request_code);
    }

    public function track(Request $request)
    {
        if (is_string($request->input('request_code'))) $request->merge(['request_code' => strtoupper(trim($request->input('request_code')))]);
        $data = $request->validate(['request_code' => ['required', 'string', 'regex:/^(?:G-[A-Z0-9]{4}|(?:GT|TR)-[A-F0-9]{32})$/D']]);
        $appointment = GuidanceAppointment::with('qrCode')->whereNull('batch_id')->where('request_code', strtoupper($data['request_code']))->firstOrFail();
        $active = in_array($appointment->status, ['Approved', 'In-Progress']) && $appointment->qrCode?->is_active;
        return response()->json([
            'status' => $appointment->status,
            'test' => GuidanceTestScoringService::LABELS[$appointment->test_type],
            'appointment_at' => $appointment->appointment_at?->toIso8601String(),
            'url' => $active ? route('guidance.take', $appointment->qrCode->token) : null,
        ]);
    }

    public function take(string $token, GuidanceTestScoringService $scoring, \App\Services\GuidanceAssessmentSessionService $sessions)
    {
        $sessions->expireDue();
        $qr = $this->activeQr($token);
        $definitions = [];
        foreach ($qr->appointment->testTypes() as $test) $definitions[$test] = $scoring->definition($test);
        $definition = $definitions[$qr->appointment->test_type] ?? reset($definitions);
        return view('guidance.take', [
            'token' => $token,
            'appointment' => $qr->appointment,
            'definition' => $definition,
            'definitions' => $definitions,
            'sessionState' => $sessions->state($qr->appointment),
            'sections' => $sessions->sections($qr->appointment),
        ]);
    }

    public function start(Request $request, string $token, \App\Services\GuidanceAssessmentSessionService $sessions)
    {
        $state = $sessions->start($this->activeQr($token)->appointment);
        if ($state['status'] === 'In-Progress') $request->session()->put('guidance_started.'.hash('sha256', $token), true);
        return response()->json($state);
    }

    public function saveProgress(Request $request, string $token, \App\Services\GuidanceAssessmentSessionService $sessions)
    {
        abort_unless($request->session()->get('guidance_started.'.hash('sha256', $token)), 403, 'Start this assessment first.');
        $data = $request->validate(['answers'=>'present|array', 'section_index' => 'nullable|integer|min:0']);
        return response()->json($sessions->save($this->activeQr($token)->appointment, $data['answers'], false, $data['section_index'] ?? null));
    }

    public function state(Request $request, string $token, \App\Services\GuidanceAssessmentSessionService $sessions)
    {
        abort_unless($request->session()->get('guidance_started.'.hash('sha256', $token)), 403);
        return response()->json($sessions->mutate($this->activeQr($token)->appointment, fn ($locked) => $sessions->state($locked)));
    }

    public function submit(Request $request, string $token, \App\Services\GuidanceAssessmentSessionService $sessions)
    {
        abort_unless($request->session()->get('guidance_started.'.hash('sha256', $token)), 403, 'Start this assessment first.');
        $data = $request->validate(['answers'=>'sometimes|array', 'section_index' => 'nullable|integer|min:0']);
        $state = $sessions->save($this->activeQr($token)->appointment, $data['answers'] ?? [], true, $data['section_index'] ?? null);
        // Keep the session marker so a retry can receive the inactive-pass response after a lost network response.
        return response()->json($state);
    }
    public function complete(Request $request, string $token)
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        abort_unless($request->session()->get('guidance_started.'.hash('sha256', $token)), 403);
        $qr = GuidanceTestQrCode::with('appointment')->where('token', $token)->firstOrFail();
        abort_unless($qr->appointment->status === 'Completed', 409, 'This assessment is still active.');
        return view('guidance.complete', ['terminated' => (bool) $qr->appointment->terminated_at]);
    }

    private function activeQr(string $token): GuidanceTestQrCode
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        $qr = GuidanceTestQrCode::with('appointment')->where('token', $token)->firstOrFail();
        abort_unless($qr->is_active && in_array($qr->appointment->status, ['Approved', 'In-Progress']), 410, 'This assessment pass is no longer active.');
        return $qr;
    }
}
