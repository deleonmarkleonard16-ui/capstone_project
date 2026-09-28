<?php

namespace Tests\Feature;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Models\AdmissionSession;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdmissionCheckinTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]));
    }

    public function test_sessions_page_has_session_qr_button_and_modal(): void
    {
        $this->login('admin');
        $cycle = AdmissionCycle::create([
            'name'          => '2026-2027 Summer',
            'academic_year' => '2026-2027',
            'is_active'     => true,
        ]);

        $session = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id,
            'session_name'       => 'Session A - Batch 1',
            'start_time'         => now()->addHour(),
            'start_number'       => 1,
            'end_number'         => 50,
            'room'               => 'PSU-SC Covered Court',
            'qr_token'           => Str::random(64),
            'status'             => 'In-Progress',
        ]);

        $response = $this->get('/admin/admission/sessions');
        $response->assertOk();
        $response->assertSee('Session QR');
        $response->assertSee('sessionQrModal' . $session->id);
        $response->assertSee('Display Fullscreen / Project');
        $response->assertSee('/admission/checkin/' . $session->qr_token);
    }

    public function test_checkin_page_displays_attendance_verification_form(): void
    {
        $cycle = AdmissionCycle::create([
            'name'          => '2026-2027 Summer',
            'academic_year' => '2026-2027',
            'is_active'     => true,
        ]);

        $token = Str::random(64);
        $session = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id,
            'session_name'       => 'Session A - Batch 1',
            'start_time'         => now(),
            'start_number'       => 1,
            'end_number'         => 50,
            'room'               => 'PSU-SC Covered Court',
            'qr_token'           => $token,
            'status'             => 'In-Progress',
        ]);

        $response = $this->get('/admission/checkin/' . $token);
        $response->assertOk();
        $response->assertSee('Session A - Batch 1');
        $response->assertSee('PSU-SC Covered Court');
        $response->assertSee('Attendance Verification');
        $response->assertSee('First Name');
        $response->assertSee('Middle Name');
        $response->assertSee('Last Name');
    }

    public function test_examinee_checkin_verifies_roster_and_redirects_to_digital_lockdown_exam(): void
    {
        $cycle = AdmissionCycle::create([
            'name'          => '2026-2027 Summer',
            'academic_year' => '2026-2027',
            'is_active'     => true,
        ]);

        $sessionToken = Str::random(64);
        $session = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id,
            'session_name'       => 'Session A - Batch 1',
            'start_time'         => now(),
            'start_number'       => 1,
            'end_number'         => 50,
            'room'               => 'PSU-SC Covered Court',
            'qr_token'           => $sessionToken,
            'status'             => 'In-Progress',
        ]);

        $applicant = AdmissionApplicant::create([
            'admission_cycle_id'   => $cycle->id,
            'admission_session_id' => $session->id,
            'application_number'   => 'CAT-26-0001',
            'first_name'           => 'Maria Clara',
            'middle_name'          => 'Santos',
            'last_name'            => 'Dela Cruz',
            'course_choice'        => 'BSIT',
            'gwa'                  => 90,
        ]);

        // Submit verification form
        $response = $this->post('/admission/checkin/' . $sessionToken, [
            'first_name'  => 'Maria Clara',
            'middle_name' => 'Santos',
            'last_name'   => 'Dela Cruz',
        ]);

        $applicant->refresh();
        $this->assertNotEmpty($applicant->exam_token);
        $response->assertRedirect('/admission/take/' . $applicant->exam_token);
    }

    public function test_examinee_checkin_fails_if_name_not_in_roster(): void
    {
        $cycle = AdmissionCycle::create([
            'name'          => '2026-2027 Summer',
            'academic_year' => '2026-2027',
            'is_active'     => true,
        ]);

        $sessionToken = Str::random(64);
        $session = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id,
            'session_name'       => 'Session A - Batch 1',
            'start_time'         => now(),
            'start_number'       => 1,
            'end_number'         => 50,
            'room'               => 'PSU-SC Covered Court',
            'qr_token'           => $sessionToken,
            'status'             => 'In-Progress',
        ]);

        AdmissionApplicant::create([
            'admission_cycle_id'   => $cycle->id,
            'admission_session_id' => $session->id,
            'application_number'   => 'CAT-26-0001',
            'first_name'           => 'Maria Clara',
            'last_name'            => 'Dela Cruz',
            'course_choice'        => 'BSIT',
            'gwa'                  => 90,
        ]);

        // Invalid name
        $response = $this->from('/admission/checkin/' . $sessionToken)->post('/admission/checkin/' . $sessionToken, [
            'first_name' => 'Unknown',
            'last_name'  => 'Person',
        ]);

        $response->assertRedirect('/admission/checkin/' . $sessionToken);
        $response->assertSessionHasErrors('last_name');
    }

    public function test_examinee_checkin_fails_if_already_submitted(): void
    {
        $cycle = AdmissionCycle::create([
            'name'          => '2026-2027 Summer',
            'academic_year' => '2026-2027',
            'is_active'     => true,
        ]);

        $sessionToken = Str::random(64);
        $session = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id,
            'session_name'       => 'Session A - Batch 1',
            'start_time'         => now(),
            'start_number'       => 1,
            'end_number'         => 50,
            'room'               => 'PSU-SC Covered Court',
            'qr_token'           => $sessionToken,
            'status'             => 'In-Progress',
        ]);

        $applicant = AdmissionApplicant::create([
            'admission_cycle_id'   => $cycle->id,
            'admission_session_id' => $session->id,
            'application_number'   => 'CAT-26-0001',
            'first_name'           => 'Maria Clara',
            'last_name'            => 'Dela Cruz',
            'course_choice'        => 'BSIT',
            'gwa'                  => 90,
        ]);
        $applicant->forceFill(['submitted_at' => now()])->save();

        $response = $this->from('/admission/checkin/' . $sessionToken)->post('/admission/checkin/' . $sessionToken, [
            'first_name' => 'Maria Clara',
            'last_name'  => 'Dela Cruz',
        ]);

        $response->assertRedirect('/admission/checkin/' . $sessionToken);
        $response->assertSessionHasErrors('last_name');
    }
}
