<?php

namespace App\Http\Controllers;

use App\Models\AdmissionEvaluation;
use App\Models\Applicant;
use App\Models\TestSession;
use Illuminate\Http\Request;

class AdmissionEvaluationController extends Controller
{
    // Weights (out of 100). Can be made configurable later.
    private const WEIGHTS = [
        'exam'      => 60,
        'gwa'       => 20,
        'interview' => 20,
    ];

    public function index(Request $request)
    {
        $sessions = TestSession::withCount('assignments')
            ->latest()
            ->get();

        $sessionId = $request->input('session_id', optional($sessions->first())->id);
        $session   = $sessions->firstWhere('id', $sessionId);

        $evaluations = collect();
        $applicants  = collect();

        if ($session) {
            $evaluations = AdmissionEvaluation::where('test_session_id', $session->id)
                ->with('applicant')
                ->orderBy('rank')
                ->get()
                ->keyBy('applicant_id');

            // Applicants assigned to this session but not yet evaluated
            $applicants = Applicant::whereHas('sessionAssignments', fn ($q) => $q->where('test_session_id', $session->id))
                ->orderBy('last_name')
                ->get();
        }

        return view('staff.admission-evaluation.index', [
            'sessions'    => $sessions,
            'session'     => $session,
            'applicants'  => $applicants,
            'evaluations' => $evaluations,
            'weights'     => self::WEIGHTS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'test_session_id' => 'required|exists:test_sessions,id',
            'applicant_id'    => 'required|exists:applicants,id',
            'exam_score'      => 'required|numeric|min:0|max:100',
            'gwa'             => 'required|numeric|min:1|max:100',
            'interview_score' => 'required|numeric|min:0|max:100',
        ]);

        $examPct      = $data['exam_score'] * (self::WEIGHTS['exam'] / 100);
        $gwaPct       = $data['gwa'] * (self::WEIGHTS['gwa'] / 100);
        $interviewPct = $data['interview_score'] * (self::WEIGHTS['interview'] / 100);
        $total        = round($examPct + $gwaPct + $interviewPct, 2);

        AdmissionEvaluation::updateOrCreate(
            [
                'test_session_id' => $data['test_session_id'],
                'applicant_id'    => $data['applicant_id'],
            ],
            [
                'exam_score'          => $data['exam_score'],
                'exam_percentage'     => $examPct,
                'gwa'                 => $data['gwa'],
                'gwa_percentage'      => $gwaPct,
                'interview_score'     => $data['interview_score'],
                'interview_percentage' => $interviewPct,
                'total_marks'         => $total,
                'evaluated_by'        => auth()->id(),
            ]
        );

        // Re-rank all evaluations for this session
        $this->rerank($data['test_session_id']);

        return back()->with('success', 'Evaluation saved and rankings updated.');
    }

    public function passers(Request $request)
    {
        $sessionId = $request->input('session_id');
        $session   = TestSession::findOrFail($sessionId);
        $cutoff    = (int) $request->input('cutoff', 0);

        $evaluations = AdmissionEvaluation::where('test_session_id', $sessionId)
            ->with('applicant')
            ->orderBy('rank')
            ->get();

        if ($cutoff > 0) {
            $evaluations = $evaluations->where('rank', '<=', $cutoff)->values();
        }

        return view('staff.admission-evaluation.passers', [
            'session'     => $session,
            'evaluations' => $evaluations,
            'cutoff'      => $cutoff,
            'weights'     => self::WEIGHTS,
        ]);
    }

    private function rerank(int $sessionId): void
    {
        $rows = AdmissionEvaluation::where('test_session_id', $sessionId)
            ->orderByDesc('total_marks')
            ->get();

        foreach ($rows as $i => $row) {
            $row->update(['rank' => $i + 1, 'is_passed' => null]);
        }
    }
}
