<?php

namespace Tests\Feature;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Models\Role;
use App\Models\User;
use App\Services\AdmissionReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class AdmissionReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_combined_program_settings_save_and_reports_use_saved_limits(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]));
        $cycle = AdmissionCycle::create(['name' => 'Settings Cycle', 'academic_year' => '2026-2027', 'is_active' => true, 'status' => 'Active']);
        $this->applicant($cycle, 'SETTINGS-1', 'BSIT', 5, 90, 85);
        $this->get(route('admin.admission.masterlist'))->assertOk()
            ->assertSee('Program Settings')->assertSee('Save Settings')->assertSee('All eligible')
            ->assertSee('admission-report-type', false)->assertDontSee('Save Limits &amp; Export', false);
        $this->from(route('admin.admission.masterlist'))->post(route('admin.admission.program-settings.save'), [
            'settings' => [
                'BSIT' => ['seats' => 1, 'mode' => 'top', 'limit' => 2],
                'BEED' => ['seats' => 0, 'mode' => 'none'],
                'BSOA' => ['seats' => 20, 'mode' => 'all'],
            ],
        ])->assertRedirect(route('admin.admission.masterlist'))->assertSessionHasNoErrors();
        foreach (['BSIT' => 2, 'BEED' => 0, 'BSOA' => 100000] as $code => $limit) {
            $this->assertDatabaseHas('admission_interview_cutoffs', ['admission_cycle_id' => $cycle->id, 'course_code' => $code, 'top_limit' => $limit]);
        }
        $this->assertDatabaseHas('admission_course_quotas', ['admission_cycle_id' => $cycle->id, 'course_code' => 'BSOA', 'seats' => 20]);
        $this->get(route('admin.admission.reports.interview-qualifiers', ['format' => 'pdf']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertDatabaseHas('admission_interview_cutoffs', ['admission_cycle_id' => $cycle->id, 'course_code' => 'BSIT', 'top_limit' => 2]);
    }

    public function test_invalid_program_settings_do_not_partially_save(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]));
        $cycle = AdmissionCycle::create(['name' => 'Validation Cycle', 'academic_year' => '2026-2027', 'is_active' => true, 'status' => 'Active']);
        $this->postJson(route('admin.admission.program-settings.save'), ['settings' => [
            'BSIT' => ['seats' => 1, 'mode' => 'all'],
            'BEED' => ['seats' => 2, 'mode' => 'top'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('settings.BEED.limit');
        $this->assertDatabaseMissing('admission_course_quotas', ['admission_cycle_id' => $cycle->id]);
        $this->postJson(route('admin.admission.program-settings.save'), ['settings' => ['INVALID' => ['seats' => 1, 'mode' => 'none']]])
            ->assertUnprocessable()->assertJsonValidationErrors('settings');
    }

    public function test_psu_cat_roster_replaces_application_number_with_official_status(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]));
        $cycle = AdmissionCycle::create(['name' => 'Roster Status', 'academic_year' => '2026-2027', 'is_active' => true, 'status' => 'Active']);
        $qualified = $this->applicant($cycle, 'PRIVATE-ID-1', 'BSIT', 6, 90, 90);
        $waitlisted = $this->applicant($cycle, 'PRIVATE-ID-2', 'BSIT', 5, 80, 80);
        $nonQualified = $this->applicant($cycle, 'PRIVATE-ID-3', 'BSIT', 1, 70, 70);
        $pending = $this->applicant($cycle, 'PRIVATE-ID-4', 'BSIT', null, null, null);
        foreach ([$qualified, $waitlisted, $nonQualified, $pending] as $applicant) $applicant->update(['first_name' => 'Student']);
        DB::table('admission_course_quotas')->insert(['admission_cycle_id' => $cycle->id, 'course_code' => 'BSIT', 'seats' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $roster = app(AdmissionReportService::class)->roster($cycle, 'psu-cat-qualifiers');
        $export = app(\App\Services\AdmissionReportExportService::class);
        $columns = $export->columns('psu-cat-qualifiers');
        $this->assertNotContains('Application No.', $columns);
        $this->assertSame('Status', end($columns));
        $expected = [$qualified->id => 'Qualified for Enrollment', $waitlisted->id => 'Waitlisted', $nonQualified->id => 'Not Qualified for Interview', $pending->id => 'Pending exam result'];
        foreach ($roster['groups']['BSIT'] as $item) {
            $values = $export->values($item, 'psu-cat-qualifiers');
            $this->assertCount(count($columns), $values);
            $this->assertNotContains($item['applicant']->application_number, $values);
            $this->assertSame($expected[$item['applicant']->id], end($values));
        }
        $html = view('admin.admission.reports.document', ['cycle' => $cycle, 'type' => 'psu-cat-qualifiers', 'roster' => $roster, 'batch' => null])->render();
        $this->assertStringContainsString('<th>Status</th>', $html);
        $this->assertStringNotContainsString('PRIVATE-ID-', $html);
        $this->get(route('admin.admission.reports.psu-cat-qualifiers', ['format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('admin.admission.reports.psu-cat-qualifiers', ['format' => 'docx']))->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertContains('Application No.', $export->columns('interview-qualifiers'));
    }

    public function test_cutoff_migration_recovers_a_table_left_by_failed_mysql_index_creation(): void
    {
        Schema::dropIfExists('admission_interview_cutoffs');
        Schema::create('admission_interview_cutoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_cycle_id')->constrained()->cascadeOnDelete();
            $table->string('course_code', 30);
            $table->unsignedInteger('top_limit');
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_09_30_000003_create_admission_interview_cutoffs.php');
        $migration->up();

        $this->assertTrue(Schema::hasIndex('admission_interview_cutoffs', 'adm_interview_cycle_course_uq', 'unique'));
        $migration->up();
        $this->assertTrue(Schema::hasIndex('admission_interview_cutoffs', ['admission_cycle_id', 'course_code'], 'unique'));
    }

    public function test_program_thresholds_cutoffs_quotas_and_masterlist_rank(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]));
        $cycle = AdmissionCycle::create(['name' => 'Report Cycle', 'academic_year' => '2026-2027', 'is_active' => true, 'status' => 'Active']);
        $this->applicant($cycle, 'B-1', 'BEED', 3, 99, 90);
        $this->applicant($cycle, 'B-2', 'BEED', 5, 88, 90);
        $this->applicant($cycle, 'I-1', 'BSIT', 3, 85, 90)->update(['batch_group' => 'Batch 1']);
        $this->applicant($cycle, 'I-2', 'BSIT', 3, 85, 80)->update(['batch_group' => 'Batch 2']);
        $this->applicant($cycle, 'I-3', 'BSIT', 2, 70, 70);
        $this->applicant($cycle, 'I-4', 'BSIT', null, null, null);
        DB::table('admission_course_quotas')->insert([
            'admission_cycle_id' => $cycle->id, 'course_code' => 'BSIT', 'seats' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $masterlist = $this->get('/admin/admission/masterlist');
        $masterlist->assertOk()->assertSee('RANK')->assertSee('#1')->assertSee('#6')
            ->assertSeeInOrder(['B-1', 'B-2']);
        $this->post('/admin/admission/reports/interview-qualifiers', [
            'top_limits' => ['BSIT' => 2, 'BEED' => 1], 'format' => 'pdf',
        ])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/admin/admission/masterlist')->assertOk()->assertSeeInOrder(['B-2', 'B-1']);

        $reports = app(AdmissionReportService::class);
        $this->assertSame(['B-2', 'I-1', 'I-2'], $this->numbers($reports->roster($cycle, 'interview-qualifiers')));
        $this->assertSame(['B-1', 'I-3'], $this->numbers($reports->roster($cycle, 'interview-non-qualifiers')));
        $this->assertSame(['I-1'], $this->numbers($reports->roster($cycle, 'final-enrollment-qualified')));
        $this->assertSame(['B-2', 'I-2'], $this->numbers($reports->roster($cycle, 'final-enrollment-waitlisted')));
        $this->assertSame(['I-2'], $this->numbers($reports->roster($cycle, 'final-enrollment-waitlisted', 'Batch 2')));
        $this->assertSame([], $this->numbers($reports->roster($cycle, 'final-enrollment-qualified', 'Batch 2')));
        $this->assertCount(6, $this->numbers($reports->roster($cycle, 'psu-cat-qualifiers')));
        $this->get('/admin/admission/reports/final-enrollment-qualified?format=docx')
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_enrollment_remarks_rank_passed_interviewees_and_waitlist_over_capacity(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]));
        $cycle = AdmissionCycle::create(['name' => 'Capacity Cycle', 'academic_year' => '2026-2027', 'is_active' => true, 'status' => 'Active']);
        $this->applicant($cycle, 'IT-1', 'BSIT', 5, 92, 88);
        $this->applicant($cycle, 'IT-2', 'BSIT', 5, 89, 85);
        $this->applicant($cycle, 'IT-3', 'BSIT', 5, 84, 82);

        DB::table('admission_course_quotas')->insert([
            'admission_cycle_id' => $cycle->id, 'course_code' => 'BSIT', 'seats' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->post('/admin/admission/reports/interview-qualifiers', [
            'top_limits' => ['BSIT' => 3], 'save_only' => 1,
        ])->assertRedirect()->assertSessionHas('success', 'Interview top limits saved.');

        $reports = app(AdmissionReportService::class);
        $outcomes = $reports->outcomes($cycle);
        $this->assertSame('Qualified for Enrollment', $outcomes[AdmissionApplicant::where('application_number', 'IT-1')->value('id')]['remark']);
        $this->assertSame('Qualified for Enrollment', $outcomes[AdmissionApplicant::where('application_number', 'IT-2')->value('id')]['remark']);
        $this->assertSame('Waitlisted', $outcomes[AdmissionApplicant::where('application_number', 'IT-3')->value('id')]['remark']);
        $this->assertSame(['IT-1', 'IT-2'], $this->numbers($reports->roster($cycle, 'final-enrollment-qualified')));
        $this->assertSame(['IT-3'], $this->numbers($reports->roster($cycle, 'final-enrollment-waitlisted')));
        $this->get('/admin/admission/masterlist')->assertOk()->assertSee('Qualified for Enrollment')->assertSee('Waitlisted');
    }

    public function test_masterlist_saves_enrollment_quotas_for_all_programs_in_one_request(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]));
        $cycle = AdmissionCycle::create([
            'name' => 'Bulk Quota Cycle',
            'academic_year' => '2030-2031',
            'is_active' => true,
            'status' => AdmissionCycle::STATUS_ACTIVE,
        ]);

        $this->post(route('admin.admission.quotas.save'), [
            'quotas' => ['BSIT' => 120, 'BEED' => 80],
        ])->assertRedirect()->assertSessionHas('success', 'Enrollment quotas updated.');

        $this->assertDatabaseHas('admission_course_quotas', [
            'admission_cycle_id' => $cycle->id,
            'course_code' => 'BSIT',
            'seats' => 120,
        ]);
        $this->assertDatabaseHas('admission_course_quotas', [
            'admission_cycle_id' => $cycle->id,
            'course_code' => 'BEED',
            'seats' => 80,
        ]);
    }

    public function test_masterlist_filters_enrollment_qualifiers_using_cycle_wide_rankings(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('slug', 'admin')->value('id')]));
        $cycle = AdmissionCycle::create(['name' => 'Enrollment Filter', 'academic_year' => '2026-2027', 'is_active' => true, 'status' => 'Active']);
        $qualified = $this->applicant($cycle, 'FILTER-QUALIFIED', 'BSIT', 6, 90, 90);
        $this->applicant($cycle, 'FILTER-WAITLISTED', 'BSIT', 5, 80, 80);
        $this->applicant($cycle, 'FILTER-PENDING', 'BSIT', null, null, null);
        DB::table('admission_course_quotas')->insert(['admission_cycle_id' => $cycle->id, 'course_code' => 'BSIT', 'seats' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $this->get(route('admin.admission.masterlist', ['interview_filter' => 'qualified_for_enrollment']))
            ->assertOk()
            ->assertSee('value="qualified_for_enrollment" selected', false)
            ->assertViewHas('applicants', fn ($applicants) => $applicants->pluck('id')->all() === [$qualified->id]);

        // Searching for a waitlisted applicant must not promote them into the available seat.
        $this->get(route('admin.admission.masterlist', ['interview_filter' => 'qualified_for_enrollment', 'search' => 'FILTER-WAITLISTED']))
            ->assertOk()->assertViewHas('applicants', fn ($applicants) => $applicants->total() === 0);
    }

    public function test_enrollment_qualifier_exports_only_show_names_and_courses(): void
    {
        $cycle = AdmissionCycle::create(['name' => 'Public Qualifiers', 'academic_year' => '2026-2027', 'is_active' => true, 'status' => 'Active']);
        $applicant = $this->applicant($cycle, 'PRIVATE-APPLICATION', 'BSIT', 6, 90, 90);
        $applicant->update(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        DB::table('admission_course_quotas')->insert(['admission_cycle_id' => $cycle->id, 'course_code' => 'BSIT', 'seats' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $type = 'final-enrollment-qualified';
        $roster = app(AdmissionReportService::class)->roster($cycle, $type);
        $export = app(\App\Services\AdmissionReportExportService::class);
        $this->assertSame(['Full Name', 'Course'], $export->columns($type));
        $this->assertSame([$applicant->full_name, 'BSIT'], $export->values($roster['groups']['BSIT'][0], $type));
        $html = view('admin.admission.reports.document', compact('cycle', 'type', 'roster') + ['batch' => null])->render();
        foreach (['Generated:', 'Seats:', 'Application No.', '<th>Interview</th>', '<th>Total</th>', 'PRIVATE-APPLICATION'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html);
        }
        $this->assertStringContainsString('class="page-letterhead"', $html);
        $this->assertStringContainsString($applicant->full_name, $html);
        $this->assertStringStartsWith('%PDF-', $export->render($cycle, $type, $roster, 'pdf', null));

        $path = tempnam(sys_get_temp_dir(), 'qualifier_test_');
        try {
            file_put_contents($path, $export->render($cycle, $type, $roster, 'docx', null));
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('word/document.xml');
            $this->assertNotFalse($zip->locateName('word/header1.xml'));
            $this->assertNotFalse($zip->locateName('word/footer1.xml'));
            $zip->close();
            $this->assertStringContainsString($applicant->full_name, $xml);
            foreach (['Generated:', 'Seats:', 'Application No.', 'PRIVATE-APPLICATION', '>Interview<', '>Total<'] as $hidden) {
                $this->assertStringNotContainsString($hidden, $xml);
            }
        } finally {
            if (is_file($path)) unlink($path);
        }
    }

    private function applicant(AdmissionCycle $cycle, string $number, string $course, ?int $stanine, ?int $total, ?int $interview): AdmissionApplicant
    {
        $applicant = AdmissionApplicant::create([
            'admission_cycle_id' => $cycle->id, 'application_number' => $number,
            'first_name' => $number, 'last_name' => 'Applicant', 'course_choice' => $course,
            'gwa' => 90, 'interview_score' => $interview,
        ]);
        $applicant->forceFill(['stanine_score' => $stanine, 'exam_score' => $stanine === null ? null : 50,
            'total_score' => $total])->save();
        return $applicant;
    }

    private function numbers(array $roster): array
    {
        return collect($roster['groups'])->flatMap(fn ($items) => collect($items)
            ->map(fn ($item) => $item['applicant']->application_number))->values()->all();
    }
}
