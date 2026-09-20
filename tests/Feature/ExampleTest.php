<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\AnswerKey;
use App\Models\AnswerSheet;
use App\Models\SessionApplicant;
use App\Models\TestSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_staff_dashboard_requires_authenticated_staff(): void
    {
        $response = $this->get('/admin/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_seeded_admin_can_access_dashboard_and_checkin_route_exists(): void
    {
        $this->seed();

        $user = User::where('email', 'admin@psu-scc.test')->firstOrFail();
        $session = TestSession::firstOrFail();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk();

        $this->get(route('checkin.show', $session->qr_token))
            ->assertOk();
    }

    public function test_applicant_can_check_in_to_an_open_session(): void
    {
        Carbon::setTestNow('2026-03-31 08:30:00');
        $this->seed();

        $session = TestSession::firstOrFail();
        $assignment = SessionApplicant::with('applicant')->firstOrFail();

        $response = $this->post(route('checkin.verify', $session->qr_token), [
            'application_number' => $assignment->applicant->application_number,
            'full_name' => $assignment->applicant->full_name,
            'gender' => $assignment->applicant->gender,
        ]);

        $response->assertRedirect(route('checkin.waiting'));

        $this->assertDatabaseHas('session_applicants', [
            'id' => $assignment->id,
            'is_present' => true,
        ]);

        $this->assertDatabaseHas('attendance_logs', [
            'test_session_id' => $session->id,
            'applicant_id' => $assignment->applicant_id,
        ]);

        Carbon::setTestNow();
    }

    public function test_duplicate_check_in_is_blocked(): void
    {
        Carbon::setTestNow('2026-03-31 08:30:00');
        $this->seed();

        $session = TestSession::firstOrFail();
        $assignment = SessionApplicant::with('applicant')->firstOrFail();

        AttendanceLog::create([
            'test_session_id' => $session->id,
            'applicant_id' => $assignment->applicant_id,
            'scanned_at' => now(),
            'verified_name' => $assignment->applicant->full_name,
            'verified_gender' => $assignment->applicant->gender,
            'verified_application_number' => $assignment->applicant->application_number,
            'ip_address' => '127.0.0.1',
        ]);

        $assignment->update([
            'is_present' => true,
            'scanned_at' => now(),
        ]);

        $response = $this->from(route('checkin.show', $session->qr_token))
            ->post(route('checkin.verify', $session->qr_token), [
                'application_number' => $assignment->applicant->application_number,
                'full_name' => $assignment->applicant->full_name,
                'gender' => $assignment->applicant->gender,
            ]);

        $response->assertRedirect(route('checkin.show', $session->qr_token));
        $response->assertSessionHasErrors('application_number');

        Carbon::setTestNow();
    }

    public function test_check_in_is_blocked_outside_session_window(): void
    {
        Carbon::setTestNow('2026-03-31 11:00:00');
        $this->seed();

        $session = TestSession::firstOrFail();
        $assignment = SessionApplicant::with('applicant')->firstOrFail();
        $session->update([
            'exam_date' => '2026-03-31',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ]);

        $response = $this->from(route('checkin.show', $session->qr_token))
            ->post(route('checkin.verify', $session->qr_token), [
                'application_number' => $assignment->applicant->application_number,
                'full_name' => $assignment->applicant->full_name,
                'gender' => $assignment->applicant->gender,
            ]);

        $response->assertRedirect(route('checkin.show', $session->qr_token));
        $response->assertSessionHasErrors('application_number');

        Carbon::setTestNow();
    }

    public function test_admin_can_import_applicants_from_csv(): void
    {
        $this->seed();

        $user = User::where('email', 'admin@psu-scc.test')->firstOrFail();
        $csv = implode("\n", [
            'application_number,first_name,middle_name,last_name,gender,email,contact_number,status',
            'PSUSCC-2026-1001,Carla,Diaz,Fernandez,Female,carla@example.com,09170000001,approved',
        ]);

        $path = storage_path('framework/testing/import-applicants.csv');
        file_put_contents($path, $csv);

        $this->actingAs($user)
            ->post(route('admin.applicants.import'), [
                'import_file' => new \Illuminate\Http\UploadedFile($path, 'import-applicants.csv', 'text/csv', null, true),
            ])
            ->assertRedirect(route('admin.applicants.index'));

        $this->assertDatabaseHas('applicants', [
            'application_number' => 'PSUSCC-2026-1001',
            'first_name' => 'Carla',
            'last_name' => 'Fernandez',
        ]);
    }

    public function test_answer_key_results_page_is_accessible_to_admin(): void
    {
        $this->seed();

        $user = User::where('email', 'admin@psu-scc.test')->firstOrFail();
        $session = TestSession::firstOrFail();
        $sheet = AnswerSheet::firstOrFail();

        $payload = ['submitted_at' => now(), 'is_locked' => true];
        foreach (AnswerSheet::questionColumns() as $index => $column) {
            $payload[$column] = ['A', 'B', 'C', 'D'][$index % 4];
        }
        $sheet->update($payload);

        $this->actingAs($user)
            ->get(route('admin.sessions.results.index', $session))
            ->assertOk()
            ->assertSee('Exam Results');
    }

    public function test_admin_can_search_exam_results(): void
    {
        $this->seed();

        $user = User::where('email', 'admin@psu-scc.test')->firstOrFail();
        $session = TestSession::firstOrFail();
        $sheet = AnswerSheet::firstOrFail();

        $sheet->applicant->update([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'application_number' => 'PSUSCC-RESULT-1001',
        ]);

        $sheet->update([
            'submitted_at' => now(),
            'is_locked' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.sessions.results.index', [
                'session' => $session,
                'search' => 'Maria',
                'sort' => 'name_asc',
            ]))
            ->assertOk()
            ->assertSee('SANTOS, MARIA')
            ->assertSee('PSUSCC-RESULT-1001');

        $this->actingAs($user)
            ->get(route('admin.sessions.results.index', [
                'session' => $session,
                'search' => 'NotExistingName',
            ]))
            ->assertOk()
            ->assertSee('No results matched your search or filter.');
    }

    public function test_admin_can_view_detailed_answer_sheet_after_test_has_finished(): void
    {
        $this->seed();

        $user = User::where('email', 'admin@psu-scc.test')->firstOrFail();
        $session = TestSession::firstOrFail();
        $sheet = AnswerSheet::firstOrFail();

        $session->update([
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'duration_minutes' => 40,
        ]);

        $sheet->update([
            'submitted_at' => now()->subMinutes(5),
            'is_locked' => true,
            'q1' => 'A',
            'q2' => 'B',
        ]);

        AnswerKey::create([
            'test_session_id' => $session->id,
            'passing_score' => 60,
            'q1' => 'A',
            'q2' => 'C',
        ]);

        $this->actingAs($user)
            ->get(route('admin.sessions.results.show', [$session, $sheet]))
            ->assertOk()
            ->assertSee('Applicant Test Details')
            ->assertSee('Answer Sheet')
            ->assertSee('Question 1')
            ->assertSee('Question 2');
    }

    public function test_admin_cannot_view_detailed_answer_sheet_before_test_has_finished(): void
    {
        Carbon::setTestNow('2026-03-31 08:10:00');
        $this->seed();

        $user = User::where('email', 'admin@psu-scc.test')->firstOrFail();
        $session = TestSession::firstOrFail();
        $sheet = AnswerSheet::firstOrFail();

        $session->update([
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(5),
            'duration_minutes' => 40,
        ]);

        $sheet->update([
            'submitted_at' => now(),
            'is_locked' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.sessions.results.show', [$session, $sheet]))
            ->assertRedirect(route('admin.sessions.results.index', $session));

        Carbon::setTestNow();
    }

    public function test_saved_answers_are_auto_finalized_when_the_session_time_ends(): void
    {
        Carbon::setTestNow('2026-03-31 08:05:00');
        $this->seed();

        $session = TestSession::firstOrFail();
        $assignment = SessionApplicant::firstOrFail();
        $user = User::where('email', 'admin@psu-scc.test')->firstOrFail();

        $session->update([
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(5),
            'duration_minutes' => 10,
        ]);

        $assignment->update([
            'is_present' => true,
            'scanned_at' => now(),
        ]);

        AnswerKey::create([
            'test_session_id' => $session->id,
            'passing_score' => 1,
            'q1' => 'A',
            'q2' => 'B',
        ]);

        $this->withSession(['session_applicant_id' => $assignment->id])
            ->post(route('answers.save-progress'), [
                'q1' => 'A',
                'q2' => 'C',
            ])
            ->assertOk();

        Carbon::setTestNow('2026-03-31 08:20:00');

        $this->actingAs($user)
            ->get(route('admin.sessions.results.index', $session))
            ->assertOk();

        $this->assertDatabaseHas('answer_sheets', [
            'test_session_id' => $session->id,
            'applicant_id' => $assignment->applicant_id,
            'is_locked' => true,
        ]);

        $answerSheet = AnswerSheet::where('test_session_id', $session->id)
            ->where('applicant_id', $assignment->applicant_id)
            ->firstOrFail();

        $this->assertDatabaseHas('answer_sheet_answers', [
            'answer_sheet_id' => $answerSheet->id,
            'question_number' => 1,
            'answer' => 'A',
        ]);

        $this->assertDatabaseHas('answer_sheet_answers', [
            'answer_sheet_id' => $answerSheet->id,
            'question_number' => 2,
            'answer' => 'C',
        ]);

        $this->assertNotNull(
            $answerSheet->submitted_at
        );

        Carbon::setTestNow();
    }

    public function test_answers_from_timeout_submission_are_saved_before_auto_locking(): void
    {
        Carbon::setTestNow('2026-03-31 08:20:00');
        $this->seed();

        $session = TestSession::firstOrFail();
        $assignment = SessionApplicant::firstOrFail();

        $session->update([
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(20),
            'duration_minutes' => 10,
        ]);

        $assignment->update([
            'is_present' => true,
            'scanned_at' => now()->subMinutes(20),
        ]);

        $this->withSession(['session_applicant_id' => $assignment->id])
            ->post(route('answers.submit'), [
                'q1' => 'B',
                'q2' => 'D',
            ])
            ->assertRedirect(route('answers.complete'));

        $this->assertDatabaseHas('answer_sheets', [
            'test_session_id' => $session->id,
            'applicant_id' => $assignment->applicant_id,
            'is_locked' => true,
        ]);

        $answerSheet = AnswerSheet::where('test_session_id', $session->id)
            ->where('applicant_id', $assignment->applicant_id)
            ->firstOrFail();

        $this->assertDatabaseHas('answer_sheet_answers', [
            'answer_sheet_id' => $answerSheet->id,
            'question_number' => 1,
            'answer' => 'B',
        ]);

        $this->assertDatabaseHas('answer_sheet_answers', [
            'answer_sheet_id' => $answerSheet->id,
            'question_number' => 2,
            'answer' => 'D',
        ]);

        $this->assertNotNull(
            $answerSheet->submitted_at
        );

        Carbon::setTestNow();
    }

    public function test_admin_can_delete_a_test_session_from_the_sessions_page(): void
    {
        $this->seed();

        $user = User::where('email', 'admin@psu-scc.test')->firstOrFail();
        $session = TestSession::firstOrFail();

        $this->actingAs($user)
            ->delete(route('admin.sessions.destroy', $session))
            ->assertRedirect(route('admin.sessions.index'));

        $this->assertDatabaseMissing('test_sessions', [
            'id' => $session->id,
        ]);

        $this->assertDatabaseMissing('session_applicants', [
            'test_session_id' => $session->id,
        ]);

        $this->assertDatabaseMissing('answer_sheets', [
            'test_session_id' => $session->id,
        ]);
    }
}
