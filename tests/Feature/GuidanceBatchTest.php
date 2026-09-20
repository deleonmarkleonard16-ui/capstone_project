<?php

namespace Tests\Feature;

use App\Events\GuidanceBatchStarted;
use App\Models\GuidanceAppointment;
use App\Models\GuidanceTestBatch;
use App\Models\Role;
use App\Models\User;
use App\Services\GuidanceAssessmentSessionService;
use App\Services\GuidanceBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuidanceBatchTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', 'staff')->value('id')]);
    }
    private function batch(string $category = 'Psychological Assessment'): GuidanceTestBatch
    {
        return app(GuidanceBatchService::class)->import(['batch_name' => 'BATCH-BSIT-3A', 'course' => 'BSIT', 'reason_for_request' => 'Practicum', 'test_type' => $category], UploadedFile::fake()->createWithContent('roster.csv', "student_id,first_name,middle_name,last_name\n001,Maria,Cruz,Santos\n002,Juan,,Reyes\n"));
    }
    private function receipt(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHfoAAAAASUVORK5CYII='));
    }
    private function ready(GuidanceTestBatch $batch): void
    {
        Storage::fake('local');
        foreach ($batch->appointments as $appointment) app(GuidanceBatchService::class)->receipt($batch, $appointment->getKey(), $this->receipt());
    }

    public function test_roster_import_permissions_atomic_validation_and_separate_queues(): void
    {
        $this->getJson(route('staff.guidance-batches.index'))->assertUnauthorized();
        $this->actingAs($this->staff());
        $metadata = ['batch_name' => 'BATCH-BSIT-3A', 'course' => 'BSIT', 'reason_for_request' => 'Practicum', 'test_type' => 'Psychological Assessment'];
        $this->postJson(route('staff.guidance-batches.store'), $metadata + ['roster' => UploadedFile::fake()->createWithContent('roster.csv', "student_id,first_name,last_name\n001,Maria,Santos\n001,Juan,Reyes")])->assertUnprocessable();
        $this->assertDatabaseCount('guidance_test_batches', 0);
        $this->assertDatabaseCount('guidance_appointments', 0);
        $batch = $this->batch();
        $this->get(route('staff.guidance-batches.index'))->assertOk()->assertSee('BATCH-BSIT-3A')->assertSee('Ready: 0/2')->assertSee('Mark Absentee');
        $this->get(route('staff.guidance.index'))->assertOk()->assertDontSee($batch->appointments->first()->request_code);
        $this->get(route('staff.guidance-batches.qr', $batch))->assertOk()->assertSee('data:image/svg+xml;base64,', false);
    }

    public function test_identity_requires_all_roster_fields_and_receipt_is_session_bound(): void
    {
        Storage::fake('local'); $batch = $this->batch(); $token = $batch->batch_token;
        $verify = route('guidance.batch.verify', $token);
        $identity = ['student_id' => '001', 'first_name' => 'Maria', 'middle_name' => 'Cruz', 'last_name' => 'Santos'];
        foreach (['student_id' => '002', 'first_name' => 'Other', 'middle_name' => '', 'last_name' => 'Other'] as $field => $wrong) {
            $this->postJson($verify, array_replace($identity, [$field => $wrong]))->assertUnprocessable();
        }
        $this->getJson(route('guidance.batch.state', $token))->assertForbidden();
        $this->postJson(route('guidance.batch.receipt', $token), ['receipt' => $this->receipt()])->assertForbidden();
        $this->post($verify, $identity)->assertRedirect();
        $this->get(route('guidance.batch.join', $token))->assertOk()
            ->assertSee('Pangasinan State University - San Carlos Campus')
            ->assertSee('Digital Management System for Guidance Testing and Admission')
            ->assertSee('images/psu-logo.png')
            ->assertSee('Take Photo of Receipt')->assertSee('Upload Receipt File')->assertSee('readonly', false);
        $this->post(route('guidance.batch.receipt', $token), ['receipt' => $this->receipt(), 'course' => 'Tampered'])->assertRedirect();
        $appointment = $batch->appointments()->first();
        $this->assertSame('Ready', $appointment->attendance_status);
        Storage::disk('local')->assertExists($appointment->payment_slip_path);
        $this->assertSame('BSIT', $batch->fresh()->course);
        $this->postJson(route('guidance.batch.receipt', $token), ['receipt' => $this->receipt()])->assertConflict();
        $this->postJson('/api/track-request', ['reference' => $appointment->request_code])->assertNotFound();
    }

    public function test_batch_launch_is_shared_idempotent_and_polling_exposes_only_verified_pass(): void
    {
        $this->travelTo(now()->startOfSecond());
        Event::fake([GuidanceBatchStarted::class]);
        $batch = $this->batch(); $this->ready($batch);
        $this->post(route('guidance.batch.verify', $batch->batch_token), ['student_id' => '001', 'first_name' => 'Maria', 'middle_name' => 'Cruz', 'last_name' => 'Santos'])->assertRedirect();
        $staff = $this->staff(); $this->actingAs($staff);
        $url = route('staff.guidance-batches.action', $batch);
        $this->post($url, ['action' => 'start'])->assertRedirect();
        $this->post($url, ['action' => 'start'])->assertRedirect();
        $appointments = $batch->appointments()->get();
        $this->assertTrue($appointments[0]->started_at->equalTo($appointments[1]->started_at));
        $this->assertEquals(600, $appointments[0]->started_at->diffInSeconds($appointments[0]->expires_at));
        $this->assertDatabaseCount('guidance_test_qr_codes', 2);
        Event::assertDispatchedTimes(GuidanceBatchStarted::class, 1);
        $this->getJson(route('guidance.batch.state', $batch->batch_token))->assertOk()->assertJsonPath('url', route('guidance.take', $appointments[0]->qrCode->token));
        $this->postJson(route('staff.guidance-appointments.verify', $appointments[0]))->assertConflict();
        $this->postJson($url, ['action' => 'archive'])->assertConflict();
    }

    public function test_section_deadlines_catch_up_preserve_drafts_and_auto_archive_batch(): void
    {
        $this->travelTo(now()->startOfSecond());
        $batch = $this->batch(); $this->ready($batch);
        app(GuidanceBatchService::class)->start($batch, $this->staff()->id);
        $appointment = $batch->appointments()->first(); $token = $appointment->qrCode->token;
        $this->postJson(route('guidance.start', $token))->assertOk();
        $this->postJson(route('guidance.progress', $token), ['answers' => ['dass21' => [1 => 2]], 'section_index' => 0])->assertOk();
        $this->travel(10)->minutes();
        $this->getJson(route('guidance.state', $token))->assertOk()->assertJsonPath('section_index', 1)->assertJsonPath('remaining_seconds', 600);
        $this->postJson(route('guidance.progress', $token), ['answers' => ['dass21' => [1 => 3]], 'section_index' => 0])->assertConflict();
        $this->postJson(route('guidance.progress', $token), ['answers' => ['gad7' => [1 => 1]], 'section_index' => 1])->assertUnprocessable();
        $this->travel(20)->minutes();
        app(GuidanceAssessmentSessionService::class)->expireDue();
        $this->assertSame('Completed', $batch->fresh()->status);
        $this->assertNotNull($batch->fresh()->archived_at);
        $this->assertSame(2, $appointment->fresh()->response->answers['dass21'][1]);
        $this->assertSame('Incomplete', $appointment->fresh()->response->score_summary['tests']['dass21']['completion']);
        $this->assertCount(3, $appointment->fresh()->response->score_summary['sections']);
        app(GuidanceAssessmentSessionService::class)->expireDue();
        $this->assertDatabaseCount('guidance_test_responses', 2);
        $this->actingAs($this->staff())->get(route('staff.guidance-batches.index', ['archived' => 1]))->assertOk()->assertSee('Review Submission');
        $review = $this->getJson(route('staff.guidance-appointments.review', $appointment))->assertOk();
        $this->assertStringContainsString('Used QR pass (inactive)', $review->json('html'));
        $this->assertStringContainsString('Raw item answers', $review->json('html'));
    }

    public function test_absentee_conversion_preserves_identity_and_supports_individual_receipt_upload(): void
    {
        Storage::fake('local'); $batch = $this->batch(); $appointment = $batch->appointments()->first();
        $this->actingAs($this->staff()); $url = route('staff.guidance-batches.action', $batch);
        $this->postJson($url, ['action' => 'convert', 'appointment_id' => $appointment->getKey()])->assertConflict();
        $this->post($url, ['action' => 'absent', 'appointment_id' => $appointment->getKey()])->assertRedirect();
        $this->post($url, ['action' => 'convert', 'appointment_id' => $appointment->getKey()])->assertRedirect();
        $this->assertNull($appointment->fresh()->batch_id);
        $this->assertEquals($batch->getKey(), $appointment->fresh()->source_batch_id);
        $this->get(route('staff.guidance.index'))->assertOk()->assertSee($appointment->request_code);
        $this->postJson('/api/upload-receipt', ['reference' => $appointment->request_code, 'proof' => $this->receipt()])->assertOk();
        $this->assertSame('Receipt Uploaded', $appointment->fresh()->status);
        $this->post($url, ['action' => 'archive'])->assertRedirect();
        $this->assertSame('Completed', $batch->fresh()->status);
        $this->assertSame('Absent', $batch->appointments()->first()->attendance_status);
    }

    public function test_makeup_score_returns_to_original_section_and_archived_roster(): void
    {
        $batch = $this->batch('Career Test');
        $batch->update(['year_section' => 'IT-3A']);
        $appointment = $batch->appointments()->first();
        $service = app(GuidanceBatchService::class);
        $service->action($batch, 'absent', $appointment->getKey());
        $service->action($batch, 'convert', $appointment->getKey());
        $this->assertSame('BSIT', $appointment->fresh()->origin_course);
        $this->assertSame('IT-3A', $appointment->fresh()->origin_section);
        $this->assertSame($batch->getKey(), $appointment->fresh()->source_batch_id);

        $service->action($batch, 'archive', null);
        $appointment->response()->create(['applicant_id' => $appointment->applicant_id, 'answers' => ['career' => [1 => 1]], 'score_summary' => ['tests' => []]]);
        $appointment->update(['status' => 'Completed', 'attendance_status' => 'Completed', 'is_archived' => true, 'archived_at' => now()]);

        $summary = app(\App\Services\GuidanceAnalyticsService::class)->sectionTotals('career', 'BSIT');
        $this->assertSame('IT-3A', $summary[0]['section']);
        $this->assertSame(1, $summary[0]['completed']);
        $this->assertSame(1, $summary[0]['makeup']);
        $this->actingAs($this->staff())->get(route('staff.career.batches', ['archived' => 1]))
            ->assertOk()->assertSee($appointment->request_code)->assertSee('Makeup Exam');
    }

    public function test_batch_filters_search_student_reference_and_section_without_crossing_modules(): void
    {
        $batch = $this->batch('Career Test');
        $batch->update(['year_section' => 'IT-3A']);
        $student = $batch->appointments()->first();
        $this->actingAs($this->staff());
        foreach (['001', $student->request_code, 'IT-3A', 'Maria'] as $search) {
            $this->get(route('staff.career.batches', ['q' => $search, 'course' => 'BSIT']))->assertOk()->assertSee('data-batch="'.$batch->getKey().'"', false);
            $this->get(route('staff.personality.batches', ['q' => $search]))->assertOk()->assertDontSee('data-batch="'.$batch->getKey().'"', false);
        }
        $this->get(route('staff.career.batches', ['course' => 'BSHM']))->assertDontSee('data-batch="'.$batch->getKey().'"', false);
    }

    public function test_career_sections_and_configured_personality_use_ten_minute_engine(): void
    {
        $this->travelTo(now()->startOfSecond());
        $batch = $this->batch('Career Test'); $this->ready($batch);
        app(GuidanceBatchService::class)->start($batch, $this->staff()->id);
        $appointment = $batch->appointments()->first(); $token = $appointment->qrCode->token;
        $this->postJson(route('guidance.start', $token))->assertJsonPath('remaining_seconds', 600);
        $this->assertCount(1, app(GuidanceAssessmentSessionService::class)->sections($appointment));
        $this->submitGuidanceSections($token, ['career' => array_fill(1, 6, 3)])->assertJsonPath('status', 'Completed');
        $this->assertCount(6, $appointment->fresh()->response->score_summary['tests']['career']['scores']);
        config(['guidance.bfpi' => ['items' => 2, 'subscales' => ['Example' => [1, 2]], 'reverse_items' => [2], 'overlap' => 'Average']]);
        $batch = $this->batch('Personality Test'); $this->ready($batch);
        app(GuidanceBatchService::class)->start($batch, $this->staff()->id);
        $appointment = $batch->appointments()->first(); $token = $appointment->qrCode->token;
        $this->postJson(route('guidance.start', $token))->assertJsonPath('remaining_seconds', 600);
        $this->submitGuidanceSections($token, ['bfpi' => [1 => 5, 2 => 1]])->assertJsonPath('status', 'Completed');
        $this->assertEquals(5, $appointment->fresh()->response->score_summary['tests']['bfpi']['scores']['Example']);
    }

    public function test_flexible_csv_import_handles_spaces_aliases_delimiters_and_excel_artifacts(): void
    {
        $this->actingAs($this->staff());
        $service = app(GuidanceBatchService::class);
        $metadata = [
            'batch_name' => 'BATCH-FLEX-1',
            'course' => 'BSIT',
            'reason_for_request' => 'Field Study',
            'test_type' => 'Psychological Assessment'
        ];

        // 1. Headers with spaces, capitalized, and BOM
        $csvContent = "\xEF\xBB\xBFStudent ID,First Name,Middle Name,Last Name\n23-SC-001,Juan,Santos,Dela Cruz\n23-SC-002,Maria,,Clara\n";
        $batch = $service->import($metadata, UploadedFile::fake()->createWithContent('spaced.csv', $csvContent));
        $this->assertCount(2, $batch->appointments);

        // 2. Semicolon delimited with common aliases (Student Number, Given Name, Surname)
        $semiContent = "Student Number;Given Name;Middle Initial;Surname\n23-SC-003;Pedro;L;Penduko\n";
        $metadata['batch_name'] = 'BATCH-FLEX-2';
        $batch2 = $service->import($metadata, UploadedFile::fake()->createWithContent('semi.csv', $semiContent));
        $this->assertCount(1, $batch2->appointments);
        $this->assertSame('23-SC-003', $batch2->appointments->first()->student_id_number);
    }
}
