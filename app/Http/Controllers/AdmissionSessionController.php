<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Models\AdmissionSession;
use App\Services\AdmissionScoringService;
use App\Support\CourseCatalog;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

    // ── Show / Roster ─────────────────────────────────────────────────────────

    public function show(AdmissionSession $session)
    {
        $cycle = $session->cycle;
        if (!$cycle) {
            return $this->gatekeeperRedirect();
        }

        $applicants = $session->applicants()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('admin.admission.sessions.show', compact('session', 'cycle', 'applicants'));
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

        return back()->with('success', "Session '{$session->session_name}' status changed to {$session->status}.");
    }

    public function complete(AdmissionSession $session)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot complete session on an archived cycle.');

        $session->update(['status' => AdmissionSession::STATUS_COMPLETED]);

        return back()->with('success', "Session '{$session->session_name}' has been marked as Completed.");
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
