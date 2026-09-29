<?php

namespace Tests\Feature;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Models\Role;
use App\Models\User;
use App\Services\AdmissionReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdmissionReportsTest extends TestCase
{
    use RefreshDatabase;

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
