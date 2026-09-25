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
            'questions' => $this->getQuestionsFor($test),
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
            ->with('success', 'Assessment submitted successfully! Your responses and scores have been recorded.');
    }

    public function staffReview(GuidanceTestSubmission $submission)
    {
        $submission->load('serviceRequest');
        return view('staff.guidance.assessment-detail', [
            'submission' => $submission,
            'entry' => $submission->serviceRequest,
            'testTitle' => self::TESTS[$submission->test_type] ?? ucfirst($submission->test_type),
            'questions' => $this->getQuestionsFor($submission->test_type),
        ]);
    }

    public function staffNotes(Request $request, GuidanceTestSubmission $submission)
    {
        $data = $request->validate(['counselor_notes' => 'nullable|string|max:3000']);
        $submission->update($data);

        return back()->with('success', 'Counselor notes saved successfully.');
    }

    public function getQuestionsFor(string $test): array
    {
        return match ($test) {
            'dass21' => [
                1 => 'I found it hard to wind down.',
                2 => 'I was aware of dryness of my mouth.',
                3 => 'I couldn’t seem to experience any positive feeling at all.',
                4 => 'I experienced breathing difficulty (e.g. excessively rapid breathing).',
                5 => 'I found it difficult to work up the initiative to do things.',
                6 => 'I tended to over-react to situations.',
                7 => 'I experienced trembling (e.g. in the hands).',
                8 => 'I felt that I was using a lot of nervous energy.',
                9 => 'I was worried about situations in which I might panic and make a fool of myself.',
                10 => 'I felt that I had nothing to look forward to.',
                11 => 'I found myself getting agitated.',
                12 => 'I found it difficult to relax.',
                13 => 'I felt down-hearted and blue.',
                14 => 'I was intolerant of anything that kept me from getting on with what I was doing.',
                15 => 'I felt I was close to panic.',
                16 => 'I was unable to become enthusiastic about anything.',
                17 => 'I felt I wasn’t worth much as a person.',
                18 => 'I felt that I was rather touchy.',
                19 => 'I was aware of the action of my heart in the absence of physical exertion.',
                20 => 'I felt scared without any good reason.',
                21 => 'I felt that life was meaningless.',
            ],
            'phq9' => [
                1 => 'Little interest or pleasure in doing things.',
                2 => 'Feeling down, depressed, or hopeless.',
                3 => 'Trouble falling or staying asleep, or sleeping too much.',
                4 => 'Feeling tired or having little energy.',
                5 => 'Poor appetite or overeating.',
                6 => 'Feeling bad about yourself — or that you are a failure or have let yourself or your family down.',
                7 => 'Trouble concentrating on things, such as reading the newspaper or watching television.',
                8 => 'Moving or speaking so slowly that other people could have noticed, or being fidgety/restless.',
                9 => 'Thoughts that you would be better off dead, or of hurting yourself in some way.',
            ],
            'gad7' => [
                1 => 'Feeling nervous, anxious, or on edge.',
                2 => 'Not being able to stop or control worrying.',
                3 => 'Worrying too much about different things.',
                4 => 'Trouble relaxing.',
                5 => 'Being so restless that it is hard to sit still.',
                6 => 'Becoming easily annoyed or irritable.',
                7 => 'Feeling afraid, as if something awful might happen.',
            ],
            'bfpi' => [
                1 => 'Extraversion: I see myself as someone who is outgoing, sociable, and talkative.',
                2 => 'Agreeableness: I see myself as someone who is generally trusting, considerate, and kind.',
                3 => 'Conscientiousness: I see myself as someone who does a thorough job and is dependable.',
                4 => 'Emotional Stability: I see myself as someone who is relaxed and handles stress well.',
                5 => 'Openness to Experience: I see myself as someone who has an active imagination and values artistic experiences.',
                6 => 'Extraversion: I see myself as someone who generates a lot of enthusiasm.',
                7 => 'Agreeableness: I see myself as someone who is helpful and unselfish with others.',
                8 => 'Conscientiousness: I see myself as someone who perseveres until the task is finished.',
                9 => 'Emotional Stability: I see myself as someone who remains calm in tense situations.',
                10 => 'Openness to Experience: I see myself as someone who is curious about many different things.',
            ],
            'career' => [
                1 => 'Realistic: I like to work with tools, machinery, equipment, or hands-on physical activities.',
                2 => 'Investigative: I enjoy analyzing problems, researching scientific ideas, or solving complex puzzles.',
                3 => 'Artistic: I enjoy creating art, writing, music, designing, or expressing unique creative ideas.',
                4 => 'Social: I like teaching, helping, counseling, or caring for other people.',
                5 => 'Enterprising: I enjoy leading teams, persuading others, marketing, or starting business projects.',
                6 => 'Conventional: I like organizing records, working with numbers, databases, and structured procedures.',
            ],
            default => [],
        };
    }

}
