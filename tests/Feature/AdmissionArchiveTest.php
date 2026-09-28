<?php

namespace Tests\Feature;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsAdmin(): void
    {
        $this->actingAs(User::factory()->create([
            'role_id' => Role::where('slug', 'admin')->value('id'),
        ]));
    }

    public function test_archive_lists_cycles_marked_by_either_archive_flag_or_completed_status(): void
    {
        $this->loginAsAdmin();

        $flagged = AdmissionCycle::create([
            'name' => 'S.Y. 2027 – 2028',
            'academic_year' => '2027-2028',
            'status' => AdmissionCycle::STATUS_DRAFT,
            'is_active' => false,
            'is_archived' => true,
        ]);
        $completed = AdmissionCycle::create([
            'name' => 'S.Y. 2026 – 2027',
            'academic_year' => '2026-2027',
            'status' => AdmissionCycle::STATUS_COMPLETED,
            'is_active' => false,
            'is_archived' => false,
        ]);
        AdmissionApplicant::create([
            'admission_cycle_id' => $flagged->id,
            'application_number' => 'ARCHIVE-001',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'course_choice' => 'BSIT',
        ]);

        $response = $this->get(route('admin.admission.archive'))->assertOk();

        $response->assertSee('S.Y. 2027 – 2028')
            ->assertSee('S.Y. 2026 – 2027')
            ->assertSee('1 Applicants')
            ->assertSee('Inspect Masterlist Roster')
            ->assertSee('View Analytics')
            ->assertSee('Export Evaluation DOCX')
            ->assertSee('Export Evaluation PDF')
            ->assertSee(route('admin.admission.masterlist', ['cycle_id' => $flagged->id]), false)
            ->assertSee(route('admin.admission.analytics', ['cycle_id' => $flagged->id]), false);

        $this->get(route('admin.admission.masterlist', ['cycle_id' => $flagged->id]))
            ->assertOk()
            ->assertSee('Archived Historical Record')
            ->assertSee('permanently locked');
    }

    public function test_completing_cycle_synchronizes_all_archive_flags(): void
    {
        $cycle = AdmissionCycle::create([
            'name' => '2028-2029',
            'academic_year' => '2028-2029',
            'status' => AdmissionCycle::STATUS_ACTIVE,
            'is_active' => true,
            'is_archived' => false,
        ]);

        $cycle->completeAndArchive();

        $this->assertDatabaseHas('admission_cycles', [
            'id' => $cycle->id,
            'status' => AdmissionCycle::STATUS_COMPLETED,
            'is_active' => false,
            'is_archived' => true,
        ]);
    }
}
