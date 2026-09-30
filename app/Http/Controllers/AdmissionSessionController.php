<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Models\AdmissionSession;
use App\Models\GuidanceTestSecurityLog;
use App\Services\AdmissionScoringService;
use App\Support\CourseCatalog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * PSU-CAT Admission Session Controller
 *
 * Handles Test Session Management for the Active Admission Cycle.
 * Configuration requirements:
 * 1. Session Name (e.g., "Session A - Batch 1")
 * 2. Start Date & Start Time
 * 3. Masterlist Range Assignment (Start Index # and End Index #)
 *
 * Execution starts at Start Time and remains open until manually
 * marked as 'Completed' or until all assigned applicants submit exams.
 */
class AdmissionSessionController extends Controller
{
    private function active(): ?AdmissionCycle
    {
        return AdmissionCycle::active();
    }

    private function gatekeeperRedirect()
    {
        return redirect()->route('admin.admission.index')
            ->with('warning', 'Please select or initialize an Active Admission Cycle before managing sessions.');
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $cycle = $this->active();
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        $sessions = $cycle->sessions()
            ->withCount([
                'applicants',
                'applicants as submitted_count' => fn ($q) => $q->whereNotNull('submitted_at'),
            ])
            ->orderBy('start_time', 'asc')
            ->get();

        $totalApplicants = $cycle->applicants()->count();
        $courses = CourseCatalog::activeOptions();

        return view('admin.admission.sessions.index', compact('cycle', 'sessions', 'totalApplicants', 'courses'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create()
    {
        $cycle = $this->active();
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        return redirect()->route('admin.admission.sessions.index');
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request, AdmissionScoringService $scoring)
    {
        $cycle = $this->active();
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }
        abort_if($cycle->isCompleted(), 422, 'Cannot add sessions to an archived cycle.');

        $data = $request->validate([
            'session_name'  => 'required|string|max:120',
            'start_time'    => 'required|date',
            'start_number'  => 'required|integer|min:1',
            'end_number'    => 'required|integer|gte:start_number',
            'room'          => 'nullable|string|max:120',
            'batch_group'   => 'nullable|string|max:100',
            'course_filter' => 'nullable|string|max:30',
        ]);

        $session = $cycle->sessions()->create([
            'session_name' => $data['session_name'],
            'start_time'   => Carbon::parse($data['start_time']),
            'start_number' => (int) $data['start_number'],
            'end_number'   => (int) $data['end_number'],
            'room'         => $data['room'] ?? null,
            'qr_token'     => Str::random(64),
            'status'       => AdmissionSession::STATUS_SCHEDULED,
        ]);

        // ── Masterlist Range Assignment ────────────────────────────────────────
        $start = (int) $data['start_number'];
        $end   = (int) $data['end_number'];
        $limit = ($end - $start) + 1;

        $query = $cycle->applicants()->orderBy('id');

        if (!empty($data['batch_group']) && $data['batch_group'] !== '__all__') {
            $query->where('batch_group', $data['batch_group']);
        }
        if (!empty($data['course_filter'])) {
            $query->where('course_choice', $data['course_filter']);
        }

        $targets = $query->skip($start - 1)->take($limit)->get();
        foreach ($targets as $applicant) {
            $applicant->update([
                'admission_session_id' => $session->id,
                'session_label'        => $session->session_name,
            ]);
        }

        $scoring->evaluate($cycle);

        return redirect()->route('admin.admission.sessions.index')
            ->with('success', "Session '{$session->session_name}' created successfully. {$targets->count()} applicant(s) assigned (Masterlist Range: #{$start}–#{$end}).");
    }

    // ── Show / Roster Monitor ──────────────────────────────────────────────────

    public function show(AdmissionSession $session)
    {
        $cycle = $session->cycle;
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        $session->loadMissing('cycle');

        $applicants = $session->applicants()
            ->with(['securityLogs' => fn ($q) => $q->latest('created_at')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // Other available sessions in this cycle for reassignment (not completed, not current session)
        $otherSessions = $cycle->sessions()
            ->where('id', '!=', $session->id)
            ->where('status', '!=', AdmissionSession::STATUS_COMPLETED)
            ->orderBy('start_time', 'asc')
            ->get();

        // Calculate KPI summary
        $totalAssigned   = $applicants->count();
        $submittedCount  = $applicants->whereNotNull('submitted_at')->count();
        $inProgressCount = $applicants->filter(fn ($a) => $a->computed_attendance_status === 'In-Progress')->count();
        $readyCount      = $applicants->filter(fn ($a) => $a->computed_attendance_status === 'Ready')->count();
        $absentCount     = $applicants->filter(fn ($a) => $a->computed_attendance_status === 'Absent')->count();
        $violationsCount = (int) $applicants->sum('strike_count');
        $avgScore        = $submittedCount > 0 ? round((float) $applicants->whereNotNull('exam_score')->avg('exam_score'), 2) : 0;

        // Recent prohibited rules / security logs for this session
        $recentIncidents = GuidanceTestSecurityLog::whereIn('applicant_id', $applicants->pluck('id'))
            ->with('admissionApplicant')
            ->latest('created_at')
            ->take(30)
            ->get();

        return view('admin.admission.sessions.show', compact(
            'session',
            'cycle',
            'applicants',
            'otherSessions',
            'totalAssigned',
            'submittedCount',
            'inProgressCount',
            'readyCount',
            'absentCount',
            'violationsCount',
            'avgScore',
            'recentIncidents'
        ));
    }

    public function printMasterlist(AdmissionSession $session)
    {
        request()->merge(['session_id' => $session->id]);

        return app(DocumentExportController::class)->printMasterlist(request(), 'admission');
    }

    /**
     * Record answers recognized by the session OMR web scanner.
     */
    public function scanOmr(Request $request, AdmissionScoringService $scoring)
    {
        $data = $request->validate([
            'application_number' => ['required', 'string', 'max:100'],
            'session_id' => ['required', 'integer', 'exists:admission_sessions,id'],
            'raw_choices' => ['required', 'array'],
            'raw_choices.*' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
        ]);

        $session = AdmissionSession::with('cycle')->findOrFail($data['session_id']);
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'OMR submissions are disabled for an archived admission cycle.');
        abort_if($session->status === AdmissionSession::STATUS_COMPLETED, 422, 'This admission session is already completed.');

        $applicant = $session->applicants()
            ->where('application_number', $data['application_number'])
            ->first();

        abort_unless($applicant, 422, 'The scanned examinee is not enrolled in this session.');

        $totalItems = max(1, (int) ($cycle->total_items ?: 80));
        $answers = [];
        for ($item = 1; $item <= $totalItems; $item++) {
            $answers[$item] = $data['raw_choices'][$item] ?? null;
        }

        abort_if(count($data['raw_choices']) !== $totalItems, 422, "Exactly {$totalItems} answers are required.");

        $scoring->submit($applicant, $answers);
        $applicant->refresh();

        $submittedCount = $session->applicants()->whereNotNull('submitted_at')->count();
        $averageScore = round((float) $session->applicants()->whereNotNull('exam_score')->avg('exam_score'), 2);

        return response()->json([
            'message' => 'OMR answer sheet scored and recorded successfully.',
            'applicant' => [
                'id' => $applicant->id,
                'application_number' => $applicant->application_number,
                'exam_status' => 'Submitted',
                'submitted_at' => $applicant->submitted_at?->format('M d, h:i A'),
                'raw_score' => (float) $applicant->exam_score,
                'stanine_rating' => $applicant->stanine_score,
                'total_items' => $totalItems,
            ],
            'session' => [
                'submitted_count' => $submittedCount,
                'pending_count' => max(0, $session->applicants()->count() - $submittedCount),
                'average_score' => $averageScore,
            ],
        ]);
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function edit(AdmissionSession $session)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot edit sessions on an archived cycle.');

        return redirect()->route('admin.admission.sessions.index');
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, AdmissionSession $session, AdmissionScoringService $scoring)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot edit sessions on an archived cycle.');

        $data = $request->validate([
            'session_name' => 'required|string|max:120',
            'start_time'   => 'required|date',
            'start_number' => 'required|integer|min:1',
            'end_number'   => 'required|integer|gte:start_number',
            'room'         => 'nullable|string|max:120',
            'status'       => ['nullable', Rule::in(AdmissionSession::STATUSES)],
        ]);

        $prevStart = $session->start_number;
        $prevEnd   = $session->end_number;

        $session->update([
            'session_name' => $data['session_name'],
            'start_time'   => Carbon::parse($data['start_time']),
            'start_number' => (int) $data['start_number'],
            'end_number'   => (int) $data['end_number'],
            'room'         => $data['room'] ?? null,
            'status'       => $data['status'] ?? $session->status,
        ]);

        // If applicant range changed, reallocate applicants
        $newStart = (int) $data['start_number'];
        $newEnd   = (int) $data['end_number'];
        if ($prevStart !== $newStart || $prevEnd !== $newEnd) {
            // Unlink current applicants
            AdmissionApplicant::where('admission_session_id', $session->id)
                ->update(['admission_session_id' => null, 'session_label' => null]);

            $limit = ($newEnd - $newStart) + 1;
            $targets = $cycle->applicants()->orderBy('id')->skip($newStart - 1)->take($limit)->get();
            foreach ($targets as $applicant) {
                $applicant->update([
                    'admission_session_id' => $session->id,
                    'session_label'        => $session->session_name,
                ]);
            }
        } else {
            // Sync session_label if session name changed
            AdmissionApplicant::where('admission_session_id', $session->id)
                ->update(['session_label' => $session->session_name]);
        }

        $scoring->evaluate($cycle);

        if ($session->status === AdmissionSession::STATUS_COMPLETED) {
            Cache::flush();
        }

        return redirect()->route('admin.admission.sessions.index')
            ->with('success', "Session '{$session->session_name}' updated successfully.");
    }

    // ── Status Transition (Start / In-Progress / Complete) ─────────────────────

    public function updateStatus(Request $request, AdmissionSession $session)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot update session status on an archived cycle.');

        $data = $request->validate([
            'status' => ['required', Rule::in(AdmissionSession::STATUSES)],
        ]);

        $session->update(['status' => $data['status']]);

        // When launching session (In-Progress), transition all Ready examinees into In-Progress
        if ($data['status'] === AdmissionSession::STATUS_IN_PROGRESS) {
            $session->applicants()
                ->whereNull('submitted_at')
                ->where(function ($q) {
                    $q->where('attendance_status', 'Ready')
                      ->orWhereNotNull('checked_in_at');
                })
                ->update(['attendance_status' => 'In-Progress']);
        }

        if ($session->status === AdmissionSession::STATUS_COMPLETED) {
            Cache::flush();
        }

        return back()->with('success', "Session '{$session->session_name}' status changed to {$session->status}.");
    }

    public function complete(AdmissionSession $session)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot complete session on an archived cycle.');

        $session->update(['status' => AdmissionSession::STATUS_COMPLETED]);
        Cache::flush();

        return back()->with('success', "Session '{$session->session_name}' has been marked as Completed.");
    }

    // ── Live Monitor Polling ───────────────────────────────────────────────────

    public function monitorPoll(AdmissionSession $session)
    {
        $session->refresh();
        $cycle = $session->cycle;

        $applicants = $session->applicants()
            ->with(['securityLogs' => fn ($q) => $q->latest('created_at')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $totalAssigned   = $applicants->count();
        $submittedCount  = $applicants->whereNotNull('submitted_at')->count();
        $inProgressCount = $applicants->filter(fn ($a) => $a->computed_attendance_status === 'In-Progress')->count();
        $readyCount      = $applicants->filter(fn ($a) => $a->computed_attendance_status === 'Ready')->count();
        $absentCount     = $applicants->filter(fn ($a) => $a->computed_attendance_status === 'Absent')->count();
        $violationsCount = (int) $applicants->sum('strike_count');
        $avgScore        = $submittedCount > 0 ? round((float) $applicants->whereNotNull('exam_score')->avg('exam_score'), 2) : 0;

        $recentIncidents = GuidanceTestSecurityLog::whereIn('applicant_id', $applicants->pluck('id'))
            ->with('admissionApplicant')
            ->latest('created_at')
            ->take(20)
            ->get()
            ->map(function ($log) {
                return [
                    'log_id' => $log->log_id,
                    'applicant_name' => $log->admissionApplicant?->full_name ?? 'Unknown',
                    'application_number' => $log->admissionApplicant?->application_number ?? '—',
                    'incident_type' => str_replace('_', ' ', ucwords($log->incident_type ?? '', '_')),
                    'strike_number' => $log->strike_number,
                    'time' => $log->created_at?->format('h:i:s A') ?? '—',
                ];
            });

        $applicantRows = $applicants->map(function ($a) use ($cycle) {
            $status = $a->computed_attendance_status;
            return [
                'id' => $a->id,
                'application_number' => $a->application_number,
                'full_name' => $a->full_name,
                'course_choice' => $a->course_choice,
                'attendance_status' => $status,
                'checked_in_at' => $a->checked_in_at?->format('h:i A'),
                'submitted_at' => $a->submitted_at?->format('h:i A'),
                'exam_score' => $a->exam_score !== null ? number_format($a->exam_score, 2) : null,
                'stanine_score' => $a->stanine_score,
                'total_items' => (int) ($cycle?->total_items ?: 80),
                'strike_count' => (int) $a->strike_count,
                'security_incidents' => $a->securityLogs->take(5)->map(fn ($l) => [
                    'type' => str_replace('_', ' ', ucwords($l->incident_type ?? '', '_')),
                    'strike' => $l->strike_number,
                    'time' => $l->created_at?->format('h:i A'),
                ]),
            ];
        });

        return response()->json([
            'session' => [
                'id' => $session->id,
                'name' => $session->session_name,
                'status' => $session->status,
                'is_open' => $session->isOpen(),
                'is_scheduled' => $session->isScheduled(),
                'is_in_progress' => $session->isInProgress(),
                'is_completed' => $session->isCompleted(),
            ],
            'kpis' => [
                'total' => $totalAssigned,
                'ready' => $readyCount,
                'in_progress' => $inProgressCount,
                'submitted' => $submittedCount,
                'absent' => $absentCount,
                'violations' => $violationsCount,
                'average_score' => $avgScore,
                'percentage' => $totalAssigned > 0 ? round(($submittedCount / $totalAssigned) * 100) : 0,
            ],
            'applicants' => $applicantRows,
            'recent_incidents' => $recentIncidents,
        ]);
    }

    // ── Reassign Absent Applicants ─────────────────────────────────────────────

    public function reassignApplicant(Request $request, AdmissionSession $session, AdmissionApplicant $applicant)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot reassign applicants in an archived cycle.');

        $data = $request->validate([
            'target_session_id' => [
                'required',
                'integer',
                Rule::exists('admission_sessions', 'id')->where(function ($q) use ($cycle, $session) {
                    $q->where('admission_cycle_id', $cycle->id)
                      ->where('id', '!=', $session->id);
                }),
            ],
        ]);

        $targetSession = AdmissionSession::findOrFail($data['target_session_id']);
        abort_if($targetSession->isCompleted(), 422, 'Cannot transfer examinee to a completed session.');
        abort_if($applicant->submitted_at, 422, 'Cannot transfer an examinee who has already submitted their exam.');

        $applicant->update([
            'admission_session_id' => $targetSession->id,
            'session_label'        => $targetSession->session_name,
            'batch_group'          => $targetSession->session_name,
            'checked_in_at'        => null,
            'attendance_status'    => 'Absent',
            'exam_token'           => null,
        ]);

        return back()->with('success', "Examinee {$applicant->full_name} has been transferred to '{$targetSession->session_name}'.");
    }

    public function reassignAbsentBulk(Request $request, AdmissionSession $session)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot reassign applicants in an archived cycle.');

        $data = $request->validate([
            'target_session_id' => [
                'required',
                'integer',
                Rule::exists('admission_sessions', 'id')->where(function ($q) use ($cycle, $session) {
                    $q->where('admission_cycle_id', $cycle->id)
                      ->where('id', '!=', $session->id);
                }),
            ],
            'applicant_ids' => 'nullable|array',
            'applicant_ids.*' => 'integer|exists:admission_applicants,id',
            'transfer_all_absent' => 'nullable|boolean',
        ]);

        $targetSession = AdmissionSession::findOrFail($data['target_session_id']);
        abort_if($targetSession->isCompleted(), 422, 'Cannot transfer examinees to a completed session.');

        $query = $session->applicants()->whereNull('submitted_at');

        if (empty($data['transfer_all_absent']) && !empty($data['applicant_ids'])) {
            $query->whereIn('id', $data['applicant_ids']);
        } else {
            $query->where(function ($q) {
                $q->where('attendance_status', 'Absent')
                  ->orWhereNull('checked_in_at');
            });
        }

        $targets = $query->get();
        if ($targets->isEmpty()) {
            return back()->with('warning', 'No eligible absent examinees selected for transfer.');
        }

        foreach ($targets as $applicant) {
            $applicant->update([
                'admission_session_id' => $targetSession->id,
                'session_label'        => $targetSession->session_name,
                'batch_group'          => $targetSession->session_name,
                'checked_in_at'        => null,
                'attendance_status'    => 'Absent',
                'exam_token'           => null,
            ]);
        }

        return back()->with('success', "{$targets->count()} absent examinee(s) successfully transferred to '{$targetSession->session_name}'.");
    }

    // ── Manual Check-In / Mark Absent ──────────────────────────────────────────

    public function checkinApplicant(AdmissionSession $session, AdmissionApplicant $applicant)
    {
        abort_if($applicant->submitted_at, 422, 'Examinee has already submitted.');
        abort_if($applicant->admission_session_id !== $session->id, 422, 'Applicant does not belong to this session.');

        $status = $session->isInProgress() ? 'In-Progress' : 'Ready';

        $applicant->forceFill([
            'checked_in_at'     => now(),
            'attendance_status' => $status,
            'exam_token'        => $applicant->exam_token ?: Str::random(64),
        ])->save();

        return back()->with('success', "Examinee {$applicant->full_name} is now marked as {$status}.");
    }

    public function markAbsentApplicant(AdmissionSession $session, AdmissionApplicant $applicant)
    {
        abort_if($applicant->submitted_at, 422, 'Cannot mark as absent an examinee who has already submitted.');
        abort_if($applicant->admission_session_id !== $session->id, 422, 'Applicant does not belong to this session.');

        $applicant->forceFill([
            'checked_in_at'     => null,
            'attendance_status' => 'Absent',
        ])->save();

        return back()->with('success', "Examinee {$applicant->full_name} is marked as Absent.");
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(AdmissionSession $session)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot delete sessions on an archived cycle.');

        // Unlink assigned applicants
        AdmissionApplicant::where('admission_session_id', $session->id)
            ->update(['admission_session_id' => null, 'session_label' => null]);

        $name = $session->session_name;
        $session->delete();

        return redirect()->route('admin.admission.sessions.index')
            ->with('success', "Session '{$name}' deleted. Assigned applicants have been unlinked.");
    }
}
