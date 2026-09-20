<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function submitGuidanceSections(string $token, array $answers)
    {
        $appointment = \App\Models\GuidanceTestQrCode::where('token', $token)->firstOrFail()->appointment;
        $snapshot = [];
        foreach (app(\App\Services\GuidanceAssessmentSessionService::class)->sections($appointment) as $index => $section) {
            foreach ($section['items'] as $item) $snapshot[$section['test']][$item] = $answers[$section['test']][$item];
            $response = $this->postJson(route('guidance.submit', $token), ['answers' => $snapshot, 'section_index' => $index])->assertOk();
        }
        return $response;
    }
}
