<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\GuidancePortalService;
use App\Services\GuidanceReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuidanceReviewTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $test = 'psychological'): array
    {
        return ['service' => 'testing', 'first_name' => 'Maria', 'middle_name' => 'Reyes', 'last_name' => 'Santos',
            'student_status' => 'student', 'student_number' => '2026-1234', 'course' => 'BSIT', 'purpose' => 'Field Study', 'tests' => [$test]];
    }

    private function staff(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', 'staff')->value('id')]);
    }

    private function upload(ServiceRequest $entry): void
    {
        Storage::fake('local');
        $receipt = UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aHfoAAAAASUVORK5CYII='));
        $this->postJson(route('guidance.receipt'), ['reference' => ' '.strtolower($entry->reference).' ', 'proof' => $receipt])->assertOk();
    }

    public function test_short_reference_redirect_tracking_receipt_and_no_contact_fields(): void
    {
        $this->get('/portal')->assertOk()->assertSee('<select id="requested-test" name="tests[]"', false)
            ->assertDontSee('name="email"', false)->assertDontSee('name="contact_number"', false);
        $this->postJson(route('api.submit-request'), $this->payload() + ['reason' => 'FIELD STUDY', 'consent' => 1])->assertCreated()->assertJsonPath('status', 'Pending Payment');
        $entry = ServiceRequest::firstOrFail();
        $this->assertMatchesRegularExpression('/^G-[A-Z0-9]{4}$/D', $entry->reference);
        $this->assertSame($entry->reference, $entry->guidanceAppointments->first()->request_code);
        $this->assertDatabaseHas('guidance_reference_codes', ['code' => $entry->reference]);
        $this->get('/portal')->assertSee('value="'.$entry->reference.'"', false)->assertSee('data-auto-track="true"', false);
        $this->post(route('portal.track'), ['reference' => strtolower($entry->reference)])->assertRedirect('/portal?service=testing#track');
        $this->postJson(route('api.track-request'), ['reference' => strtolower($entry->reference)])->assertOk()->assertJsonPath('status', 'Pending Payment');
        $this->upload($entry);
        $this->assertNotNull($entry->guidanceAppointments->first()->fresh()->appointment_at);
    }

    public function test_reference_collisions_retry_and_reservations_rollback(): void
    {
        DB::table('guidance_reference_codes')->insert(['code' => 'G-AAAA', 'created_at' => now()]);
        $allocator = new class extends GuidanceReferenceService {
            private int $attempts = 0;
            protected function candidate(): string { return ++$this->attempts === 1 ? 'G-AAAA' : 'G-BBBB'; }
        };
        $this->assertSame('G-BBBB', DB::transaction(fn () => $allocator->reserve()));
        $this->assertDatabaseCount('guidance_reference_codes', 2);
        try {
            DB::transaction(function () { app(GuidanceReferenceService::class)->reserve(); throw new \RuntimeException('rollback'); });
        } catch (\RuntimeException $exception) { $this->assertSame('rollback', $exception->getMessage()); }
        $this->assertDatabaseCount('guidance_reference_codes', 2);
    }

    public function test_legacy_long_references_remain_trackable_and_uploadable(): void
    {
        $entry = app(GuidancePortalService::class)->create($this->payload());
        $reference = 'TR-'.str_repeat('A', 32);
        $entry->update(['reference' => $reference]);
        $entry->guidanceAppointments()->update(['request_code' => $reference]);
        $this->postJson(route('api.track-request'), ['reference' => $reference])->assertOk()->assertJsonPath('status', 'Pending Payment');
        $this->upload($entry);
        $this->postJson(route('api.track-request'), ['reference' => $reference])->assertOk()->assertJsonPath('status', 'Receipt Uploaded');
    }

    public function test_staff_review_changes_from_receipt_to_qr_to_results_without_contact_data(): void
    {
        $entry = app(GuidancePortalService::class)->create($this->payload());
        $entry->update(['email' => 'private-contact@example.test', 'contact_number' => '09999999999']);
        $appointment = $entry->guidanceAppointments->first();
        $url = route('staff.guidance-appointments.review', $appointment);
        $this->getJson($url)->assertUnauthorized();
        $this->upload($entry);
        $this->actingAs($this->staff());
        $review = $this->getJson($url)->assertOk();
        $this->assertStringContainsString('data-review-receipt', $review->json('html'));
        $this->assertStringContainsString('data-review-receipt-loading', $review->json('html'));
        $this->assertStringContainsString('loading="lazy" decoding="async"', $review->json('html'));
        $this->assertStringContainsString('data-src="'.route('staff.guidance-appointments.receipt', $appointment).'"', $review->json('html'));
        $this->assertStringContainsString($entry->reference, $review->json('html'));
        $this->assertStringNotContainsString('private-contact', $review->json('html'));
        $this->assertStringNotContainsString('09999999999', $review->json('html'));
        $this->postJson(route('staff.guidance-appointments.verify', $appointment))->assertOk();
        $appointment->refresh();
        $token = $appointment->qrCode->token;
        $review = $this->getJson($url)->assertOk();
        $this->assertStringContainsString('data:image/svg+xml;base64,', $review->json('html'));
        $this->assertStringContainsString(route('guidance.take', $token), $review->json('html'));
        $this->postJson(route('guidance.start', $token))->assertOk();
        $this->submitGuidanceSections($token, ['dass21' => array_fill(1, 21, 3), 'phq9' => array_fill(1, 9, 3), 'gad7' => array_fill(1, 7, 3)])->assertOk();
        $review = $this->getJson($url)->assertOk();
        $this->assertStringContainsString('Raw item answers (JSON)', $review->json('html'));
        $this->assertStringContainsString('<template data-review-deferred>', $review->json('html'));
        $this->assertStringContainsString('Extremely Severe', $review->json('html'));
        $this->assertStringContainsString('Used QR pass (inactive)', $review->json('html'));
    }

    public function test_career_six_item_ranking_and_personality_pending_configuration(): void
    {
        $entry = app(GuidancePortalService::class)->create($this->payload('career'));
        $this->upload($entry);
        $appointment = $entry->guidanceAppointments->first();
        $this->actingAs($this->staff())->postJson(route('staff.guidance-appointments.verify', $appointment))->assertOk();
        $token = $appointment->fresh()->qrCode->token;
        $this->get(route('guidance.take', $token))->assertOk()->assertSee('I like to work with tools')->assertSee('Extremely interested');
        $this->postJson(route('guidance.start', $token))->assertOk();
        $this->submitGuidanceSections($token, ['career' => [1 => 1, 2 => 5, 3 => 4, 4 => 5, 5 => 2, 6 => 3]])->assertOk();
        $result = $appointment->fresh()->response->score_summary['tests']['career'];
        $this->assertSame('Investigative - Social - Artistic', $result['interpretation']['top_traits']);
        $this->assertSame(5, $result['scores']['Social']);
        $this->get(route('staff.guidance-appointments.show-results', $appointment))->assertOk()->assertSee('Investigative - Social - Artistic');
        $entry = app(GuidancePortalService::class)->create($this->payload('personality'));
        $this->upload($entry);
        $personality = $entry->guidanceAppointments->first();
        config(['guidance.bfpi.items' => 0]);
        $this->postJson(route('staff.guidance-appointments.verify', $personality))->assertUnprocessable();
        $this->assertSame('Receipt Uploaded', $personality->fresh()->status);
        $this->assertNull($personality->fresh()->qrCode);
        $review = $this->getJson(route('staff.guidance-appointments.review', $personality))->assertOk();
        $this->assertStringContainsString('Scoring configuration is pending', $review->json('html'));
    }
}
