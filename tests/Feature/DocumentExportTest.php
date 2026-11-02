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

    public function test_session_print_is_scoped_in_pdf_and_explicit_html_previews(): void
    {
        $this->login();
        $cycle = $this->cycle();
        $session = AdmissionSession::create(['admission_cycle_id' => $cycle->id, 'session_name' => 'Morning', 'start_time' => now(), 'room' => '101', 'start_number' => 1, 'end_number' => 10]);
        $this->applicant($cycle, ['admission_session_id' => $session->id, 'first_name' => 'Assigned']);
        $this->applicant($cycle, ['first_name' => 'Unassigned']);
        $pdf = $this->get('/admin/admission/print-masterlist?session_id='.$session->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->get('/admin/admission/export?type=session&format=html&auto_print=1&session_id='.$session->id)->assertOk()->assertSee('Assigned')->assertDontSee('Unassigned')->assertSee('Full Name')->assertSee('First Choice')->assertSee('Second Choice')->assertSee('GWA')->assertDontSee('Application Number')->assertDontSee('Proctor Verification')->assertSee('window.onload = function() { window.print(); };', false);
        $this->get('/admin/admission/export?type=session')->assertRedirect('/admin/exports')->assertSessionHas('error');
    }

    public function test_admission_evaluation_opens_inline_portrait_pdf_with_letterhead(): void
    {
        $this->login();
        $cycle = $this->cycle();
        $wanted = $this->applicant($cycle);
        $activeCycle = $this->cycle();
        $activeCycle->update(['is_active' => true, 'is_archived' => false, 'status' => 'Active']);
        $this->applicant($activeCycle, ['first_name' => 'OtherCycle']);
        $query = ['cycle_id' => $cycle->id, 'type' => 'summary'];
        $this->get(route('admin.admission.analytics', ['cycle_id' => $cycle->id]))
            ->assertOk()->assertSee('View / Print Evaluation PDF')
            ->assertSee(route('admin.admission.report', $query + ['format' => 'pdf']))
            ->assertDontSee('Download Evaluation (DOCX)');
        $this->get(route('admin.admission.report', $query + ['format' => 'html']))
            ->assertOk()->assertSee('size: A4 portrait', false)
            ->assertSee('data:image/png;base64,', false)
            ->assertSee($wanted->application_number)->assertDontSee('OtherCycle');
        $response = $this->get(route('admin.admission.report', $query + ['format' => 'pdf']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[0(?:\.0+)?\s+0(?:\.0+)?\s+595\.28\d*\s+841\.89\d*\]/', $response->getContent());
    }

    public function test_archive_evaluation_orders_official_results_before_pending(): void
    {
        $this->login();
        $cycle = $this->cycle();
        $active = $this->cycle();
        $active->update(['is_active' => true, 'is_archived' => false, 'status' => 'Active']);
        $this->applicant($cycle, ['application_number' => 'ORDER-PENDING', 'total_score' => 100]);
        $this->applicant($cycle, ['application_number' => 'ORDER-WAITLIST', 'stanine_score' => 5, 'exam_score' => 70, 'interview_score' => 80, 'total_score' => 80]);
        $this->applicant($cycle, ['application_number' => 'ORDER-NONQUALIFIED', 'stanine_score' => 1, 'total_score' => 95]);
        $this->applicant($cycle, ['application_number' => 'ORDER-QUALIFIED', 'stanine_score' => 6, 'exam_score' => 80, 'interview_score' => 90, 'total_score' => 90]);
        \Illuminate\Support\Facades\DB::table('admission_course_quotas')->insert([
            'admission_cycle_id' => $cycle->id, 'course_code' => 'BSIT', 'seats' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->get(route('admin.admission.archive'))->assertOk()
            ->assertSee('View / Print Evaluation PDF')->assertSee('target="_blank"', false)
            ->assertDontSee('Export Evaluation DOCX');
        $this->get(route('admin.admission.report', ['cycle_id' => $cycle->id, 'type' => 'summary', 'format' => 'html']))
            ->assertOk()->assertSeeInOrder(['ORDER-QUALIFIED', 'ORDER-NONQUALIFIED', 'ORDER-WAITLIST', 'ORDER-PENDING'])
            ->assertSee('Qualified for Enrollment')->assertSee('Not Qualified for Interview')->assertSee('Waitlisted')->assertSee('Pending exam result');
        $document = app(\App\Services\ExportReportService::class)->build('admission', ['cycle_id' => $cycle->id, 'type' => 'summary']);
        $this->assertSame(['Applicants' => 4, 'Qualified' => 1, 'Non-Qualified' => 1, 'Waitlisted' => 1, 'Pending' => 1], $document['summary']);
    }

    public function test_admission_certificate_renders_one_letterhead_page_without_blank_pages(): void
    {
        $this->login();
        $cycle = $this->cycle();
        $cycle->update(['is_active' => true, 'is_archived' => false, 'status' => 'Active']);
        $applicant = $this->applicant($cycle, ['stanine_score' => 9, 'sex' => 'Male']);
        $query = [
            'applicant' => $applicant->id, 'format' => 'pdf', 'purpose' => 'SCHOLARSHIP',
            'requestor_name' => 'MR. REYES', 'issued_date' => '2026-11-02',
            'or_number' => 'OR-CERT-123', 'or_date' => '2026-11-02',
        ];
        $response = $this->get(route('admin.admission.applicants.certificate', $query))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $response->getContent();
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
        $this->assertStringContainsString('/Subtype /Image', $pdf);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[0(?:\.0+)?\s+0(?:\.0+)?\s+612(?:\.0+)?\s+792(?:\.0+)?\]/', $pdf);
        // A one-page PDF can still be wrong if the letterhead is off-page.
        $boxes = [];
        $renderer = new \Dompdf\Dompdf();
        $renderer->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use (&$boxes) {
            $node = $frame->get_node();
            if ($node instanceof \DOMElement && in_array($node->getAttribute('class'), ['page-letterhead', 'content-body', 'results-table'], true)) {
                $boxes[$node->getAttribute('class')] = $frame->get_border_box();
            }
        }]]);
        $renderer->loadHtml(view('admin.admission.certificate', [
            'applicant' => $applicant, 'cycle' => $cycle, 'salutation' => 'MR.',
            'remarks' => $applicant->certificate_remarks, 'purpose' => 'SCHOLARSHIP',
            'requestorName' => 'MR. REYES', 'issuedDay' => '2nd', 'issuedMonth' => 'November',
            'issuedYear' => '2026', 'orNumber' => 'OR-CERT-123', 'orDate' => '2026-11-02',
        ])->render());
        $renderer->setPaper('letter', 'portrait');
        $renderer->render();
        $this->assertEqualsWithDelta(0, $boxes['page-letterhead']['x'], 0.1);
        $this->assertEqualsWithDelta(0, $boxes['page-letterhead']['y'], 0.1);
        $this->assertEqualsWithDelta(612, $boxes['page-letterhead']['w'], 0.1);
        $this->assertEqualsWithDelta(792, $boxes['page-letterhead']['h'], 0.1);
        $this->assertGreaterThanOrEqual(140, $boxes['content-body']['y']);
        $this->assertGreaterThanOrEqual(42, $boxes['results-table']['x']);
        $this->assertLessThanOrEqual(570, $boxes['results-table']['x'] + $boxes['results-table']['w']);
        $this->assertLessThan(710, $boxes['content-body']['y'] + $boxes['content-body']['h']);
        $query['format'] = 'print';
        $this->get(route('admin.admission.applicants.certificate', $query))
            ->assertOk()->assertSee('data:image/png;base64,', false)
            ->assertSee('CERTIFICATE OF ADMISSION TEST RESULT')->assertSee('SCHOLARSHIP')
            ->assertSee('MR. REYES')->assertSee('OR-CERT-123')->assertSee('NOEMI C. CARLOS, RGC');
    }

    public function test_certificate_eligibility_is_enforced_on_the_server(): void
    {
        $this->login('staff');
        foreach ([['good-moral', 'pending', false], ['good-moral', 'approved', false], ['good-moral', 'ready', false], ['good-moral', 'ready', true], ['exit-form', 'ready', false], ['exit-form', 'completed', false], ['exit-form', 'completed', true]] as [$module, $status, $eligible]) {
            $record = $this->requestRecord($module, ['status' => $status, ...($eligible ? ['receipt_data' => base64_encode('receipt')] : [])]);
            $response = $this->get('/staff/'.$module.'/export?type=certificate&request_id='.$record->id);
            if ($eligible) $response->assertOk()->assertSee('MARIA SANTOS')->assertSee('Guidance Counselor')->assertSee('class="certificate-brand"', false);
            else $response->assertRedirect('/staff/exports')->assertSessionHas('error');
        }
        $goodMoral = $this->requestRecord();
        $this->get('/staff/exit-form/export?type=certificate&request_id='.$goodMoral->id)->assertRedirect('/staff/exports');
    }

    public function test_document_queue_shows_legacy_approved_without_receipt_as_waiting_and_hides_certificate_actions(): void
    {
        $this->login('staff');
        $record = $this->requestRecord('good-moral', ['status' => 'approved']);

        $this->get('/staff/good-moral')
            ->assertOk()
            ->assertSee('Waiting for Receipt')
            ->assertSee('Not uploaded')
            ->assertDontSee('Print Certificate')
            ->assertDontSee('>PDF</a>', false)
            ->assertDontSee('>DOCX</a>', false);

        $this->get('/staff/good-moral/export?type=certificate&request_id='.$record->id)
            ->assertRedirect('/staff/exports')
            ->assertSessionHas('error');
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
        $record = $this->requestRecord('good-moral', ['status' => 'ready', 'receipt_data' => base64_encode('receipt')]);
        $this->mock(DocumentExportService::class, fn ($mock) => $mock->shouldReceive('render')->once()->andThrow(new \Error('Sensitive server path')));
        $this->get('/admin/good-moral/export?type=certificate&request_id='.$record->id.'&format=docx')->assertRedirect('/admin/exports')->assertSessionHas('error', 'Unable to generate the document. Please contact the system administrator or try another format.');
    }

    public function test_report_preview_preserves_filters_counts_and_download_choices_for_both_roles(): void
    {
        $included = $this->requestRecord('good-moral');
        $excluded = $this->requestRecord('good-moral', ['course' => 'BSOA']);
        foreach (['admin', 'staff'] as $role) {
            $this->login($role);
            $parameters = ['type' => 'queue', 'course' => 'BSIT', 'status' => 'completed', 'format' => 'html', 'preview' => 1];
            $preview = $this->get(route($role.'.good-moral.export', $parameters))->assertOk()
                ->assertViewIs('exports.preview')->assertSee('Record count: 1')
                ->assertSee('Active Filters')->assertSee('course: BSIT')->assertSee('Preview export actions')
                ->assertSee('Download DOCX')->assertSee('Download CSV')->assertSee('id="report-preview"', false)
                ->assertViewHas('previewHtml', fn ($html) => str_contains($html, 'Official PSU San Carlos City Campus letterhead') && str_contains($html, 'screen-official-letterhead footer'))
                ->assertViewHas('document', fn ($document) => $document['records']->modelKeys() === [$included->id]);
            $dom = new \DOMDocument();
            @$dom->loadHTML($preview->getContent());
            $xpath = new \DOMXPath($dom);
            $downloadUrl = $xpath->query('//a[normalize-space(text())="Download CSV"]')->item(0)->getAttribute('href');
            parse_str(parse_url($downloadUrl, PHP_URL_QUERY), $query);
            $this->assertSame('BSIT', $query['course']);
            $this->assertSame('completed', $query['status']);
            $this->assertSame('csv', $query['format']);
            $this->get($downloadUrl)->assertOk()->assertSee($included->reference)->assertDontSee($excluded->reference);
            $changeUrl = $xpath->query('//a[normalize-space(text())="Change report / filters"]')->item(0)->getAttribute('href');
            $this->get($changeUrl)->assertOk()->assertSee('Preview report')
                ->assertSee('value="BSIT" selected', false)->assertSee('name="preview" value="1"', false);
        }
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
            else $response->assertSee('Guidance Analytics Summary')->assertSee('Good Moral Total');
        }
        $this->get('/admin/analytics/export/excel')->assertOk()->assertSee($record->reference);
        $this->get('/admin/analytics/export/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_legacy_report_links_produce_printable_html_and_certificate_files(): void
    {
        $this->login();
        $record = $this->requestRecord('good-moral', ['status' => 'ready', 'receipt_data' => base64_encode('receipt')]);
        $this->get('/admin/documents/report?module=good-moral')->assertOk()->assertSee($record->reference)->assertSee('Document Request Queue Summary');
        foreach (['pdf', 'docx'] as $format) {
            $response = $this->get('/admin/good-moral/export?type=certificate&request_id='.$record->id.'&format='.$format)->assertOk();
            $this->assertStringStartsWith($format === 'pdf' ? '%PDF-' : 'PK', $response->getContent());
        }
        $cycle = $this->cycle();
        $applicant = $this->applicant($cycle, ['batch_group' => 'Batch A']);
        $activeCycle = $this->cycle();
        $activeCycle->update(['is_active' => true, 'is_archived' => false, 'status' => 'Active']);
        $this->get('/admin/admission/report?'.http_build_query(['cycle_id' => $cycle->id, 'batch_id' => 'Batch A', 'type' => 'summary', 'format' => 'html']))->assertOk()->assertSee($applicant->application_number);
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
            'career' => ['scores' => ['Social' => 5, 'Artistic' => 4, 'Realistic' => 3]],
        ]]]);
        foreach (['html', 'pdf', 'docx', 'csv'] as $format) {
            $response = $this->get('/admin/personality/export?'.http_build_query(['type' => 'batch', 'batch_name' => $batch->batch_name, 'course' => 'BSIT', 'format' => $format]))->assertOk();
            if ($format === 'pdf') $this->assertStringStartsWith('%PDF-', $response->getContent());
            elseif ($format === 'docx') $this->assertStringStartsWith('PK', $response->getContent());
            else $response->assertSee('Openness: 35')->assertDontSee('Social: 5');
        }
        $this->get('/admin/personality/export?batch_name=Other')->assertRedirect('/admin/exports');
        $this->get('/admin/career/export?type=completion&status=completed')->assertOk()->assertSee('Social: 5')->assertDontSee('Openness: 35');
        foreach (['html', 'pdf', 'docx', 'csv'] as $format) {
            $this->get(route('admin.guidance-appointments.career-report', ['appointment' => $appointment->getKey(), 'format' => $format]))->assertOk();
        }
    }
}
