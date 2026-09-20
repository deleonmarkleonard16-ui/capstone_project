<?php

namespace Tests\Feature;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]));
    }

    public function test_staff_is_forbidden_and_cycle_is_required_for_masterlist(): void
    {
        $this->login('staff');
        $this->get('/admin/admission')->assertForbidden();
        $this->get('/admin/admission/masterlist')->assertForbidden();
        $this->post('/admin/admission/answer-key', [])->assertForbidden();
        $this->login('admin');
        $this->get('/admin/admission')->assertOk();
        $this->get('/admin/admission/masterlist')->assertStatus(409);
    }

    public function test_exam_score_is_computed_and_cannot_be_overwritten_by_edit(): void
    {
        $this->login('admin');
        $cycle = AdmissionCycle::create(['name' => '2025', 'academic_year' => '2025-2026', 'is_active' => true]);
        $applicant = AdmissionApplicant::create(['admission_cycle_id' => $cycle->id, 'application_number' => 'A-001', 'first_name' => 'Ana', 'last_name' => 'Reyes', 'course_choice' => 'BSIT', 'gwa' => 90, 'interview_score' => 90]);
        $this->get('/admin/admission/masterlist')->assertOk()->assertSee('A-001');
        $this->get('/admin/admission/applicants/'.$applicant->id.'/paper')->assertOk()->assertSee('Answer Sheet');
        $this->get('/admin/admission/applicants/'.$applicant->id.'/encode')->assertOk()->assertSee('Item 80');
        $this->post('/admin/admission/quotas', ['course_code' => 'BSIT', 'seats' => 1])->assertRedirect();
        $this->post('/admin/admission/answer-key', ['answers' => array_fill_keys(range(1, 80), 'A')])->assertRedirect();
        $this->post('/admin/admission/applicants/'.$applicant->id.'/encode', ['answers' => array_fill_keys(range(1, 80), 'A')])->assertRedirect();
        $this->assertEquals(80, $applicant->fresh()->exam_score);
        $this->assertSame('Qualified', $applicant->fresh()->qualification_status);
        $this->post('/admin/admission/applicants/'.$applicant->id, ['application_number' => 'A-001', 'first_name' => 'Ana', 'last_name' => 'Reyes', 'course_choice' => 'BSIT', 'gwa' => 90, 'interview_score' => 80, 'exam_score' => 0])->assertRedirect();
        $this->assertEquals(80, $applicant->fresh()->exam_score);
        $this->get('/admin/admission/report?type=qualified&format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/admin/admission/report?type=qualified&format=docx')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_third_digital_security_strike_submits_exam_and_logs_incidents(): void
    {
        $cycle = AdmissionCycle::create(['name' => '2026', 'academic_year' => '2026-2027', 'is_active' => true]);
        $applicant = AdmissionApplicant::create(['admission_cycle_id' => $cycle->id, 'application_number' => 'A-002', 'first_name' => 'Ben', 'last_name' => 'Cruz', 'course_choice' => 'BSIT', 'exam_token' => str_repeat('a', 64)]);
        foreach (range(1, 80) as $item) DB::table('admission_answer_keys')->insert(['admission_cycle_id' => $cycle->id, 'item_number' => $item, 'correct_answer' => 'A', 'created_at' => now(), 'updated_at' => now()]);
        $this->get('/admission/take/'.str_repeat('a', 64))->assertOk();
        foreach (range(1, 3) as $strike) $this->postJson('/admission/take/'.str_repeat('a', 64).'/strike', ['incident_type' => 'back_button', 'answers' => [1 => 'A']])->assertOk()->assertJsonPath('strikes', $strike);
        $this->assertNotNull($applicant->fresh()->submitted_at);
        $this->assertSame(3, DB::table('guidance_test_security_logs')->where('applicant_id', $applicant->id)->count());
        $this->get('/admission/take/'.str_repeat('a', 64))->assertNotFound();
    }
}
