<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\GuidanceAppointment;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\DocumentExportService;
use App\Services\PsychologicalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuidanceReportTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role = 'admin'): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', $role)->value('id')]));
    }

    private function appointment(?array $tests = null): GuidanceAppointment
    {
        $applicant = Applicant::create(['first_name' => 'Ana & <Student>', 'last_name' => 'Santos', 'application_number' => 'PSY-'.uniqid(), 'gender' => 'female', 'status' => 'registered']);
        $request = ServiceRequest::create(['service' => 'testing', 'student_status' => 'student', 'reference' => ServiceRequest::newReference('testing'), 'first_name' => 'Ana', 'last_name' => 'Santos', 'course' => 'BSIT', 'purpose' => 'Practicum', 'or_number' => 'OR-12345', 'or_date' => '2026-09-28', 'status' => 'completed']);
        $appointment = GuidanceAppointment::create(['applicant_id' => $applicant->id, 'service_request_id' => $request->id, 'request_code' => 'G-'.uniqid(), 'test_type' => 'dass21', 'test_category' => 'psychological', 'test_types' => ['dass21', 'phq9', 'gad7'], 'status' => 'Completed', 'payment_slip_path' => '', 'origin_course' => 'BSIT', 'origin_section' => '3A']);
        $appointment->response()->create(['applicant_id' => $applicant->id, 'answers' => [], 'score_summary' => ['tests' => $tests ?? $this->normalTests()]]);

        return $appointment;
    }

    private function normalTests(): array
    {
        return [
            'dass21' => ['scores' => ['depression' => 0, 'anxiety' => 0, 'stress' => 0], 'interpretation' => ['depression' => 'Normal', 'anxiety' => 'Normal', 'stress' => 'Normal']],
            'phq9' => ['scores' => ['total' => 0], 'interpretation' => ['severity' => 'Minimal']],
            'gad7' => ['scores' => ['total' => 0], 'interpretation' => ['severity' => 'Minimal']],
        ];
    }

    public function test_preview_requires_authentication_and_matching_role(): void
    {
        $appointment = $this->appointment();
        $this->get('/admin/guidance/report/preview/'.$appointment->getKey())->assertRedirect('/login');
        $this->login('staff');
        $this->get('/admin/guidance/report/preview/'.$appointment->getKey())->assertForbidden();
        $this->get('/staff/guidance/report/preview/'.$appointment->getKey())->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get('/staff/guidance/report/download/'.$appointment->getKey().'?format=docx')->assertOk()->assertDownload();
    }

    public function test_official_preview_populates_profile_marks_and_print_controls(): void
    {
        $this->login();
        $appointment = $this->appointment();
        $response = $this->get('/admin/guidance/report/preview/'.$appointment->getKey().'?auto_print=1')->assertOk()
            ->assertSee('OFFICE OF ADMISSION AND GUIDANCE SERVICES')->assertSee('Ana & <Student> Santos')->assertDontSee('<Student>', false)
            ->assertSee('3A')->assertSee('Practicum')->assertSee('OR-12345')->assertSee('September 28, 2026')
            ->assertSee('[X] Fit for deployment')->assertSee('NOT VALID WITHOUT UNIVERSITY SEAL')
            ->assertSee('Depression: Very Low selected')->assertSee('PHQ-9: Minimal selected')->assertSee('GAD-7: Minimal selected')
            ->assertSee('@media print', false)->assertSee('window.print()', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_appointment_results_use_the_requested_assessment_type_without_export_bar(): void
    {
        $this->login();

        foreach ([
            ['psychological', 'dass21', 'Psychological Assessment Results', 'Official scoring summary and interpretation for Guidance Counselor review.', 'Official psychological assessment report', 'CONFIDENTIAL PSYCHOLOGICAL ASSESSMENT REPORT'],
            ['career', 'career', 'Career Assessment Results', 'Official RIASEC trait scoring summary and career interest interpretation.', 'Career assessment report / PDF', 'CONFIDENTIAL CAREER ASSESSMENT REPORT'],
            ['personality', 'bfpi', 'Personality Assessment Results', 'Official Big Five Personality Inventory scoring summary and trait evaluation.', 'Personality assessment report / PDF', 'CONFIDENTIAL PERSONALITY ASSESSMENT REPORT'],
        ] as [$category, $type, $title, $subtitle, $badge, $header]) {
            $appointment = $this->appointment();
            $appointment->update(['test_category' => $category, 'test_type' => $type, 'test_types' => [$type]]);
            $appointment->response->update(['score_summary' => ['tests' => []]]);

            $this->get(route('admin.guidance-appointments.show-results', $appointment))
                ->assertOk()
                ->assertSee($title)
                ->assertSee($subtitle)
                ->assertSee($badge)
                ->assertSee($header)
                ->assertSee('Print Certificate')
                ->assertDontSee('Print Report')
                ->assertDontSee('Print official report')
                ->assertDontSee('Export PDF')
                ->assertDontSee('Export DOCX')
                ->assertDontSee('Export CSV');
        }
    }

    public function test_psychological_certificate_is_printable_and_only_available_for_completed_assessments(): void
    {
        $appointment = $this->appointment();
        $appointment->serviceRequest->update(['student_number' => '23-SC-4111']);
        $url = '/admin/psychological/certificate/'.$appointment->getKey();

        $this->get($url)->assertRedirect('/login');
        $this->login('staff');
        $this->get($url)->assertForbidden();
        $this->login('admin');
        $this->get($url)->assertOk()
            ->assertSee('Certificate of Psychological Assessment')
            ->assertSee('Ana &amp; &lt;Student&gt; Santos', false)
            ->assertSee('23-SC-4111')
            ->assertSee('Bachelor of Science in Information Technology (BSIT)')
            ->assertSee('Practicum')
            ->assertSee('Guidance Counselor')
            ->assertSee('border: 4px double #000', false)
            ->assertSee('window.print()', false);
        $this->get(route('admin.guidance-appointments.show-results', $appointment))
            ->assertOk()->assertSee('target="_blank"', false)->assertSee('Print Certificate')
            ->assertDontSee('Print Report');

        $appointment->update(['status' => 'Approved']);
        $this->get($url)->assertNotFound();
        $appointment->update(['status' => 'Completed', 'test_category' => 'career', 'test_type' => 'career']);
        $this->get($url)->assertNotFound();
    }

    public function test_personality_and_career_certificates_use_their_own_titles_and_routes(): void
    {
        $this->login('admin');
        foreach ([
            ['personality', 'bfpi', 'Personality Assessment'],
            ['career', 'career', 'Career Assessment'],
        ] as [$category, $testType, $label]) {
            $appointment = $this->appointment();
            $appointment->update(['test_category' => $category, 'test_type' => $testType, 'test_types' => [$testType]]);
            $url = route('admin.'.$category.'.certificate', $appointment);

            $this->get(route('admin.guidance-appointments.show-results', $appointment))
                ->assertOk()->assertSee($url, false)->assertSee('Print Certificate')->assertDontSee('Print Report');
            $this->get($url)->assertOk()
                ->assertSee('Certificate of '.$label)
                ->assertSee('certificate of '.strtolower($label).' completion')
                ->assertSee('window.print()', false);
            $this->get(route('admin.psychological.certificate', $appointment))->assertNotFound();
        }
    }

    public function test_all_dass_levels_use_institutional_columns_and_recommendations(): void
    {
        foreach (['Normal' => ['Very Low', 'Fit for deployment'], 'Mild' => ['Low', 'Fit for deployment with Reservation'], 'Moderate' => ['Average', 'Fit for deployment with Reservation'], 'Severe' => ['High', 'For Counseling'], 'Extremely Severe' => ['Very High', 'For Counseling']] as $level => [$column, $recommendation]) {
            $tests = $this->normalTests();
            $tests['dass21']['interpretation'] = array_fill_keys(['depression', 'anxiety', 'stress'], $level);
            $document = app(PsychologicalReportService::class)->build($this->appointment($tests));
            $this->assertSame([$column, $column, $column], array_column($document['matrices'][0]['rows'], 'selected'));
            $this->assertSame($recommendation, $document['recommendation']);
        }
    }

    public function test_phq_and_gad_levels_select_the_correct_cells_and_flag_counseling(): void
    {
        foreach (['phq9' => ['Minimal', 'Mild', 'Moderate', 'Moderately Severe', 'Severe'], 'gad7' => ['Minimal', 'Mild', 'Moderate', 'Severe']] as $test => $levels) {
            foreach ($levels as $level) {
                $tests = $this->normalTests();
                $tests[$test]['interpretation']['severity'] = $level;
                $document = app(PsychologicalReportService::class)->build($this->appointment($tests));
                $this->assertSame($level, $document['matrices'][$test === 'phq9' ? 1 : 2]['rows'][0]['selected']);
                $this->assertSame(in_array($level, ['Severe', 'Moderately Severe']) ? 'For Counseling' : ($level === 'Minimal' ? 'Fit for deployment' : 'Fit for deployment with Reservation'), $document['recommendation']);
            }
        }
    }

    public function test_partial_unknown_and_incomplete_results_never_recommend_fit(): void
    {
        $this->login();
        foreach ([['gad7' => null], ['gad7' => ['completion' => 'Incomplete']], ['gad7' => ['scores' => ['total' => 0], 'interpretation' => ['severity' => 'Unknown']]], ['gad7' => ['scores' => ['total' => -1], 'interpretation' => ['severity' => 'Minimal']]]] as $override) {
            $appointment = $this->appointment(array_replace($this->normalTests(), $override));
            $this->get('/admin/guidance/report/preview/'.$appointment->getKey())->assertOk()->assertSee('Incomplete assessment')->assertDontSee('[X] Fit for deployment');
        }
        $tests = $this->normalTests();
        unset($tests['gad7']);
        $tests['phq9']['scores']['total'] = 27;
        $tests['phq9']['interpretation']['severity'] = 'Severe';
        $document = app(PsychologicalReportService::class)->build($this->appointment($tests));
        $this->assertSame('For Counseling', $document['recommendation']);
        $this->assertFalse($document['complete']);
    }

    public function test_missing_unscored_and_wrong_category_appointments_are_rejected(): void
    {
        $this->login();
        $this->get('/admin/guidance/report/preview/999999')->assertNotFound();
        $appointment = $this->appointment([]);
        foreach (['preview', 'download'] as $action) {
            $this->get('/admin/guidance/report/'.$action.'/'.$appointment->getKey())->assertRedirect('/admin/exports')->assertSessionHas('error');
        }
        $appointment = $this->appointment();
        $appointment->update(['status' => 'In-Progress']);
        $this->get('/admin/guidance/report/preview/'.$appointment->getKey())->assertRedirect('/admin/exports');
        $appointment->update(['status' => 'Completed', 'test_category' => 'career', 'test_type' => 'career']);
        $this->get('/admin/guidance/report/preview/'.$appointment->getKey())->assertNotFound();
    }

    public function test_actual_pdf_and_docx_contain_the_official_report(): void
    {
        $this->login();
        $appointment = $this->appointment();
        $pdf = $this->get('/admin/guidance/report/download/'.$appointment->getKey().'?format=pdf')->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf->getContent()), 'The standard report should fit on one A4 page.');
        $response = $this->get('/admin/guidance/report/download/'.$appointment->getKey().'?format=docx')->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $path = tempnam(sys_get_temp_dir(), 'psych_report_test_');
        try {
            file_put_contents($path, $response->getContent());
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('word/document.xml');
            $this->assertNotFalse(simplexml_load_string($xml));
            $this->assertStringContainsString('OFFICE OF ADMISSION AND GUIDANCE SERVICES', $xml);
            $this->assertStringContainsString('Ana &amp; &lt;Student&gt; Santos', $xml);
            $this->assertStringContainsString('[X] Fit for deployment', $xml);
            $this->assertStringContainsString('Doc. Stamp Tax Paid', $xml);
            $this->assertSame(6, substr_count($xml, '[X]'));
            $zip->close();
        } finally {
            unlink($path);
        }
    }

    public function test_legacy_single_test_summaries_are_supported_without_claiming_full_completion(): void
    {
        $this->login();
        $appointment = $this->appointment();
        $appointment->response->update(['score_summary' => ['test_type' => 'phq9', 'scores' => ['total' => 27]]]);
        $this->get('/admin/guidance/report/preview/'.$appointment->getKey())->assertOk()->assertSee('PHQ-9: Severe selected')->assertSee('[X] For Counseling')->assertSee('Incomplete assessment');
    }

    public function test_invalid_format_and_generation_failures_are_handled(): void
    {
        $this->login();
        $appointment = $this->appointment();
        $this->getJson('/admin/guidance/report/download/'.$appointment->getKey().'?format=exe')->assertUnprocessable();
        $this->mock(DocumentExportService::class, fn ($mock) => $mock->shouldReceive('render')->once()->andThrow(new \Error('Sensitive path')));
        $this->get('/admin/guidance/report/download/'.$appointment->getKey().'?format=docx')->assertRedirect('/admin/exports')->assertSessionHas('error', 'Unable to generate the document. Please contact the system administrator or try another format.');
    }

    public function test_reports_support_batch_metadata_without_a_linked_request(): void
    {
        $this->login();
        $appointment = $this->appointment();
        $batch = \App\Models\GuidanceTestBatch::create(['batch_name' => 'Source Class', 'batch_token' => str_repeat('c', 64), 'course' => 'BSIT', 'year_section' => '4B', 'test_type' => 'Psychological Assessment', 'reason_for_request' => 'Internship']);
        $appointment->update(['service_request_id' => null, 'source_batch_id' => $batch->getKey(), 'origin_course' => null, 'origin_section' => null]);
        $this->get('/admin/guidance/report/preview/'.$appointment->getKey())->assertOk()->assertSee('4B')->assertSee('Internship')->assertDontSee('OR-12345');
        $appointment->update(['source_batch_id' => null]);
        $this->get('/admin/guidance/report/preview/'.$appointment->getKey())->assertOk()->assertSee('Not specified');
    }
}
