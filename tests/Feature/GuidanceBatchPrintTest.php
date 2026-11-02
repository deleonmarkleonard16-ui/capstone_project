<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\GuidanceAppointment;
use App\Models\GuidanceTestBatch;
use App\Models\User;
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
                    ->assertSee('Original Santos')->assertSee('Makeup Santos')
                    ->assertDontSee('Absent Santos')->assertDontSee('Unrelated Santos')
                    ->assertSee('2 assessment report(s). 1 roster record(s) without printable scored results.')
                    ->assertSee('Print this batch')
                    ->assertSee('Assessment Rubric &amp; Interpretation', false)
                    ->assertViewHas('reports', fn ($reports) => count($reports) === 2);
                $this->assertSame(1, substr_count($response->getContent(), '<!doctype html>'));
                $this->assertSame(1, substr_count($response->getContent(), 'window.print(); });'));
                $this->get(route($role.'.guidance-batches.index', ['archived' => 1, 'category' => $category]))
                    ->assertOk()->assertSee(route($role.'.guidance-batches.print', $batch));
            }
        }
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
