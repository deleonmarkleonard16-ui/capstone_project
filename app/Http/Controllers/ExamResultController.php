<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnswerKeyRequest;
use App\Models\AnswerKey;
use App\Models\AnswerSheet;
use App\Models\TestSession;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ExamResultController extends Controller
{
    public function editAnswerKey(TestSession $session): View
    {
        return view('staff.results.answer-key', [
            'session' => $session,
            'answerKey' => $session->answerKey ?: new AnswerKey(['passing_score' => 60]),
            'questionNumbers' => range(1, 80),
        ]);
    }

    public function updateAnswerKey(AnswerKeyRequest $request, TestSession $session): RedirectResponse
    {
        $answerKey = $session->answerKey ?: new AnswerKey(['test_session_id' => $session->id]);
        $answerKey->fill($request->validated());
        $answerKey->test_session_id = $session->id;
        $answerKey->save();

        return redirect()
            ->route(auth()->user()->role.'.sessions.results.index', $session)
            ->with('success', 'Answer key saved successfully.');
    }

    public function index(Request $request, TestSession $session): View
    {
        $session->finalizeExpiredAnswerSheets();
        $session->load('answerKey');

        $search = trim((string) $request->string('search'));
        $sort = (string) $request->string('sort', 'score_desc');

        $results = $this->buildResults($session, $search, $sort);

        return view('staff.results.index', [
            'session' => $session,
            'results' => $results,
            'passedCount' => $results->where('passed', true)->count(),
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    public function show(TestSession $session, AnswerSheet $answerSheet): View|RedirectResponse
    {
        if ($answerSheet->test_session_id !== $session->id) {
            abort(404);
        }

        $session->finalizeExpiredAnswerSheets();

        if (! $session->isFinished()) {
            return redirect()
                ->route(auth()->user()->role.'.sessions.results.index', $session)
                ->with('warning', 'Detailed answer sheets are only available after the test has ended.');
        }

        $session->load('answerKey');
        $answerSheet->load('applicant');

        return view('staff.results.show', [
            'session' => $session,
            'answerSheet' => $answerSheet,
            'score' => $answerSheet->scoreAgainst($session->answerKey),
            'passed' => $answerSheet->passed($session->answerKey),
            'questionNumbers' => range(1, 80),
        ]);
    }

    private function buildResults(TestSession $session, string $search = '', string $sort = 'score_desc'): Collection
    {
        $results = AnswerSheet::with('applicant')
            ->where('test_session_id', $session->id)
            ->where(function ($query): void {
                $query->whereNotNull('submitted_at')
                    ->orWhere('is_locked', true);
            })
            ->get()
            ->map(function (AnswerSheet $sheet) use ($session): array {
                $score = $sheet->scoreAgainst($session->answerKey);

                return [
                    'sheet' => $sheet,
                    'score' => $score,
                    'passed' => $session->answerKey ? $score >= $session->answerKey->passing_score : false,
                ];
            })
            ->when($search !== '', function (Collection $collection) use ($search): Collection {
                $needle = mb_strtolower($search);

                return $collection->filter(function (array $result) use ($needle): bool {
                    $applicant = $result['sheet']->applicant;

                    return str_contains(mb_strtolower($applicant->full_name), $needle)
                        || str_contains(mb_strtolower((string) $applicant->application_number), $needle)
                        || str_contains(mb_strtolower((string) $applicant->email), $needle);
                });
            });

        $sortedResults = match ($sort) {
            'score_asc' => $results->sortBy('score'),
            'name_asc' => $results->sortBy(fn (array $result) => $result['sheet']->applicant->full_name),
            'name_desc' => $results->sortByDesc(fn (array $result) => $result['sheet']->applicant->full_name),
            'submitted_asc' => $results->sortBy(fn (array $result) => optional($result['sheet']->submitted_at)?->timestamp ?? 0),
            'submitted_desc' => $results->sortByDesc(fn (array $result) => optional($result['sheet']->submitted_at)?->timestamp ?? 0),
            'status_asc' => $results->sortBy(fn (array $result) => $result['passed'] ? 0 : 1),
            'status_desc' => $results->sortBy(fn (array $result) => $result['passed'] ? 1 : 0),
            default => $results->sortByDesc('score'),
        };

        return $sortedResults->values();
    }
}
