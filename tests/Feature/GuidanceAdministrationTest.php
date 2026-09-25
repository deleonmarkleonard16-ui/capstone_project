<?php

namespace Tests\Feature;

use App\Models\GuidanceAppointment;
use App\Models\Role;
use App\Models\User;
use App\Services\GuidanceAnalyticsService;
use App\Services\GuidancePortalService;
use App\Services\GuidanceTestScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuidanceAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function appointment(string $name = 'Maria', string $studentId = '2026-100'): GuidanceAppointment
    {
        $entry = app(GuidancePortalService::class)->create([
            'service' => 'testing', 'first_name' => $name, 'last_name' => 'Santos',
            'student_status' => 'student', 'student_number' => $studentId,
            'course' => 'BSIT', 'purpose' => 'Field Study', 'tests' => ['psychological'],
        ]);
        return $entry->guidanceAppointments()->firstOrFail();
    }

    private function staff(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', 'staff')->value('id')]);
    }

    private function approved(): GuidanceAppointment
    {
        Storage::fake('local');
        $appointment = $this->appointment();
        Storage::disk('local')->put('receipts/test.png', 'receipt');
        $appointment->update(['status' => 'Receipt Uploaded', 'payment_slip_path' => 'receipts/test.png', 'appointment_at' => now()]);
        $this->actingAs($this->staff())->post(route('staff.guidance-appointments.verify', $appointment))->assertSessionHasNoErrors();
        auth()->logout();
        return $appointment->fresh('qrCode');
    }

    private function complete(GuidanceAppointment $appointment, int $choice = 3): void
    {
        $token = $appointment->qrCode->token;
        $this->postJson(route('guidance.start', $token))->assertOk();
        $this->submitGuidanceSections($token, [
            'dass21' => array_fill(1, 21, $choice), 'phq9' => array_fill(1, 9, $choice), 'gad7' => array_fill(1, 7, $choice),
        ])->assertOk()->assertJsonPath('status', 'Completed');
        $appointment->refresh();
    }

    public function test_live_queue_filters_name_id_reference_status_and_any_bundled_instrument(): void
    {
        $target = $this->appointment();
        $other = $this->appointment('Elena', '2026-999');
        $this->getJson(route('staff.guidance-appointments.index'))->assertUnauthorized();
        $this->actingAs($this->staff());
        foreach (['Maria Santos', '2026-100', $target->request_code] as $term) {
            $result = $this->getJson(route('staff.guidance-appointments.index', ['q' => $term, 'status' => 'Pending Payment', 'test_type' => 'psychological']))->assertOk();
            $this->assertStringContainsString($target->request_code, $result->json('html'));
            $this->assertStringNotContainsString($other->request_code, $result->json('html'));
        }
        $result = $this->getJson(route('staff.guidance-appointments.index', ['status' => 'Approved']))->assertOk();
        $this->assertStringNotContainsString($target->request_code, $result->json('html'));
        $this->getJson(route('staff.guidance-appointments.index', ['test_type' => 'invalid']))->assertUnprocessable();
    }

    public function test_drafts_survive_reload_and_timeout_finalizes_once_without_scoring_missing_items(): void
    {
        $this->travelTo(now()->startOfSecond());
        $appointment = $this->approved();
        $token = $appointment->qrCode->token;
        $this->postJson(route('guidance.start', $token))->assertOk()->assertJsonPath('remaining_seconds', 600);
        $this->postJson(route('guidance.progress', $token), ['answers' => ['phq9' => [1 => 3]]])->assertUnprocessable();
        $draft = ['dass21' => array_fill(1, 21, 2)];
        $this->postJson(route('guidance.progress', $token), ['answers' => $draft])->assertOk();
        $this->travel(5)->minutes();
        $this->postJson(route('guidance.start', $token))->assertOk()->assertJsonPath('remaining_seconds', 300);
        $this->get(route('guidance.take', $token))->assertOk()->assertSee('data-remaining="300"', false)->assertSee('value="2" checked', false);
        $this->travel(25)->minutes();
        $this->getJson(route('guidance.state', $token))->assertOk()->assertJsonPath('status', 'Completed');
        $appointment->refresh();
        $this->assertTrue($appointment->is_archived);
        $this->assertFalse($appointment->qrCode->is_active);
        $this->assertSame($draft, $appointment->response->answers);
        $this->assertTrue($appointment->response->score_summary['timed_out']);
        $this->assertSame('Incomplete', $appointment->response->score_summary['tests']['phq9']['completion']);
        $this->assertArrayNotHasKey('scores', $appointment->response->score_summary['tests']['phq9']);
        $this->postJson(route('guidance.submit', $token), ['answers' => $draft])->assertGone();
        $this->assertDatabaseCount('guidance_test_responses', 1);
    }

    public function test_bulk_restore_archive_and_csv_pdf_exports_preserve_inactive_passes(): void
    {
        $appointment = $this->approved();
        $this->complete($appointment);
        $this->actingAs($this->staff());
        $this->get(route('staff.guidance-appointments.archive'))->assertOk()->assertSee($appointment->request_code);
        $this->get(route('staff.guidance.index'))->assertOk()->assertDontSee($appointment->request_code);
        $csv = $this->get(route('staff.guidance-appointments.export', ['format' => 'csv', 'q' => '2026-100']))->assertOk()->assertDownload();
        $this->assertStringContainsString($appointment->request_code, $csv->streamedContent());
        $pdf = $this->get(route('staff.guidance-appointments.export', ['format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $url = route('staff.guidance-appointments.archive-selected');
        $this->post($url, ['ids' => [$appointment->getKey()], 'archive' => 0])->assertSessionHasNoErrors();
        $appointment->refresh();
        $this->assertFalse($appointment->is_archived);
        $this->assertNull($appointment->archived_at);
        $this->assertNull($appointment->serviceRequest->archived_at);
        $this->assertFalse($appointment->qrCode->is_active);
        $this->get(route('guidance.take', $appointment->qrCode->token))->assertGone();
        $pending = $this->appointment('Elena');
        $this->postJson($url, ['ids' => [$appointment->getKey(), $pending->getKey()], 'archive' => 1])->assertUnprocessable();
        $this->assertFalse($appointment->fresh()->is_archived);
        $this->post($url, ['ids' => [$appointment->getKey()], 'archive' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($appointment->fresh()->is_archived);
        $this->get(route('staff.guidance-appointments.show-results', $appointment))
            ->assertOk()
            ->assertSee('Raw Item Choices')
            ->assertSee('Extremely Severe')
            ->assertSee('Psychological Assessment')
            ->assertSee('DASS-21')
            ->assertSee('PHQ-9')
            ->assertSee('GAD-7');
    }

    public function test_analytics_query_json_and_include_archived_high_severity_and_legacy_phq_totals(): void
    {
        $appointment = $this->approved();
        $this->complete($appointment);
        $legacy = $this->appointment('Historical');
        $legacy->update(['status' => 'Completed', 'test_types' => null, 'test_type' => 'phq9']);
        $legacy->response()->create(['applicant_id' => $legacy->applicant_id, 'answers' => array_fill(1, 9, 3),
            'score_summary' => ['test_type' => 'phq9', 'scores' => ['total' => 27]]]);
        $analytics = app(GuidanceAnalyticsService::class);
        $this->assertSame(1, $analytics->distributions()['dass21.depression']['counts']['Extremely Severe']);
        $this->assertSame(2, $analytics->distributions()['phq9.severity']['counts']['Severe']);
        $this->assertSame(2, $analytics->redFlags()->count());
        $this->getJson(route('staff.guidance-appointments.analytics'))->assertUnauthorized();
        $this->actingAs($this->staff())->get(route('staff.guidance-appointments.analytics'))->assertOk()->assertSee('Counselor Red-Flag Alerts')->assertSee('HISTORICAL');
        $result = $this->getJson(route('staff.guidance-appointments.analytics'))->assertOk();
        $this->assertStringContainsString('Review Student Submission', $result->json('html'));
    }

    public function test_phq9_severity_boundaries(): void
    {
        foreach ([0 => 'Minimal', 4 => 'Minimal', 5 => 'Mild', 9 => 'Mild', 10 => 'Moderate', 14 => 'Moderate', 15 => 'Moderately Severe', 19 => 'Moderately Severe', 20 => 'Severe', 27 => 'Severe'] as $score => $severity) {
            $answers = array_fill(1, 9, 0);
            $remaining = $score;
            foreach ($answers as $item => $_) { $answers[$item] = min(3, $remaining); $remaining -= $answers[$item]; }
            $result = app(GuidanceTestScoringService::class)->score('phq9', $answers);
            $this->assertSame($score, $result['scores']['total']);
            $this->assertSame($severity, $result['interpretation']['severity']);
        }
    }
}
