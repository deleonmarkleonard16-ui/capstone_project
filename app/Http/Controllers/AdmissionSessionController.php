<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Models\AdmissionSession;
use App\Services\AdmissionScoringService;
use App\Support\CourseCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * PSU-CAT Admission Session Controller
 *
 * Handles CRUD for Admission Sessions (Session A, Session B, …) and
 * the Dynamic Session Range Allocation feature (Spec §5).
 *
 * Routes are nested under /admin/admission with middleware 'admission.cycle'.
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
        if (!$cycle) return $this->gatekeeperRedirect();

        $sessions = $cycle->sessions()->withCount('applicants')->latest()->get();

        return view('admin.admission.sessions.index', compact('cycle', 'sessions'));
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create()
    {
        $cycle = $this->active();
        if (!$cycle) return $this->gatekeeperRedirect();

        return view('admin.admission.sessions.form', [
            'cycle'   => $cycle,
            'session' => new AdmissionSession(),
            'method'  => 'POST',
            'action'  => route('admin.admission.sessions.store'),
            'courses' => CourseCatalog::activeOptions(),
        ]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request, AdmissionScoringService $scoring)
    {
        $cycle = $this->active();
        if (!$cycle) return $this->gatekeeperRedirect();
        abort_if($cycle->isCompleted(), 422, 'Cannot add sessions to an archived cycle.');

        $data = $request->validate([
            'session_name'     => 'required|string|max:120',
            'start_number'     => 'required|integer|min:1',
            'end_number'       => 'required|integer|gte:start_number',
            'room'             => 'nullable|string|max:120',
            'exam_date'        => 'nullable|date',
            'start_time'       => 'nullable|date_format:H:i',
            'end_time'         => 'nullable|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1|max:480',
            'batch_group'      => 'nullable|string|max:100',
            'course_filter'    => 'nullable|string|max:30',
        ]);

        $session = $cycle->sessions()->create([
            'session_name'     => $data['session_name'],
            'start_number'     => $data['start_number'],
            'end_number'       => $data['end_number'],
            'room'             => $data['room'] ?? null,
            'exam_date'        => $data['exam_date'] ?? null,
            'start_time'       => $data['start_time'] ?? null,
            'end_time'         => $data['end_time'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? 40,
            'qr_token'         => Str::random(64),
            'status'           => 'Active',
        ]);

        // ── Range-Based Auto-Assign (Spec §5) ──────────────────────────────────
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

        return redirect()->route('admin.admission.sessions.show', $session)
            ->with('success', "Session '{$session->session_name}' created and {$targets->count()} applicant(s) assigned (Range: {$start}–{$end}).");
    }

    // ── Show / Roster ─────────────────────────────────────────────────────────

    public function show(AdmissionSession $session)
    {
        $cycle = $session->cycle;
        $applicants = $session->applicants()->orderBy('last_name')->get();
        return view('admin.admission.sessions.show', compact('session', 'cycle', 'applicants'));
    }

    // ── Edit ──────────────────────────────────────────────────────────────────

    public function edit(AdmissionSession $session)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot edit sessions on an archived cycle.');

        return view('admin.admission.sessions.form', [
            'cycle'   => $cycle,
            'session' => $session,
            'method'  => 'PUT',
            'action'  => route('admin.admission.sessions.update', $session),
            'courses' => CourseCatalog::activeOptions(),
        ]);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, AdmissionSession $session)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot edit sessions on an archived cycle.');

        $data = $request->validate([
            'session_name'     => 'required|string|max:120',
            'room'             => 'nullable|string|max:120',
            'exam_date'        => 'nullable|date',
            'start_time'       => 'nullable|date_format:H:i',
            'end_time'         => 'nullable|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:1|max:480',
            'status'           => ['nullable', Rule::in(['Active', 'Completed', 'Cancelled'])],
        ]);

        $session->update($data);

        // Sync session_label on assigned applicants
        AdmissionApplicant::where('admission_session_id', $session->id)
            ->update(['session_label' => $session->session_name]);

        return back()->with('success', "Session '{$session->session_name}' updated.");
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(AdmissionSession $session)
    {
        $cycle = $session->cycle;
        abort_if(!$cycle || $cycle->isCompleted(), 422, 'Cannot delete sessions on an archived cycle.');

        // Unlink applicants
        AdmissionApplicant::where('admission_session_id', $session->id)
            ->update(['admission_session_id' => null, 'session_label' => null]);

        $name = $session->session_name;
        $session->delete();

        return redirect()->route('admin.admission.sessions.index')
            ->with('success', "Session '{$name}' deleted. Assigned applicants have been unlinked.");
    }
}
