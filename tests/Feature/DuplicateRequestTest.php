<?php

namespace Tests\Feature;

use App\Models\GuidanceAppointment;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateRequestTest extends TestCase
{
    use RefreshDatabase;

    private const DUPLICATE_MESSAGE = 'You already have an active request for this item. Please track your existing request using your Tracking Reference code.';

    private function testingPayload(string $testCategory = 'psychological', string $studentNumber = '23-SC-4143'): array
    {
        return [
            'service'        => 'testing',
            'first_name'     => 'Juan',
            'last_name'      => 'Dela Cruz',
            'student_status' => 'student',
            'student_number' => $studentNumber,
            'course'         => 'BSIT',
            'reason'         => 'OJT',
            'tests'          => [$testCategory],
            'consent'        => 1,
        ];
    }

    private function documentPayload(string $service = 'good-moral', string $studentNumber = '23-SC-4143'): array
    {
        return [
            'service'        => $service,
            'first_name'     => 'Juan',
            'last_name'      => 'Dela Cruz',
            'student_status' => 'student',
            'student_number' => $studentNumber,
            'course'         => 'BSIT',
            'purpose'        => 'Employment requirement',
            'copies'         => 1,
            'consent'        => 1,
        ];
    }

    public function test_duplicate_psychological_request_is_blocked_via_web_and_json(): void
    {
        $payload = $this->testingPayload('psychological', '23-SC-4143');

        // First submission succeeds
        $this->post('/portal/requests', $payload)->assertRedirect('/portal?service=testing#track');
        $this->assertSame(1, ServiceRequest::where('student_number', '23-SC-4143')->count());

        // Duplicate submission via web request is blocked with flash message and error
        $webResponse = $this->post('/portal/requests', $payload);
        $webResponse->assertSessionHas('portal_notice', self::DUPLICATE_MESSAGE);
        $webResponse->assertSessionHasErrors(['service' => self::DUPLICATE_MESSAGE]);
        $this->assertSame(1, ServiceRequest::where('student_number', '23-SC-4143')->count());

        // Duplicate submission via JSON request returns HTTP 422
        $jsonResponse = $this->postJson('/api/submit-request', $payload);
        $jsonResponse->assertStatus(422);
        $jsonResponse->assertJson([
            'message' => self::DUPLICATE_MESSAGE,
            'errors'  => [
                'service' => [self::DUPLICATE_MESSAGE],
            ],
        ]);
        $this->assertSame(1, ServiceRequest::where('student_number', '23-SC-4143')->count());
    }

    public function test_duplicate_personality_test_request_is_blocked(): void
    {
        $payload = $this->testingPayload('personality', '23-SC-1111');

        $this->post('/portal/requests', $payload)->assertRedirect();
        $this->assertSame(1, ServiceRequest::where('student_number', '23-SC-1111')->count());

        $res = $this->post('/portal/requests', $payload);
        $res->assertSessionHas('portal_notice', self::DUPLICATE_MESSAGE);
        $this->assertSame(1, ServiceRequest::where('student_number', '23-SC-1111')->count());
    }

    public function test_duplicate_career_test_request_is_blocked(): void
    {
        $payload = $this->testingPayload('career', '23-SC-2222');

        $this->post('/portal/requests', $payload)->assertRedirect();
        $this->assertSame(1, ServiceRequest::where('student_number', '23-SC-2222')->count());

        $res = $this->postJson('/api/submit-request', $payload);
        $res->assertStatus(422)->assertJsonPath('message', self::DUPLICATE_MESSAGE);
    }

    public function test_duplicate_good_moral_request_is_blocked(): void
    {
        $payload = $this->documentPayload('good-moral', '23-SC-3333');

        $this->post('/portal/requests', $payload)->assertRedirect();
        $this->assertSame(1, ServiceRequest::where('student_number', '23-SC-3333')->count());

        $res = $this->post('/portal/requests', $payload);
        $res->assertSessionHas('portal_notice', self::DUPLICATE_MESSAGE);
        $this->assertSame(1, ServiceRequest::where('student_number', '23-SC-3333')->count());

        $jsonRes = $this->postJson('/api/submit-request', $payload);
        $jsonRes->assertStatus(422)->assertJsonPath('message', self::DUPLICATE_MESSAGE);
    }

    public function test_duplicate_exit_form_request_is_blocked(): void
    {
        $payload = $this->documentPayload('exit-form', '23-SC-4444');

        $this->post('/portal/requests', $payload)->assertRedirect();
        $this->assertSame(1, ServiceRequest::where('student_number', '23-SC-4444')->count());

        $res = $this->post('/portal/requests', $payload);
        $res->assertSessionHas('portal_notice', self::DUPLICATE_MESSAGE);
        $this->assertSame(1, ServiceRequest::where('student_number', '23-SC-4444')->count());

        $jsonRes = $this->postJson('/api/submit-request', $payload);
        $jsonRes->assertStatus(422)->assertJsonPath('message', self::DUPLICATE_MESSAGE);
    }

    public function test_different_services_do_not_block_each_other(): void
    {
        $testing = $this->testingPayload('psychological', '23-SC-5555');
        $goodMoral = $this->documentPayload('good-moral', '23-SC-5555');

        $this->post('/portal/requests', $testing)->assertSessionHasNoErrors();
        $this->post('/portal/requests', $goodMoral)->assertSessionHasNoErrors();

        $this->assertSame(2, ServiceRequest::where('student_number', '23-SC-5555')->count());
    }

    public function test_completed_request_allows_new_request(): void
    {
        $payload = $this->documentPayload('good-moral', '23-SC-6666');

        $this->post('/portal/requests', $payload)->assertSessionHasNoErrors();
        $entry = ServiceRequest::where('student_number', '23-SC-6666')->firstOrFail();

        // Mark completed and archived
        $entry->update([
            'status'      => 'completed',
            'archived_at' => now(),
        ]);

        // A new request should now be accepted
        $this->post('/portal/requests', $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, ServiceRequest::where('student_number', '23-SC-6666')->count());
    }

    public function test_void_request_allows_new_request(): void
    {
        $payload = $this->documentPayload('good-moral', '23-SC-7777');

        $this->post('/portal/requests', $payload)->assertSessionHasNoErrors();
        $entry = ServiceRequest::where('student_number', '23-SC-7777')->firstOrFail();

        // Mark void
        $entry->update([
            'status'      => 'void',
            'archived_at' => now(),
        ]);

        // A new request should now be accepted
        $this->post('/portal/requests', $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, ServiceRequest::where('student_number', '23-SC-7777')->count());
    }

    public function test_different_students_can_request_same_service(): void
    {
        $payloadStudentA = $this->testingPayload('psychological', '23-SC-8888');
        $payloadStudentB = $this->testingPayload('psychological', '23-SC-9999');

        $this->post('/portal/requests', $payloadStudentA)->assertSessionHasNoErrors();
        $this->post('/portal/requests', $payloadStudentB)->assertSessionHasNoErrors();

        $this->assertSame(1, ServiceRequest::where('student_number', '23-SC-8888')->count());
        $this->assertSame(1, ServiceRequest::where('student_number', '23-SC-9999')->count());
    }

    public function test_migration_up_and_down_are_safe_and_idempotent(): void
    {
        $migration = require database_path('migrations/2026_09_21_000001_add_active_request_unique_guard.php');

        // Calling up() when columns already exist should not throw exceptions
        $migration->up();
        $this->assertTrue(true);

        // Calling down() should cleanly drop columns
        $migration->down();
        $this->assertTrue(true);

        // Calling down() again when columns are already dropped should safely pass without error
        $migration->down();
        $this->assertTrue(true);

        // Calling up() again after drop should safely re-create
        $migration->up();
        $this->assertTrue(true);
    }
}
