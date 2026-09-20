<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnswerSheetSubmitRequest;
use App\Models\AnswerSheet;
use App\Models\SessionApplicant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnswerSheetController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $assignment = $this->currentAssignment();
        $guardRedirect = $this->guardAccess($assignment);

        if ($guardRedirect) {
            return $guardRedirect;
        }

        $answerSheet = AnswerSheet::firstOrCreate(
            [
                'test_session_id' => $assignment->test_session_id,
                'applicant_id' => $assignment->applicant_id,
            ],
            [
                'started_at' => now(),
            ]
        );

        if (! $answerSheet->started_at) {
            $answerSheet->update(['started_at' => now()]);
        }

        if ($this->lockIfExpired($assignment, $answerSheet)) {
            return redirect()
                ->route('answers.complete')
                ->with('warning', 'The exam timer has ended. Your answer sheet is now locked.');
        }

        if ($answerSheet->submitted_at || $answerSheet->is_locked) {
            return redirect()->route('answers.complete');
        }

        return view('examinee.answer-sheet', [
            'assignment' => $assignment,
            'answerSheet' => $answerSheet,
            'questionNumbers' => range(1, 80),
            'endTimeIso' => $assignment->testSession->examEndsAt()->toIso8601String(),
        ]);
    }

    public function submit(AnswerSheetSubmitRequest $request): RedirectResponse
    {
        $assignment = $this->currentAssignment();
        $guardRedirect = $this->guardAccess($assignment);

        if ($guardRedirect) {
            return $guardRedirect;
        }

        $answerSheet = AnswerSheet::firstOrCreate(
            [
                'test_session_id' => $assignment->test_session_id,
                'applicant_id' => $assignment->applicant_id,
            ]
        );

        if ($answerSheet->submitted_at || $answerSheet->is_locked) {
            return redirect()->route('answers.complete');
        }

        $validated = $request->validated();
        $answerSheet->fill($validated);
        $answerSheet->started_at ??= now();

        if ($this->lockIfExpired($assignment, $answerSheet, true)) {
            return redirect()
                ->route('answers.complete')
                ->with('warning', 'Time is up. The answer sheet has been locked.');
        }

        $answerSheet->submitted_at = now();
        $answerSheet->is_locked = true;
        $answerSheet->save();

        return redirect()
            ->route('answers.complete')
            ->with('success', 'Your answer sheet has been submitted successfully.');
    }

    public function saveProgress(AnswerSheetSubmitRequest $request): JsonResponse
    {
        $assignment = $this->currentAssignment();

        if (! $assignment) {
            return response()->json([
                'message' => 'Session assignment not found.',
            ], 401);
        }

        if (! $assignment->is_present || ! $assignment->testSession->isStarted()) {
            return response()->json([
                'message' => 'The answer sheet is not available right now.',
            ], 422);
        }

        $answerSheet = AnswerSheet::firstOrCreate(
            [
                'test_session_id' => $assignment->test_session_id,
                'applicant_id' => $assignment->applicant_id,
            ]
        );

        if ($answerSheet->submitted_at || $answerSheet->is_locked) {
            return response()->json([
                'message' => 'The answer sheet has already been submitted.',
            ], 409);
        }

        $answerSheet->fill($request->validated());
        $answerSheet->started_at ??= now();

        if ($this->lockIfExpired($assignment, $answerSheet, true)) {
            return response()->json([
                'message' => 'Time is up. Your answer sheet has been submitted automatically.',
                'submitted_at' => optional($answerSheet->fresh()->submitted_at)?->toIso8601String(),
            ]);
        }

        $answerSheet->save();

        return response()->json([
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    public function complete(): View|RedirectResponse
    {
        $assignment = $this->currentAssignment();

        if (! $assignment) {
            return redirect()->route('login');
        }

        return view('examinee.complete', [
            'assignment' => $assignment->load(['applicant', 'testSession']),
            'answerSheet' => AnswerSheet::where('test_session_id', $assignment->test_session_id)
                ->where('applicant_id', $assignment->applicant_id)
                ->first(),
        ]);
    }

    private function currentAssignment(): ?SessionApplicant
    {
        $assignmentId = session('session_applicant_id');

        if (! $assignmentId) {
            return null;
        }

        return SessionApplicant::with(['applicant', 'testSession'])
            ->find($assignmentId);
    }

    private function guardAccess(?SessionApplicant $assignment): ?RedirectResponse
    {
        if (! $assignment) {
            return redirect()->route('login');
        }

        if (! $assignment->is_present) {
            return redirect()
                ->route('checkin.show', $assignment->testSession->qr_token)
                ->withErrors(['full_name' => 'Please complete verification before accessing the exam.']);
        }

        if (! $assignment->testSession->isStarted()) {
            return redirect()
                ->route('checkin.waiting')
                ->with('warning', 'The test has not started yet. Please remain on the waiting page.');
        }

        return null;
    }

    private function lockIfExpired(SessionApplicant $assignment, AnswerSheet $answerSheet, bool $persistCurrentAnswers = false): bool
    {
        if (now()->lt($assignment->testSession->examEndsAt())) {
            return false;
        }

        if ($persistCurrentAnswers) {
            $answerSheet->save();
        }

        $assignment->testSession->finalizeExpiredAnswerSheets();

        return true;
    }
}
