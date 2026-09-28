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

    public function test_session_page_exposes_embedded_omr_scanner_controls(): void
    {
        $this->login('admin');
        $cycle = AdmissionCycle::create([
            'name' => '2026 OMR', 'academic_year' => '2026-2027', 'is_active' => true,
            'status' => AdmissionCycle::STATUS_ACTIVE, 'total_items' => 80,
        ]);
        $session = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id, 'session_name' => 'OMR Session',
            'start_time' => now(), 'start_number' => 1, 'end_number' => 1,
            'qr_token' => Str::random(64), 'status' => AdmissionSession::STATUS_IN_PROGRESS,
        ]);
        AdmissionApplicant::create([
            'admission_cycle_id' => $cycle->id, 'admission_session_id' => $session->id,
            'application_number' => 'CAT-OMR-001', 'first_name' => 'Ana', 'last_name' => 'Cruz',
            'course_choice' => 'BSIT',
        ]);

        $this->get(route('admin.admission.sessions.show', $session))
            ->assertOk()
            ->assertSee('OMR Web Scanner')
            ->assertSee('Scan OMR')
            ->assertSee(route('admin.admission.sessions.scan-omr'), false);
    }

    public function test_session_omr_scan_scores_enrolled_examinee_and_rejects_other_session(): void
    {
        $this->login('admin');
        $cycle = AdmissionCycle::create([
            'name' => '2026 OMR', 'academic_year' => '2026-2027', 'is_active' => true,
            'status' => AdmissionCycle::STATUS_ACTIVE, 'total_items' => 80,
        ]);
        $session = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id, 'session_name' => 'OMR Session',
            'start_time' => now(), 'start_number' => 1, 'end_number' => 2,
            'qr_token' => Str::random(64), 'status' => AdmissionSession::STATUS_IN_PROGRESS,
        ]);
        $otherSession = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id, 'session_name' => 'Other Session',
            'start_time' => now(), 'start_number' => 3, 'end_number' => 3,
            'qr_token' => Str::random(64), 'status' => AdmissionSession::STATUS_IN_PROGRESS,
        ]);
        $applicant = AdmissionApplicant::create([
            'admission_cycle_id' => $cycle->id, 'admission_session_id' => $session->id,
            'application_number' => 'CAT-OMR-002', 'first_name' => 'Ben', 'last_name' => 'Reyes',
            'course_choice' => 'BSIT',
        ]);
        $outsider = AdmissionApplicant::create([
            'admission_cycle_id' => $cycle->id, 'admission_session_id' => $otherSession->id,
            'application_number' => 'CAT-OMR-003', 'first_name' => 'Cara', 'last_name' => 'Santos',
            'course_choice' => 'BSIT',
        ]);
        foreach (range(1, 80) as $item) {
            DB::table('admission_answer_keys')->insert([
                'admission_cycle_id' => $cycle->id, 'item_number' => $item,
                'correct_answer' => 'A', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $payload = ['session_id' => $session->id, 'raw_choices' => array_fill_keys(range(1, 80), 'A')];
        $this->postJson(route('admin.admission.sessions.scan-omr'), $payload + ['application_number' => $outsider->application_number])
            ->assertUnprocessable();

        $this->postJson(route('admin.admission.sessions.scan-omr'), $payload + ['application_number' => $applicant->application_number])
            ->assertOk()
            ->assertJsonPath('applicant.exam_status', 'Submitted')
            ->assertJsonPath('applicant.raw_score', 80)
            ->assertJsonPath('applicant.stanine_rating', 9);

        $this->assertNotNull($applicant->fresh()->submitted_at);
        $this->assertSame(80.0, (float) $applicant->fresh()->exam_score);
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

    public function test_checkin_page_displays_applicant_verification_form(): void
    {
        $cycle = AdmissionCycle::create([
            'name'          => '2026-2027 Summer',
            'academic_year' => '2026-2027',
            'is_active'     => true,
        ]);

        $token = Str::random(64);
        $session = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id,
            'session_name'       => 'Second Batch Admission',
            'start_time'         => now(),
            'start_number'       => 1,
            'end_number'         => 50,
            'room'               => 'Open Court',
            'qr_token'           => $token,
            'status'             => 'In-Progress',
        ]);

        $response = $this->get('/admission/checkin/' . $token);
        $response->assertOk();
        $response->assertSee('Second Batch Admission');
        $response->assertSee('Open Court');
        $response->assertSee('APPLICANT VERIFICATION');
        $response->assertSee('First Name');
        $response->assertSee('Middle Name');
        $response->assertSee('Last Name');
        $response->assertSee('Verify and Check In');
    }

    public function test_examinee_checkin_redirects_to_exam_when_in_progress(): void
    {
        $cycle = AdmissionCycle::create([
            'name'          => '2026-2027 Summer',
            'academic_year' => '2026-2027',
            'is_active'     => true,
        ]);

        $sessionToken = Str::random(64);
        $session = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id,
            'session_name'       => 'First Batch - Session A',
            'start_time'         => now(),
            'start_number'       => 1,
            'end_number'         => 50,
            'room'               => 'PSU-SC COVERED COURT',
            'qr_token'           => $sessionToken,
            'status'             => 'In-Progress',
        ]);

        $applicant = AdmissionApplicant::create([
            'admission_cycle_id'   => $cycle->id,
            'admission_session_id' => $session->id,
            'application_number'   => 'CAT-26-0001',
            'first_name'           => 'Mark Leonard',
            'middle_name'          => 'Abalos',
            'last_name'            => 'De Leon',
            'course_choice'        => 'BSIT',
            'gwa'                  => 90,
        ]);

        $response = $this->post('/admission/checkin/' . $sessionToken, [
            'first_name'  => 'Mark Leonard',
            'middle_name' => 'Abalos',
            'last_name'   => 'De Leon',
        ]);

        $applicant->refresh();
        $this->assertNotEmpty($applicant->exam_token);
        $response->assertRedirect('/admission/take/' . $applicant->exam_token);
    }

    public function test_examinee_checkin_redirects_to_waiting_room_when_scheduled(): void
    {
        $cycle = AdmissionCycle::create([
            'name'          => '2026-2027 Summer',
            'academic_year' => '2026-2027',
            'is_active'     => true,
        ]);

        $sessionToken = Str::random(64);
        $session = AdmissionSession::create([
            'admission_cycle_id' => $cycle->id,
            'session_name'       => 'First Batch - Session A',
            'start_time'         => now()->addMinutes(15),
            'start_number'       => 1,
            'end_number'         => 50,
            'room'               => 'PSU-SC COVERED COURT',
            'qr_token'           => $sessionToken,
            'status'             => 'Scheduled',
        ]);

        $applicant = AdmissionApplicant::create([
            'admission_cycle_id'   => $cycle->id,
            'admission_session_id' => $session->id,
            'application_number'   => 'CAT-26-0001',
            'first_name'           => 'Mark Leonard',
            'middle_name'          => 'Abalos',
            'last_name'            => 'De Leon',
            'course_choice'        => 'BSIT',
            'gwa'                  => 90,
        ]);

        $response = $this->post('/admission/checkin/' . $sessionToken, [
            'first_name'  => 'Mark Leonard',
            'middle_name' => 'Abalos',
            'last_name'   => 'De Leon',
        ]);

        $response->assertRedirect('/admission/checkin/' . $sessionToken . '/waiting');

        $waitingResponse = $this->get('/admission/checkin/' . $sessionToken . '/waiting');
        $waitingResponse->assertOk();
        $waitingResponse->assertSee('CHECKED IN');
        $waitingResponse->assertSee('MARK LEONARD');
        $waitingResponse->assertSee('First Batch - Session A');
        $waitingResponse->assertSee('Waiting for the staff to click');
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

    public function test_checkin_page_renders_in_clean_guest_layout_even_if_admin_is_logged_in(): void
    {
        $this->login('admin');
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

        $response = $this->get('/admission/checkin/' . $sessionToken);
        $response->assertOk();
        $response->assertDontSee('SIGNED IN AS');
        $response->assertDontSee('MAIN NAVIGATION');
        $response->assertDontSee('appSidebar');
        $response->assertSee('top-brand guest');
    }

    public function test_examinee_can_take_and_submit_exam_without_preseeded_answer_keys(): void
    {
        $cycle = AdmissionCycle::create([
            'name'          => '2026-2027 Summer',
            'academic_year' => '2026-2027',
            'is_active'     => true,
            'total_items'   => 80,
        ]);

        $examToken = Str::random(64);
        $applicant = AdmissionApplicant::create([
            'admission_cycle_id'   => $cycle->id,
            'application_number'   => 'CAT-26-0005',
            'first_name'           => 'Juan',
            'last_name'            => 'Luna',
            'course_choice'        => 'BSIT',
            'gwa'                  => 88,
            'exam_token'           => $examToken,
        ]);

        // Ensure no keys exist yet
        DB::table('admission_answer_keys')->where('admission_cycle_id', $cycle->id)->delete();

        // Must load take exam page with 200 OK without 409 error
        $response = $this->get('/admission/take/' . $examToken);
        $response->assertOk();
        $response->assertSee('PSU-CAT Digital Exam');

        // Submit exam answers
        $answers = array_fill_keys(range(1, 80), 'A');
        $submitResponse = $this->post('/admission/take/' . $examToken, [
            'answers' => $answers,
        ]);

        $submitResponse->assertOk();
        $applicant->refresh();
        $this->assertNotNull($applicant->submitted_at);
        $this->assertEquals(80, $applicant->exam_score);
    }
}


