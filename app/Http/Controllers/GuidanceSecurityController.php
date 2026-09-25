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
            'token' => ['nullable', 'string'],
            'appointment_id' => ['nullable'],
            'event_id' => ['nullable', 'string'],
            'incident_type' => ['required', 'string'],
            'strike_number' => ['nullable', 'integer'],
        ]);

        $appointment = null;
        if (!empty($data['appointment_id'])) {
            $appointment = GuidanceAppointment::find($data['appointment_id']);
        } elseif (!empty($data['token'])) {
            $qr = GuidanceTestQrCode::where('token', $data['token'])->first();
            $appointment = $qr?->appointment;
        }

        if (!$appointment) {
            // Fallback for standalone sessions or test modes
            $strikeNum = $data['strike_number'] ?? 1;
            return response()->json([
                'status' => 'Logged',
                'strike_count' => $strikeNum,
                'terminated' => $strikeNum >= 3,
            ]);
        }

        $eventId = $data['event_id'] ?? (string) \Illuminate\Support\Str::uuid();
        $incidentKey = match (strtolower(trim($data['incident_type']))) {
            'screenshot', 'screenshot / screen record attempt', 'screen record', 'screen capture' => 'screenshot',
            'app switch', 'app_switch', 'tab switch' => 'app_switch',
            'focus loss', 'focus_loss' => 'focus_loss',
            'back navigation', 'back_navigation' => 'back_navigation',
            'print' => 'print',
            'devtools' => 'devtools',
            'copy' => 'copy',
            'context_menu' => 'context_menu',
            'fullscreen_exit' => 'fullscreen_exit',
            default => array_key_exists($data['incident_type'], GuidanceSecurityIncident::TYPES) ? $data['incident_type'] : 'screenshot',
        };

        $existing = $appointment->securityIncidents()->where('event_id', $eventId)->first();
        if (!$existing) {
            $appointment->increment('strike_count');
            $strikeCount = $data['strike_number'] ?? $appointment->strike_count;

            $appointment->securityIncidents()->create([
                'event_id' => $eventId,
                'incident_type' => $incidentKey,
                'strike_number' => $strikeCount,
                'created_at' => now(),
            ]);

            GuidanceTestSecurityLog::create([
                'guidance_appointment_id' => $appointment->getKey(),
                'incident_type' => $data['incident_type'],
                'strike_number' => $strikeCount,
            ]);

            GuidanceSecurityStrikeLogged::dispatch($appointment->getKey(), $strikeCount, $data['incident_type']);
        }

        $threshold = (int) \App\Models\GuidanceSetting::valueOf('strike_threshold', '3');
        if ($appointment->strike_count >= $threshold && $appointment->status === 'In-Progress') {
            $sessions->terminateForViolation($appointment, 'Terminated - Violation');
            return response()->json([
                'status' => 'Terminated - Violation',
                'strike_count' => $appointment->strike_count,
                'terminated' => true,
            ]);
        }

        return response()->json([
            'status' => $appointment->status,
            'strike_count' => $appointment->strike_count,
            'terminated' => $appointment->strike_count >= $threshold,
        ]);
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
