<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\GuidanceAppointment;
use App\Models\Role;
use App\Models\User;
use App\Services\GuidanceAssessmentSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuidanceSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function appointment(): GuidanceAppointment
    {
        $applicant = Applicant::create(['application_number' => Str::uuid(), 'first_name' => 'Maria', 'last_name' => 'Santos', 'status' => 'pending']);
        $appointment = GuidanceAppointment::create(['applicant_id' => $applicant->id, 'request_code' => 'GT-'.bin2hex(random_bytes(16)), 'student_id_number' => '2026-001', 'student_status' => 'student', 'test_type' => 'gad7', 'status' => 'Approved']);
        $appointment->qrCode()->create(['token' => bin2hex(random_bytes(32)), 'is_active' => true]);
        return $appointment;
    }
    private function staff(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', 'staff')->value('id')]);
    }
    private function payload(GuidanceAppointment $appointment, string $type = 'screenshot'): array
    {
        return ['token' => $appointment->qrCode->token, 'event_id' => (string) Str::uuid(), 'incident_type' => $type];
    }

    public function test_incidents_require_started_browser_session_and_use_server_identity_and_counts(): void
    {
        $appointment = $this->appointment(); $token = $appointment->qrCode->token;
        $payload = $this->payload($appointment);
        $this->postJson(route('guidance.log-strike'), $payload)->assertForbidden();
        $this->postJson(route('guidance.start', $token))->assertOk();
        $this->postJson(route('guidance.log-strike'), $this->payload($this->appointment()))->assertForbidden();
        $this->postJson(route('guidance.log-strike'), $payload + ['strike_count' => 900, 'student_name' => 'Fake'])->assertOk()->assertJsonPath('strike_count', 1);
        $this->postJson(route('guidance.log-strike'), $payload)->assertOk()->assertJsonPath('strike_count', 1);
        $this->postJson(route('guidance.log-strike'), $this->payload($appointment, 'app_switch'))->assertOk()->assertJsonPath('strike_count', 2);
        $this->postJson(route('guidance.log-strike'), $this->payload($appointment, 'unknown'))->assertUnprocessable();
        $this->assertDatabaseCount('guidance_security_incidents', 2);
        $this->assertSame(2, $appointment->fresh()->strike_count);
        $this->getJson(route('staff.guidance-appointments.security-incidents'))->assertUnauthorized();
        $this->actingAs($this->staff());
        $feed = $this->getJson(route('staff.guidance-appointments.security-incidents'))->assertOk();
        $feed->assertJsonPath('incidents.0.student_name', 'SANTOS, MARIA')->assertJsonPath('incidents.0.student_id', '2026-001')->assertJsonPath('incidents.0.strike_count', 2);
        $feed->assertJsonMissing(['token' => $token]);
        $appointment->update(['test_category' => 'psychological']);
        $this->getJson(route('staff.guidance-appointments.security-incidents', ['module' => 'psychological']))->assertOk()->assertJsonCount(2, 'incidents');
        $this->getJson(route('staff.guidance-appointments.security-incidents', ['module' => 'career']))->assertOk()->assertJsonCount(0, 'incidents');
        $this->getJson(route('staff.guidance-appointments.security-incidents', ['after' => $feed->json('cursor')]))->assertOk()->assertJsonCount(0, 'incidents');
    }

    public function test_termination_is_authorized_idempotent_and_finalizes_only_saved_answers(): void
    {
        $appointment = $this->appointment(); $token = $appointment->qrCode->token;
        $this->postJson(route('guidance.start', $token))->assertOk();
        $this->postJson(route('guidance.progress', $token), ['answers' => [1 => 2]])->assertOk();
        $this->postJson(route('guidance.log-strike'), $this->payload($appointment, 'back_navigation'))->assertOk();
        $url = route('staff.guidance-appointments.terminate', $appointment);
        $this->postJson($url, ['reason' => 'Repeated app switching'])->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role_id' => null]))->postJson($url, ['reason' => 'Invalid role'])->assertForbidden();
        $staff = $this->staff(); $this->actingAs($staff);
        $this->postJson($url, [])->assertUnprocessable();
        $this->postJson($url, ['reason' => 'Repeated app switching'])->assertOk()->assertJsonPath('status', 'Completed');
        $this->postJson($url, ['reason' => 'Retry must not replace reason'])->assertOk();
        $appointment->refresh();
        $this->assertSame($staff->id, $appointment->terminated_by);
        $this->assertSame('Repeated app switching', $appointment->termination_reason);
        $this->assertSame([1 => 2], $appointment->response->answers);
        $this->assertSame(1, $appointment->response->score_summary['strike_count']);
        $this->assertArrayNotHasKey('scores', $appointment->response->score_summary);
        $this->assertFalse($appointment->qrCode->is_active);
        $this->assertDatabaseCount('guidance_test_responses', 1);
        $this->getJson(route('guidance.state', $token))->assertGone();
        $this->postJson(route('guidance.log-strike'), $this->payload($appointment))->assertGone();
        $this->postJson(route('guidance.submit', $token), ['answers' => array_fill(1, 7, 3)])->assertGone();
        $this->get(route('guidance.complete', $token))->assertOk()->assertSee('Assessment ended by proctor');
    }

    public function test_normal_completion_and_expiry_unlock_without_fabricating_incidents(): void
    {
        $appointment = $this->appointment(); $token = $appointment->qrCode->token;
        $this->get(route('guidance.complete', $token))->assertForbidden();
        $this->postJson(route('guidance.start', $token))->assertOk();
        $this->get(route('guidance.complete', $token))->assertConflict();
        $this->travel(11)->minutes();
        app(GuidanceAssessmentSessionService::class)->expireDue();
        $this->get(route('guidance.complete', $token))->assertOk()->assertSee('Assessment completed');
        $this->assertDatabaseCount('guidance_security_incidents', 0);
        $this->assertNull($appointment->fresh()->terminated_at);
    }

    public function test_strike_at_section_boundary_is_recorded_without_extending_timer(): void
    {
        $this->travelTo(now()->startOfSecond());
        $appointment = $this->appointment();
        $appointment->update(['test_type' => 'dass21', 'test_types' => ['dass21', 'phq9', 'gad7']]);
        $this->postJson(route('guidance.start', $appointment->qrCode->token))->assertOk();
        $this->travel(10)->minutes();
        $this->postJson(route('guidance.log-strike'), $this->payload($appointment))->assertOk()->assertJsonPath('strike_count', 1);
        $this->assertSame(1, $appointment->fresh()->section_index);
        $this->assertEquals(600, now()->diffInSeconds($appointment->fresh()->expires_at));
    }

    public function test_three_strikes_automatically_terminate_and_log_to_security_logs_table(): void
    {
        $appointment = $this->appointment();
        $token = $appointment->qrCode->token;
        $this->postJson(route('guidance.start', $token))->assertOk();
        $this->postJson(route('guidance.progress', $token), ['answers' => [1 => 2]])->assertOk();

        // Strike 1
        $this->postJson(route('guidance.log-strike'), $this->payload($appointment, 'back_navigation'))
            ->assertOk()->assertJsonPath('strike_count', 1)->assertJsonPath('status', 'In-Progress');

        // Strike 2
        $this->postJson(route('guidance.log-strike'), $this->payload($appointment, 'screenshot'))
            ->assertOk()->assertJsonPath('strike_count', 2)->assertJsonPath('status', 'In-Progress');

        // Strike 3 triggers auto-termination
        $response = $this->postJson(route('guidance.log-strike'), $this->payload($appointment, 'app_switch'))
            ->assertOk()->assertJsonPath('strike_count', 3)->assertJsonPath('status', 'Completed');

        $this->assertTrue($response->json('terminated'));
        $this->assertSame('Terminated - Violation', $response->json('termination_reason'));

        $appointment->refresh();
        $this->assertSame('Completed', $appointment->status);
        $this->assertSame('Terminated - Violation', $appointment->termination_reason);
        $this->assertNotNull($appointment->terminated_at);
        $this->assertFalse($appointment->qrCode->fresh()->is_active);

        $this->assertDatabaseCount('guidance_security_incidents', 3);
        $this->assertDatabaseCount('guidance_test_security_logs', 3);
        $this->assertDatabaseHas('guidance_test_security_logs', [
            'guidance_appointment_id' => $appointment->getKey(),
            'incident_type' => 'back_navigation',
            'strike_number' => 1,
        ]);
        $this->assertDatabaseHas('guidance_test_security_logs', [
            'guidance_appointment_id' => $appointment->getKey(),
            'incident_type' => 'screenshot',
            'strike_number' => 2,
        ]);
        $this->assertDatabaseHas('guidance_test_security_logs', [
            'guidance_appointment_id' => $appointment->getKey(),
            'incident_type' => 'app_switch',
            'strike_number' => 3,
        ]);
    }
}
