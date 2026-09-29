<?php

namespace App\Http\Controllers;

use App\Models\GuidanceTestSubmission;
use App\Models\ServiceRequest;
use App\Services\GuidanceTestScoringService;
use Illuminate\Http\Request;

class GuidanceAssessmentController extends Controller
{
    public const TESTS = [
        'dass21' => 'DASS-21 (Depression, Anxiety, Stress Scales)',
        'phq9' => 'PHQ-9 (Patient Health Questionnaire)',
        'gad7' => 'GAD-7 (Generalized Anxiety Disorder)',
        'bfpi' => 'BFPI (Big Five Personality Inventory)',
        'career' => 'Career Interest Assessment (RIASEC)',
    ];

    public function show(Request $request, string $reference, string $test)
    {
        $entry = ServiceRequest::where('reference', strtoupper($reference))->firstOrFail();
        $entry->checkAndApplyExpiration();

        if ($entry->isVoid()) {
            return redirect()->route('portal.track', ['reference' => $reference])
                ->with('error', 'This request is void because the 5-day window expired.');
        }

        abort_unless(array_key_exists($test, self::TESTS), 404);

        $submission = GuidanceTestSubmission::where('service_request_id', $entry->id)
            ->where('test_type', $test)
            ->first();

        return view('portal.assessments.show', [
            'entry' => $entry,
            'test' => $test,
            'testTitle' => self::TESTS[$test],
            'definition' => app(GuidanceTestScoringService::class)->answerSheetDefinition($test),
            'submission' => $submission,
        ]);
    }

    public function submit(Request $request, string $reference, string $test)
    {
        $entry = ServiceRequest::where('reference', strtoupper($reference))->firstOrFail();
        $entry->checkAndApplyExpiration();

        if ($entry->isVoid()) {
            return redirect()->route('portal.track', ['reference' => $reference])
                ->with('error', 'This request is void because the 5-day window expired.');
        }

        abort_unless(array_key_exists($test, self::TESTS), 404);

        /** @var GuidanceTestScoringService $scoringService */
        $scoringService = app(GuidanceTestScoringService::class);
        $definition     = $scoringService->definition($test);
        $itemCount      = $definition['items'];
        $min            = $definition['min'];
        $max            = $definition['max'];

        // ── Build per-item validation rules ─────────────────────────────────
        $rules = [];
        for ($i = 1; $i <= $itemCount; $i++) {
            $rules["answers.$i"] = [
                'required',
                $test === 'bfpi' ? 'numeric' : 'integer',
                "between:$min,$max",
            ];
        }

        // PHQ-9 Item 10 / GAD-7 difficulty rating — optional, 0–3, not scored
        $hasDifficulty = in_array($test, ['phq9', 'gad7'], true);
        if ($hasDifficulty) {
            $rules['difficulty_rating'] = ['nullable', 'integer', 'between:0,3'];
        }

        $data    = $request->validate($rules);
        $answers = $data['answers'] ?? [];

        // ── Score via GuidanceTestScoringService (single source of truth) ──
        $scoringResult = $scoringService->score($test, $answers);

        // Enrich the stored payload with the difficulty rating when provided
        if ($hasDifficulty && isset($data['difficulty_rating'])) {
            $diffVal = (int) $data['difficulty_rating'];
            $scoringResult['difficulty_rating']       = $diffVal;
            $scoringResult['difficulty_rating_label'] = GuidanceTestScoringService::difficultyLabel($diffVal);
        }

        $submission = GuidanceTestSubmission::updateOrCreate(
            [
                'service_request_id' => $entry->id,
                'test_type'          => $test,
            ],
            [
                'answers'        => $answers,
                'scores'         => $scoringResult['scores'],
                'interpretation' => $scoringResult['interpretation'] + array_filter([
                    'scoring_version'        => $scoringResult['scoring_version'] ?? null,
                    'difficulty_rating'      => $scoringResult['difficulty_rating'] ?? null,
                    'difficulty_rating_label' => $scoringResult['difficulty_rating_label'] ?? null,
                ]),
                'completed_at'   => now(),
            ]
        );

        return redirect()->route('guidance.test.show', ['reference' => $entry->reference, 'test' => $test])
            ->with('success', 'Assessment completed. Your saved responses have been recorded for Guidance Office review. Please wait for the printed result that will be provided by the Guidance Office.');
    }

    public function staffReview(GuidanceTestSubmission $submission)
    {
        $submission->load('serviceRequest');
        return view('staff.guidance.assessment-detail', [
            'submission' => $submission,
            'entry' => $submission->serviceRequest,
            'testTitle' => self::TESTS[$submission->test_type] ?? ucfirst($submission->test_type),
            'items' => range(1, app(GuidanceTestScoringService::class)->definition($submission->test_type)['items']),
        ]);
    }

    public function staffNotes(Request $request, GuidanceTestSubmission $submission)
    {
        $data = $request->validate(['counselor_notes' => 'nullable|string|max:3000']);
        $submission->update($data);

        return back()->with('success', 'Counselor notes saved successfully.');
    }

}
