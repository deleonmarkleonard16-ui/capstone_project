<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\GuidanceAppointment;
use App\Models\GuidanceTestBatch;
use App\Models\User;
use App\Services\DocumentExportService;
use App\Services\GuidanceCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuidanceBatchPrintTest extends TestCase
{
    use RefreshDatabase;

    private function batch(string $category = 'psychological', string $status = 'Completed'): GuidanceTestBatch
    {
        return GuidanceTestBatch::create([
            'batch_name' => 'PRINT '.$category, 'batch_token' => Str::random(64),
            'test_type' => GuidanceCategories::LABELS[$category], 'course' => 'BSIT',
            'reason_for_request' => 'Practicum', 'status' => $status,
            'archived_at' => $status === 'Completed' ? now() : null,
        ]);
    }

    private function appointment(GuidanceTestBatch $batch, string $name, string $category, bool $makeup = false, bool $scored = true): GuidanceAppointment
    {
        $applicant = Applicant::create([
            'first_name' => $name, 'last_name' => 'Santos', 'application_number' => (string) Str::uuid(),
        ]);
        $appointment = GuidanceAppointment::create([
            'applicant_id' => $applicant->id,
            'batch_id' => $makeup ? null : $batch->getKey(),
            'source_batch_id' => $makeup ? $batch->getKey() : null,
            'student_id_number' => 'ID-'.$name,
            'request_code' => 'G-'.Str::random(12),
            'test_category' => $category, 'test_type' => GuidanceCategories::TESTS[$category][0],
            'test_types' => GuidanceCategories::TESTS[$category],
            'status' => 'Completed', 'attendance_status' => $scored ? 'Completed' : 'Absent',
            'origin_course' => 'BSIT', 'is_archived' => true,
        ]);
        if ($scored) {
            $tests = match ($category) {
                'psychological' => ['phq9' => ['scores' => ['total' => 0], 'interpretation' => ['severity' => 'Minimal']]],
                'personality' => ['bfpi' => ['scores' => ['Extraversion' => 3], 'interpretation' => ['Extraversion' => 'Average']]],
                'career' => ['career' => ['scores' => ['Realistic' => 4], 'interpretation' => ['Realistic' => 'High']]],
            };
            $appointment->response()->create([
                'applicant_id' => $applicant->id, 'answers' => [], 'score_summary' => ['tests' => $tests],
            ]);
        }

        return $appointment;
    }

    public function test_admin_and_staff_print_all_scored_batch_and_makeup_reports_in_one_document(): void
    {
        $this->mock(DocumentExportService::class, function ($mock) {
            $mock->shouldReceive('render')->withArgs(function ($document, $format) {
                $this->assertSame('pdf', $format);
                $this->assertCount(2, $document['reports']);
                $html = view($document['view'], $document)->render();
                $this->assertStringContainsString('Original Santos', $html);
                $this->assertStringContainsString('Makeup Santos', $html);
                $this->assertStringNotContainsString('Absent Santos', $html);
                $this->assertStringNotContainsString('Unrelated Santos', $html);
                $this->assertStringNotContainsString('Assessment Rubric &amp; Interpretation', $html);
                $this->assertStringNotContainsString('Raw Item Choices', $html);
                $this->assertSame(1, substr_count($html, '<!doctype html>'));
                $this->assertStringNotContainsString('window.print()', $html);
                $this->assertSame(1, substr_count($html, '<img class="assessment-letterhead"'));


                return true;
            })->andReturn('%PDF-test');
        });
        foreach (array_keys(GuidanceCategories::LABELS) as $category) {
            $batch = $this->batch($category);
            $this->appointment($batch, 'Original', $category);
            $this->appointment($batch, 'Makeup', $category, true);
            $this->appointment($batch, 'Absent', $category, false, false);
            $this->appointment($this->batch($category), 'Unrelated', $category);

            foreach (['admin', 'staff'] as $role) {
                $this->actingAs(User::factory()->create(['role' => $role]));
                $response = $this->get(route($role.'.guidance-batches.print', $batch))
                    ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                    ->assertHeader('Content-Type', 'application/pdf')
                    ->assertHeader('Content-Disposition', 'inline; filename="DMSGTA-Batch-'.$batch->getKey().'-Assessments.pdf"');
                $this->assertStringStartsWith('%PDF-', $response->getContent());
                $this->get(route($role.'.guidance-batches.index', ['archived' => 1, 'category' => $category]))
                    ->assertOk()->assertSee(route($role.'.guidance-batches.print', $batch));
            }
        }
    }

    public function test_real_batch_pdf_has_one_official_report_page_per_student_for_all_test_types(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        foreach (array_keys(GuidanceCategories::LABELS) as $category) {
            $batch = $this->batch($category);
            $this->appointment($batch, 'Original', $category);
            $this->appointment($batch, 'Makeup', $category, true);
            $response = $this->get(route('staff.guidance-batches.print', $batch))
                ->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $this->assertSame(2, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        }
    }

    public function test_pdf_failure_returns_to_archived_batch_with_a_message(): void
    {
        $batch = $this->batch();
        $this->appointment($batch, 'Original', 'psychological');
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->mock(DocumentExportService::class, function ($mock) {
            $mock->shouldReceive('render')->once()->andThrow(new \RuntimeException('PDF failed'));
        });
        $this->get(route('staff.guidance-batches.print', $batch))
            ->assertRedirect()->assertSessionHas('error', 'Unable to generate the batch PDF. Please try again.');
    }

    public function test_print_requires_authentication_correct_role_and_an_archived_testing_batch(): void
    {
        $archived = $this->batch();
        $active = $this->batch('psychological', 'Pending Registration');
        $this->get(route('staff.guidance-batches.print', $archived))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('admin.guidance-batches.print', $archived))->assertForbidden();
        $this->get(route('staff.guidance-batches.print', $active))->assertNotFound();
        $this->get(route('staff.guidance-batches.print', 999999))->assertNotFound();
        $this->get(route('staff.guidance-batches.index', ['category' => 'psychological']))
            ->assertOk()->assertDontSee('Print this batch');
        $archived->update(['test_type' => null, 'module_type' => 'Good Moral']);
        $this->get(route('staff.guidance-batches.print', $archived))->assertNotFound();
    }

    public function test_empty_or_unscored_batch_redirects_with_a_useful_message(): void
    {
        $batch = $this->batch();
        $appointment = $this->appointment($batch, 'Unscored', 'psychological');
        $appointment->response->update(['score_summary' => ['tests' => []]]);
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('staff.guidance-batches.print', $batch))
            ->assertRedirect(route('staff.guidance-batches.index', [
                'archived' => 1, 'category' => 'psychological', 'q' => $batch->batch_name,
            ]))
            ->assertSessionHas('error', 'This batch has no scored assessment reports available to print.');
    }
}
