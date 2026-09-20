<?php

namespace App\Http\Controllers;

use App\Models\GuidanceAppointment;
use App\Events\GuidanceSecurityStrikeLogged;
use App\Models\GuidanceSecurityIncident;
use App\Models\GuidanceTestQrCode;
use App\Models\GuidanceTestSecurityLog;
use App\Services\GuidanceAssessmentSessionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuidanceSecurityController extends Controller
{
    public function store(Request $request, GuidanceAssessmentSessionService $sessions)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'event_id' => 'required|uuid',
            'incident_type' => ['required', Rule::in(array_keys(GuidanceSecurityIncident::TYPES))],
        ]);
        abort_unless($request->session()->get('guidance_started.'.hash('sha256', $data['token'])), 403, 'Start this assessment in this browser first.');
        $qr = GuidanceTestQrCode::where('token', $data['token'])->firstOrFail();
        $state = $sessions->mutate($qr->appointment, function ($appointment) use ($data, $sessions) {
            abort_unless($appointment->status === 'In-Progress', 409, 'This assessment has not started.');
            $existing = $appointment->securityIncidents()->where('event_id', $data['event_id'])->first();
            if (!$existing) {
                $appointment->increment('strike_count');
                $appointment->securityIncidents()->create([
                    'event_id' => $data['event_id'],
                    'incident_type' => $data['incident_type'],
                    'strike_number' => $appointment->strike_count,
                    'created_at' => now(),
                ]);
                GuidanceTestSecurityLog::create([
                    'guidance_appointment_id' => $appointment->getKey(),
                    'incident_type' => $data['incident_type'],
                    'strike_number' => $appointment->strike_count,
                ]);
                GuidanceSecurityStrikeLogged::dispatch($appointment->getKey(), $appointment->strike_count, $data['incident_type']);
            }
            if ($appointment->strike_count >= (int) \App\Models\GuidanceSetting::valueOf('strike_threshold', '3')) {
                return $sessions->terminateForViolation($appointment, 'Terminated - Violation');
            }
            return ['status' => $appointment->status, 'strike_count' => $appointment->strike_count];
        }, true);
        return response()->json($state);
    }

    public function feed(Request $request)
    {
        $data = $request->validate(['after' => 'nullable|integer|min:0', 'module' => ['nullable', Rule::in(['psychological', 'personality', 'career'])]]);
        $query = GuidanceSecurityIncident::with(['appointment.applicant', 'appointment.serviceRequest']);
        if (!empty($data['module'])) $query->whereHas('appointment', fn ($appointments) => $appointments->where('test_category', $data['module']));
        if (array_key_exists('after', $data)) $incidents = $query->where('id', '>', $data['after'])->orderBy('id')->limit(100)->get();
        else $incidents = $query->latest('id')->limit(20)->get()->reverse()->values();
        return response()->json([
            'cursor' => $incidents->last()?->id ?? (int) ($data['after'] ?? 0),
            'incidents' => $incidents->map(fn ($incident) => [
                'id' => $incident->id,
                'appointment_id' => $incident->guidance_appointment_id,
                'student_name' => $incident->appointment->applicant->full_name,
                'student_id' => $incident->appointment->student_id_number ?? $incident->appointment->serviceRequest?->student_number ?? 'Not provided',
                'timestamp' => $incident->created_at->toIso8601String(),
                'type' => $incident->incident_type,
                'label' => GuidanceSecurityIncident::TYPES[$incident->incident_type] ?? 'Admin strike reset',
                'threshold' => (int) \App\Models\GuidanceSetting::valueOf('strike_threshold', '3'),
                'strike_count' => $incident->appointment->strike_count,
                'status' => $incident->appointment->status,
                'terminated' => (bool) $incident->appointment->terminated_at,
                'termination_reason' => $incident->appointment->termination_reason,
            ]),
        ]);
    }

    public function terminate(Request $request, GuidanceAppointment $appointment, GuidanceAssessmentSessionService $sessions)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $state = $sessions->terminate($appointment, $request->user()->id, $data['reason']);
        if ($request->expectsJson()) return response()->json($state);
        return back()->with('success', 'Assessment finalized from saved answers. The pass is now inactive.');
    }
}
