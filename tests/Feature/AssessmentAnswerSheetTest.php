<?php

namespace Tests\Feature;

use App\Models\GuidanceAppointment;
use App\Models\GuidanceTestResponse;
use App\Models\ServiceRequest;
use App\Services\AssessmentAnswerSheetService;
use App\Services\DocumentExportService;
use Tests\TestCase;

class AssessmentAnswerSheetTest extends TestCase
{
    private function appointment(array $tests, array $answers): GuidanceAppointment
    {
        $appointment = new GuidanceAppointment(['test_types' => $tests, 'test_type' => $tests[0], 'status' => 'Completed']);
        foreach (['applicant', 'batch', 'sourceBatch'] as $relation) $appointment->setRelation($relation, null);
        $appointment->setRelation('serviceRequest', new ServiceRequest(['first_name' => 'Ana', 'last_name' => 'Sample', 'purpose' => 'Internship', 'gender' => 'Female', 'course' => 'BSIT', 'year_section' => '4-A', 'or_number' => 'OR-123']));
        $appointment->setRelation('response', new GuidanceTestResponse(['answers' => $answers, 'created_at' => '2026-11-15 00:00:00']));

        return $appointment;
    }

    public function test_personality_preserves_raw_reverse_item_answers_and_fits_one_pdf_page(): void
    {
        $answers = array_fill(1, 44, 5);
        $answers[2] = 1;
        $document = app(AssessmentAnswerSheetService::class)->build($this->appointment(['bfpi'], ['bfpi' => $answers]));
        $this->assertCount(44, $document['sheets'][0]['rows']);
        $this->assertSame(1, $document['sheets'][0]['rows'][1]['selected']);
        $html = view($document['view'], $document)->render();
        $this->assertSame(44, substr_count($html, '&#10003;'));
        foreach (['Ana Sample', 'Internship', 'OR-123', 'Female', '4-A', 'DO NOT WRITE IN THIS SECTION'] as $text) $this->assertStringContainsString($text, $html);
        $pdf = app(DocumentExportService::class)->render($document, 'pdf');
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+306(?:\.0+)?\s+792(?:\.0+)?\s*\]/', $pdf);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
    }

    public function test_psychological_zero_missing_and_invalid_answers_and_separate_pages(): void
    {
        $document = app(AssessmentAnswerSheetService::class)->build($this->appointment(['dass21', 'phq9', 'gad7'], [
            'dass21' => [1 => 0, 2 => 9], 'phq9' => [1 => '3', 'difficulty_rating' => 0], 'gad7' => [7 => 2],
        ]));
        $this->assertCount(3, $document['sheets']);
        $this->assertSame(0, $document['sheets'][0]['rows'][0]['selected']);
        $this->assertNull($document['sheets'][0]['rows'][1]['selected']);
        $this->assertNull($document['sheets'][0]['rows'][2]['selected']);
        $this->assertSame('Not difficult at all', $document['sheets'][1]['difficulty']);
        $this->assertSame(3, substr_count(view($document['view'], $document)->render(), '&#10003;'));
        $pdf = app(DocumentExportService::class)->render($document, 'pdf');
        $this->assertSame(3, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
    }

    public function test_legacy_flat_career_answers_and_private_routes(): void
    {
        $document = app(AssessmentAnswerSheetService::class)->build($this->appointment(['career'], array_fill(1, 6, 4)));
        $this->assertCount(6, $document['sheets'][0]['rows']);
        $this->assertSame(6, substr_count(view($document['view'], $document)->render(), '&#10003;'));
        foreach (['staff', 'admin'] as $role) {
            $route = app('router')->getRoutes()->getByName($role.'.guidance.answer-sheet');
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('role:'.$role, $route->gatherMiddleware());
            $this->get('/'.$role.'/guidance/answer-sheet/1')->assertRedirect();
        }
    }
}
