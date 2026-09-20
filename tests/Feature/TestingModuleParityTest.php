<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TestingModuleParityTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $user = new User();
        $user->id = 101;
        $user->name = 'Guidance Staff';
        $role = new Role();
        $role->slug = 'staff';
        $user->setRelation('roleLookup', $role);
        $this->actingAs($user);
        return $user;
    }

    public function test_modules_have_ui_and_process_parity_with_dual_queue_and_bundled_modal(): void
    {
        $this->staff();

        // 1. Psychological Module
        $psychResponse = $this->get(route('staff.psychological.index'));
        $psychResponse->assertOk()
            ->assertSee('Psychological Assessment')
            ->assertSee('Individual Request Queue')
            ->assertSee('Bundled / Batch Queue')
            ->assertSee('+ Create Bundled Request')
            ->assertSee('create-bundled-modal')
            ->assertSee('Psychological Assessment');

        // 2. Personality Module
        $personalityResponse = $this->get(route('staff.personality.index'));
        $personalityResponse->assertOk()
            ->assertSee('Personality Test')
            ->assertSee('Individual Request Queue')
            ->assertSee('Bundled / Batch Queue')
            ->assertSee('+ Create Bundled Request')
            ->assertSee('create-bundled-modal')
            ->assertSee('BFPI');

        // 3. Career Module
        $careerResponse = $this->get(route('staff.career.index'));
        $careerResponse->assertOk()
            ->assertSee('Career Test')
            ->assertSee('Individual Request Queue')
            ->assertSee('Bundled / Batch Queue')
            ->assertSee('+ Create Bundled Request')
            ->assertSee('create-bundled-modal')
            ->assertSee('Career RIASEC');

        // 4. Batches Queue View
        $batchResponse = $this->get(route('staff.guidance-batches.index'));
        $batchResponse->assertOk()
            ->assertSee('Bundled / Batch Queue')
            ->assertSee('Individual Request Queue')
            ->assertSee('+ Create Bundled Request')
            ->assertSee('create-bundled-modal');
    }

    public function test_modules_isolate_and_filter_requests_accurately(): void
    {
        $this->staff();

        $psych = ServiceRequest::create([
            'reference' => 'GT-PSYCH-0001',
            'service' => 'testing',
            'first_name' => 'Alice',
            'last_name' => 'Psychology',
            'student_status' => 'student',
            'student_number' => '23-SC-1111',
            'course' => 'BSHM',
            'purpose' => 'Field Study',
            'tests' => ['psychological'],
            'status' => 'pending',
        ]);

        $pers = ServiceRequest::create([
            'reference' => 'GT-PERS-0002',
            'service' => 'testing',
            'first_name' => 'Bob',
            'last_name' => 'Personality',
            'student_status' => 'student',
            'student_number' => '23-SC-2222',
            'course' => 'BSBA-HRDM',
            'purpose' => 'OJT',
            'tests' => ['personality'],
            'status' => 'pending',
        ]);

        $career = ServiceRequest::create([
            'reference' => 'GT-CAREER-0003',
            'service' => 'testing',
            'first_name' => 'Charlie',
            'last_name' => 'Career',
            'student_status' => 'student',
            'student_number' => '23-SC-3333',
            'course' => 'BSIT',
            'purpose' => 'Career Guidance',
            'tests' => ['career'],
            'status' => 'pending',
        ]);

        // Psychological view should only see Alice
        $this->get(route('staff.psychological.index'))
            ->assertOk()
            ->assertSee('ALICE PSYCHOLOGY')
            ->assertDontSee('BOB PERSONALITY')
            ->assertDontSee('CHARLIE CAREER');

        // Personality view should only see Bob
        $this->get(route('staff.personality.index'))
            ->assertOk()
            ->assertSee('BOB PERSONALITY')
            ->assertDontSee('ALICE PSYCHOLOGY')
            ->assertDontSee('CHARLIE CAREER');

        // Career view should only see Charlie
        $this->get(route('staff.career.index'))
            ->assertOk()
            ->assertSee('CHARLIE CAREER')
            ->assertDontSee('ALICE PSYCHOLOGY')
            ->assertDontSee('BOB PERSONALITY');
    }

    public function test_create_bundled_request_modal_submission_with_csv(): void
    {
        $this->staff();

        $csvContent = "student_id,first_name,middle_name,last_name\n"
                    . "23-SC-4143,JUAN,SANTOS,DELA CRUZ\n"
                    . "23-SC-4144,MARIA,,CLARA\n";
        $file = UploadedFile::fake()->createWithContent('roster.csv', $csvContent);

        $response = $this->post(route('staff.guidance-batches.store'), [
            'batch_name' => 'BATCH-BSIT-3A',
            'course' => 'BSIT',
            'year_section' => '3A',
            'reason_for_request' => 'Practicum / OJT',
            'test_type' => 'Personality Test',
            'roster' => $file,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $batch = \App\Models\GuidanceTestBatch::where('batch_name', 'BATCH-BSIT-3A')->first();
        $this->assertNotNull($batch);
        $this->assertSame('BSIT', $batch->course);
        $this->assertSame('3A', $batch->year_section);
        $this->assertSame('Personality Test', $batch->test_type);
        $this->assertCount(2, $batch->appointments);

        $first = $batch->appointments()->where('student_id_number', '23-SC-4143')->first();
        $this->assertNotNull($first);
        $this->assertSame('personality', $first->test_category);
        $this->assertSame('bfpi', $first->test_type);
        $this->assertSame('Pending Scan', $first->attendance_status);

        // Verify Batch QR endpoint works
        $this->get(route('staff.guidance-batches.qr', $batch))
            ->assertOk()
            ->assertSee('BATCH-BSIT-3A');
    }

    public function test_module_batch_creation_ignores_a_submitted_category_override(): void
    {
        $this->staff();
        $file = UploadedFile::fake()->createWithContent('roster.csv', "student_id,first_name,middle_name,last_name\n23-SC-5151,ANA,,CRUZ\n");

        $this->post(route('staff.personality.batches.store'), [
            'batch_name' => 'PERSONALITY-5151',
            'course' => 'BSIT',
            'reason_for_request' => 'Field Study',
            'test_type' => 'Career Test',
            'roster' => $file,
        ])->assertSessionHasNoErrors();

        $batch = \App\Models\GuidanceTestBatch::where('batch_name', 'PERSONALITY-5151')->firstOrFail();
        $this->assertSame('Personality Test', $batch->test_type);
        $this->get(route('staff.personality.batches'))->assertSee('PERSONALITY-5151');
        $this->get(route('staff.career.batches'))->assertDontSee('PERSONALITY-5151');
    }

    public function test_archives_show_only_batches_and_requests_for_the_selected_module(): void
    {
        $this->staff();
        foreach (['psychological' => 'Psychological Assessment', 'personality' => 'Personality Test', 'career' => 'Career Test'] as $category => $label) {
            \App\Models\GuidanceTestBatch::create([
                'batch_token' => bin2hex(random_bytes(32)),
                'batch_name' => strtoupper($category).'-ARCHIVE',
                'course' => 'BSIT',
                'test_type' => $label,
                'reason_for_request' => 'Field Study',
                'status' => 'Completed',
                'archived_at' => now(),
            ]);
            ServiceRequest::create([
                'reference' => 'GT-'.strtoupper($category).'-ARCHIVE',
                'service' => 'testing',
                'first_name' => ucfirst($category),
                'last_name' => 'Archive',
                'student_status' => 'student',
                'student_number' => '26-'.$category,
                'course' => 'BSIT',
                'purpose' => 'Field Study',
                'tests' => [$category],
                'status' => 'completed',
                'archived_at' => now(),
            ]);
        }

        foreach (['psychological', 'personality', 'career'] as $category) {
            $page = $this->get(route('staff.'.$category.'.archive'))->assertOk()
                ->assertSee(strtoupper($category).'-ARCHIVE');
            foreach (array_diff(['psychological', 'personality', 'career'], [$category]) as $other) {
                $page->assertDontSee(strtoupper($other).'-ARCHIVE');
            }
        }
    }
}
