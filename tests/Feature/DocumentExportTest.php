<?php

namespace Tests\Feature;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Models\AdmissionSession;
use App\Models\GuidanceAppointment;
use App\Models\GuidanceTestBatch;
use App\Models\GuidanceTestSubmission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\DocumentExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentExportTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role = 'admin'): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]));
    }

    private function cycle(): AdmissionCycle
    {
        return AdmissionCycle::create(['name' => '2026 Admission', 'academic_year' => '2026-2027', 'is_active' => false, 'is_archived' => true]);
    }

    private function applicant(AdmissionCycle $cycle, array $attributes = []): AdmissionApplicant
    {
        $applicant = new AdmissionApplicant();
        $applicant->forceFill(array_merge(['admission_cycle_id' => $cycle->id, 'application_number' => 'APP-'.uniqid(), 'first_name' => 'Ana', 'last_name' => 'Reyes', 'course_choice' => 'BSIT', 'qualification_status' => 'Qualified'], $attributes))->save();

        return $applicant;
    }

    private function requestRecord(string $service = 'good-moral', array $attributes = []): ServiceRequest
    {
        return ServiceRequest::create(array_merge(['service' => $service, 'reference' => ServiceRequest::newReference($service), 'first_name' => 'Maria', 'last_name' => 'Santos', 'student_number' => '2026-100', 'student_status' => 'student', 'course' => 'BSIT', 'purpose' => 'Employment', 'status' => 'completed'], $attributes));
    }

    public function test_export_access_is_authenticated_and_admission_is_admin_only(): void
    {
        $this->get('/admin/admission/export')->assertRedirect('/login');
        $this->login('staff');
        $this->get('/admin/admission/export')->assertForbidden();
        $this->get('/staff/exports')->assertOk()->assertDontSee('Admission cycle');
        $this->login();
        $this->get('/admin/exports')->assertOk()->assertSee('Admission cycle');
    }

    public function test_empty_results_flash_for_every_module_and_format(): void
    {
        $this->login();
        $cycle = $this->cycle();
        foreach (['admission', 'guidance-testing', 'psychological', 'personality', 'career', 'good-moral', 'exit-form', 'documents'] as $module) {
            foreach (['html', 'pdf', 'docx', 'csv'] as $format) {
                $query = ['format' => $format];
                if ($module === 'admission') $query['cycle_id'] = $cycle->id;
                $this->get('/admin/'.$module.'/export?'.http_build_query($query))->assertRedirect('/admin/exports')->assertSessionHas('error', 'No eligible records found for the selected filters.');
            }
        }
    }

    public function test_admission_filters_and_historical_cycles_are_respected_in_all_formats(): void
    {
        $this->login();
        $cycle = $this->cycle();
        $wanted = $this->applicant($cycle, ['first_name' => 'Chosen & <Student>']);
        $this->applicant($cycle, ['first_name' => 'WrongCourse', 'course_choice' => 'BSED-FIL']);
        $this->applicant($cycle, ['first_name' => 'Unqualified', 'qualification_status' => 'Not Qualified']);
        $this->applicant($this->cycle(), ['first_name' => 'OtherCycle']);
        foreach (['html', 'pdf', 'docx', 'csv'] as $format) {
            $response = $this->get('/admin/admission/export?'.http_build_query(['cycle_id' => $cycle->id, 'course' => 'BSIT', 'status' => 'Qualified', 'type' => 'qualified', 'format' => $format]))->assertOk();
            if ($format === 'pdf') {
                $response->assertHeader('Content-Type', 'application/pdf');
                $this->assertStringStartsWith('%PDF-', $response->getContent());
            } elseif ($format === 'docx') {
                $this->assertStringStartsWith('PK', $response->getContent());
                $path = tempnam(sys_get_temp_dir(), 'export_test_');
                try {
                    file_put_contents($path, $response->getContent());
                    $zip = new \ZipArchive();
                    $this->assertTrue($zip->open($path));
                    $xml = $zip->getFromName('word/document.xml');
                    $this->assertNotFalse(simplexml_load_string($xml));
                    $this->assertStringContainsString($wanted->application_number, $xml);
                    $this->assertStringNotContainsString('OtherCycle', $xml);
                    $this->assertStringContainsString(DocumentExportService::UNIVERSITY, $zip->getFromName('word/header1.xml'));
                    $zip->close();
                } finally {
                    unlink($path);
                }
            } else {
                $response->assertSee($wanted->application_number)->assertDontSee('WrongCourse')->assertDontSee('Unqualified')->assertDontSee('OtherCycle');
                $response->assertSee('Prepared by')->assertSee('Approved by')->assertSee('Active Filters');
            }
        }
    }

    public function test_session_print_is_scoped_and_auto_prints_with_proctor_column(): void
    {
        $this->login();
        $cycle = $this->cycle();
        $session = AdmissionSession::create(['admission_cycle_id' => $cycle->id, 'session_name' => 'Morning', 'start_time' => now(), 'room' => '101', 'start_number' => 1, 'end_number' => 10]);
        $this->applicant($cycle, ['admission_session_id' => $session->id, 'first_name' => 'Assigned']);
        $this->applicant($cycle, ['first_name' => 'Unassigned']);
        $this->get('/admin/admission/print-masterlist?session_id='.$session->id)->assertOk()->assertSee('Assigned')->assertDontSee('Unassigned')->assertSee('Proctor Verification')->assertSee('GWA')->assertSee('window.onload = function() { window.print(); };', false);
        $this->get('/admin/admission/export?type=session')->assertRedirect('/admin/exports')->assertSessionHas('error');
    }

    public function test_certificate_eligibility_is_enforced_on_the_server(): void
    {
        $this->login('staff');
        foreach ([['good-moral', 'pending', false], ['good-moral', 'approved', true], ['exit-form', 'ready', false], ['exit-form', 'completed', true]] as [$module, $status, $eligible]) {
            $record = $this->requestRecord($module, ['status' => $status]);
            $response = $this->get('/staff/'.$module.'/export?type=certificate&request_id='.$record->id);
            if ($eligible) $response->assertOk()->assertSee('MARIA SANTOS')->assertSee('Guidance Counselor')->assertSee('border: 4px double #222', false);
            else $response->assertRedirect('/staff/exports')->assertSessionHas('error');
        }
        $goodMoral = $this->requestRecord();
        $this->get('/staff/exit-form/export?type=certificate&request_id='.$goodMoral->id)->assertRedirect('/staff/exports');
    }

    public function test_document_batch_filter_never_includes_testing_requests(): void
    {
        $this->login();
        $batch = GuidanceTestBatch::create(['batch_name' => 'Graduating Class', 'batch_token' => str_repeat('a', 64), 'course' => 'BSIT', 'test_type' => 'Psychological Assessment', 'reason_for_request' => 'Graduation']);
        $document = $this->requestRecord('good-moral', ['batch_id' => $batch->getKey()]);
        $testing = $this->requestRecord('testing', ['batch_id' => $batch->getKey()]);
        $other = $this->requestRecord();
        $this->get('/admin/documents/export?batch_id='.$batch->getKey())->assertOk()->assertSee($document->reference)->assertDontSee($testing->reference)->assertDontSee($other->reference)->assertSee('Graduating Class');
    }

    public function test_guidance_results_filter_by_category_course_and_individual_submission(): void
    {
        $this->login();
        $request = $this->requestRecord('testing', ['tests' => ['psychological', 'career']]);
        $submission = GuidanceTestSubmission::create(['service_request_id' => $request->id, 'test_type' => 'gad7', 'answers' => [], 'scores' => ['total' => 7], 'interpretation' => ['severity' => 'Mild'], 'completed_at' => now()]);
        GuidanceTestSubmission::create(['service_request_id' => $request->id, 'test_type' => 'career', 'answers' => [], 'scores' => ['Social' => 20], 'completed_at' => now()]);
        $applicant = \App\Models\Applicant::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'application_number' => 'GUIDANCE-001', 'gender' => 'female', 'status' => 'registered']);
        $appointment = GuidanceAppointment::create(['applicant_id' => $applicant->id, 'service_request_id' => $request->id, 'request_code' => 'G-ABCD', 'test_type' => 'gad7', 'test_category' => 'psychological', 'student_status' => 'student', 'status' => 'Completed', 'payment_slip_path' => '', 'origin_course' => 'BSIT']);
        $appointment->response()->create(['applicant_id' => $applicant->id, 'answers' => [], 'score_summary' => ['tests' => ['gad7' => ['scores' => ['total' => 9], 'interpretation' => ['severity' => 'Moderate']]]]]);
        $this->get('/admin/psychological/export?course=BSIT')->assertOk()->assertSee('total: 9')->assertDontSee('Social: 20')->assertDontSee('total: 7');
        $this->get('/admin/guidance-testing/export?type=individual&submission_id='.$submission->id)->assertOk()->assertSee('total: 7')->assertDontSee('total: 9')->assertDontSee('Social: 20');
        $this->get('/admin/psychological/export?course=BSED-FIL')->assertRedirect('/admin/exports');
        $this->get('/admin/career/export?type=completion')->assertOk()->assertSee('Social: 20');
    }

    public function test_invalid_filters_are_validation_errors(): void
    {
        $this->login();
        foreach ([['format' => 'exe'], ['cycle_id' => 99999], ['type' => 'certificate'], ['status' => 'bogus']] as $query) {
            $this->getJson('/admin/admission/export?'.http_build_query($query))->assertUnprocessable();
        }
        $this->getJson('/admin/personality/export?cycle_id=1')->assertUnprocessable();
    }

    public function test_generation_failure_redirects_without_exposing_exception_details(): void
    {
        $this->login();
        $record = $this->requestRecord();
        $this->mock(DocumentExportService::class, fn ($mock) => $mock->shouldReceive('render')->once()->andThrow(new \Error('Sensitive server path')));
        $this->get('/admin/good-moral/export?type=certificate&request_id='.$record->id.'&format=docx')->assertRedirect('/admin/exports')->assertSessionHas('error', 'Unable to generate the document. Please contact the system administrator or try another format.');
    }

    public function test_institutional_analytics_exports_use_shared_formats_and_empty_guards(): void
    {
        $this->login();
        $this->get('/admin/analytics/export?format=pdf')->assertRedirect('/admin/exports')->assertSessionHas('error');
        $record = $this->requestRecord();
        foreach (['html', 'pdf', 'docx', 'csv'] as $format) {
            $response = $this->get('/admin/analytics/export?format='.$format)->assertOk();
            if ($format === 'pdf') $this->assertStringStartsWith('%PDF-', $response->getContent());
            elseif ($format === 'docx') $this->assertStringStartsWith('PK', $response->getContent());
            else $response->assertSee('Executive Institutional Analytics Summary')->assertSee('Good Moral Total');
        }
        $this->get('/admin/analytics/export/excel')->assertOk()->assertSee($record->reference);
        $this->get('/admin/analytics/export/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_legacy_report_links_produce_printable_html_and_certificate_files(): void
    {
        $this->login();
        $record = $this->requestRecord();
        $this->get('/admin/documents/report?module=good-moral')->assertOk()->assertSee($record->reference)->assertSee('Document Request Queue Summary');
        foreach (['pdf', 'docx'] as $format) {
            $response = $this->get('/admin/good-moral/export?type=certificate&request_id='.$record->id.'&format='.$format)->assertOk();
            $this->assertStringStartsWith($format === 'pdf' ? '%PDF-' : 'PK', $response->getContent());
        }
        $cycle = $this->cycle();
        $applicant = $this->applicant($cycle, ['batch_group' => 'Batch A']);
        $this->get('/admin/admission/report?'.http_build_query(['cycle_id' => $cycle->id, 'batch_id' => 'Batch A', 'type' => 'summary']))->assertOk()->assertSee($applicant->application_number);
    }

    public function test_csv_neutralizes_formulas(): void
    {
        $this->login();
        $record = $this->requestRecord('good-moral', ['purpose' => '=HYPERLINK("https://example.com")']);
        $response = $this->get('/admin/documents/export?request_id='.$record->id.'&format=csv')->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $response->getContent());
    }

    public function test_bundled_batch_results_and_career_downloads_are_scoped_in_all_formats(): void
    {
        $this->login();
        $batch = GuidanceTestBatch::create(['batch_name' => 'BSIT Bundle', 'batch_token' => str_repeat('b', 64), 'course' => 'BSIT', 'test_type' => 'Personality Test', 'reason_for_request' => 'Assessment']);
        $request = $this->requestRecord('testing', ['batch_id' => $batch->getKey(), 'tests' => ['personality', 'career']]);
        $applicant = \App\Models\Applicant::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'application_number' => 'BUNDLE-001', 'gender' => 'female', 'status' => 'registered']);
        $appointment = GuidanceAppointment::create(['applicant_id' => $applicant->id, 'service_request_id' => $request->id, 'request_code' => 'G-BUND', 'test_type' => 'career', 'test_category' => 'career', 'test_types' => ['bfpi', 'career'], 'student_status' => 'student', 'status' => 'Completed', 'payment_slip_path' => '', 'batch_id' => $batch->getKey()]);
        $appointment->response()->create(['applicant_id' => $applicant->id, 'answers' => [], 'score_summary' => ['tests' => [
            'bfpi' => ['scores' => ['Openness' => 35]],
            'career' => ['scores' => ['Social' => 20, 'Artistic' => 15, 'Realistic' => 10]],
        ]]]);
        foreach (['html', 'pdf', 'docx', 'csv'] as $format) {
            $response = $this->get('/admin/personality/export?'.http_build_query(['type' => 'batch', 'batch_name' => $batch->batch_name, 'course' => 'BSIT', 'format' => $format]))->assertOk();
            if ($format === 'pdf') $this->assertStringStartsWith('%PDF-', $response->getContent());
            elseif ($format === 'docx') $this->assertStringStartsWith('PK', $response->getContent());
            else $response->assertSee('Openness: 35')->assertDontSee('Social: 20');
        }
        $this->get('/admin/personality/export?batch_name=Other')->assertRedirect('/admin/exports');
        $this->get('/admin/career/export?type=completion&status=completed')->assertOk()->assertSee('Social: 20')->assertDontSee('Openness: 35');
        foreach (['html', 'pdf', 'docx', 'csv'] as $format) {
            $this->get(route('admin.guidance-appointments.career-report', ['appointment' => $appointment->getKey(), 'format' => $format]))->assertOk();
        }
    }
}
