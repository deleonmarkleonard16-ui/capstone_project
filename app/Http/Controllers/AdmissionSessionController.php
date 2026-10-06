<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionBatch;
use App\Models\AdmissionCycle;
use App\Models\AdmissionSession;
use App\Models\GuidanceTestSecurityLog;
use App\Services\AdmissionScoringService;
use App\Support\CourseCatalog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        // Covers databases restored from a backup, test fixtures, or sessions
        // created before the batch migration. No applicant/session link changes.
        if ($cycle->sessions()->whereNull('admission_batch_id')->exists()) {
            $legacyBatch = $cycle->batches()->firstOrCreate(['batch_name' => 'Existing Sessions']);
            $cycle->sessions()->whereNull('admission_batch_id')->update(['admission_batch_id' => $legacyBatch->id]);
        }

        $sessionCounts = [
            'applicants',
            'applicants as submitted_count' => fn ($q) => $q->whereNotNull('submitted_at'),
        ];
        $batches = $cycle->batches()
            ->with(['sessions' => fn ($q) => $q->withCount($sessionCounts)->orderBy('start_time')])
            ->withCount('sessions')
            ->orderByRaw('batch_date is null, batch_date')
            ->orderBy('batch_name')
            ->get();
        $sessions = $batches->flatMap->sessions->sortBy('start_time')->values();

        $totalApplicants = $cycle->applicants()->count();
        $courses = CourseCatalog::activeOptions();

        return view('admin.admission.sessions.index', compact('cycle', 'batches', 'sessions', 'totalApplicants', 'courses'));
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

        try {
            $data = $request->validate([
                'session_name'  => 'required|string|max:120',
                'admission_batch_id' => ['required', Rule::exists('admission_batches', 'id')->where(fn ($q) => $q->where('admission_cycle_id', $cycle->id))],
                'start_date'    => 'nullable|date',
                'start_time'    => 'required|string|max:60',
                'start_number'  => 'required|integer|min:1',
                'end_number'    => 'required|integer|gte:start_number',
                'room'          => 'nullable|string|max:120',
                'batch_group'   => 'nullable|string|max:100',
                'course_filter' => 'nullable|string|max:30',
            ]);

            $batch = AdmissionBatch::where('admission_cycle_id', $cycle->id)
                ->findOrFail($data['admission_batch_id']);
            abort_if(!$batch->batch_date, 422, 'Set the batch date before adding a session.');

            // The batch owns the shared date and venue. Sessions only provide
            // their name, start time, and masterlist range.
            $startTime = $this->parseSessionStartTimeValues(
                $batch->batch_date->format('Y-m-d'),
                $data['start_time']
            );

            $startNumber = (int) $request->input('start_number', 1);
            $endNumber   = (int) $request->input('end_number', $startNumber);

            // ── Masterlist Range Assignment ────────────────────────────────────────
            $start = $startNumber;
            $end   = $endNumber;
            $limit = ($end - $start) + 1;

            $query = $cycle->applicants()->orderBy('id');

            if (!empty($data['batch_group']) && $data['batch_group'] !== '__all__') {
                $query->where('batch_group', $data['batch_group']);
            }
            if (!empty($data['course_filter'])) {
                $query->where('course_choice', $data['course_filter']);
            }

            $targets = $query->skip($start - 1)->take($limit)->get();

            // Never move an examinee out of an existing session because of a
            // range overlap. This catches, for example, Session B 10-20 when
            // Session A already owns masterlist number 10.
            $conflictIndex = $targets->search(fn (AdmissionApplicant $applicant) =>
                $applicant->admission_session_id !== null || filled($applicant->session_label)
            );
            if ($conflictIndex !== false) {
                $number = $start + $conflictIndex;
                $assignedTo = $targets[$conflictIndex]->session_label ?: 'another session';

                return redirect()->route('admin.admission.sessions.index')->withInput()->withErrors([
                    'start_number' => "Masterlist #{$number} is already assigned to {$assignedTo}. Choose a range that does not overlap an existing session.",
                ]);
            }

            $session = $cycle->sessions()->create([
                'session_name' => $request->input('session_name', 'Unnamed Session'),
                'admission_batch_id' => $data['admission_batch_id'],
                'start_time'   => $startTime,
                'start_number' => $startNumber,
                'end_number'   => $endNumber,
                'room'         => $batch->room ?: 'N/A',
                'qr_token'     => Str::random(64),
                'status'       => AdmissionSession::STATUS_SCHEDULED,
            ]);

            foreach ($targets as $applicant) {
                $applicant->update([
                    'admission_session_id' => $session->id,
                    'session_label'        => $session->session_name,
                ]);
            }

            $scoring->evaluate($cycle);

            return redirect()->route('admin.admission.sessions.index')
                ->with('success', "Session '{$session->session_name}' created successfully. {$targets->count()} applicant(s) assigned (Masterlist Range: #{$start}–#{$end}).");
        } catch (\Throwable $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create session: ' . $e->getMessage());
        }
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
     * Open one print-ready document containing a blank paper answer sheet for
     * every applicant assigned to this session. Individual paper printing is
     * intentionally kept available from each applicant row.
     */
    public function printPaperAnswerSheets(AdmissionSession $session)
    {
        $cycle = $session->cycle;
        abort_unless($cycle, 404);

        $applicants = $session->applicants()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($applicants->isEmpty()) {
            return back()->with('warning', 'There are no applicants assigned to this session yet.');
        }

        $totalItems = max(1, (int) ($cycle->total_items ?: 80));
        $psuLogoUrl = asset('images/psu-logo.png');

        return view('admin.admission.sessions.paper_answer_sheets', compact(
            'session',
            'applicants',
            'totalItems',
            'psuLogoUrl'
        ));
    }

    /** Open a standalone print-ready venue check-in QR sheet. */
    public function printQr(AdmissionSession $session)
    {
        return view('admin.admission.sessions.qr_print', compact('session'));
    }

    public function storeBatch(Request $request)
    {
        $cycle = $this->active();
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }
        abort_if($cycle->isCompleted(), 422, 'Cannot add batches to an archived cycle.');

        $data = $request->validate([
            'batch_name' => ['required', 'string', 'max:120', Rule::unique('admission_batches')->where(fn ($q) => $q->where('admission_cycle_id', $cycle->id))],
            'batch_date' => ['required', 'date'],
            'room' => ['nullable', 'string', 'max:120'],
        ]);

        $batch = $cycle->batches()->create($data);

        return redirect()->route('admin.admission.sessions.index')
            ->with('success', "Batch '{$batch->batch_name}' created. You can now add sessions inside it.");
    }

    public function updateBatch(Request $request, AdmissionBatch $batch)
    {
        $cycle = $this->active();
        abort_if(!$cycle || $cycle->id !== $batch->admission_cycle_id || $cycle->isCompleted(), 422, 'Cannot edit this batch.');

        $data = $request->validate([
            'batch_name' => ['required', 'string', 'max:120', Rule::unique('admission_batches')->where(fn ($q) => $q->where('admission_cycle_id', $cycle->id))->ignore($batch->id)],
            'batch_date' => ['required', 'date'],
            'room' => ['nullable', 'string', 'max:120'],
        ]);
        $batch->update($data);

        return redirect()->route('admin.admission.sessions.index')->with('success', "Batch '{$batch->batch_name}' updated successfully.");
    }

    public function destroyBatch(AdmissionBatch $batch)
    {
        $cycle = $this->active();
        abort_if(!$cycle || $cycle->id !== $batch->admission_cycle_id || $cycle->isCompleted(), 422, 'Cannot delete this batch.');

        $name = $batch->batch_name;
        $sessionIds = $batch->sessions()->pluck('id');

        DB::transaction(function () use ($batch, $sessionIds) {
            $this->resetDeletedSessionApplicants($sessionIds);
            $batch->sessions()->delete();
            $batch->delete();
        });

        return redirect()->route('admin.admission.sessions.index')
            ->with('success', "Batch '{$name}' and its {$sessionIds->count()} session(s) were deleted. Assigned applicants were returned to the masterlist.");
    }

    public function printBatchMasterlist(AdmissionBatch $batch)
    {
        $this->assertActiveBatch($batch);
        $sessions = $batch->sessions()->with(['applicants' => fn ($q) => $q->orderBy('last_name')->orderBy('first_name')])->orderBy('start_time')->get();
        return view('admin.admission.batches.print_masterlist', compact('batch', 'sessions'));
    }

    public function printBatchPaperAnswerSheets(AdmissionBatch $batch)
    {
        $cycle = $this->assertActiveBatch($batch);
        $sessions = $batch->sessions()->with(['applicants' => fn ($q) => $q->orderBy('last_name')->orderBy('first_name')])->orderBy('start_time')->get();
        if ($sessions->sum(fn ($session) => $session->applicants->count()) === 0) {
            return back()->with('warning', 'There are no applicants assigned to this batch yet.');
        }

        $totalItems = max(1, (int) ($cycle->total_items ?: 80));
        $psuLogoUrl = asset('images/psu-logo.png');
        return view('admin.admission.batches.paper_answer_sheets', compact('batch', 'sessions', 'totalItems', 'psuLogoUrl'));
    }

    private function assertActiveBatch(AdmissionBatch $batch): AdmissionCycle
    {
        $cycle = $this->active();
        abort_unless($cycle && $cycle->id === $batch->admission_cycle_id, 404);
        return $cycle;
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

        try {
            $data = $request->validate([
                'session_name' => 'required|string|max:120',
                // Legacy/API callers that do not send a batch keep the session
                // in its current batch; the browser form always requires one.
                'admission_batch_id' => ['nullable', Rule::exists('admission_batches', 'id')->where(fn ($q) => $q->where('admission_cycle_id', $cycle->id))],
                'start_date'   => 'nullable|date',
                'start_time'   => 'required|string|max:60',
                'start_number' => 'required|integer|min:1',
                'end_number'   => 'required|integer|gte:start_number',
                'room'         => 'nullable|string|max:120',
                'status'       => ['nullable', Rule::in(AdmissionSession::STATUSES)],
            ]);

            $prevStart = $session->start_number;
            $prevEnd   = $session->end_number;

            // Use the validated date/time values, not a fallback from the
            // existing model value. This guarantees a changed Exam Date is
            // written even when the application/database timezones differ.
            $startTime = $this->parseSessionStartTimeValues(
                $data['start_date'] ?? null,
                $data['start_time'],
                $session->getRawOriginal('start_time')
            );

            $newStart = (int) $request->input('start_number', $prevStart);
            $newEnd   = (int) $request->input('end_number', $prevEnd);

            $session->update([
                'session_name' => $request->input('session_name', $session->session_name),
                'admission_batch_id' => $data['admission_batch_id'] ?? $session->admission_batch_id,
                'start_number' => $newStart,
                'end_number'   => $newEnd,
                'room'         => $request->input('room', $session->room ?? 'N/A') ?: 'N/A',
                'status'       => $request->input('status', $session->status ?? AdmissionSession::STATUS_SCHEDULED),
            ]);

            // DATETIME is a wall-clock schedule, not an instant in UTC. Write
            // the validated Philippine date/time directly so Eloquent casting
            // or a database connection timezone cannot retain the old date.
            $updated = DB::table('admission_sessions')
                ->where('id', $session->id)
                ->update([
                    'start_time' => $startTime,
                    'updated_at' => now(),
                ]);

            // MySQL returns 0 affected rows when the submitted date/time is
            // unchanged. That is valid when the admin is only extending the
            // masterlist range, so fail only if the session was actually lost.
            if ($updated === 0 && !DB::table('admission_sessions')->where('id', $session->id)->exists()) {
                abort(500, 'The session schedule could not be updated.');
            }
            $session->refresh();

            // If applicant range changed, reallocate applicants
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
        } catch (\Throwable $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to update session: ' . $e->getMessage());
        }
    }

    // ── Status Transition (Start / In-Progress / Complete) ─────────────────────

    public function updateStatus(Request $request, AdmissionSession $session)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot update session status on an archived cycle.');

        $data = $request->validate([
            'status' => ['required', Rule::in(AdmissionSession::STATUSES)],
        ]);

        $updates = ['status' => $data['status']];
        if ($data['status'] === AdmissionSession::STATUS_IN_PROGRESS && !$session->started_at) {
            $updates['started_at'] = now();
            $updates['ended_at'] = null;
        }
        if ($data['status'] === AdmissionSession::STATUS_COMPLETED && !$session->ended_at) {
            $updates['ended_at'] = now();
        }
        $session->update($updates);

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

    public function complete(AdmissionSession $session, AdmissionScoringService $scoring)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot complete session on an archived cycle.');

        $unfinished = $session->applicants()->whereNull('submitted_at')->get();
        foreach ($unfinished as $applicant) {
            // Save the answers already entered by the examinee, if any. A
            // blank sheet is still a final submitted sheet when the admin
            // ends the session, so no examinee is left taking the test.
            $scoring->submit($applicant, is_array($applicant->answers) ? $applicant->answers : []);
        }

        $session->update([
            'status' => AdmissionSession::STATUS_COMPLETED,
            'ended_at' => now(),
        ]);
        Cache::flush();

        return back()->with('success', "Session '{$session->session_name}' has been ended. {$unfinished->count()} unfinished applicant(s) were automatically submitted and marked as Completed.");
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
                'started_at' => $session->started_at?->toIso8601String(),
                'ends_at' => $session->started_at?->copy()->addHour()->toIso8601String(),
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

    /**
     * Administrative Override / Re-entry: Reset strikes, clear auto-termination lockout,
     * and allow examinee to resume taking the exam.
     */
    public function unterminate(Request $request, AdmissionApplicant $applicant, AdmissionScoringService $scoring)
    {
        $cycle = $applicant->cycle ?? AdmissionCycle::active();
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot override examinee on an archived cycle.');

        $token = $applicant->exam_token ?: Str::random(64);

        $applicant->forceFill([
            'strike_count'      => 0,
            'submitted_at'      => null,
            'attendance_status' => 'In-Progress',
            'exam_token'        => $token,
            'exam_score'        => null,
            'stanine_score'     => null,
            'total_score'       => null,
            'qualification_status' => 'Pending',
        ])->save();

        // Log administrative override to security logs
        \Illuminate\Support\Facades\DB::table('guidance_test_security_logs')->insert([
            'applicant_id'        => $applicant->id,
            'student_id'          => $applicant->student_id ?: $applicant->application_number,
            'course_program'      => $applicant->course_choice ?: 'Not provided',
            'current_test_taking' => 'PSU College Admission Test',
            'incident_type'       => 'Admin Override / Re-entry Granted',
            'strike_number'       => 0,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        if ($applicant->cycle) {
            $scoring->evaluate($applicant->cycle);
        }

        if (class_exists(\App\Events\ApplicantSessionResumedEvent::class)) {
            event(new \App\Events\ApplicantSessionResumedEvent($applicant));
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success'      => true,
                'message'      => "Exam session for {$applicant->full_name} has been unlocked and strikes reset.",
                'applicant_id' => $applicant->id,
                'strike_count' => 0,
                'exam_token'   => $token,
                'take_url'     => route('admission.take', $token),
            ]);
        }

        return back()->with('success', "Exam session for {$applicant->full_name} has been unlocked and strikes reset. The examinee may now resume the exam.");
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(AdmissionSession $session)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot delete sessions on an archived cycle.');

        $this->resetDeletedSessionApplicants(collect([$session->id]));

        $name = $session->session_name;
        $session->delete();

        return redirect()->route('admin.admission.sessions.index')
            ->with('success', "Session '{$name}' deleted. Unfinished applicants were reset to Absent and can check in again when assigned to a new session.");
    }

    /**
     * Return examinees from deleted sessions to the masterlist's first-flow
     * state. Completed examination results stay intact; only unfinished
     * examination drafts and attendance/check-in data are reset.
     */
    private function resetDeletedSessionApplicants($sessionIds): void
    {
        $sessionIds = collect($sessionIds)->filter()->values();
        if ($sessionIds->isEmpty()) {
            return;
        }

        $unsubmitted = AdmissionApplicant::whereIn('admission_session_id', $sessionIds)
            ->whereNull('submitted_at');

        $unsubmitted->update([
            'admission_session_id' => null,
            'session_label' => null,
            'attendance_status' => 'Absent',
            'checked_in_at' => null,
            'exam_token' => null,
            'answers' => null,
            'strike_count' => 0,
        ]);

        // A completed score/result must remain part of the applicant record,
        // but it must no longer point to a session that has been deleted.
        AdmissionApplicant::whereIn('admission_session_id', $sessionIds)
            ->whereNotNull('submitted_at')
            ->update([
                'admission_session_id' => null,
                'session_label' => null,
                'checked_in_at' => null,
                'exam_token' => null,
            ]);
    }

    /**
     * Accurately resolve and format session start datetime from request inputs.
     * Supports separate start_date/exam_date and start_time, combined datetime-local strings,
     * or single time/date submissions without accidentally overwriting the selected date with today.
     */
    private function parseSessionStartTime(Request $request, ?Carbon $fallback = null): string
    {
        $rawDate = $request->input('start_date') ?: $request->input('exam_date') ?: $request->input('date');
        $rawTime = $request->input('start_time') ?: $request->input('time');

        return $this->parseSessionStartTimeValues($rawDate, $rawTime, $fallback?->format('Y-m-d H:i:s'));
    }

    /** Resolve a local Philippine session schedule without substituting today's date. */
    private function parseSessionStartTimeValues(?string $rawDate, ?string $rawTime, ?string $fallback = null): string
    {

        // Browser date/time controls submit separate values. Parse those exact
        // values together so Carbon never supplies today's date as a fallback.
        if ($rawDate && $rawTime && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate)) {
            $time = trim($rawTime);

            if (preg_match('/^(\d{1,2}:\d{2})(?::\d{2})?\s*([AaPp][Mm])$/', $time, $matches)) {
                return Carbon::createFromFormat(
                    '!Y-m-d g:i A',
                    "{$rawDate} {$matches[1]} ".strtoupper($matches[2]),
                    'Asia/Manila'
                )->format('Y-m-d H:i:s');
            }

            if (preg_match('/^(\d{1,2}:\d{2})(?::\d{2})?$/', $time, $matches)) {
                return Carbon::createFromFormat(
                    '!Y-m-d H:i',
                    "{$rawDate} {$matches[1]}",
                    'Asia/Manila'
                )->format('Y-m-d H:i:s');
            }
        }

        // 1. If start_time contains a full ISO/datetime string (e.g. "2026-10-25T08:30" or "2026-10-25 08:30:00")
        if ($rawTime && preg_match('/^\d{4}-\d{2}-\d{2}[T\s]\d{1,2}:\d{2}/', $rawTime)) {
            return Carbon::parse($rawTime, 'Asia/Manila')->format('Y-m-d H:i:s');
        }

        // 2. If separate date is provided
        if ($rawDate) {
            $dateString = Carbon::parse($rawDate, 'Asia/Manila')->format('Y-m-d');
            $timeString = $rawTime ? trim($rawTime) : '08:00:00';

            // Extract time component if extra datetime prefix was present
            if (preg_match('/(\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AaPp][Mm])?)/', $timeString, $matches)) {
                $timeString = $matches[1];
            }
            return Carbon::parse("{$dateString} {$timeString}", 'Asia/Manila')->format('Y-m-d H:i:s');
        }

        // 3. If rawTime is provided without separate date
        if ($rawTime) {
            // Full date only (e.g., "2026-10-25")
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($rawTime))) {
                return Carbon::parse($rawTime, 'Asia/Manila')->format('Y-m-d 08:00:00');
            }

            // Time only (e.g. "08:30") -> use fallback session date if editing, else today
            $baseDate = $fallback ? Carbon::parse($fallback)->format('Y-m-d') : now('Asia/Manila')->format('Y-m-d');
            return Carbon::parse("{$baseDate} {$rawTime}", 'Asia/Manila')->format('Y-m-d H:i:s');
        }

        // 4. Default fallback
        return $fallback ?: now('Asia/Manila')->format('Y-m-d H:i:s');
    }
}
