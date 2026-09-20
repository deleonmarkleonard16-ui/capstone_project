<?php

namespace Tests\Feature;

use App\Models\AdmissionEvaluation;
use App\Models\Applicant;
use App\Models\GuidanceTestSubmission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuidanceAndAdmissionRestorationTest extends TestCase
{
    use RefreshDatabase;

    private function createStaff(string $roleSlug = 'staff'): User
    {
        $user = new User();
        $user->id = 50;
        $user->name = 'Guidance Officer';
        $user->role = $roleSlug;
        $role = new Role();
        $role->slug = $roleSlug;
        $user->setRelation('roleLookup', $role);
        return $user;
    }

    public function test_request_automatically_expires_and_becomes_void_after_5_days(): void
    {
        // 1. Submit request
        $entry = ServiceRequest::create([
            'service' => 'testing',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'student_number' => '2026-00101',
            'student_status' => 'student',
            'course' => 'BSIT',
            'purpose' => 'Field Study Evaluation',
            'tests' => ['psychological'],
            'reference' => 'TR-TEST0001',
            'status' => 'pending',
            'expires_at' => now()->addDays(5),
        ]);

        $this->assertFalse($entry->isVoid());
        $this->assertSame('pending', $entry->status);

        // 2. Fast forward 6 days
        $this->travel(6)->days();

        // Check single model expiration
        $entry->checkAndApplyExpiration();
        $this->assertSame('void', $entry->fresh()->status);
        $this->assertTrue($entry->fresh()->isVoid());
        $this->assertNotNull($entry->fresh()->archived_at);

        // Check bulk expiration helper
        $entry2 = ServiceRequest::create([
            'service' => 'good-moral',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'student_number' => '2026-00102',
            'student_status' => 'alumni',
            'course' => 'BSED-FIL',
            'purpose' => 'Employment',
            'reference' => 'GM-TEST0002',
            'status' => 'pending',
            'expires_at' => now()->subDay(), // already expired
        ]);

        ServiceRequest::expirePendingRequests();
        $this->assertSame('void', $entry2->fresh()->status);

        $this->travelBack();
    }

    public function test_legacy_reference_cannot_bypass_verified_qr_workflow(): void
    {
        $this->get(route('guidance.test.show', ['reference' => 'TR-ELENA01', 'test' => 'dass21']))
            ->assertRedirect('/portal?service=good-moral#track');
        $this->post(route('guidance.test.submit', ['reference' => 'TR-ELENA01', 'test' => 'dass21']), [
            'answers' => array_fill(1, 21, 2),
        ])->assertGone();
        $this->assertDatabaseCount('guidance_test_submissions', 0);
    }
    public function test_good_moral_and_exit_form_inbox_views_render_for_staff(): void
    {
        $staff = $this->createStaff();

        ServiceRequest::create([
            'service' => 'good-moral',
            'first_name' => 'Mark',
            'last_name' => 'Bautista',
            'student_number' => '2026-00104',
            'student_status' => 'student',
            'course' => 'BSBA-HRDM',
            'purpose' => 'Scholarship Application',
            'reference' => 'GM-MARK01',
            'status' => 'ready',
            'expires_at' => now()->addDays(5),
        ]);

        $this->actingAs($staff)
            ->get(route('staff.good-moral'))
            ->assertOk()
            ->assertSee('Good Moral Certificate Requests')
            ->assertSee('MARK BAUTISTA')
            ->assertSee('Print Certificate');

        ServiceRequest::create([
            'service' => 'exit-form',
            'first_name' => 'Sarah',
            'last_name' => 'Lim',
            'student_number' => '2026-00105',
            'student_status' => 'alumni',
            'course' => 'BSIT',
            'purpose' => 'Graduation / Career Transfer',
            'reference' => 'EF-SARAH01',
            'status' => 'ready',
            'expires_at' => now()->addDays(5),
        ]);

        $this->actingAs($staff)
            ->get(route('staff.exit-form'))
            ->assertOk()
            ->assertSee('Exit Form / Clearance Requests')
            ->assertSee('SARAH LIM')
            ->assertSee('Print Exit Clearance Slip');
    }

    public function test_admission_evaluation_computes_total_marks_and_ranks_passers(): void
    {
        $admin = $this->createStaff('admin');

        $session = TestSession::create([
            'title' => 'PSU-CAT Batch 1',
            'exam_date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '11:00',
            'room' => 'Room 101',
            'qr_token' => 'token-batch-1',
            'status' => 'open',
        ]);

        $app1 = Applicant::create([
            'first_name' => 'Alpha',
            'last_name' => 'Student',
            'email' => 'alpha@example.com',
            'application_number' => 'APP-001',
            'gender' => 'female',
            'contact_number' => '09123456789',
            'status' => 'registered',
        ]);

        $app2 = Applicant::create([
            'first_name' => 'Beta',
            'last_name' => 'Student',
            'email' => 'beta@example.com',
            'application_number' => 'APP-002',
            'gender' => 'male',
            'contact_number' => '09123456788',
            'status' => 'registered',
        ]);

        // Evaluate Alpha: Exam 90, GWA 95, Interview 85
        // 90 * 0.60 = 54; 95 * 0.20 = 19; 85 * 0.20 = 17 => Total: 90
        $this->actingAs($admin)->post(route('admin.admission-evaluation.store'), [
            'test_session_id' => $session->id,
            'applicant_id' => $app1->id,
            'exam_score' => 90,
            'gwa' => 95,
            'interview_score' => 85,
        ])->assertSessionHasNoErrors();

        // Evaluate Beta: Exam 95, GWA 98, Interview 95
        // 95 * 0.60 = 57; 98 * 0.20 = 19.6; 95 * 0.20 = 19 => Total: 95.6
        $this->actingAs($admin)->post(route('admin.admission-evaluation.store'), [
            'test_session_id' => $session->id,
            'applicant_id' => $app2->id,
            'exam_score' => 95,
            'gwa' => 98,
            'interview_score' => 95,
        ])->assertSessionHasNoErrors();

        $evalAlpha = AdmissionEvaluation::where('applicant_id', $app1->id)->firstOrFail();
        $evalBeta = AdmissionEvaluation::where('applicant_id', $app2->id)->firstOrFail();

        $this->assertEquals(90.0, (float) $evalAlpha->total_marks);
        $this->assertEquals(95.6, (float) $evalBeta->total_marks);

        // Beta has higher marks -> Rank 1, Alpha -> Rank 2
        $this->assertSame(1, $evalBeta->rank);
        $this->assertSame(2, $evalAlpha->rank);

        // Check official list of passers
        $this->actingAs($admin)
            ->get(route('admin.admission-evaluation.passers', ['session_id' => $session->id, 'cutoff' => 1]))
            ->assertOk()
            ->assertSee('Student, Beta')
            ->assertDontSee('Student, Alpha');
    }
}
